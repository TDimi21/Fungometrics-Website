<?php

declare(strict_types=1);

namespace Tests\Feature\Api\DashBoard;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\LiveABPracticeResult;
use App\Models\Practice;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetDataGraphicsTest extends TestCase
{
    public function test_get_dashboard_static_ok(): void
    {
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
            'type' => PracticeTypes::LIVE_AB->value,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);


        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);






        LiveABPracticeResult::factory()->create([
            'practice_id' => $practice->id,
            'batting_result_id' => BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
            'pitching_result_id' => BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'is_in_match' => true,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
            ])->id,
        ]);

        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practice->id
        ]);
        TeamsLiveAB::factory()->create([
            'team_id' => $team->id,
            'practice_id' => $practice2->id
        ]);

        BattingPracticeResult::factory(35)->create([
            'is_in_match' => false,
            'team_id' => $team->id,
            'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'practice_id' => Practice::factory()->create(['team_id' => $team->id])->id
        ]);
        BullpenPracticeResult::factory(35)->create([
            'is_in_match' => false,
            'team_id' => $team->id,
            'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'practice_id' => Practice::factory()->create(['team_id' => $team->id])->id
        ]);
        BullpenPracticeResult::factory(35)->create([
            'is_in_match' => false,
            'team_id' => $team->id,
            'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'practice_id' => Practice::factory()->create(['team_id' => $team->id])->id
        ]);
        BattingPracticeResult::factory(35)->create([
            'is_in_match' => false,
            'team_id' => $team->id,
            'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'practice_id' => Practice::factory()->create(['team_id' => $team->id])->id
        ]);

        CagePracticeResult::factory(50)->create([
            'practice_id'=>Practice::factory()->create(['team_id' => $team->id,'modes' =>
                PracticeModes::HIT_OR_PITCH->value,'type' => PracticeTypes::CAGE->value])->id,
            'team_id' => $team->id,
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
        ]);
        $response = $this->json('GET', 'api/dashboard/'.$team->id);

        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => [
                'b/s',
                'directional_percents',
                'type_hits_batting_percents',
                'pitch_velocity_average',
                'pitch_throws',
                'type_hits_pitching_percents',
                'launch_angle_average_velocity',
                'swing_miss_take_percents'
            ]
        ]);
    }
}
