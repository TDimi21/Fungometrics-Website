<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Events\UserCreated;
use App\Listeners\SendSmsInvitation;
use App\Models\AccountClaim;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Security\AccountClaimService;
use App\Services\Security\PlayerProfileClaimService;
use App\Services\SendSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AccountClaimSecurityTest extends TestCase
{
    public function test_account_claim_uses_a_hashed_expiring_single_use_token_and_revokes_sessions(): void
    {
        $user = User::factory()->create([
            'type' => 'player',
            'email' => null,
            'password' => null,
        ]);
        $user->createToken('old-session');
        $claims = app(AccountClaimService::class);

        $raw = $claims->issue($user);
        $stored = AccountClaim::where('user_id', $user->id)->firstOrFail();

        $this->assertNotSame($raw, $stored->token_hash);
        $this->assertSame(hash('sha256', $raw), $stored->token_hash);
        $this->assertTrue($stored->expires_at->isFuture());
        $this->getJson('/api/complete/'.str_replace('-', '', $user->id))->assertUnprocessable();
        $this->getJson('/api/complete/'.$raw)->assertOk()
            ->assertJsonMissingPath('data.user.id')
            ->assertJsonPath('data.user.type', 'player');

        $claims->consume($stored, Request::create('/complete', 'POST'));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull($stored->fresh()->used_at);
        $this->assertDatabaseHas('security_audits', [
            'action' => 'account_claim.completed',
            'user_id' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        $claims->resolve($raw);
    }

    public function test_account_claim_is_bound_to_the_intended_account_type(): void
    {
        $user = User::factory()->create(['type' => 'player', 'email' => null, 'password' => null]);
        $raw = app(AccountClaimService::class)->issue($user);

        $this->postJson("/api/complete/{$raw}/coach", [])->assertUnprocessable();
        $this->assertNull(AccountClaim::where('user_id', $user->id)->firstOrFail()->used_at);
    }

    public function test_unclaimed_roster_player_can_start_claim_with_phone_and_team_code(): void
    {
        $team = Team::factory()->create(['join_code' => 'ABC123']);
        $player = User::factory()->create([
            'type' => 'player',
            'phone' => '(555) 867-5309',
            'email' => null,
            'password' => null,
            'status' => true,
        ]);
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);

        $response = $this->postJson('/api/player/join', [
            'phone' => '5558675309',
            'team_code' => 'ABC123',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']])
            ->assertJsonMissingPath('data.user')
            ->assertJsonMissingPath('data.team')
            ->assertJsonMissingPath('data.challenge_id');

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($token);
        $this->assertSame(['profile-claim'], $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertDatabaseCount('team_join_challenges', 0);
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_profile_claim_never_exposes_unexpected_database_errors(): void
    {
        $this->mock(PlayerProfileClaimService::class, function ($mock): void {
            $mock->shouldReceive('claim')
                ->once()
                ->andThrow(new RuntimeException('SQLSTATE sensitive details'));
        });
        Team::factory()->create(['join_code' => 'SAFE12']);

        $response = $this->postJson('/api/player/join', [
            'phone' => '5556666600',
            'team_code' => 'SAFE12',
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Profile claiming is temporarily unavailable. Please try again.');

        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
    }

    public function test_phone_must_match_an_unclaimed_player_already_on_the_roster(): void
    {
        $team = Team::factory()->create(['join_code' => 'TST123']);
        User::factory()->create([
            'type' => 'player',
            'phone' => '5550001234',
            'email' => null,
            'password' => null,
            'status' => true,
        ]);

        $this->postJson('/api/player/join', [
            'phone' => '5550001234',
            'team_code' => 'TST123',
        ])->assertUnprocessable()
            ->assertJsonMissingPath('data.token');
    }

    public function test_completed_player_profile_cannot_be_reclaimed(): void
    {
        $team = Team::factory()->create(['join_code' => 'TST124']);
        $player = User::factory()->create([
            'type' => 'player',
            'phone' => '5550001234',
            'email' => 'claimed@example.com',
            'password' => Hash::make('password123'),
            'status' => true,
        ]);
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);

        $this->postJson('/api/player/join', [
            'phone' => '5550001234',
            'team_code' => 'TST124',
        ])->assertUnprocessable()
            ->assertJsonMissingPath('data.token');
    }

    public function test_setting_credentials_replaces_restricted_claim_token_with_player_token(): void
    {
        $team = Team::factory()->create(['join_code' => 'NEW123']);
        $player = User::factory()->create([
            'type' => 'player',
            'phone' => '5558675309',
            'email' => null,
            'password' => null,
            'status' => true,
        ]);
        PlayerTeam::create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);

        $claim = $this->postJson('/api/player/join', [
            'phone' => '5558675309',
            'team_code' => 'NEW123',
        ])->assertOk();
        $claimToken = $claim->json('data.token');

        $credentials = $this->withToken($claimToken)->postJson('/api/player/set-credentials', [
            'email' => 'newplayer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'newplayer@example.com')
            ->assertJsonStructure(['data' => ['token', 'user', 'team']]);

        $this->assertNull(PersonalAccessToken::findToken($claimToken));
        $playerToken = PersonalAccessToken::findToken($credentials->json('data.token'));
        $this->assertNotNull($playerToken);
        $this->assertSame(['player'], $playerToken->abilities);
        $this->assertTrue(Hash::check('password123', $player->fresh()->password));
        $this->assertDatabaseHas('security_audits', [
            'action' => 'player_profile.credentials_set',
            'user_id' => $player->id,
            'team_id' => $team->id,
        ]);

        $this->postJson('/api/player/join/verify', [])->assertStatus(405);
    }

    public function test_new_players_do_not_receive_sms_claim_tokens(): void
    {
        $player = User::factory()->create(['type' => 'player']);
        $claims = $this->mock(AccountClaimService::class, function ($mock): void {
            $mock->shouldNotReceive('issue');
        });
        $sms = $this->mock(SendSmsService::class, function ($mock): void {
            $mock->shouldNotReceive('sendSms');
        });

        (new SendSmsInvitation($claims, $sms))->handle(new UserCreated($player));

        $this->assertDatabaseMissing('account_claims', ['user_id' => $player->id]);
        $this->assertDatabaseMissing('sms_logs', ['user_id' => $player->id]);
    }

    public function test_authenticated_player_can_join_without_receiving_a_new_token(): void
    {
        $team = Team::factory()->create(['join_code' => 'ZXCV12']);
        $player = User::factory()->create(['type' => 'player']);
        Sanctum::actingAs($player, ['player']);

        $this->postJson('/api/player/teams/join', ['team_code' => 'ZXCV12'])->assertOk();
        $this->assertDatabaseHas('player_teams', ['user_id' => $player->id, 'team_id' => $team->id]);
        $this->assertSame(0, $player->tokens()->count());
    }
}
