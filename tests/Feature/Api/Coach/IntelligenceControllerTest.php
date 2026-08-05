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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntelligenceControllerTest extends TestCase
{
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
