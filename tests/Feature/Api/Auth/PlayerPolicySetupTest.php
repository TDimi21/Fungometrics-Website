<?php
namespace Tests\Feature\Api\Auth;

use App\Models\{CoachTeam, Player, PlayerClaimSetup, PlayerPolicyAcceptance, PlayerTeam, Team, User};
use App\Services\Consent\PlayerConsentService;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerPolicySetupTest extends TestCase
{
    private function agreement(): array
    {
        return ['policy_accepted' => true, 'terms_version' => config('player_consent.terms_version'),
            'privacy_version' => config('player_consent.privacy_version'), 'acknowledgment_version' => config('player_consent.acknowledgment_version')];
    }
    private function claim(?string $born = null): array
    {
        $player = User::factory()->create(['type' => 'player', 'email' => 'claim@example.com', 'password' => null, 'status' => true]);
        if ($born) Player::factory()->create(['user_id' => $player->id, 'born_date' => $born]);
        $team = Team::factory()->create(['join_code' => 'TEAM23']);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        $response = $this->postJson('/api/player/join', ['contact' => $player->email, 'team_code' => $team->join_code])->assertOk();
        return [$player, $team, $response->json('data.token')];
    }
    public function test_coach_checkbox_is_required_records_actor_and_does_not_require_birthday(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $data = ['team' => $team->id, 'email' => 'newplayer@example.com', 'name' => ['first' => 'New', 'last' => 'Player']];
        $this->postJson('/api/coach/add/players', $data)->assertUnprocessable()->assertJsonValidationErrors('policy_accepted');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        $this->postJson('/api/coach/add/players', [...$data, ...$this->agreement(), 'terms_version' => 'old'])->assertUnprocessable();
        $created = $this->postJson('/api/coach/add/players', [...$data, ...$this->agreement()])->assertOk();
        $id = $created->json('data.id');
        $this->assertDatabaseHas('player_policy_acceptances', ['player_id' => $id, 'actor_id' => $coach->id, 'team_id' => $team->id, 'context' => 'coach_create', 'acknowledgment_text' => config('player_consent.coach_acknowledgment')]);
        $this->assertNull(User::findOrFail($id)->player?->born_date);
        $this->postJson('/api/coach/add/players', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, PlayerPolicyAcceptance::count());
    }
    public function test_missing_birthday_player_can_complete_setup_and_keep_original_identity(): void
    {
        [$player, $team, $token] = $this->claim();
        $this->assertSame(['profile-claim'], PersonalAccessToken::findToken($token)->abilities);
        $credentials = ['email' => $player->email, 'password' => 'Password123', 'password_confirmation' => 'Password123'];
        $this->withToken($token)->postJson('/api/player/set-credentials', $credentials)->assertUnprocessable();
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01'])->assertUnprocessable();
        $this->assertDatabaseCount('player_policy_acceptances', 0);
        $setup = ['born_date' => '2000-01-01', ...$this->agreement()];
        $this->withToken($token)->postJson('/api/player/claim-setup', $setup)->assertOk()->assertJsonPath('next_action', 'credentials');
        $this->withToken($token)->postJson('/api/player/claim-setup', $setup)->assertOk();
        $this->assertDatabaseCount('player_policy_acceptances', 1);
        $this->withToken($token)->postJson('/api/player/set-credentials', $credentials)->assertOk()->assertJsonPath('data.user.id', $player->id)->assertJsonPath('data.team.id', $team->id);
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->assertSame('2000-01-01', $player->fresh()->player->born_date);
    }
    public function test_under_13_checkbox_cannot_activate_login_or_change_age_on_retry(): void
    {
        [$player, , $token] = $this->claim();
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => now()->subYears(12)->format('Y-m-d'), ...$this->agreement()])
            ->assertOk()->assertJsonPath('next_action', 'coach_managed');
        $this->assertDatabaseHas('player_claim_setups', ['user_id' => $player->id, 'guardian_required' => true]);
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement()])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/player/set-credentials', ['email' => $player->email, 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertUnprocessable();
        $this->assertNull($player->fresh()->password);
        $this->assertDatabaseCount('player_policy_acceptances', 0);
    }
    public function test_known_youth_birth_date_cannot_be_replaced_with_an_adult_date(): void
    {
        [, , $token] = $this->claim(now()->subYears(12)->format('Y-m-d'));
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement()])->assertUnprocessable();
    }
    public function test_invalid_future_and_stale_policies_cannot_complete_setup(): void
    {
        [, , $token] = $this->claim();
        foreach (['2000-02-30', now()->addDay()->format('Y-m-d')] as $born) {
            $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => $born, ...$this->agreement()])->assertUnprocessable();
        }
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement(), 'privacy_version' => 'stale'])->assertUnprocessable();
        $this->assertDatabaseCount('player_policy_acceptances', 0);
    }
    public function test_thirteenth_birthday_is_eligible_and_policy_revision_blocks_old_acceptance(): void
    {
        [$player, , $token] = $this->claim();
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => now()->subYears(13)->format('Y-m-d'), ...$this->agreement()])->assertOk()->assertJsonPath('next_action', 'credentials');
        config(['player_consent.privacy_version' => 'next']);
        $this->withToken($token)->postJson('/api/player/set-credentials', ['email' => $player->email, 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertUnprocessable();
    }
    public function test_invitation_can_start_without_coach_birth_date_but_cannot_skip_acceptance(): void
    {
        config(['communications.invitations_enabled' => true]);
        [$player, $team] = $this->claim();
        $coach = User::factory()->create(['type' => 'coach']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $secret = str_repeat('a', 64);
        $invite = \App\Models\TeamInvitation::create(['user_id' => $player->id, 'team_id' => $team->id, 'invited_by' => $coach->id, 'role' => 'player', 'email' => $player->email, 'token_hash' => hash('sha256', $secret), 'expires_at' => now()->addDay()]);
        $credential = ['token' => $secret, 'email' => $player->email];
        $this->postJson('/api/invitations/prepare-setup', [...$credential, 'email' => 'wrong@example.com'])->assertUnprocessable();
        $this->postJson('/api/invitations/accept', [...$credential, 'password' => 'Password123'])->assertUnprocessable();
        $prepared = $this->postJson('/api/invitations/prepare-setup', $credential)->assertOk()->assertJsonPath('setup_required', true);
        $token = $prepared->json('token');
        $this->assertSame(['profile-setup'], PersonalAccessToken::findToken($token)->abilities);
        $this->withToken($token)->postJson('/api/player/set-credentials', ['email' => $player->email, 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertForbidden();
        $this->withToken($token)->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement()])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->postJson('/api/invitations/accept', [...$credential, 'password' => 'Password123'])->assertOk()->assertJsonPath('accepted', true);
        $this->assertSame($player->id, $invite->fresh()->user_id);
        $this->assertNull(PersonalAccessToken::findToken($token));
        $this->travel(61)->seconds();
        $this->postJson('/api/invitations/prepare-setup', $credential)->assertUnprocessable();
    }

    public function test_setup_requires_a_restricted_claim_token(): void
    {
        $this->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement()])->assertUnauthorized();
        $coach = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($coach, ['coach']);
        $this->postJson('/api/player/claim-setup', ['born_date' => '2000-01-01', ...$this->agreement()])->assertForbidden();
    }
}
