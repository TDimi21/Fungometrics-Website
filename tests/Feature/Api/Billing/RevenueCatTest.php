<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use App\Contracts\Billing\RevenueCatClient;
use App\Models\BillingEvent;
use App\Models\Concerns\UserTypes;
use App\Models\Subscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use RuntimeException;

class RevenueCatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.revenuecat.webhook_auth' => 'Bearer test-hook', 'billing.revenuecat.environment' => 'test']);
    }
    public function test_webhook_fails_closed_without_auth_configuration(): void
    {
        config(['billing.revenuecat.webhook_auth' => null]);
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload())->assertUnauthorized();
    }
    public function test_webhook_rejects_invalid_authorization_and_malformed_payload(): void
    {
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload(), ['Authorization' => 'wrong'])->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer test-hook')->postJson('/api/billing/revenuecat/webhook', [])->assertUnprocessable();
        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-hook',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], '{bad')->assertUnprocessable();
    }
    public function test_initial_purchase_is_idempotent_and_updates_compatibility_cache(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'free']);
        $payload = $this->payload($user, 'INITIAL_PURCHASE');
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertOk();
        $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertOk();
        $this->assertSame(1, BillingEvent::where('provider_event_id', 'rc-event-1')->count());
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'provider' => 'revenuecat', 'status' => 'active']);
        $this->assertSame('player_pro', $user->fresh()->subscription_plan);
    }
    public function test_cancellation_retains_access_until_expiration(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload($user), $headers)->assertOk();
        $cancel = $this->payload($user, 'CANCELLATION');
        $cancel['event']['id'] = 'rc-cancel';
        $this->postJson('/api/billing/revenuecat/webhook', $cancel, $headers)->assertOk();
        $subscription = Subscription::where('user_id', $user->id)->where('provider', 'revenuecat')->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
    }
    public function test_unknown_product_user_cross_audience_and_production_events_are_rejected(): void
    {
        $headers = ['Authorization' => 'Bearer test-hook'];
        $player = User::factory()->create(['type' => 'player']);
        foreach ([['product_id' => 'unknown'], ['app_user_id' => fake()->uuid], ['product_id' => 'fmtrx_coach_pro_monthly'], ['environment' => 'PRODUCTION']] as $i => $change) {
            $payload = $this->payload($player);
            $payload['event']['id'] = 'bad-'.$i;
            $payload['event'] = array_merge($payload['event'], $change);
            $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertUnprocessable();
        }
    }
    public function test_lifecycle_events_reconcile_without_duplicate_subscriptions(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload($user), $headers)->assertOk();
        $expectations = ['RENEWAL' => 'active', 'CANCELLATION' => 'active', 'UNCANCELLATION' => 'active',
            'BILLING_ISSUE' => 'grace_period', 'SUBSCRIPTION_EXTENDED' => 'active', 'EXPIRATION' => 'expired',
            'REFUND_REVERSED' => 'active', 'REFUND' => 'revoked'];
        foreach ($expectations as $type => $status) {
            $payload = $this->payload($user, $type);
            $payload['event']['id'] = 'event-'.mb_strtolower($type);
            $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertOk();
            $this->assertSame($status, Subscription::where('provider_subscription_id', 'original-1')->value('status'));
        }
        $this->assertSame(1, Subscription::where('provider_subscription_id', 'original-1')->count());
    }
    public function test_product_change_preserves_identity_and_changes_mapped_plan(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload($user), $headers)->assertOk();
        $payload = $this->payload($user, 'PRODUCT_CHANGE');
        $payload['event']['id'] = 'change';
        $payload['event']['new_product_id'] = 'fmtrx_player_basic_monthly';
        $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertOk();
        $this->assertSame('player_basic', $user->fresh()->subscription_plan);
        $this->assertSame(1, Subscription::where('provider_subscription_id', 'original-1')->count());
    }
    public function test_test_transfer_and_unknown_events_never_grant_access(): void
    {
        $headers = ['Authorization' => 'Bearer test-hook'];
        foreach (['TEST', 'TRANSFER', 'SOMETHING_NEW'] as $type) {
            $payload = $this->payload(null, $type);
            $payload['event']['id'] = mb_strtolower($type);
            $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertOk();
        }
        $this->assertSame(0, Subscription::where('provider', 'revenuecat')->count());
        $this->assertDatabaseHas('subscription_audits', ['action' => 'revenuecat.transfer_received']);
    }
    public function test_entitlement_mismatch_and_anonymous_identity_are_rejected(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $payload = $this->payload($user);
        $payload['event']['entitlement_ids'] = ['unknown'];
        $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertUnprocessable();
        $payload = $this->payload($user);
        $payload['event']['id'] = 'anonymous';
        $payload['event']['app_user_id'] = '$RCAnonymousID:abc';
        $this->postJson('/api/billing/revenuecat/webhook', $payload, $headers)->assertUnprocessable();
    }
    public function test_sync_requires_authentication_and_uses_authenticated_uuid_only(): void
    {
        $this->postJson('/api/me/billing/revenuecat/sync')->assertUnauthorized();
        $user = User::factory()->create(['type' => 'player']);
        Sanctum::actingAs($user, ['player']);
        $fake = new class ($user) implements RevenueCatClient {
            public function __construct(private User $user)
            {
            }
            public function subscriptionsFor(string $appUserId): array
            {
                if ($appUserId !== $this->user->id) {
                    throw new RuntimeException('wrong identity');
                }
                return [['id' => 'sync-sub', 'product_id' => 'fmtrx_player_basic_monthly', 'status' => 'active', 'starts_at' => now()->subDay()->valueOf(), 'current_period_ends_at' => now()->addMonth()->valueOf()]];
            }
        };
        $this->app->instance(RevenueCatClient::class, $fake);
        $this->postJson('/api/me/billing/revenuecat/sync')->assertOk();
        $this->assertSame('player_basic', $user->fresh()->subscription_plan);
    }
    public function test_sync_fails_closed_without_server_credentials(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        Sanctum::actingAs($user, ['player']);
        config(['billing.revenuecat.secret_api_key' => null, 'billing.revenuecat.project_id' => null]);
        $this->postJson('/api/me/billing/revenuecat/sync')->assertStatus(503);
    }
    public function test_product_endpoint_is_audience_scoped_and_contains_no_secrets(): void
    {
        $user = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($user, ['coach']);
        $response = $this->getJson('/api/me/billing/revenuecat/products')->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertStringNotContainsString('secret', mb_strtolower($response->getContent()));
        $this->assertSame(['coach', 'coach'], array_column($response->json('data'), 'audience'));
    }
    private function payload(?User $user = null, string $type = 'INITIAL_PURCHASE'): array
    {
        return ['api_version' => '1.0', 'event' => ['id' => 'rc-event-1', 'type' => $type, 'event_timestamp_ms' => now()->valueOf(),
            'app_user_id' => $user?->id ?? fake()->uuid, 'product_id' => 'fmtrx_player_pro_monthly', 'environment' => 'SANDBOX',
            'transaction_id' => 'txn-1', 'original_transaction_id' => 'original-1', 'purchased_at_ms' => now()->subDay()->valueOf(), 'expiration_at_ms' => now()->addMonth()->valueOf()]];
    }
}
