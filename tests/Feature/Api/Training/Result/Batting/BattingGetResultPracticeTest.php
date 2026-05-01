<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Batting;

use App\Models\BattingPracticeResult;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BattingGetResultPracticeTest extends TestCase
{
    public function test_get_batting_practice_result_by_uuid_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $result = BattingPracticeResult::factory()->create();

        $response = $this->json('GET', "api/result/batting/{$result->id}");
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_batting_practice_result_by_uuid_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = fake()->uuid();
        $response = $this->json('GET', "api/result/batting/{$id}");
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => [],
        ]);
    }

    public function test_get_batting_practice_result_by_uuid_not_unauthorized(): void
    {
        $response = $this->json('GET', 'api/result/batting/'.fake()->uuid);
        $response->assertUnauthorized();
    }
}
