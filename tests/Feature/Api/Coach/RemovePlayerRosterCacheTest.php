<?php
namespace Tests\Feature\Api\Coach;

use App\Models\{CoachTeam, PlayerTeam, Team, User, PlayerFitness};
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemovePlayerRosterCacheTest extends TestCase
{
    public function test_removal_invalidates_warm_roster_and_preserves_player_and_other_teams(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $player = User::factory()->create(['type' => 'player']);
        $team = Team::factory()->create();
        $other = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        $link = PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);
        $otherLink = PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $other->id, 'actual' => true]);
        $history = PlayerFitness::factory()->create(['user_id' => $player->id]);
        $before = $history->fresh()->getAttributes();
        Cache::put('roster_team_'.$team->id, [['id' => $player->id]], 60);
        Cache::put('roster_team_'.$other->id, [['id' => $player->id]], 60);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson('/api/coach/teams/'.$team->id)->assertOk()->assertJsonPath('data.0.id', $player->id);
        $this->postJson('/api/coach/remove/players', ['team' => $team->id, 'player' => $player->id])->assertOk();
        $this->assertFalse(Cache::has('roster_team_'.$team->id));
        $this->getJson('/api/coach/teams/'.$team->id)->assertOk()->assertJsonCount(0, 'data');
        $this->assertSoftDeleted('player_teams', ['id' => $link->id]);
        $this->assertNotNull($player->fresh());
        $this->assertNotNull($otherLink->fresh());
        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertTrue(Cache::has('roster_team_'.$other->id));
        Sanctum::actingAs($player, ['player']);
        $this->getJson('/api/player/me/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_inactive_memberships_do_not_appear_on_coach_roster(): void
    {
        $team = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        PlayerTeam::factory()->create(['user_id' => User::factory()->create(['type' => 'player'])->id, 'team_id' => $team->id, 'actual' => false]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson('/api/coach/teams/'.$team->id)->assertOk()->assertJsonCount(0, 'data');
    }
}
