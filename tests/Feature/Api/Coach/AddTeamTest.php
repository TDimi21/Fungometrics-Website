<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Http\Requests\Api\Coach\AddTeamRequest;
use App\Models\Concerns\UserTypes;
use App\Models\User;
use Faker\Provider\en_US\Address;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddTeamTest extends TestCase
{
    public function test_add_team_to_coach_ok(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('team.jpg'),
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $response->assertCreated();
        $this->assertEquals($data_response->data->name, $data['name']);
        $this->assertEquals($data_response->data->state, $data['state']);
        $this->assertNotNull($data_response->data->logo);
    }

    public function test_add_team_to_coach_unauthorized(): void
    {
        User::factory()->create(['type' => UserTypes::COACH->value]);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => fake()->imageUrl,
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertUnauthorized();
    }

    public function test_add_team_to_coach_validated(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $data = [

        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertUnprocessable();
    }

    public function test_add_team_to_coach_error(): void
    {
        $this->mock(AddTeamRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $data = [
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }



    public function test_add_team_to_coach_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, ['player']);
        $data = [
            'name' => fake()->word.' '.fake()->word,
            'zip' => Address::postcode(),
            'state' => Address::state(),
            'logo' => fake()->imageUrl,
        ];
        $response = $this->json('POST', 'api/coach/add/teams', $data);
        $response->assertForbidden();
    }
}
