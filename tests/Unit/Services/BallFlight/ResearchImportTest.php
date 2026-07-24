<?php

declare(strict_types=1);

namespace Tests\Unit\Services\BallFlight;

use App\Services\BallFlight\AccuracyMetrics;
use App\Services\BallFlight\BallFlightPartitionService;
use App\Services\BallFlight\ResearchDatabase;
use Tests\TestCase;

class ResearchImportTest extends TestCase
{
    public function test_source_detection_aliases_eligibility_and_distance_precedence(): void
    {
        $trackman = $this->csv(
            "PitchNo,Date,Batter,BatterId,ExitSpeed,Angle,Direction,Distance,LastTrackedDistance,HitSpinRate,HangTime,MaxHeight,ContactPositionZ,TaggedHitType,AutoHitType,PitchCall,GameID,PitchUID,Stadium,Level,HitLaunchConfidence,HitLandingConfidence\n".
            "1,2026-01-01,\"Player, One\",1,95,25,4,380,111,2100,4.8,90,3.1,FlyBall,FlyBall,InPlay,session-a,event-1,Facility,High School,High,High\n".
            "2,2026-01-01,\"Player, One\",1,85,-5,0,12,9,,,0.5,3.0,GroundBall,GroundBall,InPlay,session-a,event-2,Facility,High School,High,High\n".
            "3,2026-01-01,\"Player, One\",1,90,20,95,300,250,,,,3.0,LineDrive,LineDrive,InPlay,session-a,event-3,Facility,High School,High,High\n".
            "4,2026-01-01,\"Player, One\",1,70,10,0,100,90,,,,3.0,Bunt,Bunt,InPlay,session-a,event-4,Facility,High School,High,High\n".
            "5,2026-01-01,\"Player, One\",1,88,18,0,250,220,,,,3.0,LineDrive,LineDrive,InPlay,session-a,event-5,Facility,High School,Low,High\n"
        );
        $statcast = $this->csv("swing_id,exit_velocity_mph,distance_ft,launch_angle_deg,spray_angle_deg\n1,99,400,28,-10\n");
        try {
            $database = app(ResearchDatabase::class);
            $this->assertSame('trackman', $database->detect($trackman));
            $this->assertSame('statcast', $database->detect($statcast));
            $report = $database->inspect($trackman);
            $this->assertSame(380.0, $report['rows'][0]['measured_distance_ft']);
            $this->assertSame(111.0, $report['rows'][0]['last_tracked_distance_ft']);
            $this->assertTrue($report['rows'][0]['eligible_for_primary_calibration']);
            $this->assertTrue($report['rows'][1]['eligible_for_primary_calibration']);
            $this->assertStringContainsString('extreme_or_backward_spray', $report['rows'][2]['exclusion_reason']);
            $this->assertStringContainsString('bunt', $report['rows'][3]['exclusion_reason']);
            $this->assertStringContainsString('low_or_missing_launch_confidence', $report['rows'][4]['exclusion_reason']);

            $external = $database->inspect($statcast);
            $this->assertFalse($external['rows'][0]['eligible_for_primary_calibration']);
            $this->assertTrue($external['rows'][0]['eligible_for_external_validation']);
        } finally {
            @unlink($trackman);
            @unlink($statcast);
        }
    }

    public function test_row_hashes_and_session_partitions_are_deterministic(): void
    {
        $path = $this->csv("PitchNo,ExitSpeed,Angle,Direction,Distance,GameID,PitchUID,HitLaunchConfidence,HitLandingConfidence\n1,95,25,0,380,s1,e1,High,High\n");
        try {
            $database = app(ResearchDatabase::class);
            $first = $database->inspect($path, 'trackman')['rows'][0];
            $second = $database->inspect($path, 'trackman')['rows'][0];
            $this->assertSame($first['import_hash'], $second['import_hash']);
            $assignments = BallFlightPartitionService::assignSessions(['e', 'd', 'c', 'b', 'a']);
            $this->assertSame(['training' => 3, 'validation' => 1, 'locked_test' => 1], array_count_values($assignments));
            $this->assertSame($assignments, BallFlightPartitionService::assignSessions(['a', 'b', 'c', 'd', 'e']));
        } finally {
            @unlink($path);
        }
    }

    public function test_accuracy_metrics_include_required_error_statistics(): void
    {
        $metrics = app(AccuracyMetrics::class)->summarize([-20, -5, 0, 10, 30]);
        $this->assertSame(5, $metrics['count']);
        $this->assertArrayHasKey('mean_absolute_error_ft', $metrics);
        $this->assertArrayHasKey('median_absolute_error_ft', $metrics);
        $this->assertArrayHasKey('p90_absolute_error_ft', $metrics);
        $this->assertSame(30.0, $metrics['largest_overestimate_ft']);
        $this->assertSame(-20.0, $metrics['largest_underestimate_ft']);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-research-');
        if ($path === false) throw new \RuntimeException('Unable to create fixture.');
        file_put_contents($path, $contents);
        return $path;
    }
}
