<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class BenchmarkTaskPersistenceService
{
    private const PLAYER_VISIBLE_STATUSES = [
        BenchmarkCollectionTask::STATUS_ASSIGNED,
        BenchmarkCollectionTask::STATUS_IN_PROGRESS,
        BenchmarkCollectionTask::STATUS_COMPLETED,
    ];

    private const TASK_LABELS = [
        'roster_cleanup' => 'Roster Cleanup',
        'exit_velocity_baseline' => 'Exit Velocity Baseline',
        'bullpen_baseline' => 'Bullpen Baseline',
        'long_toss_weighted_ball' => 'Long Toss / Weighted Ball',
        'strength_baseline' => 'Strength Baseline',
        'athletic_testing' => 'Athletic Testing',
        'mobility_screen' => 'Mobility Screen',
    ];

    private const PLAYER_INSTRUCTIONS = [
        'roster_cleanup' => [
            'Add or confirm your date of birth.',
            'Add or confirm your position.',
            'Add height, weight, throws, and bats.',
        ],
        'exit_velocity_baseline' => [
            'Complete an exit velocity testing round.',
            'Record average EV and max EV.',
            'Track line-drive/contact quality if available.',
        ],
        'bullpen_baseline' => [
            'Throw a tracked bullpen.',
            'Record average fastball velocity, max fastball velocity, and strike percentage.',
        ],
        'long_toss_weighted_ball' => [
            'Record max long toss distance.',
            'Record 5 oz weighted ball velocity if used by your program.',
        ],
        'strength_baseline' => [
            'Record bench, squat, deadlift, pull-ups, and pushups where appropriate.',
        ],
        'athletic_testing' => [
            'Record 40-yard dash, 60-yard dash, broad jump, and vertical jump where available.',
        ],
        'mobility_screen' => [
            'Complete shoulder, hip, and T-spine mobility checks.',
        ],
    ];

    private const PLAYER_WHY = [
        'roster_cleanup' => 'This helps FMTRX compare you to the correct age and peer group.',
        'exit_velocity_baseline' => 'This helps FMTRX measure your power and barrel profile.',
        'bullpen_baseline' => 'This helps FMTRX understand command and velocity profile.',
        'long_toss_weighted_ball' => 'This helps FMTRX understand throwing capacity and mound transfer.',
        'strength_baseline' => 'This helps FMTRX connect physical strength to baseball output.',
        'athletic_testing' => 'This helps FMTRX understand speed and explosiveness.',
        'mobility_screen' => 'This helps FMTRX identify movement limitations that may affect performance.',
    ];

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
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
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

    public function listPlayerTasks(string $playerId, array $filters = []): array
    {
        try {
            $query = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->where('assigned_to_player_id', $playerId)
                ->where('status', '!=', BenchmarkCollectionTask::STATUS_DRAFT)
                ->orderByRaw("FIELD(status, 'assigned', 'in_progress', 'completed', 'dismissed')")
                ->orderByDesc('priority')
                ->orderBy('due_window')
                ->orderByDesc('updated_at');

            if (! empty($filters['team_id'])) {
                $query->where('team_id', (string) $filters['team_id']);
            }

            $statuses = $this->arrayFilter($filters['status'] ?? $filters['statuses'] ?? null);
            if (! empty($statuses)) {
                $statuses = array_values(array_filter($statuses, fn (string $status) => $status !== BenchmarkCollectionTask::STATUS_DRAFT));
                if (! empty($statuses)) {
                    $query->whereIn('status', $statuses);
                }
            } elseif (empty($filters['include_dismissed'])) {
                $query->whereIn('status', self::PLAYER_VISIBLE_STATUSES);
            }

            if (! empty($filters['task_type'])) {
                $query->where('task_type', (string) $filters['task_type']);
            }

            $tasks = $query->get()
                ->map(fn (BenchmarkCollectionTask $task) => $this->serializePlayerTask($task))
                ->values()
                ->all();

            $active = collect($tasks)->whereIn('status', [
                BenchmarkCollectionTask::STATUS_ASSIGNED,
                BenchmarkCollectionTask::STATUS_IN_PROGRESS,
            ])->values()->all();
            $completed = collect($tasks)->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)->values()->all();

            return $this->result('player_list', [
                'player_id' => $playerId,
                'team_id' => $filters['team_id'] ?? null,
                'task_count' => count($tasks),
                'active_count' => count($active),
                'completed_count' => count($completed),
                'dismissed_count' => collect($tasks)->where('status', BenchmarkCollectionTask::STATUS_DISMISSED)->count(),
                'counts_by_status' => collect($tasks)->countBy('status')->all(),
                'active_tasks' => $active,
                'completed_tasks' => $completed,
                'tasks' => $tasks,
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('player_list', null, $exception);
        }
    }

    public function getPlayerTask(string $taskId, string $playerId): array
    {
        try {
            $task = BenchmarkCollectionTask::query()
                ->with(['assignedPlayer.profile', 'submittedBy.profile', 'reviewedBy.profile', 'promotedBy.profile'])
                ->whereKey($taskId)
                ->where('assigned_to_player_id', $playerId)
                ->where('status', '!=', BenchmarkCollectionTask::STATUS_DRAFT)
                ->first();

            return $this->result('player_show', [
                'player_id' => $playerId,
                'task_id' => $taskId,
                'task' => $this->serializePlayerTask($task),
            ], (bool) $task);
        } catch (Throwable $exception) {
            return $this->errorResult('player_show', null, $exception);
        }
    }

    public function startTask(string $taskId, array $payload = []): array
    {
        try {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task) {
                return $this->result('start', [
                    'task_id' => $taskId,
                    'updated_count' => 0,
                    'error' => 'task_not_found',
                    'task' => null,
                ], false);
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_COMPLETED) {
                return $this->invalidTransition('start', $task, 'cannot_start_completed_task');
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_DISMISSED) {
                return $this->invalidTransition('start', $task, 'cannot_start_dismissed_task');
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_DRAFT) {
                return $this->invalidTransition('start', $task, 'cannot_start_draft_task');
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_IN_PROGRESS) {
                return $this->result('start', [
                    'task_id' => $taskId,
                    'updated_count' => 0,
                    'task' => $this->serializeTask($task),
                ]);
            }

            $task->status = BenchmarkCollectionTask::STATUS_IN_PROGRESS;
            $task->payload = array_replace_recursive($task->payload ?? [], [
                'progress' => [
                    'started_at' => now()->toIso8601String(),
                    'payload' => $payload,
                ],
            ]);
            $task->save();

            return $this->result('start', [
                'task_id' => $taskId,
                'updated_count' => 1,
                'task' => $this->serializeTask($task->fresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->errorResult('start', null, $exception);
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

            if ($task->status === BenchmarkCollectionTask::STATUS_DISMISSED) {
                return $this->invalidTransition('complete', $task, 'cannot_complete_dismissed_task');
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_DRAFT) {
                return $this->invalidTransition('complete', $task, 'cannot_complete_draft_task');
            }

            if ($task->status === BenchmarkCollectionTask::STATUS_COMPLETED) {
                return $this->invalidTransition('complete', $task, 'task_already_completed');
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

            if ($task->status === BenchmarkCollectionTask::STATUS_COMPLETED) {
                return $this->invalidTransition('dismiss', $task, 'cannot_dismiss_completed_task');
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
        $submittedBy = $task->relationLoaded('submittedBy') ? $task->submittedBy : null;
        $reviewedBy = $task->relationLoaded('reviewedBy') ? $task->reviewedBy : null;
        $promotedBy = $task->relationLoaded('promotedBy') ? $task->promotedBy : null;

        return [
            'id' => $task->id,
            'team_id' => $task->team_id,
            'assigned_to_player_id' => $task->assigned_to_player_id,
            'assigned_to_player_name' => $task->payload['assigned_to_player_name'] ?? $playerName,
            'created_by_user_id' => $task->created_by_user_id,
            'source' => $task->source,
            'temporary_key' => $task->temporary_key,
            'task_type' => $task->task_type,
            'completion_mode' => $this->completionModeForTaskType((string) $task->task_type),
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => $task->status,
            'review_status' => $task->review_status,
            'submitted_by_user_id' => $task->submitted_by_user_id,
            'submitted_by_name' => $this->userName($submittedBy),
            'reviewed_by_user_id' => $task->reviewed_by_user_id,
            'reviewed_by_name' => $this->userName($reviewedBy),
            'promoted_by_user_id' => $task->promoted_by_user_id,
            'promoted_by_name' => $this->userName($promotedBy),
            'due_window' => $task->due_window,
            'estimated_minutes' => $task->estimated_minutes,
            'metrics' => $task->metrics ?? [],
            'missing_fields' => $task->missing_fields ?? [],
            'instructions' => $task->instructions ?? [],
            'coach_notes' => $task->coach_notes,
            'payload' => $task->payload ?? [],
            'submitted_payload' => $task->submitted_payload ?? [],
            'approved_payload' => $task->approved_payload ?? [],
            'promotion_status' => $task->promotion_status,
            'promotion_mode' => $task->promotion_mode,
            'promotion_result' => $task->promotion_result ?? ($task->payload['promotion'] ?? null),
            'assigned_at' => $task->assigned_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'submitted_at' => $task->submitted_at?->toIso8601String(),
            'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            'promoted_at' => $task->promoted_at?->toIso8601String(),
            'dismissed_at' => $task->dismissed_at?->toIso8601String(),
            'review_notes' => $task->review_notes,
            'rejection_reason' => $task->rejection_reason,
            'correction_message' => $task->correction_message,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    public function serializePlayerTask(?BenchmarkCollectionTask $task): ?array
    {
        $serialized = $this->serializeTask($task);
        if (! $serialized) {
            return null;
        }

        $taskType = (string) ($serialized['task_type'] ?? '');
        $instructions = $this->arrayValue($serialized['instructions'] ?? []);
        if (empty($instructions)) {
            $instructions = self::PLAYER_INSTRUCTIONS[$taskType] ?? [];
        }

        return [
            'task_id' => $serialized['id'],
            'team_id' => $serialized['team_id'],
            'title' => $serialized['title'] ?: ($serialized['payload']['title'] ?? self::TASK_LABELS[$taskType] ?? 'Benchmark Task'),
            'description' => $serialized['description'],
            'priority' => $serialized['priority'],
            'status' => $serialized['status'],
            'review_status' => $serialized['review_status'],
            'submitted_at' => $serialized['submitted_at'],
            'reviewed_at' => $serialized['reviewed_at'],
            'review_notes' => $serialized['review_notes'],
            'rejection_reason' => $serialized['rejection_reason'],
            'correction_message' => $serialized['correction_message'],
            'task_type' => $taskType,
            'task_type_label' => self::TASK_LABELS[$taskType] ?? $this->headline($taskType),
            'completion_mode' => $serialized['completion_mode'],
            'due_window' => $serialized['due_window'],
            'estimated_minutes' => $serialized['estimated_minutes'],
            'metrics' => $serialized['metrics'],
            'missing_fields' => $serialized['missing_fields'],
            'instructions' => $instructions,
            'why' => self::PLAYER_WHY[$taskType] ?? null,
            'coach_notes' => $serialized['coach_notes'],
            'assigned_at' => $serialized['assigned_at'],
            'completed_at' => $serialized['completed_at'],
            'dismissed_at' => $serialized['dismissed_at'],
            'updated_at' => $serialized['updated_at'],
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

    private function invalidTransition(string $action, BenchmarkCollectionTask $task, string $reason): array
    {
        return $this->result($action, [
            'task_id' => $task->id,
            'updated_count' => 0,
            'error' => $reason,
            'task' => $this->serializeTask($task),
        ], false);
    }

    private function completionModeForTaskType(string $taskType): string
    {
        return match ($taskType) {
            'roster_cleanup',
            'strength_baseline',
            'athletic_testing',
            'mobility_screen' => 'inline_form',
            'exit_velocity_baseline',
            'bullpen_baseline',
            'long_toss_weighted_ball' => 'navigate',
            default => 'manual_confirm',
        };
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

    private function headline(string $value): string
    {
        $value = trim(str_replace(['_', '-'], ' ', $value));

        return $value !== '' ? ucwords($value) : 'Benchmark Task';
    }

    private function userName(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        $profile = $user->profile ?? null;
        $name = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? '')));

        return $name !== '' ? $name : ($user->email ?? $user->phone ?? null);
    }
}
