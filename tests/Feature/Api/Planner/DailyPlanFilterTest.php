<?php

namespace Tests\Feature\Api\Planner;

use App\Models\DailyPlan;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyPlanFilterTest extends TestCase
{
    public function test_filters_team_and_day_without_exposing_other_coaches_plans(): void
    {
        config(['access.temporary_full_access.enabled' => true, 'access.temporary_full_access.ends_at' => null]);
        $coach = User::factory()->create(['type' => 'coach']);
        $team = Team::factory()->create();
        $second = Team::factory()->create();
        $other = Team::factory()->create();
        $this->grantTeamAccess($coach, $team);
        $this->grantTeamAccess($coach, $second);
        foreach ([['today', $team, '2026-10-09'], ['tomorrow', $team, '2026-10-10'], ['second', $second, '2026-10-09'], ['private', $other, '2026-10-09']] as [$id, $owner, $date]) {
            DailyPlan::create(['id' => $id, 'team_id' => $owner->id, 'name' => $id, 'date' => $date, 'status' => 'draft', 'buckets' => []]);
        }
        Sanctum::actingAs($coach, ['coach']);
        $this->getJson("/api/coach/daily-plans?team_id={$team->id}&date=2026-10-09")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'today');
        $this->getJson('/api/coach/daily-plans')->assertOk()->assertJsonCount(3, 'data');
        $response = $this->getJson("/api/coach/daily-plans?team_id={$other->id}&date=2026-10-09");
        // The membership middleware may reject the team before the controller filters it.
        if ($response->status() === 200) $response->assertJsonCount(0, 'data');
        else $response->assertForbidden();
        $this->getJson('/api/coach/daily-plans?date=2026-02-30')->assertUnprocessable();
    }
}
