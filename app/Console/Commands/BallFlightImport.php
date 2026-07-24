<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BallFlightReferenceObservation;
use App\Services\BallFlight\ResearchDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BallFlightImport extends Command
{
    protected $signature = 'ball-flight:import {file} {--source=auto} {--dry-run} {--facility-id=} {--player-level=} {--age-group=} {--partition=} {--replace-file} {--include-excluded}';
    protected $description = 'Inspect or import one TrackMan/Statcast research CSV';

    public function handle(ResearchDatabase $database): int
    {
        $file = realpath((string) $this->argument('file'));
        if ($file === false) return $this->fail('File does not exist.');
        $report = $database->inspect($file, (string) $this->option('source'), [
            'facility_id' => $this->option('facility-id'),
            'player_level' => $this->option('player-level'),
            'age_group' => $this->option('age-group'),
            'partition' => $this->option('partition'),
        ]);
        $this->display($report);
        if ($this->option('dry-run')) return self::SUCCESS;
        if (!Schema::hasTable('ball_flight_reference_observations')) return $this->fail('Pending ball-flight migrations must be reviewed and run first.');

        if ($this->option('replace-file')) {
            BallFlightReferenceObservation::query()->where('source_file', basename($file))->delete();
        }
        $created = 0;
        $duplicates = 0;
        foreach ($report['rows'] as $row) {
            $exists = BallFlightReferenceObservation::query()->where('import_hash', $row['import_hash'])->exists();
            if ($exists) {
                $duplicates++;
                continue;
            }
            BallFlightReferenceObservation::query()->create($row);
            $created++;
        }
        $this->info("Created {$created}; duplicate rows skipped {$duplicates}.");
        return self::SUCCESS;
    }

    private function display(array $r): void
    {
        $this->table(['Metric', 'Value'], [
            ['Detected source', $r['source']], ['Total rows', $r['total_rows']],
            ['Batted-ball rows', $r['batted_ball_rows']], ['Eligible calibration', $r['eligible_calibration_rows']],
            ['Eligible external validation', $r['eligible_external_validation_rows']],
            ['Measured spin', $r['rows_with_measured_spin']], ['Measured hang time', $r['rows_with_measured_hang_time']],
            ['Measured max height', $r['rows_with_measured_max_height']], ['Duplicate rows', $r['duplicate_rows']],
            ['File SHA-256', $r['file_hash']],
        ]);
        foreach ($r['excluded_by_reason'] as $reason => $count) $this->line("Excluded {$reason}: {$count}");
        $samples = array_map(static function (array $row): array {
            unset($row['raw_metadata']);
            return $row;
        }, array_slice($r['rows'], 0, 2));
        $this->line('Sample: '.json_encode($samples, JSON_UNESCAPED_SLASHES));
    }

    private function fail(string $message): int
    {
        $this->error($message);
        return self::FAILURE;
    }
}
