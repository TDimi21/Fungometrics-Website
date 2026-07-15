<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Contracts\Billing\ProviderEventHandler;
use App\Models\BillingEvent;
use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\BillingEventProcessor;
use App\Services\Billing\EntitlementGrantManager;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class SubscriptionPhase2Test extends TestCase
{
    public function test_manual_user_creation_is_idempotent_and_audited(): void
    {
        $actor = User::factory()->create(['type' => 'coach']);
        $user = User::factory()->create();
        $manager = app(SubscriptionManager::class);
        $first = $manager->createManualUserSubscription($user, 'player_pro', null, null, $actor);
        $second = $manager->createManualUserSubscription($user, 'player_pro', null, null, $actor);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseHas('subscription_audits', ['subscription_id' => $first->id, 'action' => 'subscription.created']);
    }

    public function test_team_plan_change_preserves_history_and_replaces_head_coach_fallback(): void
    {
        $team = Team::factory()->create();
        $manager = app(SubscriptionManager::class);
        $old = $manager->createManualTeamSubscription($team, 'coach_basic');
        $current = $manager->changeSubscriptionPlan($old, 'coach_pro');
        $this->assertDatabaseHas('subscriptions', ['id' => $old->id, 'status' => 'expired']);
        $this->assertSame('coach_pro', $current->load('plan')->plan->key);
    }

    public function test_immediate_cancel_and_revocation_are_terminal(): void
    {
        $subscription = app(SubscriptionManager::class)->createManualUserSubscription(User::factory()->create(), 'player_pro');
        $canceled = app(SubscriptionManager::class)->cancelSubscription($subscription, true);
        $this->assertSame('canceled', $canceled->status);
        $revoked = app(SubscriptionManager::class)->revokeSubscription($canceled);
        $this->assertSame('revoked', $revoked->status);
        $this->expectException(ValidationException::class);
        app(SubscriptionManager::class)->startGracePeriod($revoked, now()->addDay());
    }

    public function test_cancel_at_period_end_requires_and_preserves_period_end(): void
    {
        $until = now()->addMonth();
        $subscription = app(SubscriptionManager::class)->createManualUserSubscription(User::factory()->create(), 'player_pro', null, $until);
        $canceled = app(SubscriptionManager::class)->cancelSubscription($subscription);
        $this->assertSame('active', $canceled->status);
        $this->assertNotNull($canceled->canceled_at);
        $this->assertNull($canceled->ended_at);
    }

    public function test_temporary_grant_can_be_revoked_with_audit_history(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $manager = app(EntitlementGrantManager::class);
        $grant = $manager->create(['user_id' => $user->id, 'team_id' => null, 'entitlement_key' => 'view_advanced_stats', 'ends_at' => now()->addDay()], $actor);
        $manager->revoke($grant, $actor, 'Promotion ended');
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertSame(2, SubscriptionAudit::where('grant_id', $grant->id)->count());
    }

    public function test_legacy_admin_plan_change_dual_writes(): void
    {
        $admin = User::factory()->create(['type' => 'coach']);
        $target = User::factory()->create(['subscription_plan' => 'free']);
        Sanctum::actingAs($admin, ['coach']);
        $this->patchJson("/api/admin/users/{$target->id}/plan", ['subscription_plan' => 'player_pro'])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'subscription_plan' => 'player_pro']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $target->id, 'provider' => 'manual', 'plan_id' => SubscriptionPlan::where('key', 'player_pro')->value('id')]);
        $this->assertDatabaseHas('subscription_audits', ['target_user_id' => $target->id, 'action' => 'legacy_plan.dual_written']);
    }

    public function test_admin_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson('/api/admin/entitlement-grants')->assertUnauthorized();
    }

    public function test_billing_event_duplicate_processing_is_refused_and_failure_can_retry(): void
    {
        $handler = new class () implements ProviderEventHandler {
            private int $attempt = 0;
            public function supports(string $provider, string $eventType): bool
            {
                return true;
            }
            public function handle(BillingEvent $event): void
            {
                if (1 === ++$this->attempt) {
                    throw new RuntimeException('temporary');
                }
            }
        };
        $processor = new BillingEventProcessor([$handler]);
        $event = $processor->record('fake', 'event-1', 'subscription.changed', []);
        try {
            $processor->process($event);
        } catch (RuntimeException) {
        }
        $this->assertSame('temporary', $event->fresh()->processing_error);
        $this->assertNotNull($processor->retryFailed($event->fresh())->processed_at);
        $this->expectException(ValidationException::class);
        $processor->process($event->fresh());
    }
}
