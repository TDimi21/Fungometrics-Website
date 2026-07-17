<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use App\Contracts\Billing\RevenueCatClient;
use App\Models\BillingEvent;
use App\Models\Concerns\UserTypes;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

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
        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertUnauthorized()->assertExactJson(['message' => 'Unauthorized.']);

        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'wrong',
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertUnauthorized()->assertExactJson(['message' => 'Unauthorized.']);

        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-hook',
            'CONTENT_TYPE' => 'application/json',
        ], '{}')->assertUnprocessable()->assertJson(['message' => 'The given data was invalid.']);

        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-hook',
            'CONTENT_TYPE' => 'application/json',
        ], '{bad')->assertUnprocessable()->assertExactJson(['message' => 'Malformed JSON.']);

        $this->assertDatabaseCount('billing_events', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }
    public function test_valid_test_event_without_accept_header_returns_json_success(): void
    {
        $payload = $this->payload(null, 'TEST');
        $payload['event']['id'] = 'test-without-accept';

        $this->call('POST', '/api/billing/revenuecat/webhook', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-hook',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload, JSON_THROW_ON_ERROR))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['received' => true]);

        $this->assertDatabaseHas('billing_events', [
            'provider' => 'revenuecat',
            'provider_event_id' => 'test-without-accept',
        ]);
        $this->assertDatabaseCount('subscriptions', 0);
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

    public function test_expiration_does_not_leave_access_from_an_older_active_row_past_its_period_end(): void
    {
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_basic']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => SubscriptionPlan::where('key', 'player_basic')->value('id'),
            'provider' => 'revenuecat',
            'provider_subscription_id' => 'older-period',
            'provider_product_id' => 'fmtrx_player_basic_monthly',
            'status' => 'active',
            'starts_at' => now()->subMinutes(10),
            'current_period_ends_at' => now()->subMinute(),
        ]);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload($user), $headers)->assertOk();
        $expiration = $this->payload($user, 'EXPIRATION');
        $expiration['event']['id'] = 'expiration-after-renewals';
        $this->postJson('/api/billing/revenuecat/webhook', $expiration, $headers)->assertOk();

        $user = $user->fresh();
        $this->assertSame('free', $user->subscription_plan);
        Sanctum::actingAs($user, ['player']);
        $this->getJson('/api/me/access')
            ->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.source', 'legacy')
            ->assertJsonPath('data.provider', null)
            ->assertJsonPath('data.entitlements', ['notifications', 'recent_sessions']);
    }

    public function test_expired_player_plans_resolve_player_safe_free_access(): void
    {
        foreach (['player_basic', 'player_pro'] as $planKey) {
            $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => SubscriptionPlan::where('key', $planKey)->value('id'),
                'provider' => 'revenuecat',
                'provider_subscription_id' => 'expired-'.$planKey,
                'status' => 'expired',
                'current_period_ends_at' => now()->subMinute(),
                'ended_at' => now()->subMinute(),
            ]);
            Sanctum::actingAs($user, ['player']);
            $this->getJson('/api/me/access')->assertOk()
                ->assertJsonPath('data.plan', 'free')
                ->assertJsonPath('data.entitlements', ['notifications', 'recent_sessions']);
        }
    }

    public function test_access_self_heals_a_paid_compatibility_cache_after_the_last_period_elapses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-17 09:25:14', 'America/El_Salvador'));
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => SubscriptionPlan::where('key', 'player_pro')->value('id'),
            'provider' => 'revenuecat',
            'provider_subscription_id' => 'early-expiration-period',
            'status' => 'active',
            'starts_at' => now()->subMinutes(5),
            'current_period_ends_at' => now()->addSeconds(21),
        ]);

        Carbon::setTestNow(now()->addMinute());
        Sanctum::actingAs($user, ['player']);
        $this->getJson('/api/me/access')->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.source', 'legacy')
            ->assertJsonPath('data.provider', null)
            ->assertJsonPath('data.entitlements', ['notifications', 'recent_sessions']);
        $this->assertSame('free', $user->fresh()->subscription_plan);

        Carbon::setTestNow();
    }

    public function test_refund_revokes_player_access_and_resets_compatibility_cache(): void
    {
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $this->postJson('/api/billing/revenuecat/webhook', $this->payload($user), $headers)->assertOk();
        $refund = $this->payload($user, 'REFUND');
        $refund['event']['id'] = 'player-refund';
        $this->postJson('/api/billing/revenuecat/webhook', $refund, $headers)->assertOk();

        $this->assertSame('free', $user->fresh()->subscription_plan);
        Sanctum::actingAs($user->fresh(), ['player']);
        $this->getJson('/api/me/access')->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.entitlements', ['notifications', 'recent_sessions']);
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
    public function test_apple_sandbox_is_accepted_and_production_or_unknown_stores_are_rejected(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $apple = $this->payload($user);
        $apple['event']['id'] = 'apple-sandbox';
        $apple['event']['store'] = 'APP_STORE';
        $this->postJson('/api/billing/revenuecat/webhook', $apple, $headers)->assertOk();
        $this->assertDatabaseHas('subscriptions', [
            'provider' => 'revenuecat',
            'provider_subscription_id' => 'app_store:original-1',
        ]);

        foreach ([['environment' => 'PRODUCTION'], ['store' => 'STRIPE']] as $index => $change) {
            $invalid = $this->payload($user);
            $invalid['event']['id'] = 'invalid-apple-'.$index;
            $invalid['event'] = array_merge($invalid['event'], $change);
            $this->postJson('/api/billing/revenuecat/webhook', $invalid, $headers)->assertUnprocessable();
        }
    }

    public function test_test_store_and_apple_provider_identities_cannot_collide(): void
    {
        $user = User::factory()->create(['type' => 'player']);
        $headers = ['Authorization' => 'Bearer test-hook'];
        $testStore = $this->payload($user);
        $apple = $this->payload($user);
        $apple['event']['id'] = 'same-transaction-apple';
        $apple['event']['store'] = 'APP_STORE';

        $this->postJson('/api/billing/revenuecat/webhook', $testStore, $headers)->assertOk();
        $this->postJson('/api/billing/revenuecat/webhook', $apple, $headers)->assertOk();

        $this->assertSame(1, Subscription::where('provider_subscription_id', 'original-1')->count());
        $this->assertSame(1, Subscription::where('provider_subscription_id', 'app_store:original-1')->count());
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

    public function test_sync_preserves_provider_access_when_the_application_timezone_is_not_utc(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'America/Denver']);
        date_default_timezone_set('America/Denver');
        Carbon::setTestNow(Carbon::parse('2026-07-17 08:53:12', 'America/Denver'));
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
        Sanctum::actingAs($user, ['player']);
        $fake = new class () implements RevenueCatClient {
            public function subscriptionsFor(string $appUserId): array
            {
                return [[
                    'id' => 'timezone-subscription',
                    'product_id' => 'fmtrx_player_basic_monthly',
                    'status' => 'active',
                    'starts_at' => now()->subMinute()->utc()->valueOf(),
                    'current_period_ends_at' => now()->addMinutes(20)->utc()->valueOf(),
                ]];
            }
        };
        $this->app->instance(RevenueCatClient::class, $fake);

        $response = $this->postJson('/api/me/billing/revenuecat/sync');
        $subscription = Subscription::where('provider_subscription_id', 'timezone-subscription')->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->ended_at);
        $this->assertTrue($subscription->current_period_ends_at->isFuture(), $subscription->current_period_ends_at->toIso8601String());

        $response
            ->assertOk()
            ->assertJsonPath('data.plan', 'player_basic')
            ->assertJsonPath('data.source', 'subscription')
            ->assertJsonPath('data.provider', 'revenuecat');

        Carbon::setTestNow();
        date_default_timezone_set($originalTimezone);
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
            'app_user_id' => $user?->id ?? fake()->uuid, 'product_id' => 'fmtrx_player_pro_monthly', 'environment' => 'SANDBOX', 'store' => 'TEST_STORE',
            'transaction_id' => 'txn-1', 'original_transaction_id' => 'original-1', 'purchased_at_ms' => now()->subDay()->valueOf(), 'expiration_at_ms' => now()->addMonth()->valueOf()]];
    }
}
