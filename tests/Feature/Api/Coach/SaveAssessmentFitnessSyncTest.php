<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveAssessmentFitnessSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sleep_and_recovery_fields_in_fitness_snapshot_sync_to_player_fitness(): void
    {
        [$coach, $team, $player] = $this->createCoachTeamPlayer();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->postJson('/api/assessments', [
            'user_id' => $player->id,
            'team_id' => $team->id,
            'assessment_date' => '2026-08-10',
            'type' => 'full',
            'body_weight_lbs' => 180,
            'fitness_snapshot' => [
                'sleep_hours' => 7.5,
                'sleep_quality_1_to_5' => 4,
                'recovery_score' => 82,
            ],
        ]);

        $response->assertStatus(201);

        $fitness = PlayerFitness::query()
            ->where('user_id', $player->id)
            ->where('fitness_date', '2026-08-10')
            ->first();

        $this->assertNotNull($fitness, 'Assessment save should sync a PlayerFitness row for the assessment date.');
        $this->assertEqualsWithDelta(7.5, (float) $fitness->sleep_hours, 0.001);
        $this->assertSame(4, $fitness->sleep_quality_1_to_5);
        $this->assertSame(82, $fitness->recovery_score);
    }

    private function createCoachTeamPlayer(): array
    {
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);

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
}
