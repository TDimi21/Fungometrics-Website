<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\FmtrxEndToEndQaService;
use Illuminate\Console\Command;

class FmtrxEndToEndQaAudit extends Command
{
    protected $signature = 'fmtrx:e2e-qa
        {teamId : Team id}
        {--days=365 : Lookback days}
        {--weeks=8 : Lookback weeks}
        {--json : Output structured JSON}
        {--area= : benchmark|planner|player_workout|review|trusted_data|reports|health|operating_home|privacy|actions}
        {--strict : Return failure if any failed check or high/critical warning exists}';

    protected $description = 'Run a read-only end-to-end QA pass across the FMTRX development operating workflow.';

    public function handle(FmtrxEndToEndQaService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $report = $service->runTeamQa($teamId, [
            'days' => (int) $this->option('days'),
            'weeks' => (int) $this->option('weeks'),
            'area' => $this->nullableOption('area'),
            'dry_run' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode($report);
        }

        $summary = $report['summary'] ?? [];
        $this->info('FMTRX END-TO-END QA');
        $this->newLine();
        $this->line('Team: '.$teamId);
        $this->line('QA Status: '.$this->value($report['qa_status'] ?? null));
        $this->line('Readiness: '.$this->value($summary['readiness_label'] ?? null));
        $this->line('Total checks: '.$this->value($summary['total_checks'] ?? 0));
        $this->line('Passed: '.$this->value($summary['passed_checks'] ?? 0));
        $this->line('Warnings: '.$this->value($summary['warning_checks'] ?? 0));
        $this->line('Failures: '.$this->value($summary['failed_checks'] ?? 0));
        $this->line('Skipped: '.$this->value($summary['skipped_checks'] ?? 0));
        $this->line('Critical: '.$this->value($summary['critical_failures'] ?? 0));
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));

        $this->section('AREAS');
        foreach ($this->areaRows($report['checks'] ?? []) as $area => $row) {
            $this->line(sprintf(
                '- %s: %s (%d passed, %d warning, %d failed)',
                $this->label((string) $area),
                strtoupper((string) $row['status']),
                $row['passed'],
                $row['warning'],
                $row['failed'],
            ));
        }

        $this->section('TOP FIXES');
        $fixes = array_slice($report['recommended_fixes'] ?? [], 0, 8);
        if (empty($fixes)) {
            $this->line('- none');
        }
        foreach ($fixes as $index => $fix) {
            $this->line(($index + 1).'. '.$this->value($fix['title'] ?? null).' ['.$this->value($fix['severity'] ?? null).']');
            $this->line('   '.$this->value($fix['recommended_fix'] ?? null));
        }

        $this->section('FAILURES / WARNINGS');
        $nonPassed = array_values(array_filter($report['checks'] ?? [], fn (array $check): bool => in_array($check['status'] ?? null, ['failed', 'warning'], true)));
        if (empty($nonPassed)) {
            $this->line('- none');
        }
        foreach (array_slice($nonPassed, 0, 14) as $check) {
            $this->line(sprintf(
                '- [%s] %s · %s · %s',
                strtoupper((string) ($check['status'] ?? 'unknown')),
                $this->label((string) ($check['area'] ?? 'unknown')),
                $this->value($check['title'] ?? null),
                $this->value($check['message'] ?? null),
            ));
        }

        $this->section('EVIDENCE');
        $evidence = $report['evidence'] ?? [];
        $this->line('Team exists: '.$this->yesNo($evidence['team_exists'] ?? false));
        $this->line('Roster count: '.$this->value($evidence['roster_count'] ?? 0));
        $this->line('Dry run: '.$this->yesNo($evidence['dry_run'] ?? true));
        $this->line('Next best fix: '.$this->value($summary['next_best_fix'] ?? null));

        return $this->exitCode($report);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array<string, array<string, mixed>>
     */
    private function areaRows(array $checks): array
    {
        $rows = [];
        foreach ($checks as $check) {
            $area = (string) ($check['area'] ?? 'unknown');
            $rows[$area] ??= [
                'passed' => 0,
                'warning' => 0,
                'failed' => 0,
                'skipped' => 0,
                'status' => 'passed',
            ];
            $status = (string) ($check['status'] ?? 'skipped');
            $rows[$area][$status] = (int) ($rows[$area][$status] ?? 0) + 1;
        }

        foreach ($rows as &$row) {
            $row['status'] = match (true) {
                (int) $row['failed'] > 0 => 'failed',
                (int) $row['warning'] > 0 => 'warning',
                (int) $row['passed'] > 0 => 'passed',
                default => 'skipped',
            };
        }
        unset($row);

        ksort($rows);

        return $rows;
    }

    private function exitCode(array $report): int
    {
        if (! $this->option('strict')) {
            return self::SUCCESS;
        }

        foreach ($report['checks'] ?? [] as $check) {
            if (($check['status'] ?? null) === 'failed') {
                return self::FAILURE;
            }
            if (($check['status'] ?? null) === 'warning' && in_array($check['severity'] ?? null, ['high', 'critical'], true)) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function nullableOption(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function yesNo(mixed $value): string
    {
        return (bool) $value ? 'YES' : 'NO';
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
