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
            'is_main' => true,
        ]);
        PlayerTeam::query()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'actual' => true,
        ]);
    }

    public function test_head_coach_must_invite_passwordless_player(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'password' => null]);
        $this->attachToSameTeam($coach, $player);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ]);

        $response->assertForbidden()->assertJsonPath('next_action', 'invite_player');

        $player->refresh();
        $this->assertNull($player->password);
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

    public function test_denied_password_change_preserves_existing_player_tokens(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'password' => null]);
        $this->attachToSameTeam($coach, $player);
        $player->createToken('old-device', ['player']);

        Sanctum::actingAs($coach, ['coach']);

        $this->postJson("/api/coach/players/{$player->id}/set-password", [
            'password' => 'NewSecure123',
            'password_confirmation' => 'NewSecure123',
        ])->assertForbidden();

        $this->assertSame(1, $player->tokens()->count());
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
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'password' => null]);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => 'HackedPass123',
            'password_confirmation' => 'HackedPass123',
        ]);

        $response->assertUnauthorized();
    }

    public function test_short_password_is_rejected(): void
    {
        $coach  = User::factory()->create(['type' => UserTypes::COACH->value]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'password' => null]);
        $this->attachToSameTeam($coach, $player);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', "api/coach/players/{$player->id}/set-password", [
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
    public function test_existing_password_cannot_be_overridden_even_by_head_coach(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $player = User::factory()->create(['type' => 'player', 'password' => Hash::make('OriginalPass1')]);
        $this->attachToSameTeam($coach, $player);
        Sanctum::actingAs($coach, ['coach']);
        $this->postJson("/api/coach/players/{$player->id}/set-password", ['password' => 'NewSecure123', 'password_confirmation' => 'NewSecure123'])->assertForbidden();
        $this->assertTrue(Hash::check('OriginalPass1', $player->fresh()->password));
    }
    public function test_assistant_cannot_initialize_player_password(): void
    {
        $coach = User::factory()->create(['type'=>'coach']);
        $player = User::factory()->create(['type'=>'player','password'=>null]);
        $this->attachToSameTeam($coach, $player);
        CoachTeam::where('coach_id', $coach->id)->update(['is_main'=>false]);
        Sanctum::actingAs($coach, ['coach']);
        $this->postJson("/api/coach/players/{$player->id}/set-password", ['password'=>'NewSecure123','password_confirmation'=>'NewSecure123'])->assertForbidden();
        $this->assertNull($player->fresh()->password);
    }

    public function test_former_members_and_deleted_memberships_cannot_access_password_route(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $player = User::factory()->create(['type' => 'player', 'password' => null]);
        $this->attachToSameTeam($coach, $player);
        Sanctum::actingAs($coach, ['coach']);
        PlayerTeam::where('user_id', $player->id)->update(['actual' => false]);
        $this->postJson("/api/coach/players/{$player->id}/set-password", [])->assertNotFound();
        PlayerTeam::where('user_id', $player->id)->update(['actual' => true]);
        CoachTeam::where('coach_id', $coach->id)->delete();
        $this->postJson("/api/coach/players/{$player->id}/set-password", [])->assertNotFound();
        $this->assertNull($player->fresh()->password);
    }

    public function test_all_coach_roles_and_legacy_statuses_preserve_claimed_credentials(): void
    {
        foreach ([0, 1] as $status) {
            foreach ([false, true] as $head) {
                $coach = User::factory()->create(['type' => 'coach', 'status' => $status]);
                $player = User::factory()->create(['type' => 'player']);
                $original = $player->password;
                $this->attachToSameTeam($coach, $player);
                CoachTeam::where('coach_id', $coach->id)->update(['is_main' => $head]);
                Sanctum::actingAs($coach, ['coach']);
                $this->postJson("/api/coach/players/{$player->id}/set-password", ['password' => 'NewSecure123'])
                    ->assertForbidden()->assertJsonPath('next_action', 'player_password_recovery');
                $this->assertSame($original, $player->fresh()->password);
                $this->assertTrue(PlayerTeam::where('user_id', $player->id)->where('actual', true)->exists());
            }
        }
    }

    public function test_deleted_player_cannot_be_targeted(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $player = User::factory()->create(['type' => 'player']);
        $this->attachToSameTeam($coach, $player);
        $original = $player->password;
        $player->delete();
        Sanctum::actingAs($coach, ['coach']);
        $this->postJson("/api/coach/players/{$player->id}/set-password", ['password' => 'NewSecure123'])->assertNotFound();
        $this->assertSame($original, User::withTrashed()->findOrFail($player->id)->password);
    }
}
