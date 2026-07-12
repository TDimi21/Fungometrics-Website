<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use Illuminate\Support\Facades\DB;
use Throwable;

class DailyPlanBenchmarkCompletionBridge
{
    private const ACTIVE_MATCH_STATUSES = [
        BenchmarkCollectionTask::STATUS_ASSIGNED,
        BenchmarkCollectionTask::STATUS_IN_PROGRESS,
    ];

    private const BLOCK_TASK_TYPES = [
        'roster_cleanup_block' => 'roster_cleanup',
        'exit_velocity_baseline_block' => 'exit_velocity_baseline',
        'power_development_block' => 'exit_velocity_baseline',
        'bullpen_baseline_block' => 'bullpen_baseline',
        'fastball_command_block' => 'bullpen_baseline',
        'throwing_capacity_block' => 'long_toss_weighted_ball',
        'strength_baseline_block' => 'strength_baseline',
        'athletic_testing_block' => 'athletic_testing',
        'mobility_screen_block' => 'mobility_screen',
    ];

    private const METRIC_TASK_TYPES = [
        'player_context' => 'roster_cleanup',
        'average_exit_velocity' => 'exit_velocity_baseline',
        'max_exit_velocity' => 'exit_velocity_baseline',
        'hard_hit_percentage' => 'exit_velocity_baseline',
        'line_drive_percentage' => 'exit_velocity_baseline',
        'hitter_swing_miss_percentage' => 'exit_velocity_baseline',
        'average_fastball_velocity' => 'bullpen_baseline',
        'max_fastball_velocity' => 'bullpen_baseline',
        'strike_percentage' => 'bullpen_baseline',
        'long_toss_max_distance' => 'long_toss_weighted_ball',
        'weighted_ball_5oz_velocity' => 'long_toss_weighted_ball',
        'bench_press' => 'strength_baseline',
        'squat' => 'strength_baseline',
        'deadlift' => 'strength_baseline',
        'pull_ups' => 'strength_baseline',
        'pushups' => 'strength_baseline',
        'forty_yard_dash' => 'athletic_testing',
        'sixty_yard_dash' => 'athletic_testing',
        'broad_jump' => 'athletic_testing',
        'vertical_jump' => 'athletic_testing',
        'mobility_score' => 'mobility_screen',
        'shoulder_mobility_score' => 'mobility_screen',
        'hip_mobility_score' => 'mobility_screen',
        't_spine_mobility_score' => 'mobility_screen',
    ];

    public function __construct(
        private readonly BenchmarkTaskPersistenceService $taskPersistence,
        private readonly BenchmarkTaskReviewService $taskReviewService,
        private readonly BenchmarkRefreshService $benchmarkRefreshService,
    ) {
    }

    public function handleDailyPlanProgressUpdate(string $dailyPlanId, string $playerId, array $progressPayload, ?string $userId = null): array
    {
        $result = $this->emptyResult($dailyPlanId, $playerId);
        $plan = DailyPlan::query()->find($dailyPlanId);

        if (! $plan) {
            $result['warnings'][] = 'Daily plan not found.';

            return $result;
        }

        $result['team_id'] = (string) $plan->team_id;

        if (! $this->playerAssignedToPlan($dailyPlanId, $playerId)) {
            $result['warnings'][] = 'Player is not assigned to this daily plan.';

            return $result;
        }

        $progressItems = $this->progressItems($progressPayload['items'] ?? []);
        $planItems = $this->dailyPlanItemsById($plan);

        foreach ($progressItems as $itemId => $progressItem) {
            $result['processed_items']++;

            if (! $this->isProgressItemComplete($progressItem)) {
                continue;
            }

            $planItem = $planItems[$itemId] ?? [];
            $item = $this->mergeItemPayload($planItem, $progressItem, $itemId);

            if (! $this->isBenchmarkItem($item)) {
                continue;
            }

            $result['benchmark_items_found']++;
            $completion = $this->completeBenchmarkTasksFromDailyPlanItem($dailyPlanId, $playerId, $item, $userId);

            $result['tasks_matched'] += (int) ($completion['tasks_matched'] ?? 0);
            $result['tasks_updated'] += (int) ($completion['tasks_updated'] ?? 0);
            $result['tasks_pending_review'] += (int) ($completion['tasks_pending_review'] ?? 0);
            $result['tasks_skipped'] = array_values([
                ...$result['tasks_skipped'],
                ...($completion['tasks_skipped'] ?? []),
            ]);
            $result['updated_tasks'] = array_values([
                ...$result['updated_tasks'],
                ...($completion['updated_tasks'] ?? []),
            ]);
            $result['warnings'] = array_values([
                ...$result['warnings'],
                ...($completion['warnings'] ?? []),
            ]);
            $result['evidence'][] = [
                'item_id' => $itemId,
                'item_name' => $item['name'] ?? null,
                'benchmark_item' => true,
                'completion' => $completion,
            ];
        }

        return $result;
    }

