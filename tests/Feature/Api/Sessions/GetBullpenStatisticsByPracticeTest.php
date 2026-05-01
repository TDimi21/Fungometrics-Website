<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\BullpenPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetBullpenStatisticsByPracticeTest extends TestCase
{
    public function test_get_statistics_bullpen_by_practice_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        $player1 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player2 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player3 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player4 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player1,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player2,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player3,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player4,]);

        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'type_throw' => PitchThrowTypes::FAST_BALL
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'type_throw' => PitchThrowTypes::CURVE_BALL
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player2,
            'type_throw' => PitchThrowTypes::CHANGE_UP
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'type_throw' => PitchThrowTypes::OTHER
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player3,
            'pitch_side' => SidesPitchPosition::TOP_CENTER->value
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'pitch_side' => SidesPitchPosition::TOP_LEFT->value
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player4,
            'pitch_side' => SidesPitchPosition::BOTTOM_RIGHT->value
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'pitch_side' => SidesPitchPosition::MIDDLE_CENTER->value
        ]);

        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player4,
            'trajectory' => BattingTrajectory::LINE_DRIVE->value
        ]);
        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'trajectory' => BattingTrajectory::GROUND_BALL->value
        ]);

        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player3,
            'trajectory' => BattingTrajectory::POP_FLY->value
        ]);

        BullpenPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'pitcher_id' => $player1,
            'trajectory' => BattingTrajectory::FLY_BALL->value
        ]);

        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/bullpen');
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'count',
                'ball_x_ball',
                'by_player',
                'by_throw_type',
                'by_pitch_side',
                'by_trajectory'
            ]
        ]);
    }

    public function test_get_statistics_bullpen_by_practice_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        $response = $this->json('GET', 'api/statistics/'.$this->faker->uuid.'/bullpen');
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_get_statistics_batting_by_practice_not_authorized(): void
    {
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        $response = $this->json('GET', 'api/statistics/'.$this->faker->uuid.'/bullpen');
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
