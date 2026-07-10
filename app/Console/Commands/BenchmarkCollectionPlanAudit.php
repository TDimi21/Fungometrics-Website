<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkCollectionPlanner;
use Illuminate\Console\Command;

class BenchmarkCollectionPlanAudit extends Command
{
    protected $signature = 'intelligence:benchmark-collection-plan {teamId} {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Print the FMTRX benchmark collection plan for a team.';

    public function handle(BenchmarkCollectionPlanner $planner): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $plan = $planner->buildTeamCollectionPlan($teamId, $days);

        $this->info('FMTRX BENCHMARK COLLECTION PLAN');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->kv('Priority level', $plan['priority_level'] ?? 'low');
        $this->kv('Summary', $plan['summary'] ?? '-');
        $this->kv('Estimated total minutes', ($plan['estimated_total_minutes'] ?? 0).' min');

        $this->section('NEXT BEST ACTION');
        $action = is_array($plan['next_best_action'] ?? null) ? $plan['next_best_action'] : [];
        $this->kv('Title', $action['title'] ?? '-');
        $this->kv('Priority', $action['priority'] ?? '-');
        $this->kv('Duration', isset($action['duration_minutes']) ? $action['duration_minutes'].' min' : '-');
        $this->kv('Why', $action['why'] ?? '-');
        $this->kv('Players', $this->playerNames($action['players'] ?? []));
        $this->kv('Metrics', implode(', ', $action['metrics'] ?? []) ?: '-');
        $this->printList('Coach instructions', $action['coach_instructions'] ?? []);

        $this->section('COLLECTION SESSIONS');
        $this->printRows($plan['collection_sessions'] ?? [], function (array $session): string {
            return sprintf(
                '%s | %s | %s min | %s | players: %s | metrics: %s',
                $session['title'] ?? 'Collection Session',
                $session['priority'] ?? 'low',
                $session['duration_minutes'] ?? 0,
                $session['schedule_window'] ?? 'unscheduled',
                $this->playerNames($session['players'] ?? []),
                implode(', ', $session['metric_display_names'] ?? $session['metrics'] ?? []) ?: '-',
            );
        });

        $this->section('PLAYER TASKS');
        $this->printRows(array_slice($plan['player_tasks'] ?? [], 0, 12), function (array $task): string {
            $missingContext = implode(', ', $task['missing_context'] ?? []) ?: '-';
            $metrics = collect($task['missing_metrics'] ?? [])
                ->pluck('display_name')
                ->filter()
                ->implode(', ') ?: '-';

            return sprintf(
                '%s | %s | context: %s | metrics: %s | next: %s',
                $task['player_name'] ?? $task['player_id'] ?? 'Unknown Player',
                $task['priority'] ?? 'low',
                $missingContext,
                $metrics,
                $task['next_action'] ?? '-',
            );
        });

        $this->section('METRIC TASKS');
        $this->printRows(array_slice($plan['metric_tasks'] ?? [], 0, 12), fn (array $task): string => sprintf(
            '%s | %s | missing %s of %s | session: %s',
            $task['display_name'] ?? $task['metric_key'] ?? 'Metric',
            $task['priority'] ?? 'low',
            $task['missing_count'] ?? 0,
            $task['eligible_count'] ?? 0,
            $task['recommended_session'] ?? 'Benchmark Collection',
        ));

        $this->section('COMPLETION TARGETS');
        foreach (($plan['completion_targets'] ?? []) as $window => $target) {
            if (! is_array($target)) {
                continue;
            }

            $this->line('- '.$this->human($window).': '.($target['target'] ?? '-').' ('.($target['minutes'] ?? 0).' min)');
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function printRows(array $rows, callable $formatter): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$formatter((array) $row));
        }
    }

    private function printList(string $label, array $rows): void
    {
        $this->line($label.':');
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
    }

    private function playerNames(array $players): string
    {
        $names = collect($players)
            ->map(fn ($player) => is_array($player) ? ($player['player_name'] ?? $player['name'] ?? $player['player_id'] ?? null) : null)
            ->filter()
            ->take(8)
            ->implode(', ');

        return $names !== '' ? $names : '-';
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
