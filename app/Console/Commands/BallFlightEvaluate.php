<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BallFlightReferenceObservation;
use App\Services\BallFlight\BallFlightEvaluationService;
use Illuminate\Console\Command;

class BallFlightEvaluate extends Command
{
    protected $signature = 'ball-flight:evaluate {--source=all} {--mode=standardized} {--spin=estimated} {--limit=} {--force} {--fast} {--output=}';
    protected $description = 'Evaluate raw BFI physics against imported reference observations';

    public function handle(BallFlightEvaluationService $service): int
    {
        $source = (string) $this->option('source');
        $spinOption = (string) $this->option('spin');
        $query = BallFlightReferenceObservation::query()->whereNotNull('exit_velocity_mph')
            ->whereNotNull('launch_angle_deg')->whereNotNull('measured_distance_ft');
        if ($source !== 'all') $query->where('source_type', $source);
        if ($source === 'trackman') $query->where('eligible_for_primary_calibration', true);
        if ($source === 'statcast') $query->where('eligible_for_external_validation', true);
        if ($this->option('limit')) $query->limit((int) $this->option('limit'));
        $count = 0;
        foreach ($query->cursor() as $observation) {
            $spins = $spinOption === 'both' ? ['estimated', 'measured'] : [$spinOption];
            foreach ($spins as $spin) {
                if ($spin === 'measured' && $observation->measured_spin_rpm === null) continue;
                $service->evaluate($observation, $spin, (string) $this->option('mode'), (bool) $this->option('force'), (bool) $this->option('fast'));
                $count++;
            }
        }
        $this->info("Stored/reused {$count} prediction evaluations. Calibration remained inactive.");
        if ($this->option('output')) {
            file_put_contents((string) $this->option('output'), json_encode([
                'evaluations' => $count, 'source' => $source, 'spin' => $spinOption,
                'mode' => $this->option('mode'), 'calibration_active' => false,
            ], JSON_PRETTY_PRINT));
        }
        return self::SUCCESS;
    }
}
