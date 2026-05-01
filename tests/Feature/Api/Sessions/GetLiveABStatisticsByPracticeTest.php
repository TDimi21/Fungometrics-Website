<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LiveABPracticeResult;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetLiveABStatisticsByPracticeTest extends TestCase
{
    public function test_get_statistics_batting_by_practice_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $practice = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB->value]);

        $playerA1 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $playerA2 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $playerA3 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $playerA4 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;

        $playerB1 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $playerB2 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;


        TeamsLiveAB::factory()->create([
            'practice_id' => $practice->id,
            'team_id' => $teamA->id
        ]);
        TeamsLiveAB::factory()->create([
            'practice_id' => $practice->id,
            'team_id' => $teamB->id
        ]);

        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerA1,
            'is_batting' => true,
            'is_pitching' => false
        ]);
        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerA2,
            'is_batting' => true,
            'is_pitching' => false
        ]);
        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerA3,
            'is_batting' => true,
            'is_pitching' => false
        ]);
        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerA4,
            'is_batting' => true,
            'is_pitching' => false
        ]);

        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerB1,
            'is_batting' => false,
            'is_pitching' => true
        ]);
        PracticeLineUp::factory()->create([
            'practice_id' => $practice->id,
            'user_id' => $playerB2,
            'is_batting' => false,
            'is_pitching' => true
        ]);



        LiveABPracticeResult::factory(random_int(1, 8))->create([
            'practice_id' => $practice,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamA->id,
                'batter_id' => $playerA1,
                'is_contact' => fake()->boolean,
                'is_in_match' => true
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamB->id,
                'pitcher_id' => $playerB1,
                'is_strike' => fake()->boolean,
                'is_in_match' => true
            ]),
        ]);

        LiveABPracticeResult::factory(random_int(1, 8))->create([
            'practice_id' => $practice,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamA->id,
                'batter_id' => $playerA2,
                'is_contact' => fake()->boolean,
                'is_in_match' => true
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamB->id,
                'pitcher_id' => $playerB1,
                'is_strike' => fake()->boolean,
                'is_in_match' => true
            ]),
        ]);

        LiveABPracticeResult::factory(random_int(1, 8))->create([
            'practice_id' => $practice,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamA->id,
                'batter_id' => $playerA3,
                'is_contact' => fake()->boolean,
                'is_in_match' => true
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamB->id,
                'pitcher_id' => $playerB2,
                'is_strike' => fake()->boolean,
                'is_in_match' => true
            ]),
        ]);

        LiveABPracticeResult::factory(random_int(1, 8))->create([
            'practice_id' => $practice,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamA->id,
                'batter_id' => $playerA4,
                'is_contact' => fake()->boolean,
                'is_in_match' => true
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practice->id,
                'team_id' => $teamB->id,
                'pitcher_id' => $playerB2,
                'is_strike' => fake()->boolean,
                'is_in_match' => true
            ]),
        ]);

        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/liveab');

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

  public function test_get_statistics_batting_by_practice_not_found(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);



      $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/liveab');

      $response->assertNotFound()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }

  public function test_get_statistics_batting_by_practice_unauthorized(): void
  {
      $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/liveab');

      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }

  public function test_get_statistics_batting_by_practice_forbidden(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::PLAYER->value
      ]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);


      $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/liveab');

      $response->assertForbidden()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }
}
