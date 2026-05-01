<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LiveABPracticeResult;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetDataChartsAverageExitVelocityLiveTest extends TestCase
{
    public function test_get_charts_data_avg_exit_velocity_ok_range_all(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(5),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(5),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(5),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(7),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(7),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(7),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile4->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile2->user_id,
                'created_at' => $date->subWeeks(7),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice2->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice2->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice2->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice2->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice3->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice3->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice3->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice3->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice4->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice4->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id,
                'created_at' => $date->subWeeks(10),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice4->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id,
                'created_at' => $date->subWeeks(10),
            ])->id,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice4->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => $profile1->user_id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => $profile3->user_id,
                'created_at' => $date->subWeeks(10),
            ])->id,
        ]);

        $data = [
            'team'=>$team->id,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

    public function test_get_charts_data_avg_exit_velocity_ok_range_one_year(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        BullpenPracticeResult::factory(20)->create([
            'practice_id' => $practice->id,
            'team_id' => $team,
            'pitcher_id' => $profile1->user_id
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice3->id,
            'team_id' => $team,
            'pitcher_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice4->id,
            'team_id' => $team,
            'pitcher_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        $data = [
            'team'=>$team->id,
            'range' => 12,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

    public function test_get_charts_data_avg_exit_velocity_ok_range_six_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        BullpenPracticeResult::factory(20)->create([
            'practice_id' => $practice->id,
            'team_id' => $team,
            'pitcher_id' => $profile1->user_id
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice3->id,
            'team_id' => $team,
            'pitcher_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice4->id,
            'team_id' => $team,
            'pitcher_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        $data = [
            'team'=>$team->id,
            'range' => 6,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

    public function test_get_charts_data_avg_exit_velocity_ok_range_three_months(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        BullpenPracticeResult::factory(20)->create([
            'practice_id' => $practice->id,
            'team_id' => $team,
            'pitcher_id' => $profile1->user_id
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice3->id,
            'team_id' => $team,
            'pitcher_id' => $profile3->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice4->id,
            'team_id' => $team,
            'pitcher_id' => $profile4->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,
            'team_id' => $team,
            'pitcher_id' => $profile2->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        $data = [
            'team'=>$team->id,
            'range' => 3,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_charts_data_avg_exit_velocity_not_found(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);


        $data = [
            'team'=>fake()->uuid,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_charts_data_avg_exit_velocity_unauthorized(): void
    {
        $date = Carbon::now();

        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);


        $data = [
            'team'=>$team->id,
            'range' => 0,
            'players' => [$profile1->user_id, $profile2->user_id, $profile3->user_id, $profile4->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_charts_data_avg_exit_velocity_validations(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);


        $data = [

        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);

    }

    public function test_get_charts_data_avg_exit_velocity_ok_no_team(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        BullpenPracticeResult::factory(20)->create([
            'practice_id' => $practice->id,

            'pitcher_id' => $profile1->user_id
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice3->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice4->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        $data = [
            'range' => 0,
            'players' => [$profile1->user_id,],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

    public function test_get_charts_data_avg_exit_velocity_not_found_no_team(): void
    {
        $date = Carbon::now();
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        $practice2 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice3 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::BULLPEN->value,
        ]);
        $practice4 = Practice::factory()->create([
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        $profile1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);
        $profile2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $profile4 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        BullpenPracticeResult::factory(20)->create([
            'practice_id' => $practice->id,

            'pitcher_id' => $profile1->user_id
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice3->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);
        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice4->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        BullpenPracticeResult::factory(25)->create([
            'practice_id' => $practice2->id,

            'pitcher_id' => $profile1->user_id,
            'created_at' => fake()->dateTimeBetween('-1 years', $date)
        ]);

        $data = [
            'range' => 0,
            'players' => [fake()->uuid],
            'type' => 1
        ];
        //avg exit velocity -- live
        $response = $this->json('POST', 'api/charts/', $data);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data'
        ]);
        //max exit velocity -- exit velocity
        //max cage distance -- cage
        //total strike percent -- pitching
        //max fb velocity -- pitching
        //average throw velocity for each weigth -- weigth ball
        //max distance throws with 0 hops -- long toss
        //average traininig exit velocity per session -- exit velocity
    }

}
