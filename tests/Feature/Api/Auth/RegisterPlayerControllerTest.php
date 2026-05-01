<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Http\Requests\Api\Auth\RegisterPlayerRequest;
use App\Models\Concerns\PlayerPositions;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RegisterPlayerControllerTest extends TestCase
{
    public function test_register_player_ok(): void
    {
        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => fake()->phoneNumber,
            'password' => bcrypt('password'),
            'picture' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],


            ],
            'player' => [
                'ft' => 7,
                'inch' => 2,
                'born' => fake()->date,
                'shirt'=>fake()->randomDigit()
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
                ['position' => PlayerPositions::LEFT_FIELDER->value],

            ],
        ];
        $response = $this->json('POST', 'api/player/register', $data);
        $response->assertCreated();
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($data_response->data->profile->first_name, $data['profile']['name']['first']);
        $this->assertEquals($data_response->data->profile->last_name, $data['profile']['name']['last']);
        $this->assertNotNull($data_response->data->token);
    }

    public function test_register_player_validation_fail(): void
    {
        $response = $this->json('POST', 'api/player/register', []);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_player_validation_fail_email_exist(): void
    {
        $user = User::factory()->create();
        Storage::fake('s3');
        $data = [
            'email' => $user->email,
            'phone' => fake()->phoneNumber,
            'password' => bcrypt('password'),
            'picture' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => fake()->date,

            ],
            'player' => [
                'ft' => 7,
                'inch' => 2,
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
                ['position' => PlayerPositions::LEFT_FIELDER->value],

            ],
        ];
        $response = $this->json('POST', 'api/player/register', $data);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_player_validation_fail_phone_exist(): void
    {
        $user = User::factory()->create();
        Storage::fake('s3');
        $data = [
            'email' => $user->email,
            'phone' => fake()->phoneNumber,
            'password' => bcrypt('password'),
            'picture' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => fake()->date,

            ],
            'player' => [
                'ft' => 7,
                'inch' => 2,
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
                ['position' => PlayerPositions::LEFT_FIELDER->value],

            ],
        ];
        $response = $this->json('POST', 'api/player/register', $data);

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_register_player_fail(): void
    {
        $this->mock(RegisterPlayerRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });

        $data = [
        ];
        $response = $this->json('POST', 'api/player/register', $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
