<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BenchmarkCollectionTask;
use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\BenchmarkTrustedDataPromotionService;
use App\Services\Intelligence\PopulationValueGuardrail;
use Illuminate\Console\Command;

class DailyPlanTrustedPromotionAudit extends Command
{
    protected $signature = 'intelligence:daily-plan-trusted-promotion
        {teamId}
        {--taskId= : Approved benchmark collection task id}
        {--promote : Promote the approved task}
        {--preview : Preview promotion for the approved task}';

    protected $description = 'Audit or promote approved daily-plan metric submissions into trusted benchmark data.';

    public function handle(
        BenchmarkTrustedDataPromotionService $promotionService,
        PopulationValueGuardrail $guardrail,
    ): int {
        $teamId = (string) $this->argument('teamId');
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;

        $this->info('FMTRX DAILY PLAN TRUSTED PROMOTION');
        $this->line('Team ID: '.$teamId);
        $this->line('Task ID: '.($taskId ?: '-'));

        if ($taskId) {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task || (string) $task->team_id !== $teamId) {
                $this->error('Task was not found for this team.');

                return self::FAILURE;
            }

            $this->section('TASK');
            $this->printTask($task, $guardrail);

            if ($this->option('promote')) {
                $this->printPromotionResult($promotionService->promoteApprovedTask($taskId, null, ['days' => 365]));

                return self::SUCCESS;
            }

            if ($this->option('preview')) {
                $this->printPromotionResult($promotionService->previewPromotion($taskId));

                return self::SUCCESS;
            }

            $this->newLine();
            $this->line('Add --preview to inspect promotion or --promote to write trusted promotion metadata.');

            return self::SUCCESS;
        }

        $tasks = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)
            ->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (BenchmarkCollectionTask $task): bool => $this->isDailyPlanTask($task))
            ->filter(fn (BenchmarkCollectionTask $task): bool => ! in_array((string) $task->promotion_status, [
                BenchmarkCollectionTask::PROMOTION_PROMOTED,
                BenchmarkCollectionTask::PROMOTION_PARTIAL,
            ], true))
            ->values();

        $this->newLine();
        $this->kv('Approved daily-plan tasks awaiting promotion', $tasks->count());

        $this->section('AWAITING PROMOTION');
        if ($tasks->isEmpty()) {
            $this->line('- none');

            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            $this->printTask($task, $guardrail);
        }

        return self::SUCCESS;
    }

    private function printPromotionResult(array $result): void
    {
        $this->section('PROMOTION RESULT');
        $this->kv('Promotion status', $result['promotion_status'] ?? '-');
        $this->kv('Promotion mode', $result['promotion_mode'] ?? '-');
        $this->kv('Target table', $result['target_table'] ?? '-');
        $this->kv('Promoted fields', count($result['promoted_fields'] ?? []));
        $this->kv('Warnings', $result['warnings'] ?? []);

        $trustedPayload = is_array($result['trusted_payload'] ?? null) ? $result['trusted_payload'] : [];
        $this->kv('Trusted payload', [
            'source' => $trustedPayload['source'] ?? null,
            'submitted_source' => $trustedPayload['submitted_source'] ?? null,
            'daily_plan_id' => $trustedPayload['daily_plan_id'] ?? null,
            'daily_plan_item_key' => $trustedPayload['daily_plan_item_key'] ?? null,
            'values' => $trustedPayload['values'] ?? [],
        ]);

        $refresh = is_array($result['refresh'] ?? null) ? $result['refresh'] : [];
        $this->kv('Refresh status', $refresh['refresh_status'] ?? '-');
        $this->kv('Refresh warnings', $refresh['warnings'] ?? []);
        $this->kv('Changed signals', count($refresh['changed_signals'] ?? []));
    }

    private function printTask(BenchmarkCollectionTask $task, PopulationValueGuardrail $guardrail): void
    {
        $payload = is_array($task->approved_payload ?? null) ? $task->approved_payload : [];
        $values = $this->metricValues($payload);

        $this->line(sprintf(
            '- %s | %s | review: %s | promotion: %s',
            $task->id,
            $task->task_type,
            $task->review_status ?? '-',
            $task->promotion_status ?? 'awaiting',
        ));
        $this->line('  Daily plan: '.$this->wrap([
            'id' => $payload['daily_plan_id'] ?? null,
            'item_key' => $payload['daily_plan_item_key'] ?? null,
            'item_title' => $payload['daily_plan_item_title'] ?? $payload['daily_plan_item_name'] ?? null,
        ]));
        $this->line('  Metric values: '.$this->wrap($values));

        foreach ($values as $key => $value) {
            $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $key);
            if ($guardrail->rangeForMetric($metricKey) === null) {
                continue;
            }

            $result = $guardrail->validate($metricKey, $value);
            $this->line(sprintf(
                '  - %s guardrail: %s (%s)',
                $metricKey,
                ($result['included'] ?? false) ? 'included' : 'excluded',
                $result['reason'] ?? 'valid',
            ));
        }
    }

    private function isDailyPlanTask(BenchmarkCollectionTask $task): bool
    {
        $approved = is_array($task->approved_payload ?? null) ? $task->approved_payload : [];
        $submitted = is_array($task->submitted_payload ?? null) ? $task->submitted_payload : [];

        return ($approved['source'] ?? $submitted['source'] ?? null) === 'daily_plan_progress';
    }

    private function metricValues(array $payload): array
    {
        foreach (['metric_values', 'actuals', 'results', 'submitted_values', 'values'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                return collect($payload[$key])
                    ->filter(fn ($value): bool => $value !== null && $value !== '')
                    ->all();
            }
        }

        return [];
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
