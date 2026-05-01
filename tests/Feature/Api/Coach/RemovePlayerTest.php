<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

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
        $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertNotEquals($teamPlayer->actual, $dataResponse->data->actual);
    }

  public function test_remove_player_from_team_error(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $response = $this->json('POST', 'api/coach/remove/players', [
          'player'=>fake()->uuid,
          'team'=>fake()->uuid
      ]);
      $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  public function test_remove_player_from_team_error2(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);

      $player = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
      $team = Team::factory()->create();
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
