<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\BallFlightPredictionEvaluation;
use App\Models\BallFlightReferenceObservation;
use App\Services\BallFlight\BallFlightEvaluationService;
use Tests\TestCase;

class BallFlightResearchCommandsTest extends TestCase
{
    public function test_dry_run_writes_nothing_and_repeated_import_deduplicates(): void
    {
        $path = $this->csv();
        try {
            $this->artisan("ball-flight:import '{$path}' --source=trackman --dry-run")->assertSuccessful();
            $this->assertDatabaseCount('ball_flight_reference_observations', 0);
            $this->artisan("ball-flight:import '{$path}' --source=trackman")->assertSuccessful();
            $this->artisan("ball-flight:import '{$path}' --source=trackman")->assertSuccessful();
            $this->assertDatabaseCount('ball_flight_reference_observations', 1);
        } finally {
            @unlink($path);
        }
    }

    public function test_estimated_and_measured_spin_evaluations_persist_exact_errors(): void
    {
        $observation = BallFlightReferenceObservation::query()->create([
            'source_type' => 'trackman', 'source_name' => 'TrackMan', 'source_file' => 'fixture.csv',
            'source_session_identifier' => 'session', 'exit_velocity_mph' => 95,
            'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_distance_ft' => 380,
            'measured_spin_rpm' => 2100, 'eligible_for_primary_calibration' => true,
            'eligible_for_external_validation' => false, 'partition' => 'validation',
            'import_hash' => hash('sha256', 'evaluation-fixture'),
        ]);
        $service = app(BallFlightEvaluationService::class);
        $estimated = $service->evaluate($observation, 'estimated', 'standardized', false, true);
        $measured = $service->evaluate($observation, 'measured', 'standardized', false, true);

        $this->assertSame(2, BallFlightPredictionEvaluation::query()->count());
        $this->assertEqualsWithDelta(
            (float) $estimated->predicted_distance_ft - 380,
            (float) $estimated->distance_error_ft,
            0.01
        );
        $this->assertSame('measured', $measured->spin_source);
        $this->assertEqualsWithDelta(abs((float) $measured->distance_error_ft), (float) $measured->absolute_distance_error_ft, 0.01);
    }

    public function test_accuracy_report_exports_json_and_csv(): void
    {
        $observation = BallFlightReferenceObservation::query()->create([
            'source_type' => 'statcast', 'source_name' => 'MLB Statcast', 'source_file' => 's.csv',
            'exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0,
            'measured_distance_ft' => 380, 'eligible_for_external_validation' => true,
            'eligible_for_primary_calibration' => false, 'partition' => 'external_validation',
            'import_hash' => hash('sha256', 'report-fixture'),
        ]);
        app(BallFlightEvaluationService::class)->evaluate($observation, 'estimated', 'standardized', false, true);
        $json = tempnam(sys_get_temp_dir(), 'bfi-json-');
        $csv = tempnam(sys_get_temp_dir(), 'bfi-csv-');
        try {
            $this->artisan("ball-flight:accuracy-report --cohort=statcast --spin=estimated --format=json --output={$json}")->assertSuccessful();
            $this->artisan("ball-flight:accuracy-report --cohort=statcast --spin=estimated --format=csv --output={$csv}")->assertSuccessful();
            $this->assertJson(file_get_contents($json));
            $this->assertStringContainsString('cohort,section,group,metric,value', file_get_contents($csv));
        } finally {
            @unlink($json);
            @unlink($csv);
        }
    }

    private function csv(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmtrx-command-');
        if ($path === false) throw new \RuntimeException('Unable to create fixture.');
        file_put_contents($path, "PitchNo,ExitSpeed,Angle,Direction,Distance,LastTrackedDistance,HitSpinRate,GameID,PitchUID,HitLaunchConfidence,HitLandingConfidence\n1,95,25,0,380,350,2100,s1,e1,High,High\n");
        return $path;
    }
}
