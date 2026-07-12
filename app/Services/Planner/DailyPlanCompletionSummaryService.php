<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\Profile;
use Illuminate\Support\Collection;

class DailyPlanCompletionSummaryService
{
    public function buildPlayerSummary(string $dailyPlanId, string $playerId): array
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        if (! $plan) {
            return $this->emptyPlayerSummary($dailyPlanId, $playerId, ['Daily plan was not found.']);
        }

        $progress = DailyPlanProgress::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        $planItems = $this->planItemsById($plan);
        $progressItems = $this->progressItems($progress?->items ?? []);
        $totalItems = count($planItems);
        $completedItems = 0;
        $benchmarkItemsCompleted = 0;
        $metricValuesSubmitted = [];

        foreach ($planItems as $itemId => $planItem) {
            $progressItem = $progressItems[$itemId] ?? [];
            if (! $this->itemDone($progressItem)) {
                continue;
            }

            $completedItems++;
            $merged = array_replace_recursive($planItem, $progressItem);
            if ($this->isBenchmarkItem($merged)) {
                $benchmarkItemsCompleted++;
            }

            foreach ($this->metricValues($merged) as $key => $value) {
                $metricValuesSubmitted[] = [
                    'item_id' => $itemId,
                    'item_name' => $this->string($merged['name'] ?? 'Workout item'),
                    'metric_key' => $key,
                    'label' => $this->metricLabel($key),
                    'value' => $value,
                    'unit' => $this->metricUnit($key),
                ];
            }
        }

