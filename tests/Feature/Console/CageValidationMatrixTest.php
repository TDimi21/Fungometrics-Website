<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CageValidationMatrixTest extends TestCase
{
    public function test_requires_ev_or_all(): void
    {
        $this->artisan('cage:validation-matrix')->assertFailed();
    }

    public function test_single_row_json_output(): void
    {
        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 60, '--la' => -5, '--spray' => 0, '--format' => 'json']);
        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);
        $this->assertCount(1, $payload['rows']);
        $this->assertSame('ground_ball', $payload['rows'][0]['batted_ball_type']);
    }

    // 17. CSV export works.
    public function test_csv_export_works(): void
    {
        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 60, '--la' => 15, '--spray' => 0, '--format' => 'csv']);
        $this->assertSame(0, $exitCode);

        $csv = Artisan::output();
        $this->assertStringContainsString('exit_velocity_mph,launch_angle_deg,spray_angle_deg', $csv);
        $this->assertStringContainsString('60,15,0', $csv);
    }

    public function test_csv_output_to_file(): void
    {
        $path = storage_path('app/cage-distance-validation-test.csv');
        @unlink($path);

        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 60, '--la' => 15, '--spray' => 0, '--format' => 'csv', '--output' => $path]);
        $this->assertSame(0, $exitCode);

        $this->assertFileExists($path);
        $this->assertStringContainsString('60,15,0', (string) file_get_contents($path));
        @unlink($path);
    }

    public function test_fail_on_invalid_passes_when_model_is_healthy(): void
    {
        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 95, '--spray' => 0, '--fail-on-invalid' => true]);
        $this->assertSame(0, $exitCode);
    }

    // 18. fail-on-invalid returns a nonzero exit code when rules fail. Mocks
    // CageDistanceService (not CageDistanceValidationService, so the real
    // rule-evaluation code under test still runs) to return the same large
    // fly-ball trajectory for every angle including negative ones — a
    // deliberately broken model that must trip negative_angle_invalid.
    public function test_fail_on_invalid_returns_nonzero_exit_code_when_rules_fail(): void
    {
        $this->mock(\App\Services\Cage\CageDistanceService::class, function ($mock): void {
            $mock->shouldReceive('estimate')->andReturn([
                'estimated_carry_ft' => 250.0,
                'carry_low_ft' => 245.0,
                'carry_high_ft' => 255.0,
                'confidence' => 'high',
                'hang_time_seconds' => 4.5,
                'maximum_height_ft' => 60.0,
                'landing_x_ft' => 0.0,
                'landing_y_ft' => 250.0,
                'batted_ball_type' => 'fly_ball',
                'assumptions' => [],
                'model_version' => 'cage_v2.0',
            ]);
        });

        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 60, '--spray' => 0, '--fail-on-invalid' => true]);
        $this->assertSame(1, $exitCode);
    }

    public function test_include_v1_joins_fixture_columns(): void
    {
        $exitCode = Artisan::call('cage:validation-matrix', ['--ev' => 60, '--la' => -5, '--spray' => 0, '--format' => 'json', '--include-v1' => true]);
        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('v1_distance_ft', $payload['rows'][0]);
        $this->assertNotNull($payload['rows'][0]['v1_distance_ft']);
        $this->assertArrayHasKey('difference_ft', $payload['rows'][0]);
    }

    public function test_all_fast_completes_quickly_and_reports_summary(): void
    {
        $start = microtime(true);
        $exitCode = Artisan::call('cage:validation-matrix', ['--all' => true, '--fast' => true, '--format' => 'json']);
        $elapsed = microtime(true) - $start;
        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);
        $this->assertSame(825, $payload['summary']['total_cases']);
        $this->assertLessThan(30.0, $elapsed, '--all --fast should be fast; if this is slow the grid/fast sample-count budget regressed.');
    }
}
