<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Http\Requests\Api\Coach\EditPlayerRequest;
use App\Models\Concerns\PlayerPositions;
use App\Models\Concerns\SidesPLayer;
use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\PlayerPosition;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditPlayerTest extends TestCase
{
    public function test_player_can_edit_their_own_profile(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::PLAYER->value,
            'subscription_plan' => 'player_basic',
        ]);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);
        PlayerPosition::factory()->create(['player_id' => $user->id]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        $response = $this->postJson('api/edit/players/'.$user->id, [
            'email' => $user->email,
            'phone' => $user->phone,
            'profile' => ['name' => ['first' => 'Updated', 'last' => 'Player']],
            'player' => [
                'born' => '2010-05-12',
                'ft' => 5,
                'inch' => 10,
                'shirt' => 24,
                'sides' => ['pitch' => 'R', 'hit' => 'L'],
            ],
            'positions' => [['position' => PlayerPositions::SHORT_STOP->value]],
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.profile.first_name', 'Updated')
            ->assertJsonPath('data.player.height_in_ft', 5);
    }

    public function test_player_cannot_edit_another_player_profile(): void
    {
        $actor = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $otherPlayer = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($actor, [UserTypes::PLAYER->value]);

        $this->postJson('api/edit/players/'.$otherPlayer->id, [])->assertNotFound();
    }

    public function test_coach_without_edit_player_access_cannot_update_roster_player(): void
    {
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'free',
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->postJson('api/edit/players/'.$player->id, $this->validPlayerPayload($player))
            ->assertForbidden()
            ->assertJsonPath('required_entitlement', 'edit_player');
    }

    public function test_coach_cannot_update_former_team_player(): void
    {
        $coach = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => false]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->postJson('api/edit/players/'.$player->id, [])->assertNotFound();
    }

    public function test_edit_player_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->user_id, 'team_id' => $team->id, 'actual' => true]);

        PlayerPosition::factory(3)->create([
            'player_id' => $player->user_id
        ]);


        Storage::fake('s3');
        config()->set('filesystems.default', 's3');
        config()->set('filesystems.disks.s3.bucket', 'fungometrics');
        config()->set('filesystems.disks.s3.region', 'eu-central-1');
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
                'weight' => 80,
                'born' => fake()->date,
                'shirt' => fake()->randomDigit(),
                'sides' => [
                    'pitch' => SidesPLayer::LEFT->value,
                    'hit' => SidesPLayer::LEFT->value,
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
        $imagePath = parse_url($data_response->data->profile->picture, PHP_URL_PATH);
        $imagePath = ltrim((string) $imagePath, '/');
        Storage::disk('s3')->assertExists($imagePath);
        $this->assertSame('public', Storage::disk('s3')->getVisibility($imagePath));
    }

    public function test_edit_player_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->user_id, 'team_id' => $team->id, 'actual' => true]);
        $response = $this->json('POST', 'api/edit/players/'.$player->user_id, []);
        $response->assertUnprocessable();
    }

    public function test_edit_player_unauthorized(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
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
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);

        PlayerPosition::factory(3)->create([
            'player_id' => $player->user_id
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
                'weight' => 80,
                'born' => fake()->date,
            ],
            'positions' => [
                ['position' => PlayerPositions::PITCHER->value],
                ['position' => PlayerPositions::FIRST_BASE->value],
            ],
        ];
        $response = $this->json('POST', 'api/edit/players/'.fake()->uuid, $data);
        $response->assertForbidden();
    }

    public function test_edit_player_error2(): void
    {
        $this->mock(EditPlayerRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $player = Player::factory()->create([
            'user_id' => Profile::factory()->create([
                'user_id' => User::factory()->create()->id
            ])->user_id
        ]);

        PlayerPosition::factory(3)->create([
            'player_id' => $player->user_id
        ]);


        Storage::fake('s3');
        $data = [];
        $response = $this->json('POST', 'api/edit/players/'.$player->id, $data);
        $response->assertServerError();
    }

    /** @return array<string, mixed> */
    private function validPlayerPayload(User $player): array
    {
        return [
            'email' => $player->email,
            'phone' => $player->phone,
            'profile' => ['name' => ['first' => 'Roster', 'last' => 'Player']],
            'player' => [
                'born' => '2010-05-12',
                'ft' => 5,
                'inch' => 10,
                'shirt' => 24,
                'sides' => ['pitch' => 'R', 'hit' => 'L'],
            ],
            'positions' => [['position' => PlayerPositions::SHORT_STOP->value]],
        ];
    }
}
