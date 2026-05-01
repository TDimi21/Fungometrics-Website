<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\WeigthBall;

use App\Http\Requests\Api\Training\Result\WeightBallRequest;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class WeightBallResultPracticeTest extends TestCase
{
    public function test_weight_ball_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[
            'practice_id' => Practice::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' =>  fake()->numberBetween(1, 4),
            'weight' =>  fake()->numberBetween(1, 4),
            'velocity' =>  fake()->numberBetween(10, 40),
        ];

        $response = $this->json('POST', 'api/result/weightball', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_weight_ball_practice_result_without_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[
            'practice_id' => Practice::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' =>  fake()->numberBetween(1, 4),
            'weight' =>  fake()->numberBetween(1, 4),
            'velocity' =>  fake()->numberBetween(10, 40),
        ];

        $response = $this->json('POST', 'api/result/weightball', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_weight_ball_practice_result_validate(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data =[

        ];

        $response = $this->json('POST', 'api/result/weightball', $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_weight_ball_practice_result_unauthorized(): void
    {
        $data =[

        ];

        $response = $this->json('POST', 'api/result/weightball', $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_weight_ball_practice_result_fail(): void
    {
        $this->mock(WeightBallRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());
        $data =[

        ];

        $response = $this->json('POST', 'api/result/weightball', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
