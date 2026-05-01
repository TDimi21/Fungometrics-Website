<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    public function test_login_user_ok_coach(): void
    {
        $pass = bcrypt('password');
        $user = User::factory()->create([
            'password' => $pass,
            'type' => UserTypes::COACH->value,
        ]);
        $team = Team::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
        ]);
        CoachTeam::factory()->create([
            'is_main' => true,
            'coach_id' => $user->id,
            'team_id' => $team->id,
        ]);
        $response = $this->json('POST', 'api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertOk();
        $data = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($data->data->token);
    }

    public function test_login_user_ok_coach_with_players_data(): void
    {
        $pass = bcrypt('password');
        $user = User::factory()->create([
            'password' => $pass,
            'type' => UserTypes::COACH->value,
        ]);
        $team = Team::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
        ]);

        User::factory()->count(5)->create([
            'type' => UserTypes::PLAYER->value,
        ])->each(function ($user) use ($team): void {
            Profile::factory()->create(['user_id' => $user->id]);
            Player::factory()->create(['user_id' => $user->id]);
            PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'actual' => true]);
        });
        User::factory()->count(3)->create([
            'type' => UserTypes::PLAYER->value,
        ])->each(function ($user) use ($team): void {
            Profile::factory()->create(['user_id' => $user->id]);
            Player::factory()->create(['user_id' => $user->id]);
            PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'actual' => false]);
        });
        CoachTeam::factory()->create([
            'is_main' => true,
            'coach_id' => $user->id,
            'team_id' => $team->id,
        ]);
        $response = $this->json('POST', 'api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertOk();
        $data = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($data->data->token);
    }

    public function test_login_user_ok_player(): void
    {
        $pass = bcrypt('password');
        $user = User::factory()->create([
            'password' => $pass,
            'type' => UserTypes::PLAYER->value,
        ]);
        $team = Team::factory()->create();
        Profile::factory()->create([
            'user_id' => $user->id,
        ]);
        Player::factory()->create(['user_id'=>$user->id]);
        PlayerPosition::factory()->count(5)->create([
            'player_id' => $user->id,
        ]);
        PlayerFitness::factory()->create([
            'user_id' => $user->id,
        ]);
        $response = $this->json('POST', 'api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertOk();
        $data = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertNotNull($data->data->token);
    }

    public function test_login_user_fail(): void
    {
        $user = User::factory()->create();
        $response = $this->json('POST', 'api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $response->assertUnauthorized();
    }

    public function test_login_user_validation_fail(): void
    {
        $user = User::factory()->create();
        $response = $this->json('POST', 'api/login', );
        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => ['errors'],
        ]);
    }

    public function test_login_user_not_found(): void
    {
        $response = $this->json('POST', 'api/login', [
            'email' => fake()->email,
            'password' => 'password',
        ]);
        $response->assertUnauthorized();
    }
}
