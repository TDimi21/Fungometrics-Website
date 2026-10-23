<?php

namespace Tests\Feature\Api\Sessions;

use App\Models\{CoachTeam, Player, PlayerTeam, Practice, PracticeLineUp, Profile, Team, User};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LongTossWebScoringTest extends TestCase
{
    public function test_coach_can_resume_score_and_finish_the_same_long_toss_session(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        $player = User::factory()->create(['type' => 'player']);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id]);
        $practice = Practice::factory()->create(['user_id' => $coach->id, 'team_id' => $team->id, 'type' => 'T', 'modes' => 'LT', 'is_completed' => false]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player->id, 'sort' => 0]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson("/api/training/{$practice->id}")->assertOk()
            ->assertJsonPath('data.team_id', $team->id)->assertJsonPath('data.players.0.id', $player->id);
        $this->postJson('/api/result/longtoss', [
            'practice_id' => $practice->id, 'team_id' => $team->id, 'user_id' => $player->id,
            'distance' => 180, 'hop' => 0, 'set' => 2,
        ])->assertCreated()->assertJsonPath('data.result.user_id', $player->id);
        $this->getJson("/api/statistics/{$practice->id}/longtoss")->assertOk()
            ->assertJsonPath('data.ball_x_ball.0.distance', 180)->assertJsonPath('data.ball_x_ball.0.set', 2);
        $this->putJson("/api/training/{$practice->id}", ['is_completed' => true, 'end_note' => 'Completed from web'])->assertOk();
        $this->assertTrue((bool) $practice->fresh()->is_completed);
        $outsider = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($outsider, ['coach']);
        $this->getJson("/api/training/{$practice->id}")->assertNotFound();
        $this->putJson("/api/training/{$practice->id}", ['is_completed' => false])->assertNotFound();
        $this->deleteJson("/api/training/{$practice->id}")->assertNotFound();
        $this->postJson('/api/result/longtoss', [
            'practice_id' => $practice->id, 'team_id' => $team->id, 'user_id' => $player->id,
            'distance' => 180, 'hop' => 0, 'set' => 2,
        ])->assertNotFound();
    }
}
