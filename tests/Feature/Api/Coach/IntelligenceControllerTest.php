<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
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

    public function test_coach_cannot_get_intelligence_for_team_they_do_not_coach(): void
    {
        [$coach, $team] = $this->createCoachTeamPlayer();
        $otherCoach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        Sanctum::actingAs($otherCoach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/intelligence");

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_coach_cannot_get_player_intelligence_for_player_not_on_team(): void
    {
        [$coach, $team] = $this->createCoachTeamPlayer();
        $otherPlayer = $this->createPlayerUser();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $response = $this->json('GET', "api/coach/teams/{$team->id}/players/{$otherPlayer->id}/intelligence");

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
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
