<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BallFlight;

use App\Services\BallFlight\BallFlightEngine;
use App\Services\BallFlight\CalibrationEngine;
use App\Services\BallFlight\ResearchDatabase;
use App\Services\BallFlight\ValidationEngine;
use App\Services\Cage\CageDistanceService;
use Tests\TestCase;

class BallFlightEngineTest extends TestCase
{
    public function test_engine_preserves_the_current_rk4_physics_and_adds_intelligence_metadata(): void
    {
        $input = [
            'exit_velocity_mph' => 100,
            'launch_angle_deg' => 25,
            'spray_angle_deg' => 0,
            'measured_spin_rpm' => 2100,
        ];

        $legacy = app(CageDistanceService::class)->estimate($input);
        $result = app(BallFlightEngine::class)->analyze($input);

        $this->assertSame($legacy['estimated_carry_ft'], $result['carry_ft']);
        $this->assertSame($legacy['hang_time_seconds'], $result['hang_time_seconds']);
        $this->assertSame($legacy['maximum_height_ft'], $result['maximum_height_ft']);
        $this->assertSame('bfi_v1.0', $result['engine_version']);
        $this->assertSame('uncalibrated', $result['calibration']['status']);
        $this->assertGreaterThanOrEqual(70, $result['confidence']['percent']);
    }

    public function test_calibration_is_explicit_and_fitted_from_paired_residuals(): void
    {
        $calibration = app(CalibrationEngine::class);
        $profile = $calibration->fitCarryOffset([
            ['predicted_carry_ft' => 300.0, 'measured_carry_ft' => 310.0],
            ['predicted_carry_ft' => 350.0, 'measured_carry_ft' => 354.0],
        ], 'trackman-v1');

        $this->assertSame(7.0, $profile['carry_offset_ft']);
        $this->assertSame(307.0, $calibration->apply(300.0, $profile)['carry_ft']);
    }

    public function test_research_database_normalizes_trackman_and_statcast_csvs(): void
    {
        $trackManPath = $this->csv(
            "ExitSpeed,Angle,Direction,HitSpinRate,HangTime,MaxHeight,Distance\n95,25,-4,2100,4.8,92,382\n"
        );
        $statcastPath = $this->csv(
            "launch_speed,launch_angle,hit_distance_sc\n101,28,411\n"
        );

        try {
            $database = app(ResearchDatabase::class);
            $trackMan = $database->import('trackman', $trackManPath);
            $statcast = $database->import('statcast', $statcastPath);

            $this->assertSame(382.0, $trackMan[0]['measured_distance_ft']);
            $this->assertSame(2100.0, $trackMan[0]['measured_spin_rpm']);
            $this->assertSame(411.0, $statcast[0]['measured_distance_ft']);
        } finally {
            @unlink($trackManPath);
            @unlink($statcastPath);
        }
    }

    public function test_validation_reports_error_metrics_without_mutating_data(): void
    {
        $report = app(ValidationEngine::class)->validate([[
            'source' => 'trackman',
            'exit_velocity_mph' => 95.0,
            'launch_angle_deg' => 25.0,
            'spray_angle_deg' => 0.0,
            'spin_rate_rpm' => 2100.0,
            'measured_carry_ft' => 380.0,
        ]]);

        $this->assertSame(1, $report['count']);
        $this->assertIsFloat($report['mae_ft']);
        $this->assertIsFloat($report['rmse_ft']);
        $this->assertCount(1, $report['pairs']);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-bfi-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create test CSV.');
        }
        file_put_contents($path, $contents);

        return $path;
    }
}
