<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LongTossPractice;
use App\Models\PlayerFitness;
use App\Models\Practice;
use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoreStatisticsPlayersTest extends TestCase
{
    public function test_get_score_statistics_player_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
        //max exit velocity -- bullpen
        BullpenPracticeResult::factory(10)->create([
            'pitcher_id' => $player->id,
            'practice_id' => Practice::factory()->create(['type' => PracticeTypes::BATTING->value])->id

        ]);
        //max distance -- cage
        CagePracticeResult::factory(15)->create([
            'team_id'=>null,
            'user_id' => $player->id,
            'practice_id' => Practice::factory()->create(['type' => PracticeTypes::CAGE->value])->id

        ]);
        //max fast ball -- batting fly ball
        BullpenPracticeResult::factory(6)->create([
            'pitcher_id' => $player->id,
            'type_throw' => PitchThrowTypes::FAST_BALL->value,
            'practice_id' => Practice::factory()->create(['type' => PracticeTypes::BULLPEN->value])->id

        ]);
        BullpenPracticeResult::factory(6)->create([
            'pitcher_id' => $player->id,
            'type_throw' => PitchThrowTypes::FAST_BALL->value,
            'practice_id' => Practice::factory()->create(['type' => PracticeTypes::BULLPEN->value])->id

        ]);
        //max long toss -- long toss
        LongTossPractice::factory(15)->create([
            'user_id' => $player->id,
            'practice_id' => Practice::factory()->create([
                'type' => PracticeTypes::TRAINING->value,
                'modes' => PracticeModes::LONG_TOSS->value
            ])->id

        ]);
        //max strike -- bullpen
        BullpenPracticeResult::factory(10)->create([
            'pitcher_id' => $player->id,
            'is_strike' => true,
            'practice_id' => Practice::factory()->create(['type' => PracticeTypes::BULLPEN->value])->id

        ]);
        //max weight ball -- weight ball
        WeightBallPractice::factory(15)->create([
            'user_id' => $player->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::TRAINING->value,
                    'modes' => PracticeModes::WEIGHT_BALL->value
                ]
            )->id

        ]);

        PlayerFitness::factory('15')->create([
            'user_id' => $player->id
        ]);

        $response = $this->json('GET', 'api/coach/statistics/'.$player->id);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

  public function test_get_score_statistics_player_not_data(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $response = $this->json('GET', 'api/coach/statistics/'.fake()->uuid);
      $response->assertOk()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }

  public function test_get_score_statistics_player_unauthorized(): void
  {
      $response = $this->json('GET', 'api/coach/statistics/'.fake()->uuid);
      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data'
      ]);
  }


}
