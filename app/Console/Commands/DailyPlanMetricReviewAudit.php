<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTaskReviewService;
use Illuminate\Console\Command;

class DailyPlanMetricReviewAudit extends Command
{
    protected $signature = 'intelligence:daily-plan-metric-review
        {teamId}
        {--taskId= : Benchmark collection task id}
        {--approve : Approve the selected task}
        {--reject= : Reject the selected task with this reason}
        {--correction= : Request correction on the selected task with this message}';

    protected $description = 'Audit or review daily-plan metric submissions that entered benchmark task review.';

    public function handle(BenchmarkTaskReviewService $reviewService): int
    {
        $teamId = (string) $this->argument('teamId');
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;

        $this->info('FMTRX DAILY PLAN METRIC REVIEW');
        $this->line('Team ID: '.$teamId);
        $this->line('Task ID: '.($taskId ?: '-'));

        if ($this->hasReviewAction() && ! $taskId) {
            $this->error('Provide --taskId when using --approve, --reject, or --correction.');

            return self::FAILURE;
        }

        if ($taskId && $this->option('approve')) {
            return $this->printActionResult($reviewService->approveTask($taskId, null, [
                'review_notes' => 'Approved from daily plan metric review audit.',
                'days' => 365,
            ]));
        }

        if ($taskId && $this->option('reject')) {
            return $this->printActionResult($reviewService->rejectTask($taskId, (string) $this->option('reject'), null));
        }

        if ($taskId && $this->option('correction')) {
            return $this->printActionResult($reviewService->requestCorrection($taskId, (string) $this->option('correction'), null));
        }

        $pending = $reviewService->listPendingReviewTasks($teamId);
        $tasks = collect($pending['tasks'] ?? [])
            ->filter(fn (array $task): bool => (string) ($task['submitted_source'] ?? $task['submitted_payload']['source'] ?? '') === 'daily_plan_progress')
            ->values()
            ->all();

        $this->newLine();
        $this->kv('OK', ($pending['ok'] ?? false) ? 'YES' : 'NO');
        $this->kv('All pending review tasks', $pending['pending_count'] ?? 0);
        $this->kv('Daily plan pending review tasks', count($tasks));

        $this->section('PENDING DAILY PLAN METRIC SUBMISSIONS');
        $this->printTasks($tasks);

        if (! empty($pending['error'])) {
            $this->section('ERROR');
            $this->kv('Error', $pending['error']);
            $this->kv('Message', $pending['message'] ?? '-');
        }

        return ($pending['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function hasReviewAction(): bool
    {
        return (bool) $this->option('approve') || (bool) $this->option('reject') || (bool) $this->option('correction');
    }

    private function printActionResult(array $result): int
    {
        $this->section('REVIEW ACTION RESULT');
        $this->kv('OK', ($result['ok'] ?? false) ? 'YES' : 'NO');
        $this->kv('Action', $result['action'] ?? '-');
        $this->kv('Review status', $result['review_status'] ?? '-');
        $this->kv('Task ID', $result['task_id'] ?? '-');
        $this->kv('Player', $result['task']['player_name'] ?? $result['task']['assigned_to_player_name'] ?? $result['player_id'] ?? '-');
        $this->kv('Source', $result['task']['submitted_source'] ?? $result['task']['submitted_payload']['source'] ?? '-');
        $this->kv('Daily plan item', $result['task']['daily_plan_item_title'] ?? '-');

        if (! empty($result['task']['submitted_values_summary'])) {
            $this->section('SUBMITTED VALUES');
            foreach ($result['task']['submitted_values_summary'] as $value) {
                $this->line(sprintf(
                    '- %s: %s',
                    $value['label'] ?? $value['key'] ?? 'Value',
                    $this->wrap($value['value'] ?? null),
                ));
            }
        }

        if (! empty($result['error'])) {
            $this->kv('Error', $result['error']);
        }

        if (! empty($result['message'])) {
            $this->kv('Message', $result['message']);
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            $this->line(sprintf(
                '- %s | %s | item: %s | submitted %s',
                $task['player_name'] ?? $task['assigned_to_player_name'] ?? $task['player_id'] ?? 'Player',
                $task['title'] ?? $task['task_type'] ?? 'Benchmark Task',
                $task['daily_plan_item_title'] ?? $task['daily_plan_item_key'] ?? '-',
                $task['submitted_at'] ?? '-',
            ));
            $this->line('  Task ID: '.$this->wrap($task['task_id'] ?? $task['id'] ?? null));
            $this->line('  Status: '.$this->wrap($task['review_state_label'] ?? $task['review_status'] ?? null));
            $this->line('  Note: '.$this->wrap($task['submitted_note'] ?? $task['submitted_payload']['note'] ?? null));

            foreach (($task['submitted_values_summary'] ?? []) as $value) {
                $this->line(sprintf(
                    '  - %s: %s',
                    $value['label'] ?? $value['key'] ?? 'Value',
                    $this->wrap($value['value'] ?? null),
                ));
            }
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

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        return (string) $value;
    }
}
