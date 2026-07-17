<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Models\CoachTeam;
use App\Models\Team;
use App\Models\User;
use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Services\Access\EntitlementResolver;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanFeatureAdminTest extends TestCase
{
    public function test_routes_require_authentication_and_subscription_admin(): void
    {
        $this->getJson('/api/admin/billing/plans')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['type' => 'coach', 'email' => 'ordinary@example.com']), ['coach']);
        $this->getJson('/api/admin/billing/plans')->assertForbidden();
    }

    public function test_admin_can_read_catalog_and_authoritative_plans(): void
    {
        $this->admin();
        $this->getJson('/api/admin/billing/plans')->assertOk()
            ->assertJsonFragment(['key' => 'free', 'display_name' => 'Free'])
            ->assertJsonStructure(['data' => ['plans', 'feature_groups', 'system_capabilities', 'limit_metadata']]);
        $this->getJson('/api/admin/billing/entitlements')->assertOk()
            ->assertJsonFragment(['key' => 'scripted_bp', 'audience' => 'coach']);
    }

    public function test_admin_update_is_immediate_versioned_and_audited(): void
    {
        $admin = $this->admin();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'free']);
        $plan = $this->plan('free');
        $this->assertNotContains('scripted_bp', app(EntitlementResolver::class)->getEntitlements($coach));

        $response = $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => [...$plan['entitlements'], 'scripted_bp'],
            'limits' => ['players' => 12, 'coaches' => 5, 'teams' => 1],
            'version' => $plan['version'],
            'reason' => 'Acceptance test enablement',
            'correlation_id' => 'phase3c-test',
        ])->assertOk()->assertJsonPath('data.plan.limits.players', 12);

        $this->assertContains('scripted_bp', app(EntitlementResolver::class)->getEntitlements($coach));
        $this->assertSame(12, app(EntitlementResolver::class)->getAccessSummary($coach)['limits']['players']);
        $this->assertDatabaseHas('subscription_audits', [
            'actor_user_id' => $admin->id,
            'action' => 'plan.entitlements.updated',
            'reason' => 'Acceptance test enablement',
            'correlation_id' => 'phase3c-test',
        ]);

        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => $response->json('data.plan.entitlements'),
            'limits' => ['players' => 12, 'coaches' => 5, 'teams' => 1],
            'version' => $plan['version'],
            'reason' => 'Stale overwrite',
        ])->assertConflict();
        $this->assertSame(1, SubscriptionAudit::query()->where('action', 'plan.entitlements.updated')->count());
    }

    public function test_numeric_limits_drive_team_capacity_helpers(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $admin->id, 'team_id' => $team->id, 'is_main' => true]);
        $plan = $this->plan('free');

        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => $plan['entitlements'],
            'limits' => ['players' => 17, 'coaches' => 4, 'teams' => 1],
            'version' => $plan['version'], 'reason' => 'Capacity acceptance',
        ])->assertOk();

        $this->assertSame(17, CoachUtils::playerLimit($team->id));
        $this->assertSame(4, CoachUtils::coachSeatLimit($team->id));
    }

    public function test_update_rejects_unknown_cross_audience_immutable_and_legacy_changes(): void
    {
        $this->admin();
        $player = $this->plan('player_basic');
        $this->putJson('/api/admin/billing/plans/player_basic/entitlements', [
            'entitlements' => [...$player['entitlements'], 'add_team'],
            'limits' => $player['limits'], 'version' => $player['version'], 'reason' => 'Wrong audience',
        ])->assertUnprocessable();

        $free = $this->plan('free');
        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => array_values(array_diff($free['entitlements'], ['notifications'])),
            'limits' => $free['limits'], 'version' => $free['version'], 'reason' => 'Remove baseline',
        ])->assertUnprocessable();

        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => [...$free['entitlements'], 'not_real'],
            'limits' => $free['limits'], 'version' => $free['version'], 'reason' => 'Unknown key',
        ])->assertUnprocessable();

        $legacy = $this->plan('coach_basic');
        $this->putJson('/api/admin/billing/plans/coach_basic/entitlements', [
            'entitlements' => $legacy['entitlements'], 'limits' => $legacy['limits'],
            'version' => $legacy['version'], 'reason' => 'Legacy write',
        ])->assertUnprocessable();
        $this->assertSame(0, SubscriptionAudit::query()->where('action', 'plan.entitlements.updated')->count());
    }

    public function test_audit_endpoint_identifies_actor_without_provider_fields(): void
    {
        $this->admin();
        $plan = $this->plan('free');
        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => $plan['entitlements'], 'limits' => $plan['limits'],
            'version' => $plan['version'], 'reason' => 'Audit visibility',
            'provider_product_id' => 'must-not-be-accepted',
        ])->assertUnprocessable();
        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => $plan['entitlements'], 'limits' => $plan['limits'],
            'version' => $plan['version'], 'reason' => 'Audit visibility',
        ])->assertOk();
        $this->getJson('/api/admin/billing/entitlement-audits')->assertOk()
            ->assertJsonPath('data.audits.0.reason', 'Audit visibility')
            ->assertJsonMissing(['provider_product_id' => 'must-not-be-accepted']);
    }

    public function test_runtime_route_gate_changes_without_subscription_or_token_changes(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('free');
        $this->getJson('/api/coach/daily-plans')->assertForbidden();

        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => [...$plan['entitlements'], 'planner_create'],
            'limits' => $plan['limits'], 'version' => $plan['version'],
            'reason' => 'Runtime route acceptance',
        ])->assertOk();

        $this->getJson('/api/me/access')->assertOk()->assertJsonFragment(['planner_create']);
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_cross_client_round_trip_is_immediate_and_audited_twice(): void
    {
        $admin = $this->admin();
        $plan = $this->plan('free');

        $webUpdate = $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => [...$plan['entitlements'], 'scripted_bp'],
            'limits' => $plan['limits'], 'version' => $plan['version'],
            'reason' => 'Web acceptance enable', 'correlation_id' => 'web-acceptance',
        ])->assertOk()->json('data.plan');
        $this->getJson('/api/me/access')->assertJsonFragment(['scripted_bp']);

        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => array_values(array_diff($webUpdate['entitlements'], ['scripted_bp'])),
            'limits' => $webUpdate['limits'], 'version' => $webUpdate['version'],
            'reason' => 'Mobile acceptance reverse', 'correlation_id' => 'mobile-acceptance',
        ])->assertOk();
        $this->assertNotContains('scripted_bp', app(EntitlementResolver::class)->getEntitlements($admin));
        $this->assertSame(2, SubscriptionAudit::query()->where('action', 'plan.entitlements.updated')->count());
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['type' => 'coach', 'email' => 'admin@fungometrics.com', 'subscription_plan' => 'free']);
        Sanctum::actingAs($admin, ['coach']);
        return $admin;
    }

    /** @return array<string, mixed> */
    private function plan(string $key): array
    {
        $plan = SubscriptionPlan::query()->with('entitlements')->where('key', $key)->firstOrFail();
        return app(\App\Services\Access\PlanFeatureManager::class)->serialize($plan);
    }
}
