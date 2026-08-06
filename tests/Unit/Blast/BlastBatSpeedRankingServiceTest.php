<?php

declare(strict_types=1);

namespace Tests\Unit\Blast;

use App\Services\Blast\BlastBatSpeedRankingService;
use Tests\TestCase;

final class BlastBatSpeedRankingServiceTest extends TestCase
{
    public function test_it_positions_bat_speed_within_the_supplied_competition_level_range(): void
    {
        $service = app(BlastBatSpeedRankingService::class);

        $middle = $service->rank(65, 'HIGH', 16);
        $this->assertSame('high_school_varsity', $middle['benchmark_level']);
        $this->assertSame(60.0, $middle['range_min']);
        $this->assertSame(70.0, $middle['range_max']);
        $this->assertSame(50, $middle['percentile']);
        $this->assertSame('In Suggested Range', $middle['label']);
        $this->assertFalse($middle['evidence']['population_percentile']);

        $below = $service->rank(59, 'HIGH', 16);
        $this->assertSame(0, $below['percentile']);
        $this->assertSame(60.0, $below['goal']);
        $this->assertSame(1.0, $below['gap']);

        $above = $service->rank(72, 'HIGH', 16);
        $this->assertSame(100, $above['percentile']);
        $this->assertSame('Above Suggested Range', $above['label']);
    }

    public function test_it_uses_age_when_the_profile_level_is_not_governed(): void
    {
        $service = app(BlastBatSpeedRankingService::class);

        $this->assertSame('youth', $service->resolveLevel('TRAVEL', 11));
        $this->assertSame('middle_school', $service->resolveLevel('PLAYER', 13));
        $this->assertSame('high_school_varsity', $service->resolveLevel(null, 17));
        $this->assertSame('college', $service->resolveLevel(null, 20));
    }
}
