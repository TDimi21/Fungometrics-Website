<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Cage;

use App\Models\CagePracticeResult;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CageGetResultPracticeTest extends TestCase
{
    public function test_get_cage_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = CagePracticeResult::factory()->create();

        $response = $this->json('GET', "api/result/cage/{$result->id}");
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_cage_practice_result_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = fake()->uuid();
        $response = $this->json('GET', "api/result/cage/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_cage_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/cage/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
