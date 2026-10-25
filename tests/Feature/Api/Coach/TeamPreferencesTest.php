<?php

declare(strict_types=1);
namespace Tests\Feature\Api\Coach;

use App\Models\{User, Team, CoachTeam};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamPreferencesTest extends TestCase
{
    public function test_primary_default_and_active_are_separate(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $primary = Team::factory()->create(); $other = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $other->id, 'is_main' => false]);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $primary->id, 'is_main' => true]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson('/api/coach/team-preferences')->assertOk()->assertJsonPath('data.initial_team_id', $primary->id);
        $this->patchJson('/api/coach/team-preferences', ['default_team_id' => $other->id])->assertOk();
        $this->patchJson('/api/coach/team-preferences', ['active_team_id' => $primary->id])->assertOk()
            ->assertJsonPath('data.default_team_id', $other->id)->assertJsonPath('data.active_team_id', $primary->id)
            ->assertJsonPath('data.initial_team_id', $other->id);
        $this->assertSame($other->id, $coach->fresh()->default_team_id);
    }

    public function test_foreign_deleted_membership_and_player_are_denied(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $team = Team::factory()->create();
        $link = CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $this->patchJson('/api/coach/team-preferences', ['default_team_id' => Team::factory()->create()->id])->assertForbidden();
        $this->patchJson('/api/coach/team-preferences', ['default_team_id' => $team->id, 'active_team_id' => $team->id])->assertOk();
        $link->delete();
        $this->getJson('/api/coach/team-preferences')->assertOk()->assertJsonPath('data.default_team_id', null)->assertJsonPath('data.active_team_id', null)->assertJsonPath('data.initial_team_id', null);
        $this->patchJson('/api/coach/team-preferences', ['active_team_id' => $team->id])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type' => 'player']), ['player']);
        $this->getJson('/api/coach/team-preferences')->assertForbidden();
    }

    public function test_only_team_and_removed_team_fallback(): void
    {
        $coach = User::factory()->create(['type' => 'coach']); $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson('/api/coach/team-preferences')->assertOk()->assertJsonPath('data.initial_team_id', $team->id);
        $team->delete();
        $this->getJson('/api/coach/team-preferences')->assertOk()->assertJsonPath('data.initial_team_id', null);
    }
}
