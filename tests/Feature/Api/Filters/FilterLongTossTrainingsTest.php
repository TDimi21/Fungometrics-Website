<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Filters;

use App\Http\Requests\Api\Training\Result\FilterRequest;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LongTossPractice;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FilterLongTossTrainingsTest extends TestCase
{
    public function test_get_statistics_long_toss_ok(): void
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
        $practice6 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::TRAINING->value,
            'modes' => PracticeModes::LONG_TOSS->value
        ])->id;
        LongTossPractice::factory(10)->create([
            'practice_id' => $practice6,
            'user_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);

        $data = [
            'dates'=>[
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeModes::LONG_TOSS->value => [
                    39,//Total
                    40,//%
                    41,//Average Hops
                    42,//Total Throws
                    43,//Max Distance
                    44//Average Distance
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
  public function test_get_statistics_long_toss_not_found(): void
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

    public function test_get_statistics_long_toss_validations_errors(): void
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
  public function test_get_statistics_long_toss_ok_empty_by_dates(): void
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
      $practice6 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::TRAINING->value,
          'modes' => PracticeModes::LONG_TOSS->value
      ])->id;
      LongTossPractice::factory(10)->create([
          'practice_id' => $practice6,
          'user_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);

      $data = [
          'dates'=>[
              Carbon::now()->format('Y-m-d'),
              Carbon::now()->format('Y-m-d')
          ],
          //            'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => [
              PracticeModes::LONG_TOSS->value => [
                  50,//Total
                  51,//%
                  52,//Average Hops
                  53,//Total Throws
                  54,//Max Distance
                  55//Average Distance
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

  public function test_get_statistics_long_toss_unauthorized(): void
  {
      $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
      $response->assertUnauthorized()->assertJsonStructure([
          'status',
          'message',
          'code',
          'data' => []
      ]);
  }

  public function test_get_statistics_long_toss_forbidden(): void
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

  public function test_get_statistics_long_toss_options_string_ok(): void
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
      $practice6 = Practice::factory()->create([
          'team_id' => $team->id,
          'type' => PracticeTypes::TRAINING->value,
          'modes' => PracticeModes::LONG_TOSS->value
      ])->id;
      LongTossPractice::factory(10)->create([
          'practice_id' => $practice6,
          'user_id' => Arr::random($player)->user_id,
          'team_id' => $team->id
      ]);

      $data = [
          'dates'=>[
              Carbon::now()->format('Y-m-d'),
              Carbon::now()->format('Y-m-d')
          ],
          //            'dates'=>['2023-01-01','2023-01-01'],
          'players' => collect($player)->pluck('user_id')->all(),
          'options' => [
              PracticeModes::LONG_TOSS->value => [
                  39,//Total
                  40,//%
                  41,//Average Hops
                  42,//Total Throws
                  43,//Max Distance
                  44//Average Distance
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
}
