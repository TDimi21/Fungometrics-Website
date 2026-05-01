<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Filters;

use App\Http\Requests\Api\Training\Result\FilterRequest;
use App\Models\BattingPracticeResult;
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

class FilterBattingTrainingsTest extends TestCase
{
    public function test_get_statistics_batting_results_all_ok(): void
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


        $practice2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value
        ])->id;
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
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
                PracticeTypes::BATTING->value => [
                    0,//totals
                    1,//percentages
                    2,//average velocity breakdown
                    3,//Max velocity
                    4,//Left type of hit %
                    5,//right type of hit %
                    6,//middle type of hit %
                    7,//Left quality of contact %
                    8,//right quality of contact %
                    9//middle quality of contact %
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

    public function test_get_statistics_batting_results_some_options_ok(): void
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


        $practice2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value
        ])->id;
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
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
                PracticeTypes::BATTING->value => [
                    //          0,//totals
                    //          1,//percentages
                    //          2,//average velocity breakdown
                    //          3,//Max velocity
                    4,//Left type of hit %
                    5,//right type of hit %
                    6,//middle type of hit %
                    7,//Left quality of contact %
                    8,//right quality of contact %
                    9//middle quality of contact %
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

    public function test_get_statistics_batting_results_some_options2_ok(): void
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


        $practice2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value
        ])->id;
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
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
                PracticeTypes::BATTING->value => [
                    0,//totals
                    //          1,//percentages
                    2,//average velocity breakdown
                    //          3,//Max velocity
                    4,//Left type of hit %
                    //          5,//right type of hit %
                    6,//middle type of hit %
                    //          7,//Left quality of contact %
                    8,//right quality of contact %
                    //          9//middle quality of contact %
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

    public function test_get_statistics_batting_not_found(): void
    {
        $this->mock(FilterRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid(), []);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_batting_validations_errors(): void
    {

        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid(), []);
        $response->assertUnprocessable()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_batting_ok_empty_by_dates(): void
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


        $practice2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value
        ])->id;
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);

        $data = [
            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::BATTING->value => [
                    0,//totals
                    //          1,//percentages
                    2,//average velocity breakdown
                    //          3,//Max velocity
                    4,//Left type of hit %
                    //          5,//right type of hit %
                    6,//middle type of hit %
                    //          7,//Left quality of contact %
                    8,//right quality of contact %
                    //          9//middle quality of contact %
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

    public function test_get_statistics_batting_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
        $response->assertUnauthorized()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_batting_forbidden(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $team = Team::factory()->create();

        $data = [
            'dates'=>['2023-01-01','2023-01-01'],
            'players' => [],
            'options' => [
                PracticeTypes::BATTING->value => [
                    0,//totals
                    //          1,//percentages
                    2,//average velocity breakdown
                    //          3,//Max velocity
                    4,//Left type of hit %
                    //          5,//right type of hit %
                    6,//middle type of hit %
                    //          7,//Left quality of contact %
                    8,//right quality of contact %
                    //          9//middle quality of contact %
                ],
            ]
        ];
        $response = $this->json('GET', 'api/result/statistics/'.$team->id, $data);
        $response->assertForbidden()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_batting_results_all_options_string_ok(): void
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


        $practice2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value
        ])->id;
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
            'team_id' => $team->id
        ]);
        BattingPracticeResult::factory(random_int(1, 4))->create([
            'practice_id' => $practice2,
            'batter_id' => Arr::random($player)->user_id,
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
                PracticeTypes::BATTING->value => [
                    0,//totals
                    1,//percentages
                    2,//average velocity breakdown
                    3,//Max velocity
                    4,//Left type of hit %
                    5,//right type of hit %
                    6,//middle type of hit %
                    7,//Left quality of contact %
                    8,//right quality of contact %
                    9//middle quality of contact %
                ],
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
