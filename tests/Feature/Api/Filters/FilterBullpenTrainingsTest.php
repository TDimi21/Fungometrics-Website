<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Filters;

use App\Http\Requests\Api\Training\Result\FilterRequest;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FilterBullpenTrainingsTest extends TestCase
{
    public function test_get_statistics_bullpen_all_options_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $player[1] = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->user_id
        ]);
        $player[2] = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->user_id
        ]);

        $player[3] = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->user_id
        ]);

        $player[4] = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->user_id
        ]);

        $practice1 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BATTING->value
        ])->id;
        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice1,
            'pitcher_id' => Arr::random($player)->user_id,
            'team_id' => $team->id,
            'created_at' => '2023-01-01'
        ]);
        BullpenPracticeResult::factory(8)->create([
            'practice_id' => $practice1,
            'pitcher_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);


        $data = [
            'dates' => [
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::BULLPEN->value => [
                    10,//totals
                    11,//percentages
                    12,//Average velocity breakdown
                    13,//Top velocity breakdown
                    14,//FastBalls %
                    15,// Curve Balls %
                    16,// Change Up %
                    17,//Slider %
                    18,//Other %
                    19,//Ground Balls %
                    20,//Line drives %
                    21,//Fly Balls %
                    22,//Pop flies %
                    23,//Pop flies %
                    24,//FastBalls Strike %
                    25,//Curve Balls strike%
                    26,//Change up strike %
                    27,//Slider strike %
                    28,//Other strike %
                ],
            ]
        ];
        $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }
  public function test_get_statistics_bullpen_some_options_ok(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $team = Team::factory()->create();
      $player[1] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);
      $player[2] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[3] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[4] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $practice1 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::BATTING->value
      ])->id;
      BullpenPracticeResult::factory(5)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id,
          'created_at' => '2023-01-01'
      ]);
      BullpenPracticeResult::factory(8)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);


      $data = [
          'dates' => [
              Carbon::now()->format('Y-m-d'),
              Carbon::now()->format('Y-m-d')
          ],
          //            'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => [
              PracticeTypes::BULLPEN->value => [
                  10,//totals
                  //          11,//percentages
                  12,//Average velocity breakdown
                  //          13,//Top velocity breakdown
                  14,//FastBalls %
                  //          15,// Curve Balls %
                  16,// Change Up %
                  //          17,//Slider %
                  18,//Other %
                  //          19,//Ground Balls %
                  20,//Line drives %
                  //          21,//Fly Balls %
                  22,//Pop flies %
                  //          23,//Pop flies %
                  24,//FastBalls Strike %
                  //          25,//Curve Balls strike%
                  26,//Change up strike %
                  //          27,//Slider strike %
                  28,//Other strike %
              ],
          ]
      ];
      $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
      $response->assertOk()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }
  public function test_get_statistics_bullpen_some_options2_ok(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $team = Team::factory()->create();
      $player[1] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);
      $player[2] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[3] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[4] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $practice1 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::BATTING->value
      ])->id;
      BullpenPracticeResult::factory(5)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id,
          'created_at' => '2023-01-01'
      ]);
      BullpenPracticeResult::factory(8)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);


      $data = [
          'dates' => [
              Carbon::now()->format('Y-m-d'),
              Carbon::now()->format('Y-m-d')
          ],
          //            'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => [
              PracticeTypes::BULLPEN->value => [
                  //          10,//totals
                  11,//percentages
                  //          12,//Average velocity breakdown
                  13,//Top velocity breakdown
                  //          14,//FastBalls %
                  15,// Curve Balls %
                  //          16,// Change Up %
                  17,//Slider %
                  //          18,//Other %
                  19,//Ground Balls %
                  //          20,//Line drives %
                  21,//Fly Balls %
                  //          22,//Pop flies %
                  23,//Pop flies %
                  //          24,//FastBalls Strike %
                  25,//Curve Balls strike%
                  //          26,//Change up strike %
                  27,//Slider strike %
                  //          28,//Other strike %
              ],
          ]
      ];
      $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
      $response->assertOk()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }
  public function test_get_statistics_bullpen_not_found(): void
  {
      $this->mock(FilterRequest::class, function ($mock): void {
          $mock->shouldReceive('passes')->andReturn(true);
      });
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);

      $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
      $response->assertNotFound()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }

    public function test_get_statistics_bullpen_validations_errors(): void
    {

        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
        $response->assertUnprocessable()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }
  public function test_get_statistics_bullpen_ok_empty_by_dates(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $team = Team::factory()->create();
      $player[1] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);
      $player[2] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[3] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[4] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $practice1 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::BATTING->value
      ])->id;
      BullpenPracticeResult::factory(5)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id,
          'created_at' => '2023-01-01'
      ]);
      BullpenPracticeResult::factory(8)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);


      $data = [
          'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => [
              PracticeTypes::BULLPEN->value => [
                  10,//totals
                  11,//percentages
                  12,//Average velocity breakdown
                  13,//Top velocity breakdown
                  14,//FastBalls %
                  15,// Curve Balls %
                  16,// Change Up %
                  17,//Slider %
                  18,//Other %
                  19,//Ground Balls %
                  20,//Line drives %
                  21,//Fly Balls %
                  22,//Pop flies %
                  23,//Pop flies %
                  24,//FastBalls Strike %
                  25,//Curve Balls strike%
                  26,//Change up strike %
                  27,//Slider strike %
                  28,//Other strike %
              ],
          ]
      ];
      $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
      $response->assertOk()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }

  public function test_get_statistics_bullpen_unauthorized(): void
  {
      $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
      $response->assertUnauthorized()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }
  public function test_get_statistics_bullpen_forbidden(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

      $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
      $response->assertForbidden()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }

  public function test_get_statistics_bullpen_all_options_string_ok(): void
  {
      $user = User::factory()->create([
          'type' => UserTypes::COACH->value
      ]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $team = Team::factory()->create();
      $player[1] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);
      $player[2] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[3] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $player[4] = Player::factory()->create([
          'user_id' => Profile::factory()->create([
              'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
          ])->user_id
      ]);

      $practice1 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::BATTING->value
      ])->id;
      BullpenPracticeResult::factory(5)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id,
          'created_at' => '2023-01-01'
      ]);
      BullpenPracticeResult::factory(8)->create([
          'practice_id' => $practice1,
          'pitcher_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);


      $data = [
          'dates' => [
              Carbon::now()->format('Y-m-d'),
              Carbon::now()->format('Y-m-d')
          ],
          //            'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => json_encode([
              PracticeTypes::BULLPEN->value => [
                  10,//totals
                  11,//percentages
                  12,//Average velocity breakdown
                  13,//Top velocity breakdown
                  14,//FastBalls %
                  15,// Curve Balls %
                  16,// Change Up %
                  17,//Slider %
                  18,//Other %
                  19,//Ground Balls %
                  20,//Line drives %
                  21,//Fly Balls %
                  22,//Pop flies %
                  23,//Pop flies %
                  24,//FastBalls Strike %
                  25,//Curve Balls strike%
                  26,//Change up strike %
                  27,//Slider strike %
                  28,//Other strike %
              ]
          ])
      ];
      $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
      $response->assertOk()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }

}
