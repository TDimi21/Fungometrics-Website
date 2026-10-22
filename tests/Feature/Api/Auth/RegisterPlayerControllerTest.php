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
    public function test_self_registration_uses_the_same_age_boundary_as_invitation_claiming(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-22 12:00:00'));
        foreach (['2013-10-23', '2027-01-01', 'invalid', null] as $born) {
            $count = User::count();
            $this->postJson('/api/player/register', ['player' => ['born' => $born]])
                ->assertUnprocessable()->assertJsonStructure(['data' => ['errors' => ['player.born']]]);
            $this->assertSame($count, User::count());
        }
        // Other required fields still fail; the exact thirteenth birthday does not.
        $response = $this->postJson('/api/player/register', ['player' => ['born' => '2013-10-22']])->assertUnprocessable();
        $this->assertArrayNotHasKey('player.born', $response->json('data.errors'));
    }

    public function test_register_player_ok(): void
    {
        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => fake()->phoneNumber,
            'password' => 'Password123',
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
                'born' => '2000-05-12',
                'shirt' => fake()->randomDigit()
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
        $this->assertFalse(User::findOrFail($data_response->data->user->id)->status);
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
            'password' => 'Password123',
            'picture' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => '2000-05-12',

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
        $user = User::factory()->create([
            'type' => 'player',
            'email' => null,
            'password' => null,
        ]);
        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => $user->phone,
            'password' => 'Password123',
            'picture' => UploadedFile::fake()->image('avatar.jpg'),
            'profile' => [
                'name' => [
                    'first' => fake()->firstName,
                    'last' => fake()->lastName,
                ],
                'born' => '2000-05-12',

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

        $response->assertUnprocessable()
            ->assertJsonPath('data.existing_account.type', 'player')
            ->assertJsonPath('data.existing_account.claimable', true)
            ->assertJsonPath('data.existing_account.next_action', 'claim_player_profile');
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
