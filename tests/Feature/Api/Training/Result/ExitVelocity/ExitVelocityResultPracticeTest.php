<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\ExitVelocity;

use App\Http\Requests\Api\Training\Result\ExitVelocityRequest;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ExitVelocityResultPracticeTest extends TestCase
{
    public function test_velocity_exit_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'trajectory' => fake()->randomElement([
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::LINE_DRIVE->value,
                BattingTrajectory::FLY_BALL->value,
            ]),
            'velocity' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('POST', 'api/result/exitvelocity', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_velocity_exit_practice_result_without_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'trajectory' => fake()->randomElement([
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::LINE_DRIVE->value,
                BattingTrajectory::FLY_BALL->value,
            ]),
            'velocity' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('POST', 'api/result/exitvelocity', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_velocity_exit_practice_result_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[];

        $response = $this->json('POST', 'api/result/exitvelocity', $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_velocity_exit_practice_result_unauthorized(): void
    {
        $data =[];

        $response = $this->json('POST', 'api/result/exitvelocity', $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_velocity_exit_practice_result_fail(): void
    {
        $this->mock(ExitVelocityRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());
        $data =[];

        $response = $this->json('POST', 'api/result/exitvelocity', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
