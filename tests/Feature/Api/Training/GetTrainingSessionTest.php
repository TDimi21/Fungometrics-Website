<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\CagePracticeMeta;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTrainingSessionTest extends TestCase
{
    public function test_get_training_session_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'team_id' => $team->id,
        ]);
        User::factory()->count(5)->create(['type' => UserTypes::PLAYER->value])
            ->map(static function ($user) use ($practice): void {
                Profile::factory()->create(['user_id' => $user->id]);
                Player::factory()->create(['user_id' => $user->id]);
                PracticeLineUp::factory()->create([
                    'user_id' => $user->id,
                    'practice_id' => $practice->id,
                    'is_batting' => true,

                ]);
            });

        $response = $this->json('GET', 'api/training/'.$practice->id);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'id',
                'type',
                'modes',
                'note',
                'start',
                'players' => [[
                    'id',
                    'name',
                ]],
            ],
        ]);
    }

  public function test_get_training_session_by_uuid_cage_training_ok(): void
  {
      Sanctum::actingAs(User::factory()->create());
      $team = Team::factory()->create();
      $practice = Practice::factory()->create([
          'type' => PracticeTypes::CAGE->value,
          'modes' => PracticeModes::HIT_OR_PITCH->value,
          'team_id' => $team->id,
      ]);
      CagePracticeMeta::factory()->create([
          'practice_id'=>$practice->id
      ]);
      User::factory()->count(5)->create(['type' => UserTypes::PLAYER->value])
          ->map(static function ($user) use ($practice): void {
              Profile::factory()->create(['user_id' => $user->id]);
              Player::factory()->create(['user_id' => $user->id]);
              PracticeLineUp::factory()->create([
                  'user_id' => $user->id,
                  'practice_id' => $practice->id,
                  'is_batting' => true,

              ]);
          });

      $response = $this->json('GET', 'api/training/'.$practice->id);

      $response->assertOk()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data' => [
              'id',
              'type',
              'modes',
              'note',
              'start',
              'players' => [[
                  'id',
                  'name',
              ]],
              'cage_data'
          ],
      ]);
  }

    public function test_get_training_session_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $response = $this->json('GET', 'api/training/'.fake()->uuid);
        $response->assertNotFound();
    }

    public function test_get_session_by_uuid_with_not_lineup(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create();
        $response = $this->json('GET', 'api/training/'.$practice->id);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'id',
                'note',
                'is_completed',
                'start',
                'type',
                'modes',
            ],
        ]);
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(0, $data_response->data->players);
    }

    public function test_get_training_session_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/training/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
