<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Events\{UserCreated, UserChanged};
use App\Models\{CoachTeam, Team, User};
use App\Services\Security\ApiTokenCookie;
use Illuminate\Support\Facades\{Event, Log, Notification, Queue};
use Tests\TestCase;

class PlayerCreationAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([UserCreated::class, UserChanged::class]);
        Notification::fake(); Queue::fake(); Log::spy();
        Log::shouldReceive('channel')->andReturnSelf();
        config(['communications.enabled' => false, 'communications.send_enabled' => false, 'communications.invitations_enabled' => false]);
    }

    private function payload(string $team): array
    {
        return ['team' => $team, 'name' => ['first' => 'Private', 'last' => 'Player'], 'email' => 'private-player@example.com', 'send_invitation' => false];
    }

    private function coachCookie(bool $active = true, string $role = 'coach'): array
    {
        $coach = User::factory()->create(['type' => $role, 'status' => $active]);
        $team = Team::factory()->create();
        $membership = CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        $token = $coach->createToken('web', [$role])->plainTextToken;
        $this->withCredentials()->withCookie(ApiTokenCookie::NAME, $token)->withHeader('Authorization', 'Bearer');
        return [$team, $membership];
    }

    public function test_cookie_and_empty_bearer_reproduce_team_get_success_and_membership_id_post_denial(): void
    {
        [$team, $membership] = $this->coachCookie();
        $this->getJson('/api/coach/teams')->assertOk()->assertJsonPath('data.0.id', $team->id)->assertJsonPath('data.0.id_team', $team->id);
        $count = User::count();
        $this->postJson('/api/coach/add/players', $this->payload($membership->id))
            ->assertForbidden()->assertJsonPath('message', 'A coach account with access to the selected team is required.');
        $this->assertSame($count, User::count());
        Log::shouldHaveReceived('notice')->once()->with('player_creation.authorization_denied', [
            'endpoint' => 'coach/add/players', 'http_status' => 403, 'authenticated' => true, 'actor_role' => 'coach', 'reason' => 'team_membership_required',
        ]);
    }

    public function test_cookie_with_correct_team_creates_player_without_invitations_or_bearer(): void
    {
        [$team] = $this->coachCookie();
        $this->flushHeaders();
        $this->postJson('/api/coach/add/players', $this->payload($team->id))->assertOk()->assertJsonPath('invitation.status', 'not_requested');
        $this->assertDatabaseHas('users', ['email' => 'private-player@example.com']);
        Notification::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_empty_bearer_with_valid_cookie_and_team_also_works(): void
    {
        [$team] = $this->coachCookie();
        $this->postJson('/api/coach/add/players', $this->payload($team->id))->assertOk();
        Notification::assertNothingSent();
    }

    public function test_empty_bearer_without_cookie_is_unauthenticated(): void
    {
        $this->withHeader('Authorization', 'Bearer')->postJson('/api/coach/add/players', $this->payload(Team::factory()->create()->id))->assertUnauthorized();
        Log::shouldHaveReceived('notice')->once();
    }

    public function test_other_team_remains_forbidden(): void
    {
        $this->coachCookie();
        $this->postJson('/api/coach/add/players', $this->payload(Team::factory()->create()->id))->assertForbidden();
    }

    public function test_legacy_status_zero_member_can_create_without_activating_account(): void
    {
        [$team, $membership] = $this->coachCookie(false);
        $this->getJson('/api/coach/teams')->assertOk();
        $this->postJson('/api/coach/add/players', $this->payload($team->id))->assertOk();
        $this->assertFalse(User::findOrFail($membership->coach_id)->status);
        Notification::assertNothingSent();
    }

    public function test_invalid_team_input_is_validation_error_not_blank_authorization_error(): void
    {
        $this->coachCookie();
        $this->postJson('/api/coach/add/players', $this->payload('undefined'))->assertUnprocessable()->assertJsonPath('code', '001V');
    }

    public function test_player_cookie_cannot_create_a_player(): void
    {
        [$team] = $this->coachCookie(true, 'player');
        $this->postJson('/api/coach/add/players', $this->payload($team->id))->assertForbidden();
        Log::shouldHaveReceived('notice')->once();
    }
}
