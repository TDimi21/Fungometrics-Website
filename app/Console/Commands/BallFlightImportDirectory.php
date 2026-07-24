<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BallFlight\BallFlightPartitionService;
use App\Services\BallFlight\ResearchDatabase;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class BallFlightImportDirectory extends Command
{
    protected $signature = 'ball-flight:import-directory {directory} {--recursive} {--dry-run} {--source=auto} {--pattern=*.csv}';
    protected $description = 'Discover and import a directory of ball-flight research CSV files';

    public function handle(ResearchDatabase $database): int
    {
        $directory = realpath((string) $this->argument('directory'));
        if ($directory === false || !is_dir($directory)) {
            $this->error('Directory does not exist.');
            return self::FAILURE;
        }
        $finder = Finder::create()->files()->name((string) $this->option('pattern'))->in($directory);
        if (!$this->option('recursive')) $finder->depth('== 0');
        $hashes = [];
        $files = [];
        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $hash = hash_file('sha256', $path);
            if (isset($hashes[$hash])) {
                $this->warn("Exact duplicate skipped: {$path} (same as {$hashes[$hash]})");
                continue;
            }
            $hashes[$hash] = $path;
            $files[] = $path;
        }
        $sessions = [];
        $reports = [];
        foreach ($files as $path) {
            $report = $database->inspect($path, (string) $this->option('source'));
            $reports[$path] = $report;
            if ($report['source'] === 'trackman') {
                foreach ($report['rows'] as $row) $sessions[] = (string) $row['source_session_identifier'];
            }
        }
        $partitions = BallFlightPartitionService::assignSessions($sessions);
        foreach ($files as $path) {
            $arguments = ['file' => $path, '--source' => $this->option('source')];
            if ($reports[$path]['source'] === 'trackman') {
                $session = (string) ($reports[$path]['rows'][0]['source_session_identifier'] ?? '');
                $arguments['--partition'] = $partitions[$session] ?? BallFlightPartitionService::partition($session);
                $this->line("Partition {$session}: {$arguments['--partition']}");
            }
            if ($this->option('dry-run')) $arguments['--dry-run'] = true;
            $exit = $this->call('ball-flight:import', $arguments);
            if ($exit !== self::SUCCESS) return $exit;
        }
        $totals = [
            'files' => count($files), 'source_rows' => 0, 'batted_ball_rows' => 0,
            'eligible_calibration_rows' => 0, 'eligible_external_validation_rows' => 0,
            'duplicate_rows' => 0,
        ];
        foreach ($reports as $report) {
            foreach (array_keys($totals) as $key) {
                if ($key !== 'files') $totals[$key] += (int) ($report[$key] ?? 0);
            }
        }
        $totals['source_rows'] = array_sum(array_column($reports, 'total_rows'));
        $this->table(['Total metric', 'Value'], collect($totals)->map(fn ($v, $k) => [$k, $v])->values()->all());
        return self::SUCCESS;
    }
}
