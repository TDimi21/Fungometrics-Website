<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\LongToss;

use App\Models\LongTossPractice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LongTossGetResultPracticeTest extends TestCase
{
    public function test_get_long_toss_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = LongTossPractice::factory()->create();

        $response = $this->json('GET', "api/result/longtoss/{$result->id}");
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
        $response = $this->json('GET', "api/result/longtoss/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_long_toss_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/longtoss/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
