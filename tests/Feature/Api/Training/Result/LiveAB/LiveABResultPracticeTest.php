<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\LiveAB;

use App\Http\Requests\Api\Training\Result\LiveABRequest;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesFieldPosition;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LiveABResultPracticeTest extends TestCase
{
    public function test_liveAB_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = [
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

        $response = $this->json('POST', 'api/result/liveab', $data);

        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_liveAB_practice_result_ball_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = [
            'practice_id' => $practice->id,
            'sort' => fake()->numberBetween(1, 9),
            'bases' => fake()->numberBetween(0, 3),
            'pitch_location' => SidesPitchPosition::TOP_CENTER->value,
            'pitch_mark' => fake()->numberBetween(1, 300),
            'type_of_hit' => BattingTrajectory::TAKE->value,
            'is_contact' => false,
            'zone'=>'B',

            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => 0,
                'ball' => 1,
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::NONE->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesFieldPosition::CENTER->value,
                'velocity' => 0,
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(10, 130),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_liveAB_practice_result_strike_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = [
            'practice_id' => $practice->id,
            'sort' => fake()->numberBetween(1, 9),
            'bases' => fake()->randomDigitNotZero(),
            'pitch_location' => SidesPitchPosition::TOP_CENTER->value,
            'pitch_mark' => fake()->numberBetween(10, 130),
            'type_of_hit' => BattingTrajectory::SWING_MISS->value,
            'is_contact' => false,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => 1,
                'ball' => 0,
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::MISS_FOUL->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesFieldPosition::RIGHT,
                'velocity' => 0,
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(10, 130),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_liveAB_practice_result_foul_strike_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = [
            'practice_id' => $practice->id,
            'sort' => fake()->randomDigitNotZero(),
            'bases' => fake()->randomDigitNotZero(),
            'pitch_location' => SidesPitchPosition::BOTTOM_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'type_of_hit' => BattingTrajectory::SWING_MISS->value,
            'is_contact' => true,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => 1,
                'ball' => 0,
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::MISS_FOUL->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesFieldPosition::NONE->value,
                'velocity' => fake()->numberBetween(10, 130),
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(10, 130),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_liveAB_practice_result_foul_not_sum_strike_ok(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = [
            'practice_id' => $practice->id,
            'sort' => fake()->randomDigitNotZero(),
            'bases' => fake()->numberBetween(0, 3),
            'pitch_location' => SidesFieldPosition::LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'type_of_hit' => BattingTrajectory::FLY_BALL->value,
            'is_contact' => true,
            'zone'=>'S',
            'turn' => [
                'turn' => fake()->numberBetween(1, 9),
                'pitches' => fake()->numberBetween(1, 9),
                'strike' => 2,
                'ball' => 0,
                'is_over' => false,
            ],
            'batting' => [
                'team_id' => Team::factory()->create()->id,
                'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'quality_of_contact' => ContactQuality::MISS_FOUL->value,
                'field_mark' => fake()->numberBetween(),
                'field_direction' => SidesFieldPosition::LEFT->value,
                'velocity' => fake()->numberBetween(10, 130),
            ],
            'pitching' => [
                'team_id' => Team::factory()->create()->id,
                'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
                'miles_per_hour' => fake()->numberBetween(10, 130),
                'type_throw' => PitchThrowTypes::SLIDER->value,
            ],

        ];

        $response = $this->json('POST', 'api/result/liveab', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
        ]);
    }

    public function test_liveAB_practice_result_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/result/liveab', []);
        $response->assertUnprocessable();
    }

    public function test_liveAB_practice_result_unauthorized(): void
    {
        $response = $this->json('POST', 'api/result/liveab', []);
        $response->assertUnauthorized();
    }

    public function test_liveAB_practice_result_fail(): void
    {
        $this->mock(LiveABRequest::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::COACH->value]), [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/result/liveab', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_liveAB_practice_result_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => UserTypes::PLAYER->value]));
        $response = $this->json('POST', 'api/result/liveab', []);
        $response->assertForbidden();
    }
}
