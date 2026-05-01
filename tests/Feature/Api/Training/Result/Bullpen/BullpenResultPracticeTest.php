<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Bullpen;

use App\Http\Requests\Api\Training\Result\BullpenResultRequest;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class BullpenResultPracticeTest extends TestCase
{
    public function test_save_result_bullpen_practice_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'team_id' => Team::factory()->create()->id,
            'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'pitch_side' => SidesPitchPosition::BOTTOM_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'is_strike' => false,
            'miles_per_hour' => fake()->numberBetween(),
            'type_throw' => PitchThrowTypes::SLIDER->value,
            'trajectory' => BattingTrajectory::FLY_BALL,
            'is_in_match' => false,
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>fake()->randomElement(['B','S','T'])

        ];
        $response = $this->json('POST', 'api/result/bullpen', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'id',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_save_result_bullpen_practice_no_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'pitch_side' => SidesPitchPosition::MIDDLE_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'is_strike' => true,
            'miles_per_hour' => fake()->numberBetween(),
            'type_throw' => PitchThrowTypes::SLIDER->value,
            'trajectory' => BattingTrajectory::FLY_BALL,
            'is_in_match' => false,
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>fake()->randomElement(['B','S','T'])


        ];
        $response = $this->json('POST', 'api/result/bullpen', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'id',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_save_result_bullpen_practice_no_contact_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'team_id' => Team::factory()->create()->id,
            'pitcher_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'pitch_side' => SidesPitchPosition::TOP_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'is_strike' => true,
            'miles_per_hour' => fake()->numberBetween(),
            'type_throw' => PitchThrowTypes::SLIDER->value,
            'trajectory' => BattingTrajectory::SWING_MISS,
            'is_in_match' => false,
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>fake()->randomElement(['B','S','T'])


        ];
        $response = $this->json('POST', 'api/result/bullpen', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'id',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_save_result_bullpen_practice_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->json('POST', 'api/result/bullpen', []);
        $response->assertUnprocessable();
    }

    public function test_save_result_bullpen_practice_unauthorized(): void
    {
        $response = $this->json('POST', 'api/result/bullpen', []);
        $response->assertUnauthorized();
    }

    public function test_save_result_bullpen_practice_fail(): void
    {
        $this->mock(BullpenResultRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn('true');
        });
        Sanctum::actingAs(User::factory()->create());
        $response = $this->json('POST', 'api/result/bullpen', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
