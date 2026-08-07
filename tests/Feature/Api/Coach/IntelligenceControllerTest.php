<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\BenchmarkLibrary;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntelligenceControllerTest extends TestCase
{
    public function test_player_intelligence_is_cached_and_fitness_changes_invalidate_it(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);
        $cacheKey = "player_intelligence_v3_{$team->id}_{$player->id}_60";

        $this->getJson("api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=60")
            ->assertOk();
        $this->assertTrue(Cache::has($cacheKey));

        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => now()->toDateString(),
            'recovery_score' => 88,
        ]);

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_coach_can_get_team_intelligence(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/intelligence?days=60");

        $response->assertOk()->assertJsonStructure([
            'generated_at',
            'team_id',
            'player_id',
            'data_sources_used',
            'data_gaps',
            'summary',
            'scores',
            'signals',
            'recommendations',
            'trend_blocks',
            'profile_labels',
            'players',
        ]);

        $response->assertJsonPath('team_id', (string) $team->id);
        $response->assertJsonPath('player_id', null);
    }

    public function test_coach_can_get_player_intelligence_for_rostered_player(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=7");

        $response->assertOk()->assertJsonStructure([
            'generated_at',
            'team_id',
            'player_id',
            'data_sources_used',
            'data_gaps',
            'summary',
            'scores',
            'signals',
            'recommendations',
            'trend_blocks',
            'profile_labels',
        ]);

        $response->assertJsonPath('team_id', (string) $team->id);
        $response->assertJsonPath('player_id', (string) $player->id);
    }

    public function test_player_intelligence_preserves_saved_assessment_percentiles_and_physical_metrics(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        PlayerAssessment::query()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'assessed_by' => $coach->id,
            'assessment_date' => '2026-08-01',
            'type' => 'full',
            'squat_lbs' => 315,
            'squat_percentile' => 82,
            'bench_lbs' => 225,
            'bench_press_percentile' => 74,
            'bat_speed_percentile' => 76,
            'shoulder_mobility' => 4,
            'ankle_mobility' => 3,
            'rotational_mobility' => 5,
            'team_percentiles' => ['squat_percentile' => 79],
            'age_group_percentiles' => ['squat_percentile' => 85],
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-01',
            'front_squat' => 315,
            'back_squat' => 275,
            'power_clean' => 205,
            'hand_strength' => 58,
            'med_ball_rotational_throw' => 44,
            'bat_speed' => 72,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=60");

        $response->assertOk()
            ->assertJsonPath('summary.assessment.metric_percentiles.squat', 82)
            ->assertJsonPath('summary.assessment.metric_percentiles.bench_press', 74)
            ->assertJsonPath('summary.assessment.metric_percentiles.bat_speed', 76)
            ->assertJsonPath('summary.assessment.shoulder_mobility_score', 4)
            ->assertJsonPath('summary.assessment.ankle_mobility_score', 3)
            ->assertJsonPath('summary.assessment.t_spine_mobility_score', 5)
            ->assertJsonPath('summary.assessment.team_percentiles.squat_percentile', 79)
            ->assertJsonPath('summary.assessment.age_group_percentiles.squat_percentile', 85)
            ->assertJsonPath('summary.physical.front_squat', 315)
            ->assertJsonPath('summary.physical.back_squat', 275)
            ->assertJsonPath('summary.physical.power_clean', 205)
            ->assertJsonPath('summary.physical.hand_strength', 58)
            ->assertJsonPath('summary.physical.med_ball_rotational_throw', 44)
            ->assertJsonPath('summary.physical.bat_speed', 72);
    }

    public function test_player_development_uses_most_recent_recorded_athletic_hand_strength(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-07-01',
            'hand_strength' => 48,
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-01',
            'hand_strength' => 62,
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-05',
            'front_squat' => 225,
            'hand_strength' => null,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->getJson("api/coach/development/teams/{$team->id}/players/{$player->id}?days=365")
            ->assertOk()
            ->assertJsonPath('data.current.hand_strength', 62);
    }

    public function test_recovery_uses_all_player_and_coach_fitness_entries_as_averages(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-01',
            'sleep_hours' => 8,
            'sleep_quality_1_to_5' => 5,
            'recovery_score' => 60,
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-02',
            'sleep_hours' => 6,
            'sleep_quality_1_to_5' => 3,
            'recovery_score' => 80,
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-03',
            'sleep_hours' => null,
            'sleep_quality_1_to_5' => null,
            'recovery_score' => null,
            'body_weight' => 180,
            'yd_40_dash' => 4.8,
            'yd_60_dash' => 7.1,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->getJson("api/coach/development/teams/{$team->id}/players/{$player->id}?days=365")
            ->assertOk()
            ->assertJsonPath('data.current.sleep_hours', 7)
            ->assertJsonPath('data.current.sleep_quality_1_to_5', 4)
            ->assertJsonPath('data.current.recovery_score', 70)
            ->assertJsonPath('data.scores.recovery_score', 70);

        $intelligenceResponse = $this->getJson("api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=365")
            ->assertOk()
            ->assertJsonPath('summary.physical.sleep_hours', 7)
            ->assertJsonPath('summary.physical.sleep_quality_1_to_5', 4)
            ->assertJsonPath('summary.physical.recovery_score', 70)
            ->assertJsonPath('summary.physical.40_yard_dash', 4.8)
            ->assertJsonPath('summary.physical.60_yard_dash', 7.1);

        $benchmarkKeys = collect($intelligenceResponse->json('benchmark_profile.metrics'))
            ->pluck('metric_key');
        $this->assertTrue($benchmarkKeys->contains('body_weight'));
        $this->assertTrue($benchmarkKeys->contains('sleep_hours'));
        $this->assertTrue($benchmarkKeys->contains('recovery_score'));

        $this->assertNotNull(app(BenchmarkLibrary::class)->metric('sleep_hours'));
        $this->assertNotNull(app(BenchmarkLibrary::class)->metric('recovery_score'));
        $this->assertNotNull(app(BenchmarkLibrary::class)->metric('bp_score'));
        $this->assertNotNull(app(BenchmarkLibrary::class)->metric('bullpen_score'));
        $this->assertNotNull(app(BenchmarkLibrary::class)->metric('body_weight'));
    }

    public function test_percentile_rankings_resolve_each_metric_from_its_own_latest_input(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();

        PlayerFitness::query()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-07-20',
            'body_weight' => 180,
            'hand_strength' => 58,
            'grip_strength_left' => 55,
            'grip_strength_right' => 61,
            'yd_40_dash' => 4.8,
            'yd_60_dash' => 7.1,
        ]);
        PlayerAssessment::query()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'assessed_by' => $coach->id,
            'assessment_date' => '2026-08-01',
            'type' => 'strength',
            'bench_lbs' => 230,
            'bench_press_percentile' => 74,
            'shoulder_mobility' => 4,
        ]);
        PlayerFitness::query()->create([
            'user_id' => $player->id,
            'fitness_date' => '2026-08-02',
            'body_weight' => 195,
        ]);
        PlayerAssessment::query()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'assessed_by' => $coach->id,
            'assessment_date' => '2026-08-05',
            'type' => 'mobility',
            'ankle_mobility' => 3,
            'rotational_mobility' => 5,
            'mobility_overall_score' => 40,
        ]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $endpoint = "api/coach/development/teams/{$team->id}/players/{$player->id}?days=365";
        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.current.body_weight', 195)
            ->assertJsonPath('data.current.bench_press', 230)
            ->assertJsonPath('data.current.hand_strength', 58)
            ->assertJsonPath('data.current.yd_40_dash', 4.8)
            ->assertJsonPath('data.current.yd_60_dash', 7.1)
            ->assertJsonPath('data.current.hand_strength', 58)
            ->assertJsonPath('data.current.grip_strength_left', 55)
            ->assertJsonPath('data.current.grip_strength_right', 61)
            ->assertJsonPath('data.current.mobility_score', 40)
            ->assertJsonPath('data.current.metric_freshness.body_weight.source', 'player_fitness')
            ->assertJsonPath('data.current.metric_freshness.bench_press.source', 'player_assessment')
            ->assertJsonPath('data.current.metric_freshness.mobility_score.source', 'player_assessment');

        $this->getJson("api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=365")
            ->assertOk()
            ->assertJsonPath('summary.physical.body_weight', 195)
            ->assertJsonPath('summary.physical.bench_press', 230)
            ->assertJsonPath('summary.physical.hand_strength', 58)
            ->assertJsonPath('summary.physical.mobility_score', 40)
            ->assertJsonPath('summary.assessment.shoulder_mobility_score', 4)
            ->assertJsonPath('summary.assessment.ankle_mobility_score', 3)
            ->assertJsonPath('summary.assessment.metric_percentiles.bench_press', 74);

        // The first request is cached. Saving a new single-field app update must
        // invalidate it without disturbing the other current metric values.
        PlayerFitness::query()->create([
            'user_id' => $player->id,
            'body_weight' => 205,
        ]);

        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.current.body_weight', 205)
            ->assertJsonPath('data.current.bench_press', 230)
            ->assertJsonPath('data.current.mobility_score', 40);
    }

    public function test_coach_cannot_get_intelligence_for_team_they_do_not_coach(): void
    {
        [$coach, $team] = $this->createCoachTeamPlayer();
        $otherCoach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        Sanctum::actingAs($otherCoach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/intelligence");

        $response->assertNotFound();
    }

    public function test_coach_cannot_get_player_intelligence_for_player_not_on_team(): void
    {
        [$coach, $team] = $this->createCoachTeamPlayer();
        $otherPlayer = $this->createPlayerUser();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/players/{$otherPlayer->id}/intelligence");

        $response->assertNotFound();
    }

    public function test_player_can_get_their_own_intelligence_snapshot_via_the_self_scoped_route(): void
    {
        [, $team, $player] = $this->createCoachTeamPlayer();
        $player->update(['subscription_plan' => 'player_pro']);
        Sanctum::actingAs($player, [UserTypes::PLAYER->value]);

        $response = $this->json('GET', 'api/player/intelligence?days=60');

        $response->assertOk()->assertJsonStructure([
            'generated_at', 'team_id', 'player_id', 'data_sources_used', 'data_gaps',
            'summary', 'scores', 'signals', 'recommendations', 'trend_blocks', 'profile_labels',
        ]);
        $response->assertJsonPath('team_id', (string) $team->id);
        $response->assertJsonPath('player_id', (string) $player->id);
    }

    public function test_player_without_a_team_gets_a_graceful_empty_response(): void
    {
        $player = $this->createPlayerUser();
        $player->update(['subscription_plan' => 'player_pro']);
        Sanctum::actingAs($player, [UserTypes::PLAYER->value]);

        $response = $this->json('GET', 'api/player/intelligence');

        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_free_plan_player_is_forbidden_from_self_scoped_intelligence(): void
    {
        $player = $this->createPlayerUser();
        $player->update(['subscription_plan' => 'free']);
        Sanctum::actingAs($player, [UserTypes::PLAYER->value]);

        $this->json('GET', 'api/player/intelligence')->assertForbidden();
    }

    private function createCoachTeamPlayer(): array
    {
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        $team = Team::factory()->create();
        $player = $this->createPlayerUser();

        CoachTeam::factory()->create([
            'coach_id' => $coach->id,
            'team_id' => $team->id,
            'is_main' => true,
        ]);

        PlayerTeam::factory()->create([
            'team_id' => $team->id,
            'user_id' => $player->id,
            'actual' => true,
        ]);

        return [$coach, $team, $player];
    }

    private function createPlayerUser(): User
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);

        return $user;
    }
}
