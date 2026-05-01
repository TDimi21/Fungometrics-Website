<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Filters;

use App\Http\Requests\Api\Training\Result\FilterRequest;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LiveABPracticeResult;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FilterLiveABTrainingsTest extends TestCase
{
    public function test_get_statistics_liveab_all_params_ok(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        $practiceLiveAB2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB2->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        $data = [
            'dates'=>[
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::LIVE_AB->value => [
                    48,//hitter basic
                    49,//hitter advance
                    50,//pitcher basic
                    51,//pitcher advance
                    52,//hitter pitch breakdown
                    53,//hitter contact
                    54,//hitter trajectory
                    55,//hitter velocity
                    56,//pitcher pitch breakdown
                    57,//pitcher contact
                    58//pitcher velocity
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

    public function test_get_statistics_liveab_some_params_ok(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        $practiceLiveAB2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB2->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_b_s' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);


        $data = [
            'dates'=>[
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::LIVE_AB->value => [
                    48,//hitter basic
                    50,//pitcher basic
                    52,//hitter pitch breakdown
                    54,//hitter trajectory
                    56,//pitcher pitch breakdown
                    58//pitcher velocity
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
    public function test_get_statistics_liveab_some2_params_ok(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        $practiceLiveAB2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB2->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);


        $data = [
            'dates'=>[
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::LIVE_AB->value => [
                    49,//hitter advance
                    51,//pitcher advance
                    53,//hitter contact
                    55,//hitter velocity
                    57,//pitcher contact
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

    public function test_get_statistics_liveab_no_dates_ok(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
                'is_in_match' => true,

            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
                'is_in_match' => true,

            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        $practiceLiveAB2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practiceLiveAB2->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,'is_in_match' => true,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);


        $data = [
            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => [
                PracticeTypes::LIVE_AB->value => [
                    48,//hitter basic
                    49,//hitter advance
                    50,//pitcher basic
                    51,//pitcher advance
                    52,//hitter pitch breakdown
                    53,//hitter contact
                    54,//hitter trajectory
                    55,//hitter velocity
                    56,//pitcher pitch breakdown
                    57,//pitcher contact
                    58//pitcher velocity
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

    public function test_get_statistics_liveab_not_found(): void
    {
        $this->mock(FilterRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);



        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_liveab_validations_erros(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);



        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
        $response->assertUnprocessable()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_liveab_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/statistics/'.fake()->uuid, []);
        $response->assertUnauthorized()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_liveab_forbidden(): void
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

    public function test_get_statistics_liveab_all_params_options_string_ok(): void
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
        $practiceLiveAB = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[3]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB->id,
                'team_id' => $team->id,
                'batter_id' =>  $player[1]->user_id,
            ])->id
        ]);
        $practiceLiveAB2 = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB->value,
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);
        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practiceLiveAB2->id,
            'count_s_b' => '0-0',
            'turn_pitches' => '0',
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'pitcher_id' =>  $player[2]->user_id,
            ])->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'practice_id' => $practiceLiveAB2->id,
                'team_id' => $team->id,
                'batter_id' => $player[4]->user_id,
            ])->id
        ]);

        $data = [
            'dates'=>[
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->format('Y-m-d')
            ],
            //            'dates'=>['2023-01-01','2023-01-01'],
            'players' => collect($player)->pluck('user_id')->all(),
            'options' => json_encode([
                PracticeTypes::LIVE_AB->value => [
                    48,//hitter basic
                    49,//hitter advance
                    50,//pitcher basic
                    51,//pitcher advance
                    52,//hitter pitch breakdown
                    53,//hitter contact
                    54,//hitter trajectory
                    55,//hitter velocity
                    56,//pitcher pitch breakdown
                    57,//pitcher contact
                    58//pitcher velocity
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
