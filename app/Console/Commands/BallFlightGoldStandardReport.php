<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BallFlightReferenceObservation;
use App\Services\BallFlight\AccuracyMetrics;
use App\Services\BallFlight\BallFlightEngine;
use Illuminate\Console\Command;

class BallFlightGoldStandardReport extends Command
{
    protected $signature = 'ball-flight:gold-standard-report';
    protected $description = 'Report the locked TrackMan high-confidence raw-physics cohort';

    public function handle(BallFlightEngine $engine, AccuracyMetrics $metrics): int
    {
        $rows = BallFlightReferenceObservation::query()
            ->where('source_type', 'trackman')->where('partition', 'locked_test')
            ->whereRaw('LOWER(launch_confidence) = ?', ['high'])
            ->whereRaw('LOWER(landing_confidence) = ?', ['high'])
            ->whereNotNull('measured_distance_ft')->where('exit_velocity_mph', '>=', 60)
            ->whereBetween('spray_angle_deg', [-45, 45])
            ->whereNull('exclusion_reason')->get();
        $errors = [];
        foreach ($rows as $row) {
            $flight = $engine->analyze([
                'exit_velocity_mph' => (float) $row->exit_velocity_mph,
                'launch_angle_deg' => (float) $row->launch_angle_deg,
                'spray_angle_deg' => (float) $row->spray_angle_deg,
                'mode' => 'standardized',
            ], null, 25);
            if ($flight['carry_ft'] !== null) $errors[] = $flight['carry_ft'] - (float) $row->measured_distance_ft;
        }
        $report = [
            'count' => $rows->count(), 'files' => $rows->pluck('source_file')->unique()->values()->all(),
            'sessions' => $rows->pluck('source_session_identifier')->unique()->values()->all(),
            'ev_range' => [$rows->min('exit_velocity_mph'), $rows->max('exit_velocity_mph')],
            'launch_range' => [$rows->min('launch_angle_deg'), $rows->max('launch_angle_deg')],
            'spray_range' => [$rows->min('spray_angle_deg'), $rows->max('spray_angle_deg')],
            'spin_available' => $rows->whereNotNull('measured_spin_rpm')->count(),
            'distance_range' => [$rows->min('measured_distance_ft'), $rows->max('measured_distance_ft')],
            'raw_physics_accuracy' => $metrics->summarize($errors),
        ];
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