    public function completeBenchmarkTasksFromDailyPlanItem(string $dailyPlanId, string $playerId, array $item, ?string $userId = null): array
    {
        $teamId = $this->teamIdForPlan($dailyPlanId);
        $result = [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'item_id' => $item['id'] ?? null,
            'item_name' => $item['name'] ?? null,
            'tasks_matched' => 0,
            'tasks_updated' => 0,
            'tasks_pending_review' => 0,
            'tasks_skipped' => [],
            'updated_tasks' => [],
            'warnings' => [],
            'evidence' => [],
        ];

        if (! $teamId) {
            $result['warnings'][] = 'Daily plan team could not be resolved.';

            return $result;
        }

        $matches = $this->findMatchingBenchmarkTasks($teamId, $playerId, $item);
        $result['tasks_matched'] = count($matches);

        if (empty($matches)) {
            $result['tasks_skipped'][] = [
                'reason' => 'no_matching_benchmark_task',
                'item_id' => $item['id'] ?? null,
                'item_name' => $item['name'] ?? null,
                'task_type' => $this->taskTypeForItem($item),
                'metrics' => $this->metricKeys($item),
            ];

            return $result;
        }

        if (count($matches) > 1) {
            $result['warnings'][] = 'Multiple matching benchmark tasks found; updated the best match only.';
        }

        /** @var BenchmarkCollectionTask $task */
        $task = $matches[0];
        if ($task->status === BenchmarkCollectionTask::STATUS_COMPLETED) {
            $result['updated_tasks'][] = $this->taskPersistence->serializeTask($task);
            $result['tasks_skipped'][] = [
                'task_id' => (string) $task->id,
                'reason' => 'task_already_completed',
            ];

            return $result;
        }

        $submittedValues = $this->submittedValues($item);
        $submittedPayload = $this->submittedPayload($dailyPlanId, $playerId, $item, $userId, $submittedValues);
        $payload = [
            'completed_by_user_id' => $userId,
            'source' => 'daily_plan_progress',
            'daily_plan_id' => $dailyPlanId,
            'daily_plan_item_id' => $item['id'] ?? null,
            'daily_plan_item_key' => $item['item_key'] ?? $item['id'] ?? null,
            'daily_plan_item_name' => $item['name'] ?? null,
            'task_type' => (string) $task->task_type,
            'metric_values' => $submittedValues,
            'submitted_values' => $submittedValues,
            'submitted_payload' => $submittedPayload,
            'note' => $this->completionNote($item),
            'metric_keys' => $this->metricKeys($item),
            'item_payload' => $this->safeItemPayload($item),
            'completed_at' => $item['completed_at'] ?? now()->toIso8601String(),
        ];

        $completion = $this->taskPersistence->markTaskComplete((string) $task->id, $payload);
        if (! ($completion['ok'] ?? false)) {
            $result['tasks_skipped'][] = [
                'task_id' => (string) $task->id,
                'reason' => $completion['error'] ?? 'completion_failed',
                'completion' => $completion,
            ];

            return $result;
        }

        $result['tasks_updated']++;
        $result['updated_tasks'][] = $completion['task'] ?? null;

        if (empty($submittedValues)) {
            $result['warnings'][] = 'No metric values were submitted for '.$this->itemName($item).'.';
        } else {
            $review = $this->taskReviewService->recordCompletionSubmission((string) $task->id, $userId, $submittedPayload);

            if (($review['requires_review'] ?? false) === true) {
                $result['tasks_pending_review']++;
            }

            $result['updated_tasks'] = [$review['task'] ?? $completion['task'] ?? null];
            $result['evidence']['review'] = $review;
        }

        $result['evidence']['refresh'] = $this->refreshAfterCompletion((string) $task->id);

        return $result;
    }

