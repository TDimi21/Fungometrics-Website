<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkTaskAssignmentService;
use App\Services\Intelligence\BenchmarkTaskPersistenceService;
use Illuminate\Console\Command;

class BenchmarkTaskPersistenceAudit extends Command
{
    protected $signature = 'intelligence:benchmark-task-persistence
        {teamId}
        {--days=365 : Intelligence lookback window in days}
        {--save-drafts : Save generated tasks as draft records}
        {--assign : Save generated tasks and assign draft records}';

    protected $description = 'Generate, save, and optionally assign benchmark collection tasks for a team.';

    public function handle(
        BenchmarkTaskAssignmentService $assignmentService,
        BenchmarkTaskPersistenceService $persistenceService,
    ): int {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $generated = $assignmentService->buildAssignableTasks($teamId, $days);
        $tasks = array_values([
            ...($generated['team_tasks'] ?? []),
            ...($generated['assignable_tasks'] ?? []),
        ]);

        $this->info('FMTRX BENCHMARK TASK PERSISTENCE AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->kv('Generated task count', count($tasks));
        $this->kv('Generated player tasks', $generated['player_task_count'] ?? 0);
        $this->kv('Generated team tasks', $generated['team_task_count'] ?? 0);
        $this->kv('Generated priority', $generated['priority_level'] ?? 'low');

        if (! $this->option('save-drafts') && ! $this->option('assign')) {
            $this->section('GENERATED TASKS ONLY');
            $this->printTasks($tasks);

            return self::SUCCESS;
        }

        $save = $persistenceService->saveDraftTasks($teamId, $tasks);
        $this->section('SAVE DRAFTS');
        $this->kv('OK', ($save['ok'] ?? false) ? 'YES' : 'NO');
        $this->kv('Created', $save['created_count'] ?? 0);
        $this->kv('Updated', $save['updated_count'] ?? 0);
        $this->kv('Skipped', $save['skipped_count'] ?? 0);

        if ($this->option('assign')) {
            $taskIds = collect([...($save['created'] ?? []), ...($save['updated'] ?? [])])
                ->pluck('id')
                ->filter()
                ->values()
                ->all();

            $assign = $persistenceService->assignTasks($teamId, $taskIds);
            $this->section('ASSIGN TASKS');
            $this->kv('OK', ($assign['ok'] ?? false) ? 'YES' : 'NO');
            $this->kv('Assigned', $assign['assigned_count'] ?? 0);
            $this->kv('Skipped', $assign['skipped_count'] ?? 0);
        }

        $list = $persistenceService->listTeamTasks($teamId);
        $this->section('SAVED TASKS BY PLAYER');
        $groups = collect($list['tasks'] ?? [])->groupBy(function (array $task): string {
            $playerId = $task['assigned_to_player_id'] ?? null;

            return $playerId
                ? $playerId.'|'.($task['assigned_to_player_name'] ?? 'Unknown Player')
                : 'team|Team Task';
        });
        if ($groups->isEmpty()) {
            $this->line('- none');
        } else {
            foreach ($groups as $playerKey => $playerTasks) {
                [$playerId, $playerName] = array_pad(explode('|', (string) $playerKey, 2), 2, 'Team Task');
                $playerLabel = $playerId === 'team'
                    ? 'Team Task'
                    : $playerName.' ('.$playerId.')';
                $this->line('- '.$playerLabel.' | tasks '.$playerTasks->count());
                foreach ($playerTasks as $task) {
                    $this->line(sprintf(
                        '  - %s | %s | %s | %s | %s min',
                        $task['title'] ?? 'Task',
                        $task['task_type'] ?? 'task',
                        $task['priority'] ?? 'medium',
                        $task['status'] ?? 'draft',
                        $task['estimated_minutes'] ?? 0,
                    ));
                }
            }
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

    private function printTasks(array $tasks): void
    {
        if (empty($tasks)) {
            $this->line('- none');

            return;
        }

        foreach ($tasks as $task) {
            $this->line(sprintf(
                '- %s | %s | %s | player: %s | %s | %s min',
                $task['title'] ?? 'Task',
                $task['task_type'] ?? 'task',
                $task['priority'] ?? 'medium',
                $task['assigned_to_player_name'] ?? $task['assigned_to_player_id'] ?? 'Team Task',
                $task['status'] ?? 'draft',
                $task['estimated_minutes'] ?? 0,
            ));
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
