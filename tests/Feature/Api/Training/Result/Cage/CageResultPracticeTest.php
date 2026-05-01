<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result\Cage;

use App\Http\Requests\Api\Training\Result\CageRequest;
use App\Models\Concerns\CagePositions;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Arr;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CageResultPracticeTest extends TestCase
{
    public function test_cage_practice_result_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data=[
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'launch_angle'=>fake()->numerify('##.#'),
            'launch_angle_velocity'=>fake()->numerify('##.#'),
            'spray_angle'=>fake()->numerify('##.#'),
            'distance_travel'=>fake()->numberBetween(50, 300),
            'cage_mark'=>fake()->numberBetween(50, 300),
            'cage_position'=>Arr::random(CagePositions::cases()),
            'ground_ball'=>false,
        ];

        $response = $this->json('POST', 'api/result/cage', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_cage_practice_result_without_team_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data=[
            'practice_id' => Practice::factory()->create(['type' => UserTypes::PLAYER->value])->id,
            'user_id' => User::factory()->create()->id,
            'launch_angle'=>fake()->numerify('##.#'),
            'launch_angle_velocity'=>fake()->numerify('##.#'),
            'spray_angle'=>fake()->numerify('##.#'),
            'cage_mark'=>fake()->numberBetween(50, 300),
            'cage_position'=>Arr::random(CagePositions::cases()),
            'distance_travel'=>fake()->numberBetween(50, 300),
            'ground_ball'=>false,
        ];

        $response = $this->json('POST', 'api/result/cage', $data);
        $response->assertCreated()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_cage_practice_result_validate(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $data=[

        ];

        $response = $this->json('POST', 'api/result/cage', $data);
        $response->assertUnprocessable()->assertJsonStructure([
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

        $response = $this->json('POST', 'api/result/cage', $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_cage_practice_result_fail(): void
    {
        $this->mock(CageRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn(true));
        Sanctum::actingAs(User::factory()->create());
        $data=[

        ];

        $response = $this->json('POST', 'api/result/cage', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
}
