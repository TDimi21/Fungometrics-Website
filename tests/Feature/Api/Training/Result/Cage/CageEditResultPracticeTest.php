<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Cage;

use App\Http\Requests\Api\Training\Result\CageResultEditRequest;
use App\Models\CagePracticeResult;
use App\Models\Concerns\CagePositions;
use App\Models\User;
use Arr;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CageEditResultPracticeTest extends TestCase
{
    public function test_cage_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $practice = CagePracticeResult::factory()->create();

        $data=[
            'launch_angle'=>fake()->numerify('##.#'),
            'launch_angle_velocity'=>fake()->numerify('##.#'),
            'spray_angle'=>fake()->numerify('##.#'),
            'distance_travel'=>fake()->numberBetween(50, 300),
            'cage_mark'=>fake()->numberBetween(50, 300),
            'cage_position'=>Arr::random(CagePositions::cases()),
            'ground_ball'=>false,
        ];

        $response = $this->json('PUT', "api/result/cage/{$practice->id}", $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }


    public function test_cage_practice_result_unauthorized(): void
    {
        $data=[

        ];

        $response = $this->json('PUT', "api/result/cage/{fake()->uuid()}", $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_cage_practice_result_not_found(): void
    {
        $this->mock(CageResultEditRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());

        $data=[

        ];

        $response = $this->json('PUT', "api/result/cage/{fake()->uuid()}", $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
}
