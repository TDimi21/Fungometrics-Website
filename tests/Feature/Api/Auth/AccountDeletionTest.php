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
