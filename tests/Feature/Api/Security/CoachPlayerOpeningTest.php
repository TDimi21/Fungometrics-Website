<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Security;

use App\Models\{CoachTeam, PlayerTeam, Profile, Team, User};
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoachPlayerOpeningTest extends TestCase
{
    public static function rosterCases(): array
    {
        return [[true, true], [true, false], [false, true], [false, false]];
    }

    /** @dataProvider rosterCases */
    public function test_head_and_assistant_can_open_claimed_and_unclaimed_players(bool $claimed, bool $head): void
    {
        config(['communications.invitations_enabled' => false]);
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'status' => true]);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => $head]);
        $player = User::factory()->create(['type' => 'player', 'status' => true, 'password' => $claimed ? Hash::make('Password123') : null]);
        Profile::factory()->create(['user_id' => $player->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        $before = $player->fresh()->getAttributes();
        $count = User::count();
        // Exercise a real bearer token, not just Sanctum's actingAs ability mock.
        $token = $coach->createToken('coach-web', ['coach'])->plainTextToken;
        $this->withToken($token)->getJson("/api/player/fitness/{$player->id}")->assertOk();
        $this->withToken($token)->getJson("/api/coach/statistics/{$player->id}")->assertOk();
        $this->assertSame($before, $player->fresh()->getAttributes());
        $this->assertSame($count, User::count());
    }

    public function test_unrelated_coach_cannot_open_player(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'status' => true]);
        $player = User::factory()->create(['type' => 'player', 'status' => true]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson("/api/player/fitness/{$player->id}")->assertForbidden();
        $this->getJson("/api/coach/statistics/{$player->id}")->assertNotFound();
    }

    public function test_player_or_claim_only_token_cannot_use_coach_statistics_route(): void
    {
        $player = User::factory()->create(['type' => 'player', 'status' => true]);
        foreach (['player', 'profile-claim'] as $ability) {
            Sanctum::actingAs($player, [$ability]);
            $this->getJson("/api/coach/statistics/{$player->id}")->assertForbidden();
        }
    }
}
