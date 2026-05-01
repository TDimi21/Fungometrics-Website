<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddPlayerToTrainingTest extends TestCase
{
    public function test_add_player_to_training_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create();
        PracticeLineUp::factory(10)->create([
            'practice_id'=>$practice->id,
            'user_id'=>Player::factory()->create([
                'user_id'=>Profile::factory()->create(['user_id'=>User::factory()
                    ->create(['type'=>UserTypes::PLAYER->value])
                    ->id])->user_id,
            ])->user_id,
            'is_pitching'=>true,
            'is_batting'=>false,
        ]);

        $data = [
            'player'=> Player::factory()->create([
                'user_id'=>Profile::factory()->create(['user_id'=>User::factory()
                    ->create(['type'=>UserTypes::PLAYER->value])
                    ->id])->user_id,
            ])->user_id,
            'pitching' => true,
            'batting' => false,
            'sort'=>fake()->randomDigitNotZero()
        ];

        $response = $this->post('api/coach/lineup/'.$practice->id, $data);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

  public function test_add_player_to_training_not_found(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $practice = Practice::factory()->create();
      PracticeLineUp::factory(10)->create([
          'practice_id'=>$practice->id,
          'user_id'=>Profile::factory()->create(['user_id'=>User::factory()->create(['type'=>UserTypes::PLAYER->value])
            ->id])->user_id,
          'is_pitching'=>true,
          'is_batting'=>false,
      ]);

      $data = [
          'player'=>Profile::factory()->create(['user_id'=>User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
          'pitching' => true,
          'batting' => false,
          'sort'=>fake()->randomDigitNotZero()
      ];

      $response = $this->post('api/coach/lineup/'.fake()->uuid, $data);

      $response->assertNotFound()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }


  public function test_add_player_to_training_server_error(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $practice = Practice::factory()->create();
      PracticeLineUp::factory(10)->create([
          'practice_id'=>$practice->id,
          'user_id'=>Profile::factory()->create(['user_id'=>User::factory()->create(['type'=>UserTypes::PLAYER->value])
            ->id])->user_id,
          'is_pitching'=>true,
          'is_batting'=>false,
      ]);

      $data = [
          'pitching' => true,
          'batting' => false,
          'sort'=>fake()->randomDigitNotZero()
      ];

      $response = $this->post('api/coach/lineup/'.$practice->id, $data);

      $response->assertServerError()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }
  public function test_add_player_to_training_unauthorized(): void
  {

      $response = $this->post('api/coach/lineup/'.fake()->uuid);

      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }

  public function test_add_player_to_training_forbidden(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
      $practice = Practice::factory()->create();
      PracticeLineUp::factory(10)->create([
          'practice_id'=>$practice->id,
          'user_id'=>Profile::factory()->create(['user_id'=>User::factory()->create(['type'=>UserTypes::PLAYER->value])
            ->id])->user_id,
          'is_pitching'=>true,
          'is_batting'=>false,
      ]);

      $data = [
          'player'=>Profile::factory()->create(['user_id'=>User::factory()->create(['type'=>UserTypes::PLAYER->value])->id])->user_id,
          'pitching' => true,
          'batting' => false,
          'sort'=>fake()->randomDigitNotZero()
      ];

      $response = $this->post('api/coach/lineup/'.$practice->id, $data);

      $response->assertForbidden()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }
}
