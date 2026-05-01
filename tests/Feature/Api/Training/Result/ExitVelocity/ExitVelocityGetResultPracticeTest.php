<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\ExitVelocity;

use App\Models\ExitVelocityPractice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExitVelocityGetResultPracticeTest extends TestCase
{
    public function test_get_exit_velocity_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = ExitVelocityPractice::factory()->create();

        $response = $this->json('GET', "api/result/exitvelocity/{$result->id}");
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_exit_velocity_practice_result_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = fake()->uuid();
        $response = $this->json('GET', "api/result/exitvelocity/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_exit_velocity_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/exitvelocity/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
