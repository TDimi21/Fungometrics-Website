<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\WeigthBall;

use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeigthBallGetResultPracticeTest extends TestCase
{
    public function test_get_long_toss_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = WeightBallPractice::factory()->create();

        $response = $this->json('GET', "api/result/weightball/{$result->id}");
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_long_toss_practice_result_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = fake()->uuid();
        $response = $this->json('GET', "api/result/weightball/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_long_toss_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/weightball/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
