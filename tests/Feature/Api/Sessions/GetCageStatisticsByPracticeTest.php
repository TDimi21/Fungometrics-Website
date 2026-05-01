<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetCageStatisticsByPracticeTest extends TestCase
{
    public function test_get_result_cage_practice_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::CAGE->value, 'team_id' => $team->id]);
        $player1 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player2 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player3 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player4 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player1,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player2,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player3,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player4,]);

        CagePracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'user_id' => $player2,
            'team_id' => $team->id
        ]);

        CagePracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'user_id' => $player3,
            'team_id' => $team->id
        ]);

        CagePracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'user_id' => $player4,
            'team_id' => $team->id
        ]);


        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/cage');
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'count',
                'ball_x_ball',
                'by_player',
                'by_line_drive',
                'by_fly_ball',
                'by_pop_fly',
                'by_ground_ball'
            ]
        ]);
    }

  public function test_get_result_cage_practice_not_found(): void
  {
      $user = User::factory()->create();
      Sanctum::actingAs($user);

      $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/cage');
      $response->assertNotFound()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }

  public function test_get_result_cage_practice_not_authorized(): void
  {
      $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/cage');
      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }
}
