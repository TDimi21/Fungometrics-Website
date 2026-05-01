<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\Concerns\UserTypes;
use App\Models\PlayerFitness;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetFitnessTest extends TestCase
{
    public function test_get_fitness_for_player_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);

        $player = Profile::factory()->create([
            'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        PlayerFitness::factory(15)->create([
            'user_id'=>$player->user_id
        ]);

        $response = $this->json('GET', 'api/player/fitness/'.$player->user_id);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
        $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThanOrEqual(1, count($dataResponse->data));
    }

  public function test_get_fitness_for_player_not_found(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user);
      $player = Profile::factory()->create([
          'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
      ]);

      $response = $this->json('GET', 'api/player/fitness/'.$player->user_id);
      $response->assertNotFound()->assertJsonStructure([
          'code',
          'status',
          'message',
          'data'
      ]);
  }

  public function test_get_fitness_for_player_not_authorized(): void
  {
      $player = Profile::factory()->create([
          'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
      ]);

      $response = $this->json('GET', 'api/player/fitness/'.$player->user_id);
      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'status',
          'message',
          'data'
      ]);
  }
}
