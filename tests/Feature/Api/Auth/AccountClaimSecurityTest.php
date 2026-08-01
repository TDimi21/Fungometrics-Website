<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\AccountClaim;
use App\Models\Team;
use App\Models\TeamJoinChallenge;
use App\Models\User;
use App\Services\Security\AccountClaimService;
use App\Services\SendSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
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

    public function test_public_team_join_requires_phone_otp_before_membership_or_authentication(): void
    {
        $this->mock(SendSmsService::class, function ($mock): void {
            $mock->shouldReceive('sendSms')->once()->andReturnTrue();
        });
        $team = Team::factory()->create(['join_code' => 'ABC123']);
        $player = User::factory()->create(['type' => 'player', 'phone' => '5558675309']);

        $this->postJson('/api/player/join', [
            'phone' => '5558675309',
            'team_code' => 'ABC123',
        ])->assertStatus(202)
            ->assertJsonPath('status', 'verification_required')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseMissing('player_teams', ['user_id' => $player->id, 'team_id' => $team->id]);
        $this->assertSame(0, $player->tokens()->count());
    }

    public function test_allowlisted_fake_phone_uses_expiring_test_code_without_sending_sms(): void
    {
        config([
            'security.test_phone_verification.enabled' => true,
            'security.test_phone_verification.code' => '246810',
            'security.test_phone_verification.phones' => ['5550001234'],
            'security.test_phone_verification.ends_at' => now()->addHour()->toIso8601String(),
        ]);
        $this->mock(SendSmsService::class, function ($mock): void {
            $mock->shouldNotReceive('sendSms');
        });
        $team = Team::factory()->create(['join_code' => 'TST123']);
        $player = User::factory()->create(['type' => 'player', 'phone' => '5550001234']);

        $response = $this->postJson('/api/player/join', [
            'phone' => '5550001234',
            'team_code' => 'TST123',
        ])->assertStatus(202)
            ->assertJsonPath('data.verification_mode', 'test')
            ->assertJsonMissingPath('data.verification_code');

        $this->postJson('/api/player/join/verify', [
            'challenge_id' => $response->json('data.challenge_id'),
            'verification_code' => '246810',
        ])->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('player_teams', ['user_id' => $player->id, 'team_id' => $team->id]);
        $this->assertDatabaseHas('security_audits', [
            'action' => 'team_join.requested',
            'user_id' => $player->id,
        ]);
    }

    public function test_test_phone_mode_fails_closed_when_expired(): void
    {
        config([
            'security.test_phone_verification.enabled' => true,
            'security.test_phone_verification.code' => '246810',
            'security.test_phone_verification.phones' => ['5550001234'],
            'security.test_phone_verification.ends_at' => now()->subSecond()->toIso8601String(),
        ]);
        $this->mock(SendSmsService::class, function ($mock): void {
            $mock->shouldReceive('sendSms')->once()->andReturnTrue();
        });
        Team::factory()->create(['join_code' => 'TST124']);

        $response = $this->postJson('/api/player/join', [
            'phone' => '5550001234',
            'team_code' => 'TST124',
        ])->assertStatus(202)->assertJsonPath('data.verification_mode', 'sms');

        $this->postJson('/api/player/join/verify', [
            'challenge_id' => $response->json('data.challenge_id'),
            'verification_code' => '246810',
        ])->assertUnprocessable();
    }

    public function test_verified_player_join_is_single_use_and_coach_cannot_use_player_flow(): void
    {
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => 'player']);
        $challenge = TeamJoinChallenge::create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'phone_hash' => hash('sha256', '5558675309'),
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = ['challenge_id' => $challenge->id, 'verification_code' => '123456'];
        $this->postJson('/api/player/join/verify', $payload)->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['token']]);
        $this->assertDatabaseHas('player_teams', ['user_id' => $player->id, 'team_id' => $team->id]);
        $this->postJson('/api/player/join/verify', $payload)->assertUnprocessable();

        $coach = User::factory()->create(['type' => 'coach']);
        $coachChallenge = TeamJoinChallenge::create([
            'user_id' => $coach->id,
            'team_id' => $team->id,
            'phone_hash' => hash('sha256', '5558675310'),
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->postJson('/api/player/join/verify', [
            'challenge_id' => $coachChallenge->id,
            'verification_code' => '654321',
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('coach_teams', ['coach_id' => $coach->id, 'team_id' => $team->id]);
        $this->assertSame(0, $coach->tokens()->count());
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
