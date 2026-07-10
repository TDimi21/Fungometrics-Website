<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BenchmarkCollectionTask;
use App\Services\Intelligence\BenchmarkTaskCompletionService;
use Illuminate\Console\Command;

class BenchmarkTaskCompletionAudit extends Command
{
    protected $signature = 'intelligence:benchmark-task-completion
        {taskId}
        {--complete : Attempt completion using --payload or manual confirm}
        {--payload= : JSON payload for completion}';

    protected $description = 'Print or test a benchmark collection task completion workflow.';

    public function handle(BenchmarkTaskCompletionService $completionService): int
    {
        $taskId = (string) $this->argument('taskId');
        $taskBefore = BenchmarkCollectionTask::query()->find($taskId);
        $payload = $this->payload();

        $this->info('FMTRX BENCHMARK TASK COMPLETION AUDIT');
        $this->line('Task ID: '.$taskId);
        $this->line('Status before: '.($taskBefore?->status ?? 'not_found'));

        if ($this->option('complete')) {
            if (empty($payload)) {
                $payload = [
                    'manual_confirm' => true,
                    'note' => 'Completed from benchmark task completion audit.',
                ];
            }

            $result = $completionService->completeTaskWithPayload($taskId, $payload, null);
            $this->line('Completion OK: '.(($result['ok'] ?? false) ? 'YES' : 'NO'));
            if (! empty($result['error'])) {
                $this->line('Error: '.$result['error']);
            }
            if (! empty($result['message'])) {
                $this->line('Message: '.$result['message']);
            }
        } else {
            $result = $completionService->getCompletionWorkflow($taskId, null);
        }

        $workflow = $result['workflow'] ?? null;
        if (! is_array($workflow)) {
            $this->line('Workflow: not available');

            return self::SUCCESS;
        }

        $taskAfter = BenchmarkCollectionTask::query()->find($taskId);
        $this->line('Status after: '.($taskAfter?->status ?? 'not_found'));
        $this->newLine();

        $this->kv('Task type', $workflow['task_type'] ?? null);
        $this->kv('Title', $workflow['title'] ?? null);
        $this->kv('Completion mode', $workflow['completion_mode'] ?? null);
        $this->kv('Target route', $workflow['target_route'] ?? null);
        $this->kv('Target screen', $workflow['target_screen'] ?? null);
        $this->kv('Existing data found', $workflow['existing_data_found'] ?? false);

        $this->section('REQUIRED FIELDS');
        $this->printFields($workflow['required_fields'] ?? []);

        $this->section('OPTIONAL FIELDS');
        $this->printFields($workflow['optional_fields'] ?? []);

        $this->section('INSTRUCTIONS');
        $this->printList($workflow['instructions'] ?? []);

        $this->section('COMPLETION RULES');
        foreach (($workflow['completion_rules'] ?? []) as $key => $value) {
            $this->kv($key, $value);
        }

        $this->section('EXISTING DATA SUMMARY');
        foreach (($workflow['existing_data_summary'] ?? []) as $key => $value) {
            $this->kv($key, $value);
        }

        return self::SUCCESS;
    }

    private function payload(): array
    {
        $raw = $this->option('payload');
        if (! $raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            $this->warn('Payload was not valid JSON. Using manual confirm fallback.');

            return [];
        }

        return $decoded;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function printFields(array $fields): void
    {
        if (empty($fields)) {
            $this->line('- none');

            return;
        }

        foreach ($fields as $field) {
            if (! is_array($field)) {
                $this->line('- '.$field);

                continue;
            }

            $label = $field['label'] ?? $field['key'] ?? 'Field';
            $key = $field['key'] ?? '-';
            $type = $field['type'] ?? '-';
            $unit = $field['unit'] ?? null;
            $this->line(sprintf('- %s (%s, %s%s)', $label, $key, $type, $unit ? ', '.$unit : ''));
        }
    }

    private function printList(array $items): void
    {
        if (empty($items)) {
            $this->line('- none');

            return;
        }

        foreach ($items as $item) {
            $this->line('- '.$this->wrap($item));
        }
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function wrap(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
