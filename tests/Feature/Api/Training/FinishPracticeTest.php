<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Http\Requests\Api\Training\FinishPracticeRequest;
use App\Models\Practice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FinishPracticeTest extends TestCase
{
    public function test_finish_practice_session_ok(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create(['is_completed' => false]);
        $data =[
            'end_note'=>fake()->paragraph,
            'is_completed'=>true,
        ];
        $response = $this->json('PUT', 'api/training/'.$practice->id, $data);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($data_response->data->is_completed);
    }
    public function test_finish_practice_session_validate(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create(['is_completed' => false]);
        $data =[

        ];
        $response = $this->json('PUT', 'api/training/'.$practice->id, $data);
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }
    public function test_finish_practice_session_unauthorized(): void
    {
        $practice = Practice::factory()->create(['is_completed' => false]);
        $data =[

        ];
        $response = $this->json('PUT', 'api/training/'.$practice->id, $data);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }
    public function test_finish_practice_session_not_found(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create(['is_completed' => false]);
        $data =[
            'end_note'=>fake()->paragraph,
            'is_completed'=>true,
        ];
        $response = $this->json('PUT', 'api/training/'.fake()->uuid, $data);
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }
    public function test_finish_practice_session_fail(): void
    {
        $this->mock(FinishPracticeRequest::class, fn ($mock) =>$mock->shouldReceive('passes')->andReturn('true'));
        Sanctum::actingAs(User::factory()->create());
        $practice = Practice::factory()->create(['is_completed' => false]);
        $data =[
            'end_note'=>null,
            'is_completed'=>null,
        ];
        $response = $this->json('PUT', 'api/training/'.fake()->uuid, $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }
}
