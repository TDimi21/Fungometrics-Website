<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\AccountDeletionRequest;
use App\Models\CoachTeam;
use App\Models\SecurityAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_authorization_and_destructive_confirmation_delete_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        $token = $user->createToken('test', [$user->type])->plainTextToken;

        $authorized = $this->withToken($token)->postJson('/api/me/account-deletion/authorize', [
            'password' => 'CorrectPassword1!',
        ])->assertOk()->assertJsonPath('data.required_phrase', 'DELETE');
        $this->withToken($token)->getJson('/api/me/account-deletion/status')
            ->assertOk()->assertJsonPath('data.status', 'awaiting_confirmation');

        $confirmation = $authorized->json('data.confirmation_token');
        $this->withToken($token)->deleteJson('/api/me/account', [
            'confirmation_token' => $confirmation,
            'confirmation' => 'DELETE',
        ])->assertOk()->assertJsonPath('data.status', 'deleted');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('security_audits', ['action' => 'account_deletion.completed']);
    }

    public function test_real_deletion_scrubs_dob_and_communications_and_revokes_invitations(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $user = User::factory()->create(['type' => 'player', 'password' => Hash::make('CorrectPassword1!')]);
        \App\Models\Player::factory()->create(['user_id' => $user->id, 'born_date' => '2000-01-01']);
        $team = \App\Models\Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach']);
        $oldEmail = $user->email;
        \Illuminate\Support\Facades\DB::table('password_resets')->insert(['email' => $oldEmail, 'token' => Hash::make('old-reset'), 'created_at' => now()]);
        foreach ([null, now()] as $accepted) {
            \App\Models\TeamInvitation::create(['team_id' => $team->id, 'invited_by' => $coach->id,
                'user_id' => $user->id, 'email' => $oldEmail, 'role' => 'player',
                'token_hash' => hash('sha256', uniqid()), 'claim_code_hash' => hash('sha256', uniqid()),
                'accepted_at' => $accepted, 'expires_at' => now()->addDay()]);
        }
        $event = \App\Models\CommunicationEvent::create(['dedupe_key' => hash('sha256', 'deletion-event'),
            'event_type' => 'WeeklyProgressReady', 'resource_type' => 'user', 'resource_id' => $user->id,
            'occurrence' => 'test', 'recipient_id' => $user->id, 'payload' => ['email' => $oldEmail]]);
        $delivery = \App\Models\CommunicationDelivery::create(['event_id' => $event->id,
            'dedupe_key' => hash('sha256', 'deletion-delivery'), 'recipient_id' => $user->id, 'player_id' => $user->id,
            'event_type' => 'WeeklyProgressReady', 'resource_type' => 'user', 'resource_id' => $user->id,
            'payload' => ['heading' => 'Private player report'], 'status' => 'pending']);
        \App\Models\CommunicationPreference::create(['user_id' => $user->id]);
        $authorized = $this->actingAs($user)->postJson('/api/me/account-deletion/authorize', ['password' => 'CorrectPassword1!'])->assertOk();
        $this->actingAs($user)->deleteJson('/api/me/account', [
            'confirmation_token' => $authorized->json('data.confirmation_token'), 'confirmation' => 'DELETE',
        ])->assertOk();
        $this->assertDatabaseHas('players', ['user_id' => $user->id, 'born_date' => null]);
        $this->assertDatabaseMissing('team_invitations', ['email' => $oldEmail]);
        $this->assertDatabaseMissing('password_resets', ['email' => $oldEmail]);
        foreach (\App\Models\TeamInvitation::where('user_id', $user->id)->get() as $invite) {
            $this->assertNotNull($invite->revoked_at);
            $this->assertNull($invite->token_hash);
            $this->assertNull($invite->claim_code_hash);
        }
        $this->assertSame([], $event->fresh()->payload);
        $this->assertNotNull($event->fresh()->processed_at);
        $this->assertSame([], $delivery->fresh()->payload);
        $this->assertSame('suppressed', $delivery->fresh()->status);
        $this->assertDatabaseMissing('communication_preferences', ['user_id' => $user->id]);
        config(['communications.enabled' => true, 'communications.send_enabled' => true]);
        (new \App\Jobs\SendCommunicationDelivery($delivery->id))->handle(app(\App\Services\Communications\CommunicationContent::class));
        \Illuminate\Support\Facades\Notification::assertNothingSent();
        $this->assertSame(1, \App\Models\Player::where('user_id', $user->id)->count());
        // Reusing the deleted person's address for a new authorized profile must
        // not collide with a historical accepted invitation.
        \Illuminate\Support\Facades\Queue::fake();
        config(['communications.invitations_enabled' => true, 'queue.default' => 'database']);
        CoachTeam::create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        $replacement = User::factory()->create(['type' => 'player', 'email' => $oldEmail, 'password' => null]);
        \App\Models\Player::factory()->create(['user_id' => $replacement->id, 'born_date' => '2000-01-01']);
        \App\Models\PlayerTeam::create(['user_id' => $replacement->id, 'team_id' => $team->id, 'actual' => true]);
        $fresh = app(\App\Services\Communications\InvitationService::class)->send($coach, $team->id,
            ['role' => 'player', 'user_id' => $replacement->id, 'email' => $oldEmail]);
        $this->assertSame($replacement->id, $fresh->user_id);
        $this->assertNull($fresh->accepted_at);

    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        $this->actingAs($user)->postJson('/api/me/account-deletion/authorize', [
            'password' => 'WrongPassword1!',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame(1, SecurityAudit::where('action', 'account_deletion.authorization_failed')->count());
    }

    public function test_confirmation_is_authorized_to_owner_and_cannot_be_replayed(): void
    {
        $owner = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        $other = User::factory()->create();
        $authorized = $this->actingAs($owner)->postJson('/api/me/account-deletion/authorize', [
            'password' => 'CorrectPassword1!',
        ])->assertOk();
        $token = $authorized->json('data.confirmation_token');

        $this->actingAs($other)->deleteJson('/api/me/account', [
            'confirmation_token' => $token, 'confirmation' => 'DELETE',
        ])->assertUnprocessable();

        $this->actingAs($owner)->deleteJson('/api/me/account', [
            'confirmation_token' => $token, 'confirmation' => 'DELETE',
        ])->assertOk();

        $this->actingAs($owner)->deleteJson('/api/me/account', [
            'confirmation_token' => $token, 'confirmation' => 'DELETE',
        ])->assertUnprocessable();
        $this->assertNotNull(AccountDeletionRequest::where('confirmation_hash', hash('sha256', $token))->first()->used_at);
    }

    public function test_memberships_are_removed_and_identity_is_anonymized(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'phone' => '15551234567',
            'password' => Hash::make('CorrectPassword1!'),
        ]);
        $team = \App\Models\Team::factory()->create();
        CoachTeam::create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        $authorized = $this->actingAs($user)->postJson('/api/me/account-deletion/authorize', [
            'password' => 'CorrectPassword1!',
        ]);
        $this->actingAs($user)->deleteJson('/api/me/account', [
            'confirmation_token' => $authorized->json('data.confirmation_token'),
            'confirmation' => 'DELETE',
        ])->assertOk();

        $deleted = User::withTrashed()->findOrFail($user->id);
        $this->assertStringEndsWith('@deleted.fmtrx.invalid', $deleted->email);
        $this->assertStringStartsWith('deleted-', $deleted->phone);
        $this->assertDatabaseMissing('coach_teams', ['coach_id' => $user->id]);
    }

    public function test_routes_are_authenticated_and_rate_limited(): void
    {
        $this->postJson('/api/me/account-deletion/authorize', ['password' => 'anything'])
            ->assertUnauthorized();
        $this->deleteJson('/api/me/account', [])
            ->assertUnauthorized();

        $user = User::factory()->create(['password' => Hash::make('CorrectPassword1!')]);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)->postJson('/api/me/account-deletion/authorize', [
                'password' => 'WrongPassword1!',
            ])->assertUnprocessable();
        }
        $this->actingAs($user)->postJson('/api/me/account-deletion/authorize', [
            'password' => 'WrongPassword1!',
        ])->assertStatus(429);
    }
}
