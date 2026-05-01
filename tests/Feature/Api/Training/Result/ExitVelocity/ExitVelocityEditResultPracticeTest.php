<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\ExitVelocity;

use App\Http\Requests\Api\Training\Result\ExitVelocityEditRequest;
use App\Models\Concerns\BattingTrajectory;
use App\Models\ExitVelocityPractice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExitVelocityEditResultPracticeTest extends TestCase
{
    public function test_velocity_exit_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $practice = ExitVelocityPractice::factory()->create();

        $data =[
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'trajectory' => fake()->randomElement([
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::LINE_DRIVE->value,
                BattingTrajectory::FLY_BALL->value,
            ]),
            'velocity' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('PUT', "api/result/exitvelocity/{$practice->id}", $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_velocity_exit_practice_result_unauthorized(): void
    {
        $data =[];

        $response = $this->json('PUT', "api/result/exitvelocity/{fake()->uuid()}", $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_velocity_exit_practice_result_not_found(): void
    {
        $this->mock(ExitVelocityEditRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());
        $data =[];

        $response = $this->json('PUT', "api/result/exitvelocity/{fake()->uuid()}", $data);
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
