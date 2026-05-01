<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Bullpen;

use App\Models\bullpenPracticeResult;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BullpenGetResultPracticeTest extends TestCase
{
    public function test_get_bullpen_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = bullpenPracticeResult::factory()->create();

        $response = $this->json('GET', "api/result/bullpen/{$result->id}");
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_bullpen_practice_result_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = fake()->uuid();
        $response = $this->json('GET', "api/result/bullpen/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_bullpen_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/bullpen/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
