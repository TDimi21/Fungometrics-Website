<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Access;

use App\Models\CoachTeam;
use App\Models\Subscription;
use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Access\EntitlementResolver;
use App\Services\Access\PlanFeatureManager;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase3C3EntitlementMatrixTest extends TestCase
{
    /** @dataProvider originalDisabledControlDecisions */
    public function test_every_original_phase_3c2_disabled_control_has_an_explicit_reviewed_decision(
        string $key,
        string $status
    ): void {
        $coverage = config("entitlement_coverage.entitlements.{$key}");

        $this->assertIsArray($coverage);
        $this->assertSame($status, $coverage['implementation_status']);

        if (in_array($status, ['fully_wired', 'platform_wired', 'composite_wired'], true)) {
            $this->assertSame([], $coverage['gaps']);
        } else {
            $this->assertNotEmpty($coverage['gaps']);
        }
    }

    public static function originalDisabledControlDecisions(): array
    {
        return [
            'practice sessions uses planner_create' => ['practice_sessions', 'deprecated'],
            'advanced statistics remains mixed' => ['view_advanced_stats', 'disabled_incomplete'],
            'own statistics remains mixed' => ['view_own_stats', 'disabled_incomplete'],
            'personal statistics remains mixed' => ['personal_stats', 'disabled_incomplete'],
            'performance overview is complete' => ['performance_overview', 'fully_wired'],
            'heat maps remain mixed' => ['heat_maps', 'disabled_incomplete'],
            'exports remain unmapped' => ['export_stats', 'disabled_incomplete'],
            'AI analytics remains mixed' => ['ai_analytics', 'disabled_incomplete'],
            'AI recommendations are not isolated' => ['ai_recommendations', 'not_implemented'],
            'team recaps are not isolated' => ['team_recaps', 'not_implemented'],
            'player recaps are not isolated' => ['player_recaps', 'not_implemented'],
            'team switching remains mixed' => ['team_switching', 'disabled_incomplete'],
            'development graphs are backend and web only' => ['development_graphs', 'platform_wired'],
        ];
    }

    public function test_deprecated_and_unimplemented_keys_never_grant_runtime_access(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        $resolver = app(EntitlementResolver::class);

        $this->assertNotContains('practice_sessions', $resolver->getEntitlements($coach));
        $this->assertNotContains('team_recaps', $resolver->getEntitlements($coach));
        $this->assertNotContains('ai_recommendations', $resolver->getEntitlements($player));
        $this->assertNotContains('player_recaps', $resolver->getEntitlements($player));

        foreach (['ai_recommendations', 'team_recaps', 'player_recaps'] as $key) {
            $this->assertTrue(config("entitlements.items.{$key}.hidden"));
            $this->assertFalse(config("entitlements.items.{$key}.toggleable"));
        }
        $this->assertFalse(config('entitlements.items.practice_sessions.toggleable'));
    }

    public function test_development_graph_routes_use_the_dedicated_entitlement(): void
    {
        foreach ([
            'api/player/development/players/{player}',
            'api/player/development/teams/{team}/players/{player}',
        ] as $uri) {
            /** @var IlluminateRoute|null $route */
            $route = collect(Route::getRoutes())->first(fn (IlluminateRoute $route): bool => $route->uri() === $uri);
            $this->assertNotNull($route, "Missing route [{$uri}].");
            $this->assertContains('plan:development_graphs', $route->gatherMiddleware());
            $this->assertNotContains('plan:view_advanced_stats', $route->gatherMiddleware());
        }
    }

    public function test_development_graphs_enforce_entitlement_audience_owner_and_team(): void
    {
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        Sanctum::actingAs($player, ['player']);

        $this->assertContains(
            'development_graphs',
            $this->getJson('/api/me/access')->assertOk()->json('data.entitlements')
        );
        $this->getJson("/api/player/development/players/{$player->id}")->assertOk();

        $otherPlayer = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        $this->getJson("/api/player/development/players/{$otherPlayer->id}")->assertForbidden();

        $unrelatedTeam = Team::factory()->create();
        $this->getJson("/api/player/development/teams/{$unrelatedTeam->id}/players/{$player->id}")
            ->assertForbidden();

        $freePlayer = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
        Sanctum::actingAs($freePlayer, ['player']);
        $this->assertNotContains(
            'development_graphs',
            $this->getJson('/api/me/access')->assertOk()->json('data.entitlements')
        );
        $this->getJson("/api/player/development/players/{$freePlayer->id}")->assertForbidden();

        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson("/api/player/development/players/{$player->id}")->assertForbidden();
    }

    public function test_performance_overview_preserves_audience_and_team_authorization(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $ownedTeam = Team::factory()->create();
        $unrelatedTeam = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $ownedTeam->id]);
        Sanctum::actingAs($coach, ['coach']);

        $this->getJson("/api/coach/performance-overview/{$ownedTeam->id}")->assertOk();
        $this->getJson("/api/coach/performance-overview/{$unrelatedTeam->id}")->assertForbidden();

        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        Sanctum::actingAs($player, ['player']);
        $this->getJson("/api/coach/performance-overview/{$ownedTeam->id}")->assertForbidden();
    }

    public function test_completed_controls_change_access_immediately_and_do_not_touch_subscriptions(): void
    {
        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
            'subscription_plan' => 'free',
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $admin->id, 'team_id' => $team->id, 'is_main' => true]);
        Sanctum::actingAs($admin, ['coach']);

        $subscriptionsBefore = Subscription::query()->count();
        $plan = SubscriptionPlan::query()->with('entitlements')->where('key', 'free')->firstOrFail();
        $serialized = app(PlanFeatureManager::class)->serialize($plan);

        $response = $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => [...$serialized['entitlements'], 'performance_overview'],
            'limits' => $serialized['limits'],
            'version' => $serialized['version'],
            'reason' => 'Phase 3C.3 completed-control proof',
        ])->assertOk();

        $enabledAccess = $this->getJson('/api/me/access')->assertOk()->json('data.entitlements');
        $this->assertContains('performance_overview', $enabledAccess);
        $this->getJson("/api/coach/performance-overview/{$team->id}")->assertStatus(200);
        $this->assertSame($subscriptionsBefore, Subscription::query()->count());
        $this->assertDatabaseHas('subscription_audits', [
            'actor_user_id' => $admin->id,
            'action' => 'plan.entitlements.updated',
            'reason' => 'Phase 3C.3 completed-control proof',
        ]);

        $updated = $response->json('data.plan');
        $this->putJson('/api/admin/billing/plans/free/entitlements', [
            'entitlements' => array_values(array_diff($updated['entitlements'], ['performance_overview'])),
            'limits' => $updated['limits'],
            'version' => $updated['version'],
            'reason' => 'Phase 3C.3 completed-control proof revert',
        ])->assertOk();

        $disabledAccess = $this->getJson('/api/me/access')->assertOk()->json('data.entitlements');
        $this->assertNotContains('performance_overview', $disabledAccess);
        $this->getJson("/api/coach/performance-overview/{$team->id}")->assertForbidden();
        $this->assertSame($subscriptionsBefore, Subscription::query()->count());
        $this->assertSame(2, SubscriptionAudit::query()->where('action', 'plan.entitlements.updated')->count());
    }
}
