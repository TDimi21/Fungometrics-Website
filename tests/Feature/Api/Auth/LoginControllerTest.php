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
use App\Services\Security\ApiTokenCookie;
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

    public function test_web_login_uses_http_only_cookie_without_returning_a_bearer_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'type' => UserTypes::PLAYER->value,
        ]);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);
        PlayerFitness::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeader('X-FMTRX-Client', 'web')->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token', null)
            ->assertCookie(ApiTokenCookie::NAME);

        $cookie = $response->getCookie(ApiTokenCookie::NAME);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function test_mobile_login_still_returns_bearer_token_without_web_cookie(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'type' => UserTypes::PLAYER->value,
        ]);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);
        PlayerFitness::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('data.type', UserTypes::PLAYER->value);
        self::assertNotEmpty($response->json('data.token'));
        $response->assertCookieMissing(ApiTokenCookie::NAME);
    }

    public function test_bearer_token_can_be_exchanged_for_web_cookie_and_logged_out(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'type' => UserTypes::PLAYER->value,
        ]);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);
        PlayerFitness::factory()->create(['user_id' => $user->id]);
        $plainTextToken = $user->createToken('web-exchange', ['player'])->plainTextToken;

        $exchange = $this->withToken($plainTextToken)->postJson('/api/auth/web-session');

        $exchange->assertOk()->assertCookie(ApiTokenCookie::NAME);
        self::assertSame(1, $user->tokens()->count());

        $this->withToken($plainTextToken)->postJson('/api/logout')
            ->assertOk()
            ->assertCookieExpired(ApiTokenCookie::NAME);
        self::assertSame(0, $user->tokens()->count());
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
