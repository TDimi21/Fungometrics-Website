<?php

namespace Tests\Feature\Api\Planner;

use App\Models\{DailyPlan, DailyPlanAssignment, DailyPlanProgress, Team, User};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyPlanDeletionTest extends TestCase
{
    public function test_authorized_deletion_removes_assignments_and_lists_but_preserves_history(): void
    {
        config(['access.temporary_full_access.enabled' => true, 'access.temporary_full_access.ends_at' => null]);
        $coach = User::factory()->create(['type' => 'coach']);
        $player = User::factory()->create(['type' => 'player']);
        $team = Team::factory()->create();
        $this->grantTeamAccess($coach, $team);
        $this->grantTeamAccess($player, $team);
        $plan = DailyPlan::create(['id' => 'delete-test', 'team_id' => $team->id, 'name' => 'Workout', 'date' => now()->toDateString(), 'status' => 'published', 'buckets' => []]);
        DailyPlanAssignment::create(['plan_id' => $plan->id, 'user_id' => $player->id]);
        $progress = DailyPlanProgress::create(['plan_id' => $plan->id, 'user_id' => $player->id, 'items' => []]);
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson('/api/coach/daily-plans?team_id='.$team->id)->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson('/api/coach/daily-plans/'.$plan->id)->assertOk()->assertJsonPath('status', 'success');
        $this->assertSoftDeleted('daily_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('daily_plan_assignments', ['plan_id' => $plan->id]);
        $this->assertNotNull($progress->fresh());
        $this->getJson('/api/coach/daily-plans?team_id='.$team->id)->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson('/api/coach/daily-plans/'.$plan->id)->assertNotFound();
        Sanctum::actingAs($player, ['player']);
        $this->getJson('/api/player/daily-plans')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_unrelated_coach_and_player_cannot_delete(): void
    {
        config(['access.temporary_full_access.enabled' => true, 'access.temporary_full_access.ends_at' => null]);
        $team = Team::factory()->create();
        $plan = DailyPlan::create(['id' => 'private-plan', 'team_id' => $team->id, 'name' => 'Private', 'status' => 'draft', 'buckets' => []]);
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);
        $this->deleteJson('/api/coach/daily-plans/'.$plan->id)->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['type' => 'player']), ['player']);
        $this->deleteJson('/api/coach/daily-plans/'.$plan->id)->assertForbidden();
        $this->assertNotNull($plan->fresh());
        $this->assertNull($plan->fresh()->deleted_at);
    }
}
