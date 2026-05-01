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

class GetPlayersListTest extends TestCase
{
    public function test_get_all_players_roster_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $team2 = Team::factory()->create();
        $team3 = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team2->id, 'is_main' => true]);
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team3->id, 'is_main' => true]);

        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team2->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team2->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team2->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team3->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id])->user_id])->user_id, 'team_id' => $team3->id, 'actual' => true]);

        $response = $this->json('GET', 'api/coach/roster/players');
        $response->assertOk()->assertJsonStructure(['code', 'message', 'status', 'data']);
    }

    public function test_get_all_players_roster_not_found(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        $response = $this->json('GET', 'api/coach/roster/players');
        $response->assertNotFound()->assertJsonStructure(['code', 'message', 'status', 'data']);
    }

    public function test_get_all_players_roster_unauthorized(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        $response = $this->json('GET', 'api/coach/roster/players');
        $response->assertUnauthorized()->assertJsonStructure(['code', 'message', 'status', 'data']);
    }
    public function test_get_all_players_roster_not_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        $response = $this->json('GET', 'api/coach/roster/players');
        $response->assertForbidden()->assertJsonStructure(['code', 'message', 'status', 'data']);
    }
}
