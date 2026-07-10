<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class BenchmarkTaskPersistenceService
{
    public function saveDraftTasks(string $teamId, array $tasks, ?string $createdByUserId = null): array
    {
        try {
            $normalizedTasks = $this->flattenTasks($tasks);
            $created = [];
            $updated = [];
            $skipped = [];

            DB::transaction(function () use ($teamId, $createdByUserId, $normalizedTasks, &$created, &$updated, &$skipped): void {
                foreach ($normalizedTasks as $task) {
                    $payload = $this->normalizeTaskPayload($teamId, $task, $createdByUserId);
                    if ($payload === null) {
                        $skipped[] = [
                            'reason' => 'invalid_task_payload',
                            'task' => $task,
                        ];

                        continue;
                    }

                    $existing = $this->existingActiveTask($teamId, $payload);
                    if ($existing) {
                        if ($existing->status === BenchmarkCollectionTask::STATUS_DRAFT) {
                            $existing->fill($payload);
                            $existing->status = BenchmarkCollectionTask::STATUS_DRAFT;
                            $existing->assigned_at = null;
                            $existing->completed_at = null;
                            $existing->dismissed_at = null;
                            $existing->save();
                            $updated[] = $this->serializeTask($existing->fresh());
                        } else {
                            $skipped[] = [
                                'id' => $existing->id,
                                'reason' => 'active_task_already_exists',
                                'status' => $existing->status,
                                'task' => $this->serializeTask($existing),
                            ];
                        }

                        continue;
                    }

                    $model = BenchmarkCollectionTask::query()->create($payload);
                    $created[] = $this->serializeTask($model);
                }
            });

            return $this->result('save_drafts', [
                'team_id' => $teamId,
                'created_count' => count($created),
                'updated_count' => count($updated),
                'skipped_count' => count($skipped),
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'tasks' => array_values([...$created, ...$updated]),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('save_drafts', $teamId, $exception);
        }
    }

    public function assignTasks(string $teamId, array $taskIds, ?string $createdByUserId = null): array
    {
        try {
            $assigned = [];
            $skipped = [];

            DB::transaction(function () use ($teamId, $taskIds, $createdByUserId, &$assigned, &$skipped): void {
                $query = BenchmarkCollectionTask::query()
                    ->where('team_id', $teamId)
                    ->orderByDesc('priority')
                    ->orderBy('created_at');

                if (! empty($taskIds)) {
                    $query->whereIn('id', array_values(array_unique(array_filter($taskIds))));
                } else {
                    $query->where('status', BenchmarkCollectionTask::STATUS_DRAFT);
                }

                foreach ($query->get() as $task) {
                    if ($task->status !== BenchmarkCollectionTask::STATUS_DRAFT) {
                        $skipped[] = [
                            'id' => $task->id,
                            'reason' => 'task_not_draft',
                            'status' => $task->status,
                            'task' => $this->serializeTask($task),
                        ];

                        continue;
                    }

                    $task->status = BenchmarkCollectionTask::STATUS_ASSIGNED;
                    $task->assigned_at = now();
                    if ($createdByUserId && ! $task->created_by_user_id) {
                        $task->created_by_user_id = $createdByUserId;
                    }
                    $task->save();
                    $assigned[] = $this->serializeTask($task->fresh());
                }
            });

            return $this->result('assign', [
                'team_id' => $teamId,
                'assigned_count' => count($assigned),
                'skipped_count' => count($skipped),
                'assigned' => $assigned,
                'skipped' => $skipped,
                'tasks' => $assigned,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('assign', $teamId, $exception);
        }
    }

    public function listTeamTasks(string $teamId, array $filters = []): array
    {
        try {
            $query = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile'])
                ->where('team_id', $teamId)
                ->orderByRaw("FIELD(status, 'draft', 'assigned', 'in_progress', 'completed', 'dismissed')")
                ->orderByDesc('priority')
                ->orderBy('due_window')
                ->orderByDesc('updated_at');

            $statuses = $this->arrayFilter($filters['status'] ?? $filters['statuses'] ?? null);
            if (! empty($statuses)) {
                $query->whereIn('status', $statuses);
            }

            if (! empty($filters['player_id'])) {
                $query->where('assigned_to_player_id', (string) $filters['player_id']);
            }

            if (! empty($filters['task_type'])) {
                $query->where('task_type', (string) $filters['task_type']);
            }

            $tasks = $query->get()->map(fn (BenchmarkCollectionTask $task) => $this->serializeTask($task))->values()->all();

            return $this->result('list', [
                'team_id' => $teamId,
                'task_count' => count($tasks),
                'counts_by_status' => collect($tasks)->countBy('status')->all(),
                'tasks' => $tasks,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('list', $teamId, $exception);
        }
    }

    public function markTaskComplete(string $taskId, array $payload = []): array
    {
        try {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task) {
                return $this->result('complete', [
                    'task_id' => $taskId,
                    'updated_count' => 0,
                    'error' => 'task_not_found',
                    'task' => null,
                ], false);
            }

            $task->status = BenchmarkCollectionTask::STATUS_COMPLETED;
            $task->completed_at = now();
            $task->payload = array_replace_recursive($task->payload ?? [], [
                'completion' => $payload,
            ]);
            $task->save();

            return $this->result('complete', [
                'task_id' => $taskId,
                'updated_count' => 1,
                'task' => $this->serializeTask($task->fresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('complete', null, $exception);
        }
    }

    public function dismissTask(string $taskId, ?string $reason = null): array
    {
        try {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task) {
                return $this->result('dismiss', [
                    'task_id' => $taskId,
                    'updated_count' => 0,
                    'error' => 'task_not_found',
                    'task' => null,
                ], false);
            }

            $task->status = BenchmarkCollectionTask::STATUS_DISMISSED;
            $task->dismissed_at = now();
            $task->payload = array_replace_recursive($task->payload ?? [], [
                'dismissal' => [
                    'reason' => $reason,
                    'dismissed_at' => now()->toIso8601String(),
                ],
            ]);
            $task->save();

            return $this->result('dismiss', [
                'task_id' => $taskId,
                'updated_count' => 1,
                'task' => $this->serializeTask($task->fresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('dismiss', null, $exception);
        }
    }

    public function serializeTask(?BenchmarkCollectionTask $task): ?array
    {
        if (! $task) {
            return null;
        }

        $player = $task->relationLoaded('assignedPlayer') ? $task->assignedPlayer : null;
        $profile = $player?->profile;
        $playerName = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? ''))) ?: null;

        return [
            'id' => $task->id,
            'team_id' => $task->team_id,
            'assigned_to_player_id' => $task->assigned_to_player_id,
            'assigned_to_player_name' => $task->payload['assigned_to_player_name'] ?? $playerName,
            'created_by_user_id' => $task->created_by_user_id,
            'source' => $task->source,
            'temporary_key' => $task->temporary_key,
            'task_type' => $task->task_type,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'due_window' => $task->due_window,
            'estimated_minutes' => $task->estimated_minutes,
            'metrics' => $task->metrics ?? [],
            'missing_fields' => $task->missing_fields ?? [],
            'instructions' => $task->instructions ?? [],
            'coach_notes' => $task->coach_notes,
            'payload' => $task->payload ?? [],
            'assigned_at' => $task->assigned_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'dismissed_at' => $task->dismissed_at?->toIso8601String(),
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    private function flattenTasks(array $tasks): array
    {
        return collect($tasks)
            ->flatMap(function ($task) {
                if (! is_array($task)) {
                    return [];
                }

                if (isset($task['tasks']) && is_array($task['tasks'])) {
                    return $task['tasks'];
                }

                return [$task];
            })
            ->filter(fn ($task) => is_array($task))
            ->values()
            ->all();
    }

    private function normalizeTaskPayload(string $teamId, array $task, ?string $createdByUserId): ?array
    {
        $taskType = trim((string) ($task['task_type'] ?? ''));
        $title = trim((string) ($task['title'] ?? ''));

        if ($taskType === '' || $title === '') {
            return null;
        }

        return [
            'team_id' => $teamId,
            'assigned_to_player_id' => $this->nullableString($task['assigned_to_player_id'] ?? null),
            'created_by_user_id' => $createdByUserId,
            'source' => $this->nullableString($task['source'] ?? null) ?? 'benchmark_collection_plan',
            'temporary_key' => $this->nullableString($task['temporary_key'] ?? null),
            'task_type' => $taskType,
            'title' => $title,
            'description' => $this->nullableString($task['description'] ?? null),
            'priority' => $this->normalizePriority((string) ($task['priority'] ?? 'medium')),
            'status' => BenchmarkCollectionTask::STATUS_DRAFT,
            'due_window' => $this->nullableString($task['due_window'] ?? null),
            'estimated_minutes' => is_numeric($task['estimated_minutes'] ?? null) ? (int) $task['estimated_minutes'] : null,
            'metrics' => $this->arrayValue($task['metrics'] ?? []),
            'missing_fields' => $this->arrayValue($task['missing_fields'] ?? []),
            'instructions' => $this->arrayValue($task['instructions'] ?? []),
            'coach_notes' => $this->nullableString($task['coach_notes'] ?? null),
            'payload' => $task,
        ];
    }

    private function existingActiveTask(string $teamId, array $payload): ?BenchmarkCollectionTask
    {
        $temporaryKey = $payload['temporary_key'] ?? null;
        if ($temporaryKey) {
            $existing = BenchmarkCollectionTask::query()
                ->where('team_id', $teamId)
                ->where('temporary_key', $temporaryKey)
                ->whereIn('status', BenchmarkCollectionTask::ACTIVE_STATUSES)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->where('task_type', $payload['task_type'])
            ->where('assigned_to_player_id', $payload['assigned_to_player_id'])
            ->whereIn('status', BenchmarkCollectionTask::ACTIVE_STATUSES)
            ->first();
    }

    private function result(string $action, array $payload, bool $ok = true): array
    {
        return [
            'ok' => $ok,
            'action' => $action,
            'generated_at' => now()->toIso8601String(),
            ...$payload,
        ];
    }

    private function errorResult(string $action, ?string $teamId, Throwable $exception): array
    {
        return $this->result($action, [
            'team_id' => $teamId,
            'error' => class_basename($exception),
            'message' => $exception->getMessage(),
            'tasks' => [],
        ], false);
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function arrayFilter(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_filter(array_map('strval', $value)));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        return in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'medium';
    }
}