        $tasks = $this->dailyPlanTasks($plan, $playerId);
        $pending = $this->taskRows($tasks->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING));
        $approved = $this->taskRows($tasks->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED));
        $corrections = $this->taskRows($tasks->filter(fn (BenchmarkCollectionTask $task): bool => in_array((string) $task->review_status, [
            BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED,
            BenchmarkCollectionTask::REVIEW_REJECTED,
        ], true)));
        $coachFeedback = $this->coachFeedbackRows($tasks);

        $status = $this->summaryStatus($completedItems, $totalItems, $pending->count(), $approved->count(), $corrections->count());
        $next = $this->buildNextStepRecommendation($dailyPlanId, $playerId, [
            'status' => $status,
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'pending_review_count' => $pending->count(),
            'approved_count' => $approved->count(),
            'correction_requested_count' => $corrections->count(),
            'submitted_metric_count' => count($metricValuesSubmitted),
        ]);

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'plan_title' => $plan->name,
            'summary_status' => $status,
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'completion_percentage' => $this->percent($completedItems, $totalItems),
            'benchmark_items_completed' => $benchmarkItemsCompleted,
            'metric_values_submitted' => $metricValuesSubmitted,
            'pending_review' => $pending->values()->all(),
            'approved_results' => $approved->values()->all(),
            'corrections_requested' => $corrections->values()->all(),
            'coach_feedback' => $coachFeedback,
            'next_step' => $next['next_step'] ?? null,
            'message' => $this->playerMessage($status, count($metricValuesSubmitted)),
            'warnings' => [],
        ];
    }

    public function buildCoachSummary(string $dailyPlanId, array $options = []): array
    {
        $plan = DailyPlan::query()->find($dailyPlanId);
        if (! $plan) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'team_id' => null,
                'plan_title' => null,
                'assigned_player_count' => 0,
                'completed_player_count' => 0,
                'in_progress_player_count' => 0,
                'not_started_player_count' => 0,
                'team_completion_percentage' => 0,
                'benchmark_submissions_count' => 0,
                'pending_review_count' => 0,
                'approved_count' => 0,
                'correction_requested_count' => 0,
                'player_summaries' => [],
                'coach_next_actions' => [],
                'warnings' => ['Daily plan was not found.'],
            ];
        }

        $playerIds = DailyPlanAssignment::query()
            ->where('plan_id', $dailyPlanId)
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        $profiles = Profile::query()
            ->whereIn('user_id', $playerIds->all())
            ->get()
            ->keyBy('user_id');

        $rows = $playerIds
            ->map(function (string $playerId) use ($dailyPlanId, $profiles): array {
                $summary = $this->buildPlayerSummary($dailyPlanId, $playerId);
                $profile = $profiles->get($playerId);
                $name = trim((string) (($profile->first_name ?? '').' '.($profile->last_name ?? ''))) ?: 'Player';

                return [
                    'player_id' => $playerId,
                    'player_name' => $name,
                    'completion_percentage' => $summary['completion_percentage'],
                    'completed_items' => $summary['completed_items'],
                    'total_items' => $summary['total_items'],
                    'benchmark_values_submitted' => count($summary['metric_values_submitted'] ?? []),
                    'pending_review_count' => count($summary['pending_review'] ?? []),
                    'approved_count' => count($summary['approved_results'] ?? []),
                    'correction_requested_count' => count($summary['corrections_requested'] ?? []),
                    'last_activity_at' => $this->lastActivityAt($dailyPlanId, $playerId),
                    'next_needed_action' => $summary['next_step'],
                    'summary_status' => $summary['summary_status'],
                ];
            })
            ->values();

        $assignedCount = $rows->count();
        $completedCount = $rows->where('completion_percentage', '>=', 100)->count();
        $inProgressCount = $rows
            ->filter(fn (array $row): bool => (float) $row['completion_percentage'] > 0 && (float) $row['completion_percentage'] < 100)
            ->count();
        $notStartedCount = max(0, $assignedCount - $completedCount - $inProgressCount);
        $benchmarkSubmissions = (int) $rows->sum('benchmark_values_submitted');
        $pending = (int) $rows->sum('pending_review_count');
        $approved = (int) $rows->sum('approved_count');
        $corrections = (int) $rows->sum('correction_requested_count');

        return [
            'daily_plan_id' => $dailyPlanId,
            'team_id' => $plan->team_id,
            'plan_title' => $plan->name,
            'assigned_player_count' => $assignedCount,
            'completed_player_count' => $completedCount,
            'in_progress_player_count' => $inProgressCount,
            'not_started_player_count' => $notStartedCount,
            'team_completion_percentage' => $this->average($rows->pluck('completion_percentage')->all()),
            'benchmark_submissions_count' => $benchmarkSubmissions,
            'pending_review_count' => $pending,
            'approved_count' => $approved,
            'correction_requested_count' => $corrections,
            'player_summaries' => $rows->all(),
            'coach_next_actions' => $this->coachNextActions($pending, $corrections, $approved, $notStartedCount),
            'warnings' => [],
        ];
    }

    public function buildPlayerFeedbackStatus(string $dailyPlanId, string $playerId): array
    {
        $summary = $this->buildPlayerSummary($dailyPlanId, $playerId);

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'summary_status' => $summary['summary_status'],
            'message' => $summary['message'],
            'pending_review_count' => count($summary['pending_review'] ?? []),
            'approved_count' => count($summary['approved_results'] ?? []),
            'correction_requested_count' => count($summary['corrections_requested'] ?? []),
            'coach_feedback' => $summary['coach_feedback'] ?? [],
            'next_step' => $summary['next_step'],
        ];
    }

    public function buildNextStepRecommendation(string $dailyPlanId, string $playerId, array $context = []): array
    {
        $status = (string) ($context['status'] ?? '');
        $pending = (int) ($context['pending_review_count'] ?? 0);
        $corrections = (int) ($context['correction_requested_count'] ?? 0);
        $submitted = (int) ($context['submitted_metric_count'] ?? 0);
        $completed = (int) ($context['completed_items'] ?? 0);
        $total = (int) ($context['total_items'] ?? 0);

        $next = match (true) {
            $corrections > 0 => 'Update the results your coach flagged, then resubmit.',
            $pending > 0 => 'Wait for coach approval.',
            $status === 'approved' => 'Your coach approved the results. Keep your next session ready.',
            $completed >= $total && $total > 0 && $submitted > 0 => 'Your results were submitted. Watch for coach feedback.',
            $completed >= $total && $total > 0 => 'Workout complete. Nice work.',
            $completed > 0 => 'Keep going. Finish the remaining blocks when your coach is ready.',
            default => 'Start the first block when your coach is ready.',
        };

        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'next_step' => $next,
        ];
    }

    private function emptyPlayerSummary(string $dailyPlanId, string $playerId, array $warnings = []): array
    {
        return [
            'daily_plan_id' => $dailyPlanId,
            'player_id' => $playerId,
            'plan_title' => null,
            'summary_status' => 'not_started',
            'completed_items' => 0,
            'total_items' => 0,
            'completion_percentage' => 0,
            'benchmark_items_completed' => 0,
            'metric_values_submitted' => [],
            'pending_review' => [],
            'approved_results' => [],
            'corrections_requested' => [],
            'coach_feedback' => [],
            'next_step' => null,
            'message' => 'Completion summary is not available yet.',
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function planItemsById(DailyPlan $plan): array
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
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function progressItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $id = (string) ($value['id'] ?? $key);
            if ($id === '') {
                continue;
            }

            $normalized[$id] = $value;
        }

        return $normalized;
    }

    private function itemDone(array $item): bool
    {
        return ($item['done'] ?? false) === true || ($item['completed'] ?? false) === true || ! empty($item['completed_at']);
    }

    private function isBenchmarkItem(array $item): bool
    {
        $source = $this->token($item['source'] ?? null);
        $tags = collect($item['tags'] ?? [])->map(fn ($tag): string => $this->token($tag))->all();

        return in_array($source, ['coach_action_practice_plan', 'benchmark_collection_plan', 'benchmark-generated', 'benchmark_generated'], true)
            || count(array_intersect($tags, ['benchmark-generated', 'benchmark_generated', 'coach_action_practice_plan', 'benchmark_collection_plan'])) > 0
            || ! empty($item['relatedMetrics'] ?? $item['related_metrics'] ?? $item['metrics_to_collect'] ?? $item['metricsToCollect'] ?? $item['metrics'] ?? $item['required_fields'] ?? [])
            || ! empty($item['benchmark_task_type'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function metricValues(array $item): array
    {
        $values = [];
        foreach (['metric_values', 'actuals', 'results', 'values', 'submitted_values'] as $key) {
            if (is_array($item[$key] ?? null)) {
                $values = array_replace($values, $item[$key]);
            }
        }

        return collect($values)
            ->reject(fn ($value): bool => $value === '' || $value === null)
            ->all();
    }

    /**
     * @return Collection<int, BenchmarkCollectionTask>
     */
    private function dailyPlanTasks(DailyPlan $plan, ?string $playerId = null): Collection
    {
        $query = BenchmarkCollectionTask::query()
            ->where('team_id', (string) $plan->team_id)
            ->where('status', BenchmarkCollectionTask::STATUS_COMPLETED);

        if ($playerId) {
            $query->where('assigned_to_player_id', $playerId);
        }

        return $query->get()
            ->filter(fn (BenchmarkCollectionTask $task): bool => $this->taskBelongsToDailyPlan($task, (string) $plan->id))
            ->values();
    }

    private function taskBelongsToDailyPlan(BenchmarkCollectionTask $task, string $dailyPlanId): bool
    {
        foreach ([$task->submitted_payload, $task->approved_payload, $task->payload] as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $ids = [
                $payload['daily_plan_id'] ?? null,
                $payload['completion']['daily_plan_id'] ?? null,
                $payload['completion']['submitted_payload']['daily_plan_id'] ?? null,
                $payload['submitted_payload']['daily_plan_id'] ?? null,
            ];

            if (in_array($dailyPlanId, array_map(fn ($id): string => (string) $id, array_filter($ids)), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Collection<int, BenchmarkCollectionTask> $tasks
     * @return Collection<int, array<string, mixed>>
     */
    private function taskRows(Collection $tasks): Collection
    {
        return $tasks->map(function (BenchmarkCollectionTask $task): array {
            $payload = is_array($task->approved_payload ?? null) && ! empty($task->approved_payload)
                ? $task->approved_payload
                : (is_array($task->submitted_payload ?? null) ? $task->submitted_payload : []);

            $values = $this->taskMetricValues($task);

            return [
                'task_id' => (string) $task->id,
                'title' => $task->title,
                'task_type' => $task->task_type,
                'review_status' => $task->review_status,
                'submitted_at' => $task->submitted_at?->toIso8601String(),
                'reviewed_at' => $task->reviewed_at?->toIso8601String(),
                'metric_values' => $values,
                'metric_labels' => collect($values)
                    ->map(fn ($value, $key): string => trim($this->metricLabel((string) $key).' '.$value.' '.$this->metricUnit((string) $key)))
                    ->values()
                    ->all(),
                'note' => $task->correction_message ?: ($task->rejection_reason ?: ($task->review_notes ?: ($payload['note'] ?? null))),
            ];
        })->values();
    }

    private function coachFeedbackRows(Collection $tasks): array
    {
        return $tasks
            ->filter(fn (BenchmarkCollectionTask $task): bool => (bool) ($task->review_notes || $task->correction_message || $task->rejection_reason))
            ->map(fn (BenchmarkCollectionTask $task): array => [
                'task_id' => (string) $task->id,
                'title' => $task->title,
                'review_status' => $task->review_status,
                'message' => $task->correction_message ?: ($task->rejection_reason ?: $task->review_notes),
                'reviewed_at' => $task->reviewed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function taskMetricValues(BenchmarkCollectionTask $task): array
    {
        $payloads = [
            $task->approved_payload,
            $task->submitted_payload,
            $task->payload['completion'] ?? null,
            $task->payload['completion']['submitted_payload'] ?? null,
        ];

        $values = [];
        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            foreach (['metric_values', 'actuals', 'results', 'submitted_values', 'values'] as $key) {
                if (is_array($payload[$key] ?? null)) {
                    $values = array_replace($values, $payload[$key]);
                }
            }
        }

        return collect($values)
            ->reject(fn ($value): bool => $value === '' || $value === null)
            ->all();
    }

    private function summaryStatus(int $completedItems, int $totalItems, int $pending, int $approved, int $corrections): string
    {
        if ($corrections > 0) {
            return 'needs_correction';
        }
        if ($pending > 0) {
            return 'submitted_for_review';
        }
        if ($approved > 0) {
            return 'approved';
        }
        if ($totalItems > 0 && $completedItems >= $totalItems) {
            return 'completed';
        }
        if ($completedItems > 0) {
            return 'in_progress';
        }

        return 'not_started';
    }

    private function playerMessage(string $status, int $submittedMetricCount): string
    {
        return match ($status) {
            'needs_correction' => 'Your coach requested a correction.',
            'submitted_for_review' => 'Your submitted results are waiting for coach review.',
            'approved' => 'Your coach approved your submitted results.',
            'completed' => $submittedMetricCount > 0 ? 'Results submitted for coach review.' : 'Workout complete. Nice work.',
            'in_progress' => 'Keep going. Finish the remaining blocks when your coach is ready.',
            default => 'Start the first block when your coach is ready.',
        };
    }

    private function coachNextActions(int $pending, int $corrections, int $approved, int $notStarted): array
    {
        $actions = [];
        if ($pending > 0) {
            $actions[] = 'Review '.$pending.' submitted benchmark result'.($pending === 1 ? '' : 's').'.';
        }
        if ($corrections > 0) {
            $actions[] = 'Follow up on '.$corrections.' correction request'.($corrections === 1 ? '' : 's').'.';
        }
        if ($approved > 0) {
            $actions[] = 'Promote approved trusted benchmark values if they are ready.';
        }
        if ($notStarted > 0) {
            $actions[] = 'Send reminder to players who have not completed the workout.';
        }
        $actions[] = 'Build the next plan from updated FMTRX intelligence.';

        return $actions;
    }

    private function lastActivityAt(string $dailyPlanId, string $playerId): ?string
    {
        $progress = DailyPlanProgress::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        return $progress?->updated_at?->toIso8601String();
    }

    private function percent(int $value, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }

    private function average(array $values): float
    {
        $values = array_values(array_filter($values, fn ($value): bool => is_numeric($value)));
        if (empty($values)) {
            return 0.0;
        }

        return round(array_sum($values) / count($values), 1);
    }

    private function metricLabel(string $key): string
    {
        return [
            'average_exit_velocity' => 'Average EV',
            'max_exit_velocity' => 'Max EV',
            'hard_hit_percentage' => 'Hard-Hit %',
            'line_drive_percentage' => 'Line-Drive %',
            'hitter_swing_miss_percentage' => 'Swing/Miss %',
            'average_fastball_velocity' => 'Avg Fastball',
            'max_fastball_velocity' => 'Max Fastball',
            'strike_percentage' => 'Strike %',
            'long_toss_max_distance' => 'Long Toss Distance',
            'weighted_ball_5oz_velocity' => '5 oz Velocity',
            'bench_press' => 'Bench Press',
            'squat' => 'Squat',
            'deadlift' => 'Deadlift',
            'pull_ups' => 'Pull-Ups',
            'pushups' => 'Pushups',
            'forty_yard_dash' => '40-Yard Dash',
            'sixty_yard_dash' => '60-Yard Dash',
            'broad_jump' => 'Broad Jump',
            'vertical_jump' => 'Vertical Jump',
            'mobility_score' => 'Mobility Score',
            'shoulder_mobility_score' => 'Shoulder Mobility',
            'hip_mobility_score' => 'Hip Mobility',
            't_spine_mobility_score' => 'T-Spine Mobility',
        ][$key] ?? str($key)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function metricUnit(string $key): ?string
    {
        return match ($key) {
            'average_exit_velocity', 'max_exit_velocity', 'average_fastball_velocity', 'max_fastball_velocity', 'weighted_ball_5oz_velocity' => 'mph',
            'hard_hit_percentage', 'line_drive_percentage', 'hitter_swing_miss_percentage', 'strike_percentage' => '%',
            'long_toss_max_distance' => 'ft',
            'bench_press', 'squat', 'deadlift' => 'lb',
            'pull_ups', 'pushups' => 'reps',
            'forty_yard_dash', 'sixty_yard_dash' => 'sec',
            'broad_jump', 'vertical_jump' => 'in',
            'mobility_score', 'shoulder_mobility_score', 'hip_mobility_score', 't_spine_mobility_score' => '/100',
            default => null,
        };
    }

    private function token(mixed $value): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim((string) $value)));
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }
}