    /**
     * @return array<int, BenchmarkCollectionTask>
     */
    public function findMatchingBenchmarkTasks(string $teamId, string $playerId, array $item): array
    {
        $directTaskId = $this->nullableString($item['benchmark_task_id'] ?? $item['task_id'] ?? null);
        if ($directTaskId) {
            $task = BenchmarkCollectionTask::query()
                ->whereKey($directTaskId)
                ->where('team_id', $teamId)
                ->where('assigned_to_player_id', $playerId)
                ->where('status', '!=', BenchmarkCollectionTask::STATUS_DRAFT)
                ->where('status', '!=', BenchmarkCollectionTask::STATUS_DISMISSED)
                ->first();

            return $task ? [$task] : [];
        }

        $temporaryKey = $this->nullableString($item['benchmark_task_temporary_key'] ?? $item['temporary_key'] ?? null);
        if ($temporaryKey) {
            $tasks = BenchmarkCollectionTask::query()
                ->where('team_id', $teamId)
                ->where('assigned_to_player_id', $playerId)
                ->where('temporary_key', $temporaryKey)
                ->whereIn('status', self::ACTIVE_MATCH_STATUSES)
                ->get();

            if ($tasks->isNotEmpty()) {
                return $tasks->all();
            }
        }

        $taskType = $this->taskTypeForItem($item);
        $metrics = $this->metricKeys($item);
        if (! $taskType && empty($metrics)) {
            return [];
        }

        $query = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->where('assigned_to_player_id', $playerId)
            ->whereIn('status', self::ACTIVE_MATCH_STATUSES);

        if ($taskType) {
            $query->where('task_type', $taskType);
        }

        $tasks = $query->get()
            ->filter(function (BenchmarkCollectionTask $task) use ($metrics): bool {
                if (empty($metrics)) {
                    return true;
                }

                return count(array_intersect($metrics, $this->metricKeys($task->metrics ?? []))) > 0;
            })
            ->sortByDesc(function (BenchmarkCollectionTask $task) use ($metrics, $temporaryKey, $taskType): int {
                $score = 0;
                if ($temporaryKey && (string) $task->temporary_key === $temporaryKey) {
                    $score += 100;
                }
                if ($taskType && (string) $task->task_type === $taskType) {
                    $score += 50;
                }
                $score += count(array_intersect($metrics, $this->metricKeys($task->metrics ?? []))) * 10;
                $score += match ((string) $task->status) {
                    BenchmarkCollectionTask::STATUS_IN_PROGRESS => 5,
                    BenchmarkCollectionTask::STATUS_ASSIGNED => 3,
                    default => 0,
                };

                return $score;
            })
            ->values();

        return $tasks->all();
    }

