<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Team;
use App\Models\User;
use App\Services\Intelligence\ResearchPercentileEngine;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamBenchmarkOverrideControllerTest extends TestCase
{
    public function test_coach_can_save_use_and_reset_a_team_benchmark_override(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $payload = [
            'metric_key' => 'max_fastball_velocity',
            'age_group' => '15U_16U',
            'anchors' => ['p5' => 50, 'p25' => 60, 'p50' => 70, 'p75' => 80, 'p95' => 90],
        ];
        $this->json('PUT', "api/coach/teams/{$team->id}/benchmark-overrides", $payload)
            ->assertOk()->assertJsonPath('data.saved', true);

        $result = app(ResearchPercentileEngine::class)->percentileForMetric(
            'max_fastball_velocity', 85, null, ['age_group' => '15U_16U', 'team_id' => $team->id]
        );
        $this->assertTrue($result['evidence']['team_benchmark_override']);
        $this->assertSame(85, $result['percentile_estimate']);

        $this->json('DELETE', "api/coach/teams/{$team->id}/benchmark-overrides", [
            'metric_key' => 'max_fastball_velocity', 'age_group' => '15U_16U',
        ])->assertOk()->assertJsonPath('data.reset', true);
        $this->assertSame(0, DB::table('team_benchmark_overrides')->where('team_id', $team->id)->count());
    }

    public function test_coach_cannot_edit_another_teams_benchmarks(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);

        $this->getJson("api/coach/teams/{$team->id}/benchmark-overrides")->assertNotFound();
    }

    public function test_dash_percentiles_use_an_unambiguous_player_level_when_age_is_missing(): void
    {
        $engine = app(ResearchPercentileEngine::class);

        $forty = $engine->percentileForMetric(
            'forty_yard_dash', 4.5, null, ['age_group' => 'UNKNOWN', 'level' => 'D1']
        );
        $sixty = $engine->percentileForMetric(
            'sixty_yard_dash', 7.2, null, ['age_group' => 'UNKNOWN', 'level' => 'D1']
        );

        $this->assertSame('COLLEGE_19_PLUS', $forty['age_group']);
        $this->assertSame(90, $forty['percentile_estimate']);
        $this->assertSame('COLLEGE_19_PLUS', $sixty['age_group']);
        $this->assertSame(43, $sixty['percentile_estimate']);
    }
}
