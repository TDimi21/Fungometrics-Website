<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeletePracticeTest extends TestCase
{
    public function test_delete_practice_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value
        ]);

        BullpenPracticeResult::factory(15)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => User::factory()->create()->id
        ]);

        $response = $this->json('DELETE', 'api/training/'.$practice->id);
        $response->assertOk();
    }

  public function test_delete_practice_error(): void
  {
      $user = User::factory()->create();
      Sanctum::actingAs($user);
      $practice = Practice::factory()->create([
          'type' => PracticeTypes::BATTING->value,
          'modes' => PracticeModes::HIT_OR_PITCH->value
      ]);

      BullpenPracticeResult::factory(15)->create([
          'practice_id' => $practice->id,
          'pitcher_id' => User::factory()->create()->id
      ]);

      $response = $this->json('DELETE', 'api/training/'.fake()->uuid);
      $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
  }

  public function test_delete_practice_unauthorized(): void
  {
      $practice = Practice::factory()->create([
          'type' => PracticeTypes::BATTING->value,
          'modes' => PracticeModes::HIT_OR_PITCH->value
      ]);

      BullpenPracticeResult::factory(15)->create([
          'practice_id' => $practice->id,
          'pitcher_id' => User::factory()->create()->id
      ]);

      $response = $this->json('DELETE', 'api/training/'.fake()->uuid);
      $response->assertUnauthorized();
  }
}