    public function buildBridgeStatus(string $teamId, ?string $playerId = null): array
    {
        $query = BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->whereIn('status', [
                BenchmarkCollectionTask::STATUS_ASSIGNED,
                BenchmarkCollectionTask::STATUS_IN_PROGRESS,
                BenchmarkCollectionTask::STATUS_COMPLETED,
            ]);

        if ($playerId) {
            $query->where('assigned_to_player_id', $playerId);
        }

        $tasks = $query->get();

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => $playerId,
            'assigned_or_active_tasks' => $tasks->whereIn('status', self::ACTIVE_MATCH_STATUSES)->count(),
            'completed_tasks' => $tasks->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)->count(),
            'pending_review_tasks' => $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)->count(),
            'counts_by_status' => $tasks->countBy('status')->all(),
            'counts_by_review_status' => $tasks->filter(fn (BenchmarkCollectionTask $task) => $task->review_status)->countBy('review_status')->all(),
        ];
    }

    public function inspectDailyPlanProgress(string $dailyPlanId, string $playerId): array
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        $progress = DB::table('daily_plan_progress')
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        $items = [];
        if ($progress && is_string($progress->items)) {
            $decoded = json_decode($progress->items, true);
            $items = is_array($decoded) ? $decoded : [];
        } elseif ($progress && is_array($progress->items ?? null)) {
            $items = $progress->items;
        }

        $progressItems = $this->progressItems($items);
        $planItems = $plan ? $this->dailyPlanItemsById($plan) : [];
        $completed = [];
        $benchmark = [];

        foreach ($progressItems as $itemId => $progressItem) {
            if (! $this->isProgressItemComplete($progressItem)) {
                continue;
            }

            $item = $this->mergeItemPayload($planItems[$itemId] ?? [], $progressItem, $itemId);
            $completed[] = $item;
            if ($this->isBenchmarkItem($item)) {
                $submittedValues = $this->submittedValues($item);
                $benchmark[] = [
                    'item' => $item,
                    'metric_values' => $submittedValues,
                    'submitted_payload_preview' => $this->submittedPayload($dailyPlanId, $playerId, $item, $playerId, $submittedValues),
                    'matches' => $plan ? array_map(
                        fn (BenchmarkCollectionTask $task): array => $this->taskPersistence->serializeTask($task) ?? [],
                        $this->findMatchingBenchmarkTasks((string) $plan->team_id, $playerId, $item)
                    ) : [],
                ];
            }
        }

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'team_id' => $plan?->team_id,
            'daily_plan_found' => (bool) $plan,
            'progress_found' => (bool) $progress,
            'completed_items_count' => count($completed),
            'benchmark_items_count' => count($benchmark),
            'benchmark_items' => $benchmark,
        ];
    }

    private function emptyResult(string $dailyPlanId, string $playerId): array
    {
        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'processed_items' => 0,
            'benchmark_items_found' => 0,
            'tasks_matched' => 0,
            'tasks_updated' => 0,
            'tasks_pending_review' => 0,
            'tasks_skipped' => [],
            'updated_tasks' => [],
            'warnings' => [],
            'evidence' => [],
        ];
    }

    private function dailyPlanItemsById(DailyPlan $plan): array
    {
        $items = [];
        foreach (($plan->buckets ?? []) as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            foreach (($bucket['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $items[$id] = [
                    ...$item,
                    'bucket_type' => $bucket['type'] ?? null,
                    'bucket_title' => $bucket['title'] ?? null,
                    'bucket_note' => $bucket['note'] ?? null,
                ];
            }
        }

        return $items;
    }

    private function progressItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $id = (string) ($value['id'] ?? $key);
                $normalized[$id] = $value;
            }
        }

        return $normalized;
    }

    private function mergeItemPayload(array $planItem, array $progressItem, string $itemId): array
    {
        return [
            ...$planItem,
            ...$progressItem,
            'id' => (string) ($planItem['id'] ?? $progressItem['id'] ?? $itemId),
        ];
    }

    private function isProgressItemComplete(array $item): bool
    {
        return filter_var($item['done'] ?? $item['completed'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function isBenchmarkItem(array $item): bool
    {
        $source = $this->token($item['source'] ?? null);
        $tags = collect($item['tags'] ?? [])
            ->map(fn ($tag): string => $this->token($tag))
            ->all();

        return in_array($source, ['coach_action_practice_plan', 'benchmark_collection_plan', 'benchmark_generated', 'benchmark-generated'], true)
            || in_array('benchmark_generated', $tags, true)
            || in_array('benchmark-generated', $tags, true)
            || in_array('coach_action_practice_plan', $tags, true)
            || ! empty($this->metricKeys($item))
            || ! empty($item['benchmark_task_type'])
            || ! empty($item['benchmark_task_temporary_key']);
    }

    private function taskTypeForItem(array $item): ?string
    {
        $direct = $this->nullableString($item['benchmark_task_type'] ?? $item['task_type'] ?? null);
        if ($direct) {
            return $direct;
        }

        $temporaryKey = $this->nullableString($item['benchmark_task_temporary_key'] ?? $item['temporary_key'] ?? null);
        if ($temporaryKey && isset(self::BLOCK_TASK_TYPES[$temporaryKey])) {
            return self::BLOCK_TASK_TYPES[$temporaryKey];
        }

        foreach ($this->metricKeys($item) as $metric) {
            if (isset(self::METRIC_TASK_TYPES[$metric])) {
                return self::METRIC_TASK_TYPES[$metric];
            }
        }

        return null;
    }

    private function metricKeys(mixed $value): array
    {
        $raw = [];
        if ($value instanceof BenchmarkCollectionTask) {
            $raw = $value->metrics ?? [];
        } elseif (is_array($value)) {
            $raw = $this->isListArray($value)
                ? $value
                : [
                    ...$this->arrayValue($value['relatedMetrics'] ?? []),
                    ...$this->arrayValue($value['related_metrics'] ?? []),
                    ...$this->arrayValue($value['metrics_to_collect'] ?? []),
                    ...$this->arrayValue($value['metricsToCollect'] ?? []),
                    ...$this->arrayValue($value['metrics'] ?? []),
                ];
        }

        return collect($raw)
            ->map(fn ($metric): string => is_array($metric)
                ? (string) ($metric['metric_key'] ?? $metric['key'] ?? $metric['name'] ?? '')
                : (string) $metric)
            ->map(fn (string $metric): string => strtolower(trim($metric)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function submittedValues(array $item): array
    {
        $values = [];
        foreach (['metric_values', 'metricValues', 'actuals', 'results', 'values', 'submitted_values'] as $key) {
            if (is_array($item[$key] ?? null)) {
                $values = array_replace($values, $item[$key]);
            }
        }

        return collect($values)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function safeItemPayload(array $item): array
    {
        return array_intersect_key($item, array_flip([
            'id',
            'name',
            'source',
            'tags',
            'relatedMetrics',
            'related_metrics',
            'metrics_to_collect',
            'metricsToCollect',
            'metrics',
            'benchmark_task_id',
            'benchmark_task_type',
            'benchmark_task_temporary_key',
            'bucket',
            'bucket_type',
            'bucket_title',
            'done',
            'completed',
            'completed_at',
            'note',
            'completion_note',
            'player_note',
            'metric_values',
            'metricValues',
            'actuals',
            'results',
            'values',
            'submitted_values',
            'submitted_at',
            'item_key',
            'required_fields',
        ]));
    }

    private function submittedPayload(string $dailyPlanId, string $playerId, array $item, ?string $userId, array $submittedValues): array
    {
        $relatedMetrics = $this->metricKeys($item);

        return [
            'source' => 'daily_plan_progress',
            'daily_plan_id' => $dailyPlanId,
            'daily_plan_item_id' => $item['id'] ?? null,
            'daily_plan_item_key' => $item['item_key'] ?? $item['id'] ?? null,
            'daily_plan_item_name' => $item['name'] ?? null,
            'player_id' => $playerId,
            'submitted_by_user_id' => $userId,
            'submitted_at' => now()->toIso8601String(),
            'metric_values' => $submittedValues,
            'submitted_values' => $submittedValues,
            'actuals' => $submittedValues,
            'note' => $this->completionNote($item),
            'related_metrics' => $relatedMetrics,
            'metric_keys' => $relatedMetrics,
        ];
    }

    private function completionNote(array $item): ?string
    {
        return $this->nullableString(
            $item['completion_note']
                ?? $item['player_note']
                ?? $item['result_note']
                ?? $item['note']
                ?? null
        );
    }

    private function refreshAfterCompletion(string $taskId): array
    {
        try {
            return $this->benchmarkRefreshService->refreshAfterTaskCompletion($taskId);
        } catch (Throwable $exception) {
            return [
                'task_id' => $taskId,
                'refreshed_at' => now()->toIso8601String(),
                'refresh_status' => 'failed',
                'changed_signals' => [],
                'warnings' => [$exception->getMessage()],
            ];
        }
    }

    private function teamIdForPlan(string $dailyPlanId): ?string
    {
        $teamId = DailyPlan::query()->whereKey($dailyPlanId)->value('team_id');

        return $teamId ? (string) $teamId : null;
    }

    private function playerAssignedToPlan(string $dailyPlanId, string $playerId): bool
    {
        return DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->exists();
    }

    private function itemName(array $item): string
    {
        return (string) ($item['name'] ?? $item['id'] ?? 'daily plan item');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function token(mixed $value): string
    {
        return strtolower(str_replace(' ', '_', trim((string) ($value ?? ''))));
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function isListArray(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
