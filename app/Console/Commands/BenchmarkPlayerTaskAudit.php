<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTaskPersistenceService;
use Illuminate\Console\Command;

class BenchmarkPlayerTaskAudit extends Command
{
    protected $signature = 'intelligence:benchmark-player-tasks
        {playerId}
        {--teamId= : Optional team filter}';

    protected $description = 'Print saved benchmark collection tasks visible to a player.';

    public function handle(BenchmarkTaskPersistenceService $persistenceService): int
    {
        $playerId = (string) $this->argument('playerId');
        $teamId = $this->option('teamId') ? (string) $this->option('teamId') : null;

        $result = $persistenceService->listPlayerTasks($playerId, [
            'team_id' => $teamId,
            'include_dismissed' => true,
        ]);

        $tasks = $result['tasks'] ?? [];
        $activeTasks = array_values(array_filter($tasks, fn (array $task) => in_array($task['status'] ?? null, ['assigned', 'in_progress'], true)));
        $completedTasks = array_values(array_filter($tasks, fn (array $task) => ($task['status'] ?? null) === 'completed'));
        $dismissedTasks = array_values(array_filter($tasks, fn (array $task) => ($task['status'] ?? null) === 'dismissed'));

        $this->info('FMTRX BENCHMARK PLAYER TASK AUDIT');
        $this->line('Player ID: '.$playerId);
        $this->line('Team filter: '.($teamId ?: '-'));
        $this->kv('Task count', count($tasks));
        $this->kv('Active tasks', count($activeTasks));
        $this->kv('Completed tasks', count($completedTasks));
        $this->kv('Dismissed tasks', count($dismissedTasks));
        $this->kv('Counts by status', $result['counts_by_status'] ?? []);

        $this->section('ACTIVE TASKS');
        $this->printTasks($activeTasks);

        $this->section('COMPLETED TASKS');
        $this->printTasks($completedTasks);

        $this->section('DISMISSED TASKS');
        $this->printTasks($dismissedTasks);

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

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            $this->line(sprintf(
                '- %s | %s | %s | %s | %s min | team %s',
                $task['title'] ?? 'Benchmark Task',
                $task['task_type_label'] ?? $task['task_type'] ?? 'Task',
                $task['priority'] ?? 'medium',
                $task['status'] ?? 'assigned',
                $task['estimated_minutes'] ?? 0,
                $task['team_id'] ?? '-',
            ));

            foreach (($task['instructions'] ?? []) as $instruction) {
                $this->line('  - '.$instruction);
            }
        }
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
