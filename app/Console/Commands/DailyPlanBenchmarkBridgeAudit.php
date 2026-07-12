<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyPlanProgress;
use App\Services\Intelligence\DailyPlanBenchmarkCompletionBridge;
use Illuminate\Console\Command;

class DailyPlanBenchmarkBridgeAudit extends Command
{
    protected $signature = 'intelligence:daily-plan-benchmark-bridge
        {dailyPlanId}
        {playerId}
        {--teamId= : Optional expected team id}
        {--dry-run : Inspect matches without updating benchmark tasks}
        {--complete : Run the bridge for completed benchmark items}';

    protected $description = 'Inspect or run the Daily Plan completion to Benchmark Task completion bridge.';

    public function handle(DailyPlanBenchmarkCompletionBridge $bridge): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $playerId = (string) $this->argument('playerId');
        $expectedTeamId = $this->option('teamId') ? (string) $this->option('teamId') : null;
        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('complete');

        $this->info('FMTRX DAILY PLAN BENCHMARK BRIDGE AUDIT');
        $this->line('Daily plan ID: '.$dailyPlanId);
        $this->line('Player ID: '.$playerId);
        $this->line('Expected team ID: '.($expectedTeamId ?: '-'));
        $this->line('Mode: '.($dryRun ? 'dry-run' : 'complete'));

        $inspection = $bridge->inspectDailyPlanProgress($dailyPlanId, $playerId);
        if ($expectedTeamId && (string) ($inspection['team_id'] ?? '') !== $expectedTeamId) {
            $this->warn('Expected team id does not match the daily plan team. No updates were run.');
            $dryRun = true;
        }

        $this->section('INSPECTION');
        $this->kv('Daily plan found', $inspection['daily_plan_found'] ?? false);
        $this->kv('Progress found', $inspection['progress_found'] ?? false);
        $this->kv('Team ID', $inspection['team_id'] ?? '-');
        $this->kv('Completed plan items', $inspection['completed_items_count'] ?? 0);
        $this->kv('Benchmark-generated items', $inspection['benchmark_items_count'] ?? 0);

        $this->section('MATCHING BENCHMARK TASKS');
        $benchmarkItems = $inspection['benchmark_items'] ?? [];
        if (empty($benchmarkItems)) {
            $this->line('- none');
        } else {
            foreach ($benchmarkItems as $row) {
                $item = is_array($row['item'] ?? null) ? $row['item'] : [];
                $matches = is_array($row['matches'] ?? null) ? $row['matches'] : [];
                $this->line(sprintf(
                    '- %s | item: %s | matches: %s',
                    $item['name'] ?? $item['id'] ?? 'Benchmark Item',
                    $item['id'] ?? '-',
                    count($matches),
                ));
                $this->line('  Metric values found: '.$this->wrap($row['metric_values'] ?? []));
                $this->line('  Submitted payload preview: '.$this->wrap($row['submitted_payload_preview'] ?? []));

                foreach ($matches as $match) {
                    if (! is_array($match)) {
                        continue;
                    }

                    $this->line(sprintf(
                        '  - %s | %s | status: %s | review: %s | metrics: %s',
                        $match['id'] ?? '-',
                        $match['task_type'] ?? '-',
                        $match['status'] ?? '-',
                        $match['review_status'] ?? '-',
                        $this->list($match['metrics'] ?? []),
                    ));
                }
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run only. Add --complete without --dry-run to update matching benchmark tasks.');

            return self::SUCCESS;
        }

        $progress = DailyPlanProgress::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        if (! $progress) {
            $this->error('No DailyPlanProgress row exists for this player and plan.');

            return self::FAILURE;
        }

        $result = $bridge->handleDailyPlanProgressUpdate($dailyPlanId, $playerId, [
            'items' => $progress->items ?? [],
            'readiness' => $progress->readiness ?? [],
            'reflection' => $progress->reflection ?? [],
            'started_at' => $progress->started_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
        ], $playerId);

        $this->section('COMPLETION RESULT');
        $this->kv('Processed items', $result['processed_items'] ?? 0);
        $this->kv('Benchmark items found', $result['benchmark_items_found'] ?? 0);
        $this->kv('Tasks matched', $result['tasks_matched'] ?? 0);
        $this->kv('Tasks updated', $result['tasks_updated'] ?? 0);
        $this->kv('Tasks pending review', $result['tasks_pending_review'] ?? 0);

        $this->section('UPDATED TASKS');
        $this->printTasks($result['updated_tasks'] ?? []);

        $this->section('TASKS SKIPPED');
        $this->printRows($result['tasks_skipped'] ?? []);

        $this->section('WARNINGS');
        $this->printList($result['warnings'] ?? []);

        return self::SUCCESS;
    }

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $this->line(sprintf(
                '- %s | %s | status: %s | review: %s',
                $task['id'] ?? $task['task_id'] ?? '-',
                $task['task_type'] ?? '-',
                $task['status'] ?? '-',
                $task['review_status'] ?? '-',
            ));
        }
    }

    private function printRows(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
    }

    private function printList(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
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

    private function list(array $values): string
    {
        $list = collect($values)
            ->map(fn ($value) => is_array($value) ? ($value['metric_key'] ?? $value['key'] ?? $value['name'] ?? null) : $value)
            ->filter()
            ->implode(', ');

        return $list !== '' ? $list : '-';
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
