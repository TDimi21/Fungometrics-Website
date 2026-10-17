<?php
namespace Tests\Feature\Api\Player;

use App\Models\{User, Team, PlayerTeam, PlayerFitness};
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyTeamsTest extends TestCase
{
    public function test_player_lists_and_leaves_only_own_active_teams_preserving_history(): void
    {
        $player = User::factory()->create(['type' => 'player', 'status' => false]);
        $other = User::factory()->create(['type' => 'player']);
        $teams = Team::factory()->count(3)->create();
        $link = PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $teams[0]->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $teams[1]->id, 'actual' => true]);
        $otherLink = PlayerTeam::factory()->create(['user_id' => $other->id, 'team_id' => $teams[0]->id, 'actual' => true]);
        PlayerTeam::factory()->create(['user_id' => $other->id, 'team_id' => $teams[2]->id, 'actual' => true]);
        $history = PlayerFitness::factory()->create(['user_id' => $player->id]);
        $snapshot = $history->fresh()->getAttributes();
        Cache::put('roster_team_'.$teams[0]->id, ['cached'], 60);
        Sanctum::actingAs($player, ['player']);
        $this->getJson('/api/player/me/teams')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissing(['id' => $teams[2]->id]);
        $this->deleteJson('/api/player/me/teams/'.$teams[2]->id)->assertNotFound();
        $this->deleteJson('/api/player/me/teams/'.$teams[0]->id, ['user_id' => $other->id])->assertOk();
        $this->assertSoftDeleted('player_teams', ['id' => $link->id, 'actual' => false]);
        $this->assertNotNull($otherLink->fresh());
        $this->assertSame($snapshot, $history->fresh()->getAttributes());
        $this->assertNotNull($player->fresh());
        $this->assertFalse(Cache::has('roster_team_'.$teams[0]->id));
        $this->getJson('/api/player/me/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $teams[1]->id);
        $this->deleteJson('/api/player/me/teams/'.$teams[0]->id)->assertNotFound();
        $this->deleteJson('/api/player/me/teams/'.$teams[1]->id)->assertOk();
        $this->getJson('/api/player/me/teams')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_and_coach_cannot_leave_player_teams(): void
    {
        $team = Team::factory()->create();
        $this->getJson('/api/player/me/teams')->assertUnauthorized();
        $this->deleteJson('/api/player/me/teams/'.$team->id)->assertUnauthorized();
        $coach = User::factory()->create(['type' => 'coach']);
        \App\Models\CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['*']);
        $this->getJson('/api/player/me/teams')->assertForbidden();
        $this->deleteJson('/api/player/me/teams/'.$team->id)->assertForbidden();
    }
}
