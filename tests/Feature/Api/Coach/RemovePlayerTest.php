<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RemovePlayerTest extends TestCase
{
    public function test_remove_player_from_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
        $team = Team::factory()->create();
        // Actor must be the head coach of this team to remove players.
        CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $user->id,
            'is_main' => true,
        ]);
        $teamPlayer = PlayerTeam::factory()->create([
            'team_id' => $team->id,
            'user_id' => $player->id,
            'actual' => true
        ]);

        $response = $this->json('POST', 'api/coach/remove/players', [
            'player'=>$player->id,
            'team'=>$team->id
        ]);

        $response->assertOk();
        $response->assertJsonPath('data', true);
        $this->assertSoftDeleted('player_teams', ['id' => $teamPlayer->id]);
    }

  public function test_remove_player_forbidden_when_not_head(): void
  {
      // Actor is only an assistant on the team → cannot remove players.
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $player = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
      $team = Team::factory()->create();
      CoachTeam::factory()->create([
          'team_id' => $team->id,
          'coach_id' => $user->id,
          'is_main' => false,
      ]);
      PlayerTeam::factory()->create([
          'team_id' => $team->id,
          'user_id' => $player->id,
          'actual' => true,
      ]);
      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>$player->id,
          'team'=>$team->id
      ]);
      $response->assertForbidden();
  }

  public function test_remove_player_from_team_error(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      // Head coach of a real team, but the player id doesn't exist → 500.
      $team = Team::factory()->create();
      CoachTeam::factory()->create([
          'team_id' => $team->id,
          'coach_id' => $user->id,
          'is_main' => true,
      ]);
      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>fake()->uuid,
          'team'=>$team->id
      ]);
      $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  public function test_remove_player_from_team_error2(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);

      $player = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
      $team = Team::factory()->create();
      CoachTeam::factory()->create([
          'team_id' => $team->id,
          'coach_id' => $user->id,
          'is_main' => true,
      ]);
      $teamPlayer = PlayerTeam::factory()->create([
          'team_id' => $team->id,
          'user_id' => $player->id,
          'actual' => true
      ]);

      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>null,
          'team'=>$team->id
      ]);

      $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  public function test_remove_player_from_team_unauthorized(): void
  {
      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>fake()->uuid,
          'team'=>fake()->uuid
      ]);
      $response->assertUnauthorized();
  }

  public function test_remove_player_from_team_forbidden(): void
  {
      $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>fake()->uuid,
          'team'=>fake()->uuid
      ]);
      $response->assertForbidden();
  }
}
