<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Http\Requests\Api\Training\AddNewSessionRequest;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddNewSessionTest extends TestCase
{
    public function test_add_new_session_training_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $data = [
            'team' => Team::factory()->create()->id,
            'type' => PracticeTypes::TRAINING->value,
            'note' => fake()->paragraph,
        ];
        $i = 0;
        $temp_players = User::factory()->count(4)->create(['type' => UserTypes::PLAYER->value]);
        $data['players'] = $temp_players->map(function ($temp_item) use (&$i) {
            $i++;

            return ['id' => $temp_item->id, 'sort' => $i];
        });
        foreach ($temp_players as $player) {
            Profile::factory()->create([
                'user_id' => $player->id,
            ]);
            Player::factory()->create([
                'user_id' => $player->id,
            ]);
        }
        $response = $this->json('POST', 'api/training', $data);
        $response->assertCreated();
    }

  public function test_add_new_session_training_type_ok(): void
  {
      $user = User::factory()->create();
      Sanctum::actingAs($user);
      $data = [
          'team' => Team::factory()->create()->id,
          'type' => PracticeTypes::BATTING->value,
          'note' => fake()->paragraph,
      ];
      $i = 0;
      $temp_players = User::factory()->count(4)->create(['type' => UserTypes::PLAYER->value]);
      $data['players'] = $temp_players->map(function ($temp_item) use (&$i) {
          $i++;

          return ['id' => $temp_item->id, 'sort' => $i];
      });
      foreach ($temp_players as $player) {
          Profile::factory()->create([
              'user_id' => $player->id,
          ]);
          Player::factory()->create([
              'user_id' => $player->id,
          ]);
      }
      $response = $this->json('POST', 'api/training', $data);
      $response->assertCreated();
  }

    public function test_add_new_session_training_type_cage_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $data = [
            'team' => Team::factory()->create()->id,
            'type' => PracticeTypes::CAGE->value,
            'note' => fake()->paragraph,
            'cage' => [
                'height' => ['ft' => fake()->numberBetween(14, 20), 'inch' => fake()->numberBetween(0, 9)],
                'width' => ['ft' => fake()->numberBetween(14, 20), 'inch' => fake()->numberBetween(0, 9)],
                'length' => ['ft' => fake()->numberBetween(14, 20), 'inch' => fake()->numberBetween(0, 9)]
            ]
        ];
        $i = 0;
        $temp_players = User::factory()->count(4)->create(['type' => UserTypes::PLAYER->value]);
        $data['players'] = $temp_players->map(function ($temp_item) use (&$i) {
            $i++;

            return ['id' => $temp_item->id, 'sort' => $i];
        });
        foreach ($temp_players as $player) {
            Profile::factory()->create([
                'user_id' => $player->id,
            ]);
            Player::factory()->create([
                'user_id' => $player->id,
            ]);
        }
        $response = $this->json('POST', 'api/training', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'=>[
                'cage_data'=>[
                    'height',
                    'width',
                    'length'
                ]
            ]
        ]);
    }

    public function test_add_new_session_training_without_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create([
            'user_id' => $user->id,
        ]);
        Player::factory()->create([
            'user_id' => $user->id,
        ]);
        Sanctum::actingAs($user);
        $data = [
            'team' => null,
            'type' => PracticeTypes::BATTING->value,
            'note' => fake()->paragraph,
            'players' => [['id' => $user->id, 'sort' => 1]],
        ];
        $response = $this->json('POST', 'api/training', $data);
        $response->assertCreated();
    }

    public function test_add_new_session_training_unauthorized(): void
    {
        $response = $this->json('POST', 'api/training', []);
        $response->assertUnauthorized();
    }

    public function test_add_new_session_training_validations(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->json('POST', 'api/training', []);
        $response->assertUnprocessable();
    }

    public function test_add_new_session_training_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->json('POST', 'api/training', []);
        $response->assertUnprocessable();
    }

    public function test_add_new_session_training_fail(): void
    {
        $this->mock(AddNewSessionRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->json('POST', 'api/training', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
