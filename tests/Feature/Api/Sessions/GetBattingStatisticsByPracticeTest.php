<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\BattingPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesFieldPosition;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetBattingStatisticsByPracticeTest extends TestCase
{
    public function test_get_statistics_batting_by_practice_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        $player1 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player2 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player3 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        $player4 = Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id;
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player1,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player2,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player3,]);
        PracticeLineUp::factory()->create(['practice_id' => $practice->id, 'user_id' => $player4,]);

        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'quality_of_contact'=>ContactQuality::HARD->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'quality_of_contact'=>ContactQuality::WEAK->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player2,
            'quality_of_contact'=>ContactQuality::AVERAGE->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'quality_of_contact'=>ContactQuality::NONE->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player3,
            'pitch_location'=>SidesPitchPosition::TOP_CENTER->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'pitch_location'=>SidesPitchPosition::TOP_LEFT->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player4,
            'pitch_location'=>SidesPitchPosition::BOTTOM_RIGHT->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'pitch_location'=>SidesPitchPosition::MIDDLE_CENTER->value
        ]);

        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player4,
            'type_of_hit'=>BattingTrajectory::LINE_DRIVE->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'type_of_hit'=>BattingTrajectory::GROUND_BALL->value
        ]);

        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player3,
            'type_of_hit'=>BattingTrajectory::POP_FLY->value
        ]);

        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'type_of_hit'=>BattingTrajectory::FLY_BALL->value
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'field_direction'=>SidesFieldPosition::RIGHT
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player1,
            'field_direction'=>SidesFieldPosition::CENTER
        ]);
        BattingPracticeResult::factory(2)->create([
            'practice_id' => $practice->id,
            'team_id' => $team->id,
            'batter_id' => $player2,
            'field_direction'=>SidesFieldPosition::LEFT
        ]);

        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/batting');

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'count',
                'ball_x_ball',
                'by_contact',
                'by_field_location',
                'by_trajectory',
                'by_player'
            ]
        ]);
    }

    public function test_get_statistics_batting_by_practice_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $practice = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        $response = $this->json('GET', 'api/statistics/'.$this->faker->uuid.'/batting');
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
        $practice = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        $response = $this->json('GET', 'api/statistics/'.$this->faker->uuid.'/batting');
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
