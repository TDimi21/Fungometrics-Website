<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Batting;

use App\Http\Requests\Api\Training\Result\BattingPracticeRequest;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
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

class BattingResultPracticeTest extends TestCase
{
    public function test_batting_practice_result_with_hit_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'team_id' => Team::factory()->create()->id,
            'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'is_contact' => true,
            'pitch_location' => SidesPitchPosition::TOP_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'quality_of_contact' => ContactQuality::HARD->value,
            'type_of_hit' => BattingTrajectory::FLY_BALL->value,
            'field_mark' => fake()->numberBetween(),
            'field_direction' => SidesPitchPosition::MIDDLE_LEFT->value,
            'velocity' => fake()->numberBetween(),
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>'S'

        ];
        $response = $this->json('POST', 'api/result/batting', $data);
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

    public function test_batting_practice_result_not_hit_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'team_id' => Team::factory()->create()->id,
            'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'pitch_location' => SidesPitchPosition::TOP_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'quality_of_contact' => ContactQuality::MISS_FOUL->value,
            'type_of_hit' => BattingTrajectory::SWING_MISS->value,
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>'S'

        ];
        $response = $this->json('POST', 'api/result/batting', $data);
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

    public function test_batting_practice_result_no_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        $data = [
            'practice_id' => $practice->id,
            'batter_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'pitch_location' => SidesPitchPosition::BOTTOM_LEFT->value,
            'pitch_mark' => fake()->numberBetween(),
            'quality_of_contact' => ContactQuality::MISS_FOUL->value,
            'type_of_hit' => BattingTrajectory::SWING_MISS->value,
            'sort' => fake()->randomDigitNotZero(),
            'zone'=>'S'

        ];
        $response = $this->json('POST', 'api/result/batting', $data);
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

    public function test_batting_practice_result_unauthorized(): void
    {
        $data = [];
        $response = $this->json('POST', 'api/result/batting', $data);
        $response->assertUnauthorized();
    }

    public function test_batting_practice_result_validated(): void
    {
        $data = [];
        Sanctum::actingAs(User::factory()->create());
        $response = $this->json('POST', 'api/result/batting', $data);
        $response->assertUnprocessable();
    }

    public function test_batting_practice_result_fail(): void
    {
        $this->mock(BattingPracticeRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $data = [];
        Sanctum::actingAs(User::factory()->create());
        $response = $this->json('POST', 'api/result/batting', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
