<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Access;

use App\Models\BillingEvent;
use App\Models\CoachTeam;
use App\Models\EntitlementGrant;
use App\Models\PlayerTeam;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Access\EntitlementResolver;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntitlementResolverTest extends TestCase
{
    private EntitlementResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(EntitlementResolver::class);
    }

    public function test_legacy_plans_retain_their_entitlements(): void
    {
        $free = User::factory()->create(['subscription_plan' => 'free']);
        $coach = User::factory()->create(['subscription_plan' => 'coach_pro']);
        $player = User::factory()->create(['subscription_plan' => 'player_pro']);

        $this->assertTrue($this->resolver->hasEntitlement($free, 'create_session'));
        $this->assertTrue($this->resolver->hasEntitlement($coach, 'view_advanced_stats'));
        $this->assertTrue($this->resolver->hasEntitlement($player, 'recruiting_profile'));
    }

    public function test_active_personal_subscription_provides_access_but_expired_does_not(): void
    {
        $user = User::factory()->create(['subscription_plan' => 'free']);
        $plan = SubscriptionPlan::where('key', 'coach_pro')->firstOrFail();
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'manual',
            'status' => 'active', 'starts_at' => now()->subDay(), 'current_period_ends_at' => now()->addDay()]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'provider' => 'stripe',
            'provider_subscription_id' => 'expired-1', 'status' => 'active', 'current_period_ends_at' => now()->subSecond()]);

        $this->assertTrue($this->resolver->hasEntitlement($user, 'view_advanced_stats'));

        Subscription::where('provider', 'manual')->update(['ended_at' => now()]);
        $this->assertFalse($this->resolver->hasEntitlement($user->fresh(), 'view_advanced_stats'));
    }

    public function test_grace_period_only_applies_until_its_expiration(): void
    {
        $user = User::factory()->create(['subscription_plan' => 'free']);
        $plan = SubscriptionPlan::where('key', 'player_pro')->firstOrFail();
        $subscription = Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id,
            'provider' => 'apple', 'status' => 'grace_period', 'grace_period_ends_at' => now()->addMinute()]);
        $this->assertTrue($this->resolver->hasEntitlement($user, 'recruiting_profile'));

        $subscription->update(['grace_period_ends_at' => now()->subSecond()]);
        $this->assertFalse($this->resolver->hasEntitlement($user, 'recruiting_profile'));
    }

    public function test_direct_grants_respect_dates_and_revocation(): void
    {
        $user = User::factory()->create(['subscription_plan' => 'free']);
        $active = EntitlementGrant::create(['user_id' => $user->id, 'entitlement_key' => 'special_tool',
            'source_type' => 'promotion', 'ends_at' => now()->addDay()]);
        EntitlementGrant::create(['user_id' => $user->id, 'entitlement_key' => 'expired_tool',
            'source_type' => 'promotion', 'ends_at' => now()->subDay()]);
        EntitlementGrant::create(['user_id' => $user->id, 'entitlement_key' => 'revoked_tool',
            'source_type' => 'admin', 'revoked_at' => now()]);

        $this->assertTrue($this->resolver->hasEntitlement($user, 'special_tool'));
        $this->assertFalse($this->resolver->hasEntitlement($user, 'expired_tool'));
        $active->update(['revoked_at' => now()]);
        $this->assertFalse($this->resolver->hasEntitlement($user, 'special_tool'));
    }

    public function test_player_inherits_only_player_appropriate_team_access(): void
    {
        [$player, $team] = $this->playerOnTeam();
        $this->subscribeTeam($team, 'coach_pro');

        $this->assertTrue($this->resolver->hasEntitlement($player, 'view_advanced_stats', $team->id));
        $this->assertFalse($this->resolver->hasEntitlement($player, 'edit_team', $team->id));
    }

    public function test_coach_inherits_team_access_and_unrelated_user_cannot_request_it(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'free']);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $this->subscribeTeam($team, 'coach_pro');
        $this->assertTrue($this->resolver->hasEntitlement($coach, 'edit_team', $team->id));

        $this->expectException(\Illuminate\Validation\UnauthorizedException::class);
        $this->resolver->getEntitlements(User::factory()->create(), $team->id);
    }

    public function test_real_team_subscription_replaces_head_coach_legacy_fallback(): void
    {
        $head = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $member = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'free']);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $head->id, 'team_id' => $team->id, 'is_main' => true]);
        CoachTeam::factory()->create(['coach_id' => $member->id, 'team_id' => $team->id, 'is_main' => false]);
        $this->subscribeTeam($team, 'coach_basic');

        $summary = $this->resolver->getAccessSummary($member, $team->id);
        $this->assertSame('coach_basic', $summary['plan']);
        $this->assertFalse($this->resolver->hasEntitlement($member, 'view_advanced_stats', $team->id));
    }

    public function test_team_access_requires_explicit_context_and_switches_deterministically(): void
    {
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
        $basic = Team::factory()->create();
        $pro = Team::factory()->create();
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $basic->id, 'actual' => true]);
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $pro->id, 'actual' => true]);
        $this->subscribeTeam($basic, 'coach_basic');
        $this->subscribeTeam($pro, 'coach_pro');

        $this->assertFalse($this->resolver->hasEntitlement($player, 'view_advanced_stats'));
        $this->assertFalse($this->resolver->hasEntitlement($player, 'view_advanced_stats', $basic->id));
        $this->assertTrue($this->resolver->hasEntitlement($player, 'view_advanced_stats', $pro->id));
    }

    public function test_player_filters_coach_only_team_grants(): void
    {
        [$player, $team] = $this->playerOnTeam();
        EntitlementGrant::create(['team_id' => $team->id, 'entitlement_key' => 'edit_team', 'source_type' => 'admin']);
        EntitlementGrant::create(['team_id' => $team->id, 'entitlement_key' => 'view_advanced_stats', 'source_type' => 'admin']);

        $this->assertFalse($this->resolver->hasEntitlement($player, 'edit_team', $team->id));
        $this->assertTrue($this->resolver->hasEntitlement($player, 'view_advanced_stats', $team->id));
    }

    public function test_middleware_allows_an_entitled_request(): void
    {
        $pro = User::factory()->create(['subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($pro, ['coach']);
        $this->assertNotSame(403, $this->postJson('/api/result/exitvelocity')->status());
    }

    public function test_middleware_denies_a_non_entitled_request(): void
    {
        $free = User::factory()->create(['subscription_plan' => 'free']);
        Sanctum::actingAs($free, ['coach']);
        $this->postJson('/api/result/exitvelocity')->assertForbidden()
            ->assertJsonPath('required_entitlement', 'exit_velocity_sessions')
            ->assertJsonPath('effective_plan', 'free');
    }

    public function test_access_endpoint_returns_normalized_summary_and_rejects_cross_team_access(): void
    {
        [$player, $team] = $this->playerOnTeam();
        $this->subscribeTeam($team, 'coach_pro');
        Sanctum::actingAs($player);

        $this->getJson("/api/me/access?team_id={$team->id}")->assertOk()
            ->assertJsonPath('data.team.role', 'player')
            ->assertJsonPath('data.plan', 'coach_pro')
            ->assertJsonPath('data.source', 'subscription')
            ->assertJsonFragment(['view_advanced_stats']);

        $other = Team::factory()->create();
        $this->getJson("/api/me/access?team_id={$other->id}")->assertForbidden();
    }

    public function test_duplicate_billing_events_are_rejected(): void
    {
        $event = ['provider' => 'stripe', 'provider_event_id' => 'evt_1', 'event_type' => 'test', 'payload' => []];
        BillingEvent::create($event);
        $this->expectException(QueryException::class);
        BillingEvent::create($event);
    }

    /** @return array{User, Team} */
    private function playerOnTeam(): array
    {
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
        $team = Team::factory()->create();
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        return [$player, $team];
    }

    private function subscribeTeam(Team $team, string $planKey): void
    {
        Subscription::create(['team_id' => $team->id, 'plan_id' => SubscriptionPlan::where('key', $planKey)->value('id'),
            'provider' => 'manual', 'status' => 'active']);
    }
}
