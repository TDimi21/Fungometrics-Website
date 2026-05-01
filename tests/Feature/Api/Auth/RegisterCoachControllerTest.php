<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Http\Requests\Api\Auth\RegisterCoachRequest;
use App\Models\Concerns\LevelTypes;
use App\Models\User;
use Faker\Provider\en_US\Address;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RegisterCoachControllerTest extends TestCase
{
    public function test_register_coach_ok(): void
    {
        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => fake()->phoneNumber,
            'password' => bcrypt('password'),
            'zip' => fake()->postcode,
            'city' => fake()->city,
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => fake()->date,
                'level' => Arr::random(LevelTypes::cases()),
            ],
            'team' =>  fake()->word.' '.fake()->city
        ];
        $response = $this->json('POST', 'api/coach/register', $data);
        $response->assertCreated();
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($data_response->data->profile->first_name, $data['profile']['name']['first']);
        $this->assertEquals($data_response->data->profile->last_name, $data['profile']['name']['last']);
        $this->assertNotNull($data_response->data->token);
    }

    public function test_register_coach_validation_fail(): void
    {
        $response = $this->json('POST', 'api/coach/register', []);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_coach_validation_email_exist(): void
    {
        $user = User::factory()->create();
        Storage::fake('s3');
        $data = [
            'email' => $user->email,
            'phone' => fake()->phoneNumber,
            'password' => bcrypt('password'),
            'zip' => fake()->postcode,
            'city' => fake()->city,
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => fake()->date,
                'level' => Arr::random(LevelTypes::cases()),
            ],
            'team' =>  fake()->word.' '.fake()->city
        ];
        $response = $this->json('POST', 'api/coach/register', $data);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_coach_validation_phone_exist(): void
    {
        $user = User::factory()->create();
        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => $user->phone,
            'password' => bcrypt('password'),
            'zip' => fake()->postcode,
            'city' => fake()->city,
            'state' => Address::state(),
            'logo' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => fake()->date,
                'level' => Arr::random(LevelTypes::cases()),
            ],
            'team' =>  fake()->word.' '.fake()->city
        ];
        $response = $this->json('POST', 'api/coach/register', $data);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_coach_fail(): void
    {
        $this->mock(RegisterCoachRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });

        $data = [
        ];
        $response = $this->json('POST', 'api/coach/register', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
