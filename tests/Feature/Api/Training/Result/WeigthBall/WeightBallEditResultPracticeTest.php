<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\WeigthBall;

use App\Http\Requests\Api\Training\Result\WeightBallEditRequest;
use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeightBallEditResultPracticeTest extends TestCase
{
    public function test_weight_ball_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $practice = WeightBallPractice::factory()->create();

        $data =[
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 4),
            'weight' => fake()->numberBetween(1, 4),
            'velocity' => fake()->numberBetween(10, 40),
        ];

        $response = $this->json('PUT', "api/result/weightball/{$practice->id}", $data);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_weight_ball_practice_result_unauthorized(): void
    {

        $practice = WeightBallPractice::factory()->create();

        $data =[

        ];

        $response = $this->json('PUT', "api/result/weightball/{$practice->id}", $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_weight_ball_practice_result_not_found(): void
    {
        $this->mock(WeightBallEditRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());
        $data = [];

        $response = $this->json('PUT', "api/result/weightball/{fake()->uuid()}", $data);
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

}
