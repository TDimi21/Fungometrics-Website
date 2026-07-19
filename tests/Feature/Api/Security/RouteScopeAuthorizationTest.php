<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Security;

use App\Models\CoachTeam;
use App\Models\BattingPracticeResult;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteScopeAuthorizationTest extends TestCase
{
    public function test_unrelated_coach_cannot_read_team_dashboard_or_top_ten_data(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        Sanctum::actingAs($coach, ['coach']);

        $this->getJson("/api/dashboard/{$team->id}")->assertNotFound();
        $this->postJson("/api/table/{$team->id}", [])->assertNotFound();
        $this->getJson("/api/coach/leaderboard/{$team->id}")->assertNotFound();
    }

    public function test_head_and_assistant_coaches_can_reach_their_team_scope(): void
    {
        $team = Team::factory()->create();
        foreach ([true, false] as $isMain) {
            $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
            CoachTeam::factory()->create([
                'coach_id' => $coach->id,
                'team_id' => $team->id,
                'is_main' => $isMain,
            ]);
            Sanctum::actingAs($coach, ['coach']);
            $this->getJson("/api/dashboard/{$team->id}")->assertStatus(200);
        }
    }

    public function test_player_can_read_self_but_not_an_unrelated_player(): void
    {
        $player = User::factory()->create(['type' => 'player']);
        $other = User::factory()->create(['type' => 'player']);
        Sanctum::actingAs($player, ['player']);

        $this->getJson("/api/player-compare/{$other->id}")->assertNotFound();
        $this->getJson("/api/player-compare/{$player->id}")->assertStatus(200);
    }

    public function test_team_coach_can_read_roster_player_and_team_practice_but_unrelated_coach_cannot(): void
    {
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => 'player']);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id]);
        $practice = Practice::factory()->create(['team_id' => $team->id, 'user_id' => $player->id]);
        BattingPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player->id,
        ]);

        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson("/api/player-compare/{$player->id}")->assertStatus(200);
        $this->getJson("/api/statistics/{$practice->id}/batting")->assertStatus(200);

        $unrelated = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($unrelated, ['coach']);
        $this->getJson("/api/player-compare/{$player->id}")->assertNotFound();
        $this->getJson("/api/statistics/{$practice->id}/batting")->assertNotFound();
    }

    public function test_subscription_administrator_can_cross_scopes(): void
    {
        $admin = User::factory()->create([
            'type' => 'admin',
            'subscription_plan' => 'coach_pro',
        ]);
        $team = Team::factory()->create();
        Sanctum::actingAs($admin, ['coach']);

        $this->getJson("/api/dashboard/{$team->id}")->assertStatus(200);
    }
}
