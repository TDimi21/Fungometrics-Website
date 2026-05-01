<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveFitnessTest extends TestCase
{
    public function test_save_fitness_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);

        $player = Profile::factory()->create([
            'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $data = [
            'user_id'=>$player->user_id,
            'fitness_date'=>fake()->date,
            'bench_press'=>fake()->numberBetween('10', 50),
            'front_squat'=>fake()->numberBetween(10, 50),
            'back_squat'=>fake()->numberBetween(10, 50),
            'power_clean'=>fake()->numberBetween(30, 90),
            'dead_lift'=>fake()->numberBetween(50, 100),
            'yd_40_dash'=>fake()->numerify('##.##'),
            'yd_60_dash'=>fake()->numerify('##.##'),
        ];

        $response = $this->json('POST', 'api/player/fitness', $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

  public function test_save_fitness_not_authorized(): void
  {
      $player = Profile::factory()->create([
          'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
      ]);

      $data = [
          'user_id'=>$player->user_id,
          'bench_press'=>fake()->numberBetween('10', 50),
          'front_squat'=>fake()->numberBetween(10, 50),
          'back_squat'=>fake()->numberBetween(10, 50),
          'power_clean'=>fake()->numberBetween(30, 90),
          'dead_lift'=>fake()->numberBetween(50, 100),
          'yd_40_dash'=>fake()->numerify('##.##'),
          'yd_60_dash'=>fake()->numerify('##.##'),
      ];

      $response = $this->json('POST', 'api/player/fitness', $data);
      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'status',
          'message',
          'data'
      ]);
  }
  public function test_save_fitness_validations(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user);
      $player = Profile::factory()->create([
          'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
      ]);

      $data = [

          'bench_press'=>fake()->numberBetween('10', 50),
          'front_squat'=>fake()->numberBetween(10, 50),
          'back_squat'=>fake()->numberBetween(10, 50),
          'power_clean'=>fake()->numberBetween(30, 90),
          'dead_lift'=>fake()->numberBetween(50, 100),
          'yd_40_dash'=>fake()->numerify('##.##'),
          'yd_60_dash'=>fake()->numerify('##.##'),
      ];

      $response = $this->json('POST', 'api/player/fitness', $data);
      $response->assertUnprocessable()->assertJsonStructure([
          'code',
          'status',
          'message',
          'data'
      ]);
  }

  public function test_save_fitness_error(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user);
      $player = Profile::factory()->create([
          'user_id'=>User::factory()->create(['type' => UserTypes::PLAYER->value])->id
      ]);

      $data = [
          'user_id'=>fake()->uuid,
          'fitness_date'=>fake()->date,
          'bench_press'=>fake()->numberBetween('10', 50),
          'front_squat'=>fake()->numberBetween(10, 50),
          'back_squat'=>fake()->numberBetween(10, 50),
          'power_clean'=>fake()->numberBetween(30, 90),
          'dead_lift'=>fake()->numberBetween(50, 100),
          'yd_40_dash'=>fake()->numerify('##.##'),
          'yd_60_dash'=>fake()->numerify('##.##'),
      ];

      $response = $this->json('POST', 'api/player/fitness', $data);
      $response->assertServerError()->assertJsonStructure([
          'code',
          'status',
          'message',
          'data'
      ]);
  }
}
