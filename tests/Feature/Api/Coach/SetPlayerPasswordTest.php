<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetPlayerPasswordTest extends TestCase
{
    private function attachToSameTeam(User $coach, User $player): void
    {
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $coach->id,
            'team_id' => $team->id,
        ]);
        PlayerTeam::query()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'actual' => true,
        ]);
    }

    public function test_coach_can_set_player_password(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $this->attachToSameTeam($coach, $player);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ]);

        $response->assertOk();

        $player->refresh();
        $this->assertTrue(Hash::check('NewSecure123', $player->password));
    }

    public function test_coach_cannot_set_unrelated_player_password(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create([
            'type' => UserTypes::PLAYER->value,
            'password' => Hash::make('OriginalPass1'),
        ]);

        Sanctum::actingAs($coach, ['coach']);

        $this->postJson("/api/coach/players/{$player->id}/set-password", [
            'password' => 'HackedPass123',
            'password_confirmation' => 'HackedPass123',
        ])->assertNotFound();

        $this->assertTrue(Hash::check('OriginalPass1', $player->fresh()->password));
    }

    public function test_setting_player_password_revokes_existing_player_tokens(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $this->attachToSameTeam($coach, $player);
        $player->createToken('old-device', ['player']);

        Sanctum::actingAs($coach, ['coach']);

        $this->postJson("/api/coach/players/{$player->id}/set-password", [
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])->assertOk();

        $this->assertSame(0, $player->tokens()->count());
    }

    public function test_player_cannot_set_another_players_password(): void
    {
        $attacker = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $victim   = User::factory()->create(['type' => UserTypes::PLAYER->value, 'password' => Hash::make('OriginalPass1')]);

        Sanctum::actingAs($attacker, ['player']);

        $response = $this->json('POST', "api/coach/players/{$victim->id}/set-password", [
            'password'              => 'HackedPass123',
            'password_confirmation' => 'HackedPass123',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $victim->refresh();
        $this->assertTrue(Hash::check('OriginalPass1', $victim->password));
    }

    public function test_unauthenticated_cannot_set_password(): void
    {
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => 'HackedPass123',
            'password_confirmation' => 'HackedPass123',
        ]);

        $response->assertUnauthorized();
    }

    public function test_short_password_is_rejected(): void
    {
        $coach  = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $this->attachToSameTeam($coach, $player);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
