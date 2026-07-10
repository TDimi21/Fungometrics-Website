<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTaskAssignmentService;
use Illuminate\Console\Command;

class BenchmarkTaskAssignmentAudit extends Command
{
    protected $signature = 'intelligence:benchmark-task-assignments {teamId} {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Print draft assignable benchmark collection tasks for a team.';

    public function handle(BenchmarkTaskAssignmentService $assignmentService): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $result = $assignmentService->buildAssignableTasks($teamId, $days);

        $this->info('FMTRX BENCHMARK TASK ASSIGNMENTS');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->kv('Priority level', $result['priority_level'] ?? 'low');
        $this->kv('Task count', $result['task_count'] ?? 0);
        $this->kv('Player task count', $result['player_task_count'] ?? 0);
        $this->kv('Team task count', $result['team_task_count'] ?? 0);
        $this->kv('Source', $result['source'] ?? '-');
        $this->kv('Persistence', $result['evidence']['persistence'] ?? 'dry_run_payload_only');

        $this->section('UPSTREAM PLAN EVIDENCE');
        $evidence = is_array($result['evidence'] ?? null) ? $result['evidence'] : [];
        $planEvidence = is_array($evidence['collection_plan_evidence'] ?? null) ? $evidence['collection_plan_evidence'] : [];
        $this->kv('Team found', ($evidence['team_found'] ?? false) ? 'YES' : 'NO');
        $this->kv('Roster count', $evidence['roster_count'] ?? 0);
        $this->kv('Collection plan priority', $evidence['collection_plan_priority'] ?? '-');
        $this->kv('Collection plan summary', $evidence['collection_plan_summary'] ?? '-');
        $this->kv('Collection plan next action', $evidence['collection_plan_next_action'] ?? '-');
        $this->kv('Benchmark player count', $planEvidence['player_count'] ?? '-');
        $this->kv('Benchmark metric count', $planEvidence['benchmark_metric_count'] ?? '-');
        $this->kv('Players with benchmark metrics', $planEvidence['players_with_benchmark_metrics'] ?? '-');
        $this->kv('Players without benchmark metrics', $planEvidence['players_without_benchmark_metrics'] ?? '-');
        $this->kv('Missing metric count', $planEvidence['missing_metric_count'] ?? '-');

        if (($result['task_count'] ?? 0) === 0) {
            $this->warn('No draft tasks were created because the upstream benchmark collection plan returned no collection sessions or player tasks.');
        }

        $this->section('TEAM TASKS');
        $this->printRows($result['team_tasks'] ?? [], fn (array $task): string => sprintf(
            '%s | %s | %s | %s min | %s | metrics: %s',
            $task['title'] ?? 'Team Task',
            $task['task_type'] ?? 'task',
            $task['priority'] ?? 'low',
            $task['estimated_minutes'] ?? 0,
            $task['due_window'] ?? 'this_week',
            $this->metricList($task['metrics'] ?? []),
        ));

        $this->section('PLAYER TASKS BY PLAYER');
        $groups = $result['player_tasks'] ?? [];
        if (empty($groups)) {
            $this->line('- none');
        } else {
            foreach ($groups as $group) {
                $this->line('- '.($group['player_name'] ?? $group['player_id'] ?? 'Unknown Player').' | '.($group['priority'] ?? 'low').' | tasks '.($group['task_count'] ?? 0));
                foreach (($group['tasks'] ?? []) as $task) {
                    $this->line('  - '.($task['title'] ?? 'Task').' | '.($task['task_type'] ?? 'task').' | '.($task['priority'] ?? 'low').' | '.($task['estimated_minutes'] ?? 0).' min | '.$task['due_window']);
                    $this->line('    metrics: '.$this->metricList($task['metrics'] ?? []));
                    $this->line('    fields: '.$this->fieldList($task['missing_fields'] ?? []));
                }
            }
        }

        $this->section('FLAT ASSIGNABLE TASKS');
        $this->printRows(array_slice($result['assignable_tasks'] ?? [], 0, 20), fn (array $task): string => sprintf(
            '%s | %s | %s | player: %s | %s min | %s | status %s',
            $task['title'] ?? 'Task',
            $task['task_type'] ?? 'task',
            $task['priority'] ?? 'low',
            $task['assigned_to_player_name'] ?? $task['assigned_to_player_id'] ?? '-',
            $task['estimated_minutes'] ?? 0,
            $task['due_window'] ?? 'this_week',
            $task['status'] ?? 'draft',
        ));

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

    private function metricList(array $metrics): string
    {
        $list = collect($metrics)
            ->map(fn ($metric) => is_array($metric) ? ($metric['display_name'] ?? $metric['metric_key'] ?? null) : $metric)
            ->filter()
            ->implode(', ');

        return $list !== '' ? $list : '-';
    }

    private function fieldList(array $fields): string
    {
        $list = collect($fields)->filter()->implode(', ');

        return $list !== '' ? $list : '-';
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
}
