<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Http\Requests\Api\Coach\EditPlayerRequest;
use App\Models\Concerns\PlayerPositions;
use App\Models\Concerns\SidesPLayer;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerPosition;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EditPlayerTest extends TestCase
{
    public function test_edit_player_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);

        PlayerPosition::factory(3)->create([
            'player_id'=>$player->user_id
        ]);


        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => fake()->phoneNumber,
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
                'weight'=>80,
                'born' => fake()->date,
                'shirt' => fake()->randomDigit(),
                'sides'=>[
                    'pitch'=>SidesPLayer::LEFT->value,
                    'hit'=>SidesPLayer::LEFT->value,
                ]
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
            ],
        ];
        $response = $this->json('POST', 'api/edit/players/'.$player->user_id, $data);
        $response->assertOk();
        $data_response = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($data_response->data->email, $data['email']);
        $this->assertEquals($data_response->data->profile->first_name, $data['profile']['name']['first']);
        $this->assertEquals($data_response->data->player->height_in_ft, $data['player']['ft']);
        $this->assertEquals($data_response->data->player->hit_side, $data['player']['sides']['pitch']);
        $this->assertEquals($data_response->data->player->throw_side, $data['player']['sides']['hit']);
        $this->assertEquals(count($data['positions']), count($data_response->data->positions));
    }

    public function test_edit_player_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);
        $response = $this->json('POST', 'api/edit/players/'.$player->user_id, []);
        $response->assertUnprocessable();
    }

    public function test_edit_player_unauthorized(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);
        $response = $this->json('POST', 'api/edit/players/'.$player->user_id, []);
        $response->assertUnauthorized();
    }


    public function test_edit_player_error(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);

        PlayerPosition::factory(3)->create([
            'player_id'=>$player->user_id
        ]);


        Storage::fake('s3');
        $data = [
            'email' => fake()->safeEmail,
            'phone' => fake()->phoneNumber,
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
                'weight'=>80,
                'born' => fake()->date,
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
            ],
        ];
        $response = $this->json('POST', 'api/edit/players/'.fake()->uuid, $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_edit_player_error2(): void
    {
        $this->mock(EditPlayerRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);

        PlayerPosition::factory(3)->create([
            'player_id'=>$player->user_id
        ]);


        Storage::fake('s3');
        $data = [];
        $response = $this->json('POST', 'api/edit/players/'.$player->id, $data);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
