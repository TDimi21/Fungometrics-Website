<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\LongToss;

use App\Http\Requests\Api\Training\Result\LongTossEditRequest;
use App\Models\LongTossPractice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LongTossEditResultPracticeTest extends TestCase
{
    public function test_long_toss_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $practice = LongTossPractice::factory()->create();
        $data = [
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'hop' => fake()->numberBetween(0, 3),
            'distance' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('PUT', "api/result/longtoss/{$practice->id}", $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_long_toss_practice_result_team_unauthorized(): void
    {
        $response = $this->json('PUT', "api/result/longtoss/{fake()->uuid()}", []);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_long_toss_practice_result_team_not_found(): void
    {
        $this->mock(LongTossEditRequest::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));

        Sanctum::actingAs(User::factory()->create());

        $data = [
        ];

        $response = $this->json('PUT', "api/result/longtoss/{fake()->uuid()}", $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
