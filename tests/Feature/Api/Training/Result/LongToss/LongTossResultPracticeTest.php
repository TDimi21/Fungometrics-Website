<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\LongToss;

use App\Http\Requests\Api\Training\Result\LongTossRequest;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class LongTossResultPracticeTest extends TestCase
{
    public function test_long_toss_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = [
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'hop' => fake()->numberBetween(0, 3),
            'distance' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('POST', 'api/result/longtoss', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_long_toss_practice_result_without_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = [
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'set' => fake()->numberBetween(1, 4),
            'sort' => fake()->numberBetween(1, 5),
            'hop' => fake()->numberBetween(0, 3),
            'distance' => fake()->numberBetween(20, 200),
        ];

        $response = $this->json('POST', 'api/result/longtoss', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_long_toss_practice_result_team_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data = [

        ];

        $response = $this->json('POST', 'api/result/longtoss', $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [
                'errors'
            ]
        ]);
    }

    public function test_long_toss_practice_result_team_unauthorized(): void
    {
        $response = $this->json('POST', 'api/result/longtoss', []);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_long_toss_practice_result_team_fail(): void
    {
        $this->mock(LongTossRequest::class, fn ($mock) => $mock->shouldReceive('passes')->andReturn(true));

        Sanctum::actingAs(User::factory()->create());
        $data = [
        ];

        $response = $this->json('POST', 'api/result/longtoss', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
