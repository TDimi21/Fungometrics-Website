<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use Illuminate\Console\Command;

class BenchmarkTrustedDataPromotionAudit extends Command
{
    protected $signature = 'intelligence:benchmark-trusted-promotion
        {teamId}
        {--taskId= : Preview or promote one approved benchmark task}
        {--preview : Preview promotion without writing}
        {--promote : Promote one task or all approved unpromoted tasks}
        {--overwrite : Allow promotion to overwrite existing mapped fields}
        {--promotedBy= : Optional reviewer/promoter user id}
        {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Audit and run trusted data promotion for approved benchmark collection tasks.';

    public function handle(BenchmarkTrustedDataPromotionService $promotionService): int
    {
        $teamId = (string) $this->argument('teamId');
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;
        $days = max(7, min(365, (int) $this->option('days')));

        $this->info('FMTRX BENCHMARK TRUSTED DATA PROMOTION');
        $this->kv('Team ID', $teamId);
        $this->kv('Days', $days);
        $this->kv('Mode', $this->option('promote') ? 'promote' : ($this->option('preview') ? 'preview' : 'status'));
        if ($taskId) {
            $this->kv('Task ID', $taskId);
        }

        if ($taskId && $this->option('preview')) {
            $this->printPromotionResult($promotionService->previewPromotion($taskId));

            return self::SUCCESS;
        }

        if ($taskId && $this->option('promote')) {
            $result = $promotionService->promoteApprovedTask($taskId, $this->option('promotedBy') ? (string) $this->option('promotedBy') : null, [
                'overwrite' => (bool) $this->option('overwrite'),
                'days' => $days,
            ]);
            $this->printPromotionResult($result);

            return ($result['promotion_status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('promote')) {
            $result = $promotionService->promoteTeamApprovedTasks($teamId, [
                'overwrite' => (bool) $this->option('overwrite'),
                'days' => $days,
                'promoted_by_user_id' => $this->option('promotedBy') ? (string) $this->option('promotedBy') : null,
            ]);
            $this->newLine();
            $this->info('PROMOTE APPROVED TASKS');
            $this->kv('Promotion count', $result['promotion_count'] ?? 0);
            $this->kv('Promoted', $result['promoted_count'] ?? 0);
            $this->kv('Partial', $result['partial_count'] ?? 0);
            $this->kv('Skipped', $result['skipped_count'] ?? 0);
            $this->kv('Failed', $result['failed_count'] ?? 0);

            foreach (($result['results'] ?? []) as $promotionResult) {
                $this->printPromotionResult($promotionResult);
            }

            return ((int) ($result['failed_count'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
        }

        $status = $promotionService->buildPromotionStatus($teamId);
        $this->printStatus($status);

        return self::SUCCESS;
    }

    private function printStatus(array $status): void
    {
        $this->newLine();
        $this->info('PROMOTION STATUS');
        $this->kv('Approved tasks', $status['approved_count'] ?? 0);
        $this->kv('Awaiting promotion', $status['awaiting_promotion_count'] ?? 0);
        $this->kv('Promoted tasks', $status['promoted_count'] ?? 0);
        $this->kv('Manual review', $status['manual_review_count'] ?? 0);
        $this->kv('Skipped', $status['skipped_count'] ?? 0);
        $this->kv('Metadata columns present', $status['evidence']['promotion_metadata_columns_present'] ?? false);

        $this->section('APPROVED AWAITING PROMOTION');
        $this->printTasks($status['approved_awaiting_promotion'] ?? []);

        $this->section('PROMOTED TASKS');
        $this->printTasks($status['promoted_tasks'] ?? []);

        $this->section('MANUAL REVIEW / SKIPPED');
        $this->printTasks([
            ...($status['manual_review_tasks'] ?? []),
            ...($status['skipped_tasks'] ?? []),
        ]);
    }

    private function printPromotionResult(array $result): void
    {
        $this->newLine();
        $this->info('PROMOTION RESULT');
        $this->kv('Task ID', $result['task_id'] ?? '-');
        $this->kv('Player ID', $result['player_id'] ?? '-');
        $this->kv('Task type', $result['task_type'] ?? '-');
        $this->kv('Promotion status', $result['promotion_status'] ?? '-');
        $this->kv('Promotion mode', $result['promotion_mode'] ?? '-');
        $this->kv('Target model', $result['target_model'] ?? '-');
        $this->kv('Target table', $result['target_table'] ?? '-');
        $this->kv('Target record ID', $result['target_record_id'] ?? '-');
        $this->kv('Warnings', $result['warnings'] ?? []);
        $this->kv('Promoted fields', $this->fieldNames($result['promoted_fields'] ?? []));
        $this->kv('Skipped fields', $this->fieldNames($result['skipped_fields'] ?? [], 'reason'));
        $this->kv('Refresh status', $result['refresh']['refresh_status'] ?? '-');
    }

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            $this->line(sprintf(
                '- %s | %s | %s | review %s | promotion %s/%s',
                $task['assigned_to_player_name'] ?? $task['assigned_to_player_id'] ?? 'Team Task',
                $task['title'] ?? 'Benchmark Task',
                $task['task_type'] ?? 'task',
                $task['review_status'] ?? '-',
                $task['promotion_status'] ?? 'not_promoted',
                $task['promotion_mode'] ?? '-',
            ));
            if (! empty($task['promotion_result']['warnings'])) {
                $this->line('  warnings: '.$this->wrap($task['promotion_result']['warnings']));
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

    private function fieldNames(array $fields, ?string $suffixKey = null): string
    {
        if (empty($fields)) {
            return '-';
        }

        return implode(', ', array_map(function (array $field) use ($suffixKey): string {
            $name = (string) ($field['field'] ?? $field['target'] ?? 'field');
            if ($suffixKey && ! empty($field[$suffixKey])) {
                return $name.' ('.$field[$suffixKey].')';
            }

            return $name;
        }, $fields));
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '-' : (json_encode($value, JSON_UNESCAPED_SLASHES) ?: '');
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
