<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cage\CageDistanceValidationService;
use Illuminate\Console\Command;

class CageValidationMatrix extends Command
{
    protected $signature = 'cage:validation-matrix
        {--ev= : Single exit velocity (mph); omit and use --all for the full EV grid}
        {--la= : Single launch angle (deg) to display; omit to show every launch angle for the selected EV(s)}
        {--spray= : Single spray angle (deg) to display; omit to show every spray angle}
        {--all : Use the full default EV/LA/spray grid}
        {--format=table : table|json|csv}
        {--output= : Write the report to this file path (in addition to the console summary for table format)}
        {--include-v1 : Join the mobile v1 reference fixture (tests/Fixtures/cage_distance_v1_reference.json)}
        {--fast : Cheapest Monte Carlo (1 sample) for quick iteration}
        {--full : Full production Monte Carlo (500 samples); slow, most rigorous}
        {--fail-on-invalid : Exit 1 if any displayed row has a hard physical-validation failure}';

    protected $description = 'FMTRX Cage Distance Validation Lab: CageDistanceService v2 vs physical-behavior rules, optionally vs the mobile v1 model.';

    public function handle(CageDistanceValidationService $validation): int
    {
        $all = (bool) $this->option('all');
        $evOption = $this->numericOrNull($this->option('ev'));
        $laOption = $this->numericOrNull($this->option('la'));
        $sprayOption = $this->numericOrNull($this->option('spray'));

        if (!$all && $evOption === null) {
            $this->error('Provide --ev=<mph> or use --all.');

            return self::FAILURE;
        }

        $speed = $this->option('fast')
            ? CageDistanceValidationService::SPEED_FAST
            : ($this->option('full') ? CageDistanceValidationService::SPEED_FULL : CageDistanceValidationService::SPEED_GRID);

        $v1Lookup = null;
        if ($this->option('include-v1')) {
            $v1Lookup = $validation->loadV1Reference();
            if (empty($v1Lookup)) {
                $this->warn('--include-v1 requested but tests/Fixtures/cage_distance_v1_reference.json is missing/empty. '
                    . 'Run scripts/generate-cage-v1-reference.mjs in the mobile repo and copy the JSON in (see that script\'s docblock).');
            }
        }

        // Launch angle + spray are always evaluated across their FULL default
        // sweep internally (even when the user only wants to see one),
        // because rules like negative-angle, peak-curve, and spray-symmetry
        // are only meaningful with the surrounding context present. --la/--spray
        // just filter what's displayed below.
        $evs = $all ? null : [$evOption];
        $matrix = $validation->buildMatrix($evs, null, null, $speed, $v1Lookup);

        $displayRows = array_values(array_filter($matrix['rows'], static function (array $row) use ($laOption, $sprayOption): bool {
            if ($laOption !== null && abs($row['launch_angle_deg'] - $laOption) > 1e-6) {
                return false;
            }
            if ($sprayOption !== null && abs($row['spray_angle_deg'] - $sprayOption) > 1e-6) {
                return false;
            }

            return true;
        }));

        $format = (string) $this->option('format');
        $outputPath = $this->option('output');

        match ($format) {
            'json' => $this->outputJson($displayRows, $matrix['summary'], $outputPath),
            'csv' => $this->outputCsv($displayRows, $outputPath),
            default => $this->outputTable($displayRows, $matrix['summary'], $outputPath, (bool) $this->option('include-v1')),
        };

        if ($this->option('fail-on-invalid')) {
            $hardFlags = CageDistanceValidationService::hardFlags();
            foreach ($displayRows as $row) {
                if (array_intersect($row['validation_flags'], $hardFlags) !== []) {
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    // ── Output renderers ──────────────────────────────────────────────────────

    private function outputJson(array $rows, array $summary, ?string $outputPath): void
    {
        $payload = json_encode(['rows' => $rows, 'summary' => $summary], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($outputPath) {
            file_put_contents($outputPath, $payload);
            $this->info("Wrote JSON to {$outputPath}");

            return;
        }
        $this->line((string) $payload);
    }

    private function outputCsv(array $rows, ?string $outputPath): void
    {
        $csv = $this->toCsv($rows);
        if ($outputPath) {
            file_put_contents($outputPath, $csv);
            $this->info("Wrote CSV to {$outputPath}");

            return;
        }
        $this->line($csv);
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        $header = ['exit_velocity_mph', 'launch_angle_deg', 'spray_angle_deg', 'v1_distance_ft', 'v2_estimated_carry_ft',
            'v2_low_ft', 'v2_high_ft', 'difference_ft', 'difference_percent', 'hang_time_seconds', 'maximum_height_ft',
            'batted_ball_type', 'confidence', 'validation_flags'];
        fputcsv($handle, $header);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['exit_velocity_mph'],
                $row['launch_angle_deg'],
                $row['spray_angle_deg'],
                $row['v1_distance_ft'] ?? '',
                $row['v2_estimated_carry_ft'] ?? '',
                $row['v2_low_ft'] ?? '',
                $row['v2_high_ft'] ?? '',
                $row['difference_ft'] ?? '',
                $row['difference_percent'] ?? '',
                $row['hang_time_seconds'],
                $row['maximum_height_ft'],
                $row['batted_ball_type'],
                $row['confidence'],
                implode(';', $row['validation_flags']),
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function outputTable(array $rows, array $summary, ?string $outputPath, bool $includeV1): void
    {
        if (count($rows) === 1) {
            $this->renderSingleRow($rows[0], $includeV1);
        } else {
            $this->renderMultiRowTable($rows, $includeV1);
        }

        $this->newLine();
        $this->renderSummary($summary);

        if ($outputPath) {
            file_put_contents($outputPath, $this->toCsv($rows));
            $this->info("Also wrote CSV to {$outputPath}");
        }
    }

    private function renderSingleRow(array $row, bool $includeV1): void
    {
        $this->info('FMTRX CAGE DISTANCE VALIDATION');
        $this->line(sprintf('EV=%.1f mph  LA=%.1f°  Spray=%.1f°', $row['exit_velocity_mph'], $row['launch_angle_deg'], $row['spray_angle_deg']));
        $this->newLine();
        if ($includeV1) {
            $this->line('v1 (mobile) distance: '.($row['v1_distance_ft'] ?? 'n/a').' ft');
        }
        $this->line('v2 (backend) estimate: '.($row['v2_estimated_carry_ft'] ?? 'n/a (ground ball — air carry to first contact above)').' ft');
        if ($row['v2_low_ft'] !== null) {
            $this->line('v2 likely range: '.$row['v2_low_ft'].' - '.$row['v2_high_ft'].' ft');
        }
        if ($includeV1 && isset($row['difference_ft'])) {
            $this->line(sprintf('Difference (v2 - v1): %+.1f ft (%.1f%%)', $row['difference_ft'], $row['difference_percent'] ?? 0.0));
        }
        $this->line('Hang time: '.$row['hang_time_seconds'].' s');
        $this->line('Maximum height: '.$row['maximum_height_ft'].' ft');
        $this->line('Batted-ball type: '.$row['batted_ball_type']);
        $this->line('Confidence: '.$row['confidence']);
        $this->newLine();
        $this->line('Validation flags: '.(empty($row['validation_flags']) ? 'none' : implode(', ', $row['validation_flags'])));
        $this->line('Assumptions:');
        foreach ($row['assumptions'] as $assumption) {
            $this->line('  - '.$assumption);
        }
    }

    private function renderMultiRowTable(array $rows, bool $includeV1): void
    {
        $headers = ['EV', 'LA', 'Spray', 'v2 carry', 'v2 range', 'Hang', 'Type', 'Flags'];
        if ($includeV1) {
            array_splice($headers, 3, 0, ['v1', 'diff']);
        }

        $tableRows = array_map(function (array $row) use ($includeV1): array {
            $base = [
                $row['exit_velocity_mph'],
                $row['launch_angle_deg'],
                $row['spray_angle_deg'],
            ];
            if ($includeV1) {
                $base[] = $row['v1_distance_ft'] ?? '-';
                $base[] = isset($row['difference_ft']) ? sprintf('%+.1f', $row['difference_ft']) : '-';
            }
            $base[] = $row['v2_estimated_carry_ft'] ?? '-';
            $base[] = $row['v2_low_ft'] !== null ? "{$row['v2_low_ft']}-{$row['v2_high_ft']}" : '-';
            $base[] = $row['hang_time_seconds'];
            $base[] = $row['batted_ball_type'];
            $base[] = empty($row['validation_flags']) ? '' : implode(',', $row['validation_flags']);

            return $base;
        }, $rows);

        $this->table($headers, $tableRows);
    }

    private function renderSummary(array $summary): void
    {
        $this->info('SUMMARY');
        $this->line('Total cases: '.$summary['total_cases']);
        $this->line('Passing: '.$summary['passing_cases'].'  Warning: '.$summary['warning_cases'].'  Failing: '.$summary['failing_cases']);
        $this->line('Negative-angle failures: '.$summary['negative_angle_failures']);
        $this->line('Monotonicity failures: '.$summary['monotonicity_failures']);
        $this->line('Spray-symmetry failures: '.$summary['spray_symmetry_failures']);

        if (!empty($summary['peak_launch_angle_by_ev'])) {
            $this->newLine();
            $this->line('Peak launch angle by EV:');
            foreach ($summary['peak_launch_angle_by_ev'] as $ev => $la) {
                $this->line("  EV {$ev}: peak at {$la}° (max carry ".($summary['max_carry_by_ev'][$ev] ?? '?').' ft)');
            }
        }

        if (!empty($summary['largest_v1_v2_difference'])) {
            $d = $summary['largest_v1_v2_difference'];
            $this->newLine();
            $this->line(sprintf(
                'Largest v1/v2 difference: %+.1f ft (%.1f%%) at EV=%.1f LA=%.1f Spray=%.1f',
                $d['difference_ft'],
                $d['difference_percent'] ?? 0.0,
                $d['exit_velocity_mph'],
                $d['launch_angle_deg'],
                $d['spray_angle_deg'],
            ));
        }
    }
}
