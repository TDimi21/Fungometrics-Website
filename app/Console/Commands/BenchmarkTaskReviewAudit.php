<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTaskReviewService;
use Illuminate\Console\Command;

class BenchmarkTaskReviewAudit extends Command
{
    protected $signature = 'intelligence:benchmark-task-review
        {teamId}
        {--taskId= : Review a specific benchmark task}
        {--approve : Approve the task specified by --taskId}
        {--reject= : Reject the task specified by --taskId with this reason}
        {--correction= : Request correction for the task specified by --taskId}
        {--reviewedBy= : Optional reviewer user id}
        {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Audit or perform coach review actions for benchmark collection tasks.';

    public function handle(BenchmarkTaskReviewService $reviewService): int
    {
        $teamId = (string) $this->argument('teamId');
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;
        $reviewedBy = $this->option('reviewedBy') ? (string) $this->option('reviewedBy') : null;
        $days = max(7, min(365, (int) $this->option('days')));

        $this->info('FMTRX BENCHMARK TASK REVIEW AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        if ($taskId) {
            $this->line('Task ID: '.$taskId);
        }

        if ($taskId && $this->option('approve')) {
            $result = $reviewService->approveTask($taskId, $reviewedBy, [
                'review_notes' => 'Approved from benchmark task review audit.',
                'days' => $days,
            ]);
            $this->printReviewResult($result);

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if ($taskId && $this->option('reject')) {
            $result = $reviewService->rejectTask($taskId, (string) $this->option('reject'), $reviewedBy);
            $this->printReviewResult($result);

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        if ($taskId && $this->option('correction')) {
            $result = $reviewService->requestCorrection($taskId, (string) $this->option('correction'), $reviewedBy);
            $this->printReviewResult($result);

            return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
        }

        $summary = $reviewService->buildTeamReviewSummary($teamId);
        $this->newLine();
        $this->kv('OK', ($summary['ok'] ?? false) ? 'YES' : 'NO');
        $this->kv('Pending count', $summary['pending_count'] ?? 0);
        $this->kv('Approved count', $summary['approved_count'] ?? 0);
        $this->kv('Rejected count', $summary['rejected_count'] ?? 0);
        $this->kv('Correction requested count', $summary['correction_requested_count'] ?? 0);
        $this->kv('Not required count', $summary['not_required_count'] ?? 0);

        $this->section('COUNTS BY REVIEW STATUS');
        foreach (($summary['counts_by_review_status'] ?? []) as $status => $count) {
            $this->kv((string) $status, $count);
        }

        $this->section('PENDING TASKS');
        $this->printTasks($summary['pending_tasks'] ?? []);

        $this->section('RECENT REVIEWED TASKS');
        $this->printTasks($summary['recent_reviewed_tasks'] ?? []);

        return ($summary['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    private function printReviewResult(array $result): void
    {
        $this->newLine();
        $this->kv('OK', ($result['ok'] ?? false) ? 'YES' : 'NO');
        $this->kv('Action', $result['action'] ?? '-');
        $this->kv('Review status', $result['review_status'] ?? '-');
        $this->kv('Task ID', $result['task_id'] ?? '-');
        $this->kv('Player', $result['task']['assigned_to_player_name'] ?? $result['player_id'] ?? '-');
        if (! empty($result['error'])) {
            $this->kv('Error', $result['error']);
        }
        if (! empty($result['message'])) {
            $this->kv('Message', $result['message']);
        }

        if (isset($result['refresh'])) {
            $this->section('REFRESH RESULT');
            $this->kv('Refresh status', $result['refresh']['refresh_status'] ?? '-');
            $this->kv('Warnings', $result['refresh']['warnings'] ?? []);
            $this->kv('Changed signals', count($result['refresh']['changed_signals'] ?? []));
        }
    }

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            $this->line(sprintf(
                '- %s | %s | %s | %s | submitted %s',
                $task['assigned_to_player_name'] ?? $task['assigned_to_player_id'] ?? 'Team Task',
                $task['title'] ?? 'Benchmark Task',
                $task['task_type'] ?? 'task',
                $task['review_state_label'] ?? $task['review_status'] ?? '-',
                $task['submitted_at'] ?? '-',
            ));

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
