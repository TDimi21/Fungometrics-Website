<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\Statistics\CageStatisticsService;
use App\Services\Statistics\ExitVelocityStatisticsService;
use App\Services\Statistics\LongTossStatisticsService;
use App\Services\Statistics\WeightBallStatisticsService;
use Tests\TestCase;

class StatisticsAutoloadTest extends TestCase
{
    public function test_statistics_services_resolve_and_handle_no_training_results(): void
    {
        foreach ([WeightBallStatisticsService::class, ExitVelocityStatisticsService::class] as $class) {
            $service = app($class);
            foreach (['totals', 'averageVelocities', 'maxVelocities'] as $method) {
                $this->assertSame([], $service->$method(collect()));
            }
        }
        $this->assertSame([], app(CageStatisticsService::class)->launchAngleTotals(collect()));
        $this->assertSame([], app(LongTossStatisticsService::class)->distanceTotals(collect()));
    }

    public function test_weighted_ball_statistics_preserve_team_and_player_totals(): void
    {
        $rows = collect([
            (object) ['user_id' => 10, 'weight' => '5', 'velocity' => 70],
            (object) ['user_id' => 10, 'weight' => '5', 'velocity' => 80],
            (object) ['user_id' => 20, 'weight' => '3', 'velocity' => 90],
        ]);
        $service = app(WeightBallStatisticsService::class);
        $totals = $service->totals($rows);
        $this->assertSame(3, $totals['team_totals']['throws']);
        $this->assertSame(2, $totals['team_totals'][5]);
        $this->assertSame(2, $totals['players'][10]['throws']);
        $this->assertEquals(75, $service->averageVelocities($rows)['team_totals'][5]);
        $this->assertEquals(80, $service->maxVelocities($rows)['team_totals'][5]);
    }
}
