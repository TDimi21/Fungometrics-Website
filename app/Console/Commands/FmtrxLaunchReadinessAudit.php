<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\FmtrxLaunchReadinessService;
use Illuminate\Console\Command;

class FmtrxLaunchReadinessAudit extends Command
{
    protected $signature = 'fmtrx:launch-readiness
        {teamId : Team id}
        {--days=365 : Lookback days}
        {--weeks=8 : Lookback weeks}
        {--strict : Treat warning-heavy features as not ready}
        {--json : Output structured JSON}';

    protected $description = 'Build the final FMTRX launch-readiness report and backlog for a team.';

    public function handle(FmtrxLaunchReadinessService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $report = $service->buildReadinessReport($teamId, [
            'days' => (int) $this->option('days'),
            'weeks' => (int) $this->option('weeks'),
            'strict' => (bool) $this->option('strict'),
            'include_internal_features' => true,
            'include_backlog' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->exitCode($report);
        }

        $this->info('FMTRX LAUNCH READINESS');
        $this->newLine();
        $this->line('Team: '.$teamId);
        $this->line('Status: '.$this->value($report['readiness_status'] ?? null));
        $this->line('Score: '.$this->value($report['overall_score_0_100'] ?? null));

        $summary = $report['launch_summary'] ?? [];
        $this->section('RECOMMENDED LAUNCH MODE');
        $this->line($this->value($summary['recommended_launch_mode'] ?? null));
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));
        $this->line('Primary blocker: '.$this->value($summary['primary_blocker'] ?? null));
        $this->line('Next best step: '.$this->value($summary['next_best_step'] ?? null));

        $this->section('COMPONENT READINESS');
        foreach ([
            'coach_readiness' => 'Coach',
            'player_readiness' => 'Player',
            'benchmark_readiness' => 'Benchmark',
            'planner_readiness' => 'Planner',
            'report_readiness' => 'Reports',
            'privacy_safety' => 'Privacy / Safety',
        ] as $key => $label) {
            $row = $report[$key] ?? [];
            $this->line(sprintf(
                '- %s: %s · %s',
                $label,
                $this->value($row['status'] ?? null),
                $this->value($row['score_0_100'] ?? null),
            ));
            if (! empty($row['summary'])) {
                $this->line('  '.$this->value($row['summary']));
            }
        }

        $this->listSection('READY NOW', $report['ready_now'] ?? []);
        $this->listSection('INTERNAL ONLY', $report['internal_only'] ?? []);
        $this->structuredSection('NEEDS MORE DATA', $report['needs_more_data'] ?? [], ['title', 'priority', 'why']);
        $this->listSection('KNOWN RISKS', $report['known_risks'] ?? []);
        $this->structuredSection('LAUNCH BLOCKERS', $report['launch_blockers'] ?? [], ['title', 'area', 'why']);
        $this->structuredSection('FEATURE FLAGS', $report['feature_flags_recommendation'] ?? [], ['display_name', 'recommended_status', 'why']);
        $this->structuredSection('NEXT CYCLE BACKLOG', $report['next_cycle_backlog'] ?? [], ['title', 'priority', 'area', 'why']);

        $this->section('SAFETY');
        $evidence = $report['evidence'] ?? [];
        $this->line('Dry run: '.$this->yesNo($evidence['dry_run'] ?? true));
        $this->line('Data modified: '.$this->yesNo($evidence['data_modified'] ?? false));

        return $this->exitCode($report);
    }

    private function exitCode(array $report): int
    {
        if (! $this->option('strict')) {
            return self::SUCCESS;
        }

        return in_array($report['readiness_status'] ?? null, ['failed', 'not_ready'], true)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    /**
     * @param array<int, mixed> $items
     */
    private function listSection(string $title, array $items): void
    {
        $this->section($title);
        if (empty($items)) {
            $this->line('- none');

            return;
        }
        foreach (array_slice($items, 0, 14) as $item) {
            $this->line('- '.$this->value($item));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, string> $fields
     */
    private function structuredSection(string $title, array $items, array $fields): void
    {
        $this->section($title);
        if (empty($items)) {
            $this->line('- none');

            return;
        }
        foreach (array_slice($items, 0, 14) as $item) {
            $parts = [];
            foreach ($fields as $field) {
                if (($item[$field] ?? null) === null || $item[$field] === '') {
                    continue;
                }
                $parts[] = $this->value($item[$field]);
            }
            $this->line('- '.implode(' · ', $parts));
        }
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
        if (is_float($value)) {
            return number_format($value, 1);
        }

        return (string) $value;
    }
}
