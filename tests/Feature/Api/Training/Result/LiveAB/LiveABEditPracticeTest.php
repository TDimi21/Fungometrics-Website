<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\LiveAB;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Concerns\UserTypes;
use App\Models\LiveABPracticeResult;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveABEditResultPracticeTest extends TestCase
{
    public function test_liveab_edit_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $dataCreate = [
            'practice_id' => $practice->id,
            'sort' => fake()->numberBetween(1, 9),
            'bases' => fake()->numberBetween(0, 3),
            'pitch_location' => SidesPitchPosition::BOTTOM_RIGHT->value,
            'pitch_mark' => fake()->numberBetween(1, 60),
            'type_of_hit' => BattingTrajectory::FLY_BALL->value,
            'is_contact' => true,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => fake()->numberBetween(0, 2),
                'ball' => fake()->numberBetween(0, 3),
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::HARD->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesPitchPosition::MIDDLE_RIGHT->value,
                'velocity' => fake()->numberBetween(),
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $dataCreate);

        $liveab = LiveABPracticeResult::first();
        $data =[
            'sort' => fake()->numberBetween(1, 9),
            'bases' => fake()->numberBetween(0, 3),
            'pitch_location' => SidesPitchPosition::BOTTOM_RIGHT->value,
            'pitch_mark' => fake()->numberBetween(1, 60),
            'type_of_hit' => BattingTrajectory::FLY_BALL->value,
            'is_contact' => true,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => fake()->numberBetween(0, 2),
                'ball' => fake()->numberBetween(0, 3),
                'is_over' => false,
            ],
            'batting' => [
                'quality_of_contact' => ContactQuality::HARD->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesPitchPosition::MIDDLE_RIGHT->value,
                'velocity' => fake()->numberBetween(),
            ],
            'pitching' => [
                'miles_per_hour' => fake()->numberBetween(),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('PUT', "api/result/liveab/{$liveab->id}", $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_liveab_edit_practice_result_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);

        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $dataCreate = [
            'practice_id' => $practice->id,
            'sort' => fake()->numberBetween(1, 9),
            'bases' => fake()->numberBetween(0, 3),
            'pitch_location' => SidesPitchPosition::BOTTOM_RIGHT->value,
            'pitch_mark' => fake()->numberBetween(1, 60),
            'type_of_hit' => BattingTrajectory::FLY_BALL->value,
            'is_contact' => true,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => fake()->numberBetween(0, 2),
                'ball' => fake()->numberBetween(0, 3),
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::HARD->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesPitchPosition::MIDDLE_RIGHT->value,
                'velocity' => fake()->numberBetween(),
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $dataCreate);

        $data =[

        ];

        $response = $this->json('PUT', "api/result/liveab/{$practice->id}", $data);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_liveab_practice_result_unauthorized(): void
    {
        $data =[];

        $response = $this->json('PUT', "api/result/liveab/{fake()->uuid()}", $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
