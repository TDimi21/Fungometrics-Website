<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Events\{UserCreated, UserChanged};
use App\Models\{CoachTeam, Profile, Team, User};
use App\Services\Communications\InvitationService;
use Illuminate\Support\Facades\{Event, Hash, Notification, Queue};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CoachStatusAuthorizationTest extends TestCase
{
    public static function statuses(): array
    {
        return [[false, true], [false, false], [true, true], [true, false]];
    }

    /** @dataProvider statuses */
    public function test_both_statuses_can_log_in_and_create_on_own_team(bool $status, bool $head): void
    {
        Event::fake([UserCreated::class, UserChanged::class]);
        Notification::fake(); Queue::fake();
        config(['communications.enabled' => false, 'communications.invitations_enabled' => false]);
        $coach = User::factory()->create(['type' => 'coach', 'status' => $status, 'password' => Hash::make('Password123')]);
        Profile::factory()->create(['user_id' => $coach->id]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => $head]);
        $login = $this->postJson('/api/login', ['email' => $coach->email, 'password' => 'Password123'])->assertOk();
        $token = $login->json('data.token');
        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/coach/teams')->assertOk();
        $payload = ['name' => ['first' => 'New', 'last' => 'Player'], 'team' => $team->id, 'send_invitation' => false];
        $this->withToken($token)->postJson('/api/coach/add/players', $payload)->assertOk()->assertJsonPath('invitation.status', 'not_requested');
        $this->assertSame($status, $coach->fresh()->status);
        $this->withToken($token)->postJson('/api/coach/add/players', array_replace($payload, ['team' => Team::factory()->create()->id]))->assertForbidden();
        Notification::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_deleted_coach_cannot_reuse_old_token_or_stale_actor(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'status' => false]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $token = $coach->createToken('web', ['coach'])->plainTextToken;
        $staleActor = $coach->fresh();
        $coach->delete();
        $this->withToken($token)->postJson('/api/coach/add/players', ['team' => $team->id, 'name' => ['first' => 'No', 'last' => 'Access']])->assertUnauthorized();
        $this->expectException(HttpException::class);
        app(InvitationService::class)->authorize($staleActor, $team->id);
    }

    public function test_removed_membership_cannot_authorize_a_status_zero_coach(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'status' => false]);
        $team = Team::factory()->create();
        $link = CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        $link->delete();
        $token = $coach->createToken('web', ['coach'])->plainTextToken;
        $this->withToken($token)->postJson('/api/coach/add/players', ['team' => $team->id, 'name' => ['first' => 'No', 'last' => 'Access']])->assertForbidden();
    }

    public function test_status_zero_assistant_still_cannot_invite_coaches(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'status' => false]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => false]);
        $this->expectException(HttpException::class);
        app(InvitationService::class)->authorize($coach, $team->id, true);
    }
}
