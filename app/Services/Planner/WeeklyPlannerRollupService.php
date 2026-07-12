<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\BenchmarkCollectionTask;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use App\Models\Profile;
use App\Services\Intelligence\DecisionEngine;
use App\Services\Intelligence\TeamBenchmarkProfileService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class WeeklyPlannerRollupService
{
    public function __construct(
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
        private readonly DecisionEngine $decisionEngine,
    ) {
    }

    public function buildTeamWeeklyRollup(string $teamId, array $options = []): array
    {
        [$start, $end] = $this->dateWindow($options);
        $includePlayers = $this->bool($options['include_players'] ?? true);
        $includeBenchmarkIntelligence = $this->bool($options['include_benchmark_intelligence'] ?? true);
        $warnings = [];

        try {
            $plans = $this->plansForWindow($teamId, $start, $end);
            $planExecution = $this->buildPlanExecutionSummaryFromPlans($teamId, $start, $end, $plans);
            $tasks = $this->tasksForWindow($teamId, $start, $end, $plans->pluck('id')->map(fn ($id): string => (string) $id)->all());
            $benchmarkCollection = $this->benchmarkCollectionSummaryFromTasks($teamId, $start, $end, $tasks);
            $reviewSummary = $this->reviewSummaryFromTasks($tasks);
            $trustedDataSummary = $this->trustedDataSummaryFromTasks($tasks);
            $playerRollups = $includePlayers
                ? $this->playerRollupsFromPlans($teamId, $plans, $tasks)
                : [];
            $playerCompletionSummary = $this->playerCompletionSummary($playerRollups);
            $intelligenceChanges = $includeBenchmarkIntelligence
                ? $this->buildIntelligenceChanges($teamId, $options, $warnings)
                : $this->emptyIntelligenceChanges();
            $weeklySummary = [
                'plan_execution_summary' => $planExecution,
                'player_completion_summary' => $playerCompletionSummary,
                'benchmark_collection_summary' => $benchmarkCollection,
                'review_summary' => $reviewSummary,
                'trusted_data_summary' => $trustedDataSummary,
                'intelligence_changes' => $intelligenceChanges,
                'player_rollups' => $playerRollups,
            ];
            $recommendations = $this->buildNextWeekRecommendations($teamId, $weeklySummary);
            $status = $this->summaryStatus($planExecution, $benchmarkCollection, $playerRollups, $warnings);

            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'week_label' => $this->weekLabel($start, $end),
                'summary_status' => $status,
                'plan_execution_summary' => $planExecution,
                'player_completion_summary' => $playerCompletionSummary,
                'benchmark_collection_summary' => $benchmarkCollection,
                'review_summary' => $reviewSummary,
                'trusted_data_summary' => $trustedDataSummary,
                'intelligence_changes' => $intelligenceChanges,
                'player_rollups' => $playerRollups,
                'next_week_recommendations' => $recommendations,
                'coach_summary' => $this->coachSummary($planExecution, $benchmarkCollection, $reviewSummary, $recommendations),
                'warnings' => array_values(array_unique(array_filter($warnings))),
                'evidence' => [
                    'plan_count' => $plans->count(),
                    'benchmark_task_count' => $tasks->count(),
                    'include_players' => $includePlayers,
                    'include_benchmark_intelligence' => $includeBenchmarkIntelligence,
                    'source' => 'daily_plans_benchmark_tasks_current_intelligence',
                ],
            ];
        } catch (Throwable $exception) {
            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'week_label' => $this->weekLabel($start, $end),
                'summary_status' => 'failed',
                'plan_execution_summary' => $this->emptyPlanExecutionSummary(),
                'player_completion_summary' => $this->emptyPlayerCompletionSummary(),
                'benchmark_collection_summary' => $this->emptyBenchmarkCollectionSummary(),
                'review_summary' => $this->emptyReviewSummary(),
                'trusted_data_summary' => $this->emptyTrustedDataSummary(),
                'intelligence_changes' => $this->emptyIntelligenceChanges(),
                'player_rollups' => [],
                'next_week_recommendations' => [],
                'coach_summary' => 'Weekly rollup is not available yet.',
                'warnings' => [$exception->getMessage()],
                'evidence' => [
                    'exception' => class_basename($exception),
                ],
            ];
        }
    }

    public function buildPlayerWeeklyRollup(string $teamId, string $playerId, array $options = []): array
    {
        [$start, $end] = $this->dateWindow($options);
        $plans = $this->plansForWindow($teamId, $start, $end)
            ->filter(fn (DailyPlan $plan): bool => $plan->assignments->contains(fn (DailyPlanAssignment $assignment): bool => (string) $assignment->user_id === $playerId))
            ->values();
        $tasks = $this->tasksForWindow($teamId, $start, $end, $plans->pluck('id')->map(fn ($id): string => (string) $id)->all())
            ->filter(fn (BenchmarkCollectionTask $task): bool => (string) $task->assigned_to_player_id === $playerId)
            ->values();
        $rollups = $this->playerRollupsFromPlans($teamId, $plans, $tasks);
        $row = collect($rollups)->firstWhere('player_id', $playerId) ?? $this->emptyPlayerRollup($playerId, $this->playerName($playerId));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => $playerId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'week_label' => $this->weekLabel($start, $end),
            'summary_status' => empty($row['plans_assigned']) ? 'empty' : 'complete',
            'player_rollup' => $row,
            'warnings' => [],
            'evidence' => [
                'plans_scoped_to_player' => $plans->count(),
                'benchmark_tasks_scoped_to_player' => $tasks->count(),
            ],
        ];
    }

    public function buildPlanExecutionSummary(string $teamId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();

        return $this->buildPlanExecutionSummaryFromPlans($teamId, $start, $end, $this->plansForWindow($teamId, $start, $end));
    }

    public function buildBenchmarkCollectionSummary(string $teamId, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->endOfDay();
        $plans = $this->plansForWindow($teamId, $start, $end);

        return $this->benchmarkCollectionSummaryFromTasks($teamId, $start, $end, $this->tasksForWindow($teamId, $start, $end, $plans->pluck('id')->map(fn ($id): string => (string) $id)->all()));
    }

    public function buildNextWeekRecommendations(string $teamId, array $weeklySummary = []): array
    {
        $recommendations = [];
        $plan = $weeklySummary['plan_execution_summary'] ?? [];
        $players = $weeklySummary['player_completion_summary'] ?? [];
        $benchmark = $weeklySummary['benchmark_collection_summary'] ?? [];
        $review = $weeklySummary['review_summary'] ?? [];
        $trusted = $weeklySummary['trusted_data_summary'] ?? [];
        $intelligence = $weeklySummary['intelligence_changes'] ?? [];

        if ((int) ($review['pending_review_count'] ?? 0) > 0) {
            $recommendations[] = $this->recommendation(
                'Review Pending Benchmark Submissions',
                'high',
                ((int) $review['pending_review_count']).' submitted benchmark result(s) are still waiting for coach review.',
                'Review queue block',
                $this->pendingPlayers($review),
                [],
                12,
                'weekly_rollup',
            );
        }

        if (! empty($players['players_needing_follow_up'])) {
            $recommendations[] = $this->recommendation(
                'Follow Up With Missed Work',
                'medium',
                count($players['players_needing_follow_up']).' player(s) missed or partially completed assigned work.',
                'Player reminder and makeup block',
                array_slice($players['players_needing_follow_up'], 0, 8),
                [],
                10,
                'weekly_rollup',
            );
        }

        $topGaps = Arr::wrap($benchmark['top_missing_metrics_remaining'] ?? []);
        if (! empty($topGaps)) {
            $topGap = $topGaps[0];
            $recommendations[] = $this->recommendation(
                'Collect '.$this->displayName((string) ($topGap['metric_key'] ?? $topGap['display_name'] ?? 'Benchmark Baselines')),
                'medium',
                ($topGap['display_name'] ?? 'Benchmark data').' remains one of the largest data gaps after this week.',
                $this->blockForMetric((string) ($topGap['metric_key'] ?? 'benchmark')),
                [],
                array_values(array_filter([(string) ($topGap['metric_key'] ?? '')])),
                18,
                'team_benchmark_profile',
            );
        }

        if ((int) ($trusted['trusted_values_added'] ?? 0) > 0) {
            $recommendations[] = $this->recommendation(
                'Build Next Plan From Approved Data',
                'medium',
                ((int) $trusted['trusted_values_added']).' trusted value(s) were approved or promoted this week.',
                'Use updated FMTRX intelligence to set next-week priorities',
                [],
                Arr::wrap($trusted['metrics_improved'] ?? []),
                15,
                'weekly_rollup',
            );
        }

        $focus = $intelligence['primary_focus_after'] ?? null;
        if ($focus) {
            $recommendations[] = $this->recommendation(
                'Prioritize '.$focus,
                'medium',
                'Current FMTRX decision brief lists '.$focus.' as the next team focus.',
                $focus.' block',
                [],
                [],
                20,
                'decision_brief',
            );
        }

        if (empty($recommendations)) {
            $recommendations[] = $this->recommendation(
                ((int) ($plan['plans_published'] ?? 0) > 0) ? 'Keep Next Week Consistent' : 'Create Next Week Plan',
                'low',
                ((int) ($plan['plans_published'] ?? 0) > 0)
                    ? 'This week has no urgent review or collection blockers.'
                    : 'No daily plans were published in the selected week.',
                'Daily Planner setup block',
                [],
                [],
                15,
                'weekly_rollup',
            );
        }

        return collect($recommendations)
            ->unique(fn (array $row): string => (string) ($row['title'] ?? ''))
            ->values()
            ->take(6)
            ->all();
    }

    public function buildNextWeekPlanDraft(string $teamId, array $options = []): array
    {
        return app(NextWeekPlanGeneratorService::class)->generateForTeam($teamId, $options);
    }

    /**
     * @return Collection<int, DailyPlan>
     */
    private function plansForWindow(string $teamId, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return DailyPlan::query()
            ->with(['assignments.user.profile', 'progress', 'revisions'])
            ->where('team_id', $teamId)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    ->orWhereBetween('published_at', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();
    }

    private function buildPlanExecutionSummaryFromPlans(string $teamId, CarbonImmutable $start, CarbonImmutable $end, Collection $plans): array
    {
        if ($plans->isEmpty()) {
            return $this->emptyPlanExecutionSummary();
        }

        $planRows = $plans
            ->map(fn (DailyPlan $plan): array => $this->planRow($plan))
            ->values();

        return [
            'plans_created' => $plans->count(),
            'plans_published' => $plans->where('status', 'published')->count(),
            'plans_completed' => $planRows->filter(fn (array $row): bool => (int) ($row['assigned_count'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) >= 100.0)->count(),
            'plans_dismissed' => $plans->where('status', 'dismissed')->count(),
            'total_assigned_players' => (int) $planRows->sum('assigned_count'),
            'total_completed_assignments' => (int) $planRows->sum('completed_count'),
            'average_completion_percentage' => $this->average($planRows->pluck('completion_percentage')->all()),
            'total_planned_minutes' => (int) $planRows->sum('planned_minutes'),
            'total_completed_minutes_estimate' => (int) $planRows->sum('completed_minutes_estimate'),
            'benchmark_generated_plan_count' => $planRows->where('benchmark_generated', true)->count(),
            'plans' => $planRows->all(),
        ];
    }

    private function planRow(DailyPlan $plan): array
    {
        $assignments = $plan->assignments;
        $progressByUser = $plan->progress->keyBy(fn (DailyPlanProgress $progress): string => (string) $progress->user_id);
        $totalItems = $this->countPlanItems(is_array($plan->buckets) ? $plan->buckets : []);
        $planMinutes = $this->planMinutes($plan, $totalItems);
        $completedCount = 0;
        $completionPercentages = [];
        $completedMinutes = 0;

        foreach ($assignments as $assignment) {
            $progress = $progressByUser->get((string) $assignment->user_id);
            $actualTotal = max($totalItems, $this->progressItemCount($progress));
            $completedItems = $this->completedPlanItemCount($progress);
            $pct = $actualTotal > 0 ? round(($completedItems / $actualTotal) * 100, 1) : 0.0;
            $completionPercentages[] = $pct;
            $completedMinutes += (int) round($planMinutes * ($pct / 100));
            if ($actualTotal > 0 && $completedItems >= $actualTotal) {
                $completedCount++;
            }
        }

        return [
            'daily_plan_id' => (string) $plan->id,
            'title' => $plan->name,
            'status' => $plan->status,
            'scheduled_for' => $plan->date?->format('Y-m-d'),
            'assigned_count' => $assignments->count(),
            'completed_count' => $completedCount,
            'completion_percentage' => $this->average($completionPercentages),
            'benchmark_blocks_count' => $this->benchmarkBlocksCount(is_array($plan->buckets) ? $plan->buckets : []),
            'submitted_metric_count' => 0,
            'pending_review_count' => 0,
            'planned_minutes' => $planMinutes,
            'completed_minutes_estimate' => $completedMinutes,
            'benchmark_generated' => $this->hasBenchmarkGenerated(is_array($plan->buckets) ? $plan->buckets : []),
        ];
    }

    /**
     * @return Collection<int, BenchmarkCollectionTask>
     */
    private function tasksForWindow(string $teamId, CarbonImmutable $start, CarbonImmutable $end, array $planIds = []): Collection
    {
        return BenchmarkCollectionTask::query()
            ->where('team_id', $teamId)
            ->get()
            ->filter(fn (BenchmarkCollectionTask $task): bool => $this->taskInWindow($task, $start, $end, $planIds))
            ->values();
    }

    private function benchmarkCollectionSummaryFromTasks(string $teamId, CarbonImmutable $start, CarbonImmutable $end, Collection $tasks): array
    {
        if ($tasks->isEmpty()) {
            return $this->emptyBenchmarkCollectionSummary();
        }

        $submitted = 0;
        $approved = 0;
        $rejected = 0;
        $corrections = 0;
        $trusted = 0;
        $metricRows = [];

        foreach ($tasks as $task) {
            $submittedValues = $this->submittedMetricValues($task);
            $approvedValues = $this->approvedMetricValues($task);
            $trustedValues = $this->trustedPayloadValues($task);
            $submitted += count($submittedValues);

            if ($task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED) {
                $approved += count($approvedValues);
            }
            if ($task->review_status === BenchmarkCollectionTask::REVIEW_REJECTED) {
                $rejected += count($submittedValues);
            }
            if ($task->review_status === BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED) {
                $corrections += count($submittedValues);
            }
            if ($this->taskIsPromoted($task)) {
                $trusted += count($trustedValues);
            }

            foreach ($submittedValues as $metricKey => $value) {
                $key = (string) $metricKey;
                $metricRows[$key] ??= [
                    'metric_key' => $key,
                    'display_name' => $this->displayName($key),
                    'category' => $this->metricCategory($key),
                    'submitted_count' => 0,
                    'approved_count' => 0,
                    'trusted_count' => 0,
                    'players' => [],
                ];
                $metricRows[$key]['submitted_count']++;
                if (array_key_exists($key, $approvedValues) && $task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED) {
                    $metricRows[$key]['approved_count']++;
                }
                if (array_key_exists($key, $trustedValues) && $this->taskIsPromoted($task)) {
                    $metricRows[$key]['trusted_count']++;
                }
                $metricRows[$key]['players'][(string) $task->assigned_to_player_id] = [
                    'player_id' => (string) $task->assigned_to_player_id,
                    'player_name' => $this->playerName((string) $task->assigned_to_player_id),
                ];
            }
        }

        return [
            'benchmark_items_assigned' => $tasks->count(),
            'benchmark_items_completed' => $tasks->where('status', BenchmarkCollectionTask::STATUS_COMPLETED)->count(),
            'metric_values_submitted' => $submitted,
            'metric_values_approved' => $approved,
            'metric_values_rejected' => $rejected,
            'metric_values_correction_requested' => $corrections,
            'trusted_values_promoted' => $trusted,
            'metrics_collected' => collect($metricRows)
                ->map(function (array $row): array {
                    $row['players'] = array_values($row['players']);

                    return $row;
                })
                ->sortByDesc('submitted_count')
                ->values()
                ->all(),
            'top_missing_metrics_remaining' => $this->topMissingMetrics($teamId),
        ];
    }

    private function reviewSummaryFromTasks(Collection $tasks): array
    {
        $pending = $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_PENDING)->values();

        return [
            'pending_review_count' => $pending->count(),
            'approved_count' => $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_APPROVED)->count(),
            'rejected_count' => $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_REJECTED)->count(),
            'correction_requested_count' => $tasks->where('review_status', BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED)->count(),
            'oldest_pending_at' => $pending
                ->map(fn (BenchmarkCollectionTask $task): ?string => $this->dateString($task->submitted_at) ?: $this->dateString($task->completed_at))
                ->filter()
                ->sort()
                ->first(),
            'tasks_pending_review' => $pending
                ->take(10)
                ->map(fn (BenchmarkCollectionTask $task): array => [
                    'task_id' => (string) $task->id,
                    'player_id' => (string) $task->assigned_to_player_id,
                    'player_name' => $this->playerName((string) $task->assigned_to_player_id),
                    'title' => $task->title,
                    'submitted_at' => $this->dateString($task->submitted_at),
                    'submitted_values_summary' => $this->submittedValuesSummary($task),
                ])
                ->values()
                ->all(),
        ];
    }

    private function trustedDataSummaryFromTasks(Collection $tasks): array
    {
        $promoted = $tasks
            ->filter(fn (BenchmarkCollectionTask $task): bool => $this->taskIsPromoted($task))
            ->values();
        $metrics = $promoted
            ->flatMap(fn (BenchmarkCollectionTask $task): array => array_keys($this->trustedPayloadValues($task)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'trusted_values_added' => (int) $promoted->sum(fn (BenchmarkCollectionTask $task): int => count($this->trustedPayloadValues($task))),
            'players_improved' => $promoted->pluck('assigned_to_player_id')->filter()->unique()->count(),
            'metrics_improved' => $metrics,
            'last_promotion_at' => $promoted
                ->map(fn (BenchmarkCollectionTask $task): ?string => $this->dateString($task->promoted_at))
                ->filter()
                ->sort()
                ->last(),
            'promotion_warnings' => $promoted
                ->flatMap(fn (BenchmarkCollectionTask $task): array => Arr::wrap(Arr::get($task->promotion_result ?? [], 'warnings', [])))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function playerRollupsFromPlans(string $teamId, Collection $plans, Collection $tasks): array
    {
        $rows = [];
        $tasksByPlayer = $tasks->groupBy(fn (BenchmarkCollectionTask $task): string => (string) $task->assigned_to_player_id);

        foreach ($plans as $plan) {
            $totalItems = $this->countPlanItems(is_array($plan->buckets) ? $plan->buckets : []);
            $progressByUser = $plan->progress->keyBy(fn (DailyPlanProgress $progress): string => (string) $progress->user_id);

            foreach ($plan->assignments as $assignment) {
                $playerId = (string) $assignment->user_id;
                $rows[$playerId] ??= $this->emptyPlayerRollup($playerId, $this->assignmentPlayerName($assignment));
                $progress = $progressByUser->get($playerId);
                $actualTotal = max($totalItems, $this->progressItemCount($progress));
                $completedItems = $this->completedPlanItemCount($progress);
                $missed = max(0, $actualTotal - $completedItems);

                $rows[$playerId]['plans_assigned']++;
                if ($actualTotal > 0 && $completedItems >= $actualTotal) {
                    $rows[$playerId]['plans_completed']++;
                }
                $rows[$playerId]['completed_items'] += $completedItems;
                $rows[$playerId]['total_items'] += $actualTotal;
                if ($missed > 0) {
                    $rows[$playerId]['missed_items'][] = [
                        'daily_plan_id' => (string) $plan->id,
                        'title' => $plan->name,
                        'missed_count' => $missed,
                    ];
                }
            }
        }

        foreach ($tasksByPlayer as $playerId => $playerTasks) {
            $id = (string) $playerId;
            if ($id === '') {
                continue;
            }
            $rows[$id] ??= $this->emptyPlayerRollup($id, $this->playerName($id));
            foreach ($playerTasks as $task) {
                $submittedValues = $this->submittedMetricValues($task);
                $approvedValues = $task->review_status === BenchmarkCollectionTask::REVIEW_APPROVED ? $this->approvedMetricValues($task) : [];
                $rows[$id]['benchmark_values_submitted'] += count($submittedValues);
                $rows[$id]['benchmark_values_approved'] += count($approvedValues);
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_PENDING) {
                    $rows[$id]['pending_review_count']++;
                }
                if ($task->review_status === BenchmarkCollectionTask::REVIEW_CORRECTION_REQUESTED) {
                    $rows[$id]['correction_requested_count']++;
                }
                if ($this->taskIsPromoted($task)) {
                    $rows[$id]['trusted_metrics_added'] = array_values(array_unique([
                        ...$rows[$id]['trusted_metrics_added'],
                        ...array_keys($this->trustedPayloadValues($task)),
                    ]));
                }
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['completion_percentage'] = $this->percent((int) $row['completed_items'], (int) $row['total_items']);
                $row['next_recommended_action'] = $this->playerNextAction($row);
                unset($row['completed_items'], $row['total_items']);

                return $row;
            })
            ->sortBy('player_name')
            ->values()
            ->all();
    }

    private function playerCompletionSummary(array $playerRollups): array
    {
        $rows = collect($playerRollups);

        return [
            'player_count' => $rows->count(),
            'players_completed_all' => $rows->filter(fn (array $row): bool => (float) ($row['completion_percentage'] ?? 0) >= 100.0 && (int) ($row['plans_assigned'] ?? 0) > 0)->count(),
            'players_partially_completed' => $rows->filter(fn (array $row): bool => (float) ($row['completion_percentage'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) < 100.0)->count(),
            'players_not_started' => $rows->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && (float) ($row['completion_percentage'] ?? 0) <= 0.0)->count(),
            'players_with_submitted_metrics' => $rows->filter(fn (array $row): bool => (int) ($row['benchmark_values_submitted'] ?? 0) > 0)->count(),
            'players_with_pending_review' => $rows->filter(fn (array $row): bool => (int) ($row['pending_review_count'] ?? 0) > 0)->count(),
            'players_needing_follow_up' => $rows
                ->filter(fn (array $row): bool => (int) ($row['plans_assigned'] ?? 0) > 0 && ((float) ($row['completion_percentage'] ?? 0) < 100.0 || (int) ($row['correction_requested_count'] ?? 0) > 0))
                ->map(fn (array $row): array => [
                    'player_id' => $row['player_id'],
                    'player_name' => $row['player_name'],
                    'reason' => $row['next_recommended_action'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function buildIntelligenceChanges(string $teamId, array $options, array &$warnings): array
    {
        $days = $this->days($options['days'] ?? 365);
        $teamProfile = [];
        $decision = [];

        $warnings[] = 'Exact before/after intelligence snapshots are not persisted yet. Weekly rollup uses current FMTRX intelligence as the after state.';

        try {
            $teamProfile = $this->teamBenchmarkProfileService->build($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Team benchmark profile unavailable: '.$exception->getMessage();
        }

        try {
            $decision = $this->decisionEngine->buildTeamDecisionBrief($teamId, $days);
        } catch (Throwable $exception) {
            $warnings[] = 'Decision brief unavailable: '.$exception->getMessage();
        }

        return [
            'benchmark_confidence_before' => null,
            'benchmark_confidence_after' => $teamProfile['benchmark_confidence'] ?? null,
            'data_collection_priority_before' => null,
            'data_collection_priority_after' => Arr::get($decision, 'data_collection_priority.level'),
            'primary_focus_before' => null,
            'primary_focus_after' => Arr::get($decision, 'primary_focus.title') ?? Arr::get($decision, 'primary_focus.focus'),
            'source_mix_changes' => [
                'before' => null,
                'after' => $teamProfile['source_mix'] ?? [],
            ],
            'changed_signals' => [],
        ];
    }

    private function emptyPlanExecutionSummary(): array
    {
        return [
            'plans_created' => 0,
            'plans_published' => 0,
            'plans_completed' => 0,
            'plans_dismissed' => 0,
            'total_assigned_players' => 0,
            'total_completed_assignments' => 0,
            'average_completion_percentage' => 0.0,
            'total_planned_minutes' => 0,
            'total_completed_minutes_estimate' => 0,
            'benchmark_generated_plan_count' => 0,
            'plans' => [],
        ];
    }

    private function emptyPlayerCompletionSummary(): array
    {
        return [
            'player_count' => 0,
            'players_completed_all' => 0,
            'players_partially_completed' => 0,
            'players_not_started' => 0,
            'players_with_submitted_metrics' => 0,
            'players_with_pending_review' => 0,
            'players_needing_follow_up' => [],
        ];
    }

    private function emptyBenchmarkCollectionSummary(): array
    {
        return [
            'benchmark_items_assigned' => 0,
            'benchmark_items_completed' => 0,
            'metric_values_submitted' => 0,
            'metric_values_approved' => 0,
            'metric_values_rejected' => 0,
            'metric_values_correction_requested' => 0,
            'trusted_values_promoted' => 0,
            'metrics_collected' => [],
            'top_missing_metrics_remaining' => [],
        ];
    }

    private function emptyReviewSummary(): array
    {
        return [
            'pending_review_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'correction_requested_count' => 0,
            'oldest_pending_at' => null,
            'tasks_pending_review' => [],
        ];
    }

    private function emptyTrustedDataSummary(): array
    {
        return [
            'trusted_values_added' => 0,
            'players_improved' => 0,
            'metrics_improved' => [],
            'last_promotion_at' => null,
            'promotion_warnings' => [],
        ];
    }

    private function emptyIntelligenceChanges(): array
    {
        return [
            'benchmark_confidence_before' => null,
            'benchmark_confidence_after' => null,
            'data_collection_priority_before' => null,
            'data_collection_priority_after' => null,
            'primary_focus_before' => null,
            'primary_focus_after' => null,
            'source_mix_changes' => [],
            'changed_signals' => [],
        ];
    }

    private function emptyPlayerRollup(string $playerId, string $playerName): array
    {
        return [
            'player_id' => $playerId,
            'player_name' => $playerName,
            'plans_assigned' => 0,
            'plans_completed' => 0,
            'completion_percentage' => 0.0,
            'completed_items' => 0,
            'total_items' => 0,
            'benchmark_values_submitted' => 0,
            'benchmark_values_approved' => 0,
            'pending_review_count' => 0,
            'correction_requested_count' => 0,
            'trusted_metrics_added' => [],
            'missed_items' => [],
            'next_recommended_action' => null,
        ];
    }

    private function summaryStatus(array $plan, array $benchmark, array $playerRollups, array $warnings): string
    {
        if (! empty($warnings)) {
            return ((int) ($plan['plans_created'] ?? 0) === 0 && (int) ($benchmark['benchmark_items_assigned'] ?? 0) === 0) ? 'empty' : 'partial';
        }

        if ((int) ($plan['plans_created'] ?? 0) === 0 && (int) ($benchmark['benchmark_items_assigned'] ?? 0) === 0) {
            return 'empty';
        }

        return 'complete';
    }

    private function coachSummary(array $plan, array $benchmark, array $review, array $recommendations): string
    {
        if ((int) ($plan['plans_created'] ?? 0) === 0) {
            return 'No daily plans were assigned this week.';
        }

        $parts = [
            'This week, '.(int) $plan['plans_published'].' daily plan(s) were published and the team completed '.$this->fmt($plan['average_completion_percentage'] ?? 0).'% of assigned work.',
            'FMTRX collected '.(int) $benchmark['metric_values_submitted'].' benchmark value(s), with '.(int) $benchmark['metric_values_approved'].' approved and '.(int) $review['pending_review_count'].' still pending review.',
        ];

        if ((int) ($benchmark['trusted_values_promoted'] ?? 0) > 0) {
            $parts[] = (int) $benchmark['trusted_values_promoted'].' trusted value(s) were promoted this week.';
        }

        if (! empty($recommendations[0]['title'])) {
            $parts[] = 'Next week should prioritize '.$recommendations[0]['title'].'.';
        }

        return implode(' ', $parts);
    }

    private function recommendation(string $title, string $priority, string $why, ?string $block, array $players, array $metrics, ?int $minutes, string $source): array
    {
        return [
            'title' => $title,
            'priority' => $priority,
            'why' => $why,
            'recommended_plan_block' => $block,
            'players' => $players,
            'metrics' => $metrics,
            'estimated_minutes' => $minutes,
            'source' => $source,
        ];
    }

    private function dateWindow(array $options): array
    {
        $days = $this->days($options['days'] ?? 7);
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : now()->toImmutable()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->subDays($days - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    private function days(mixed $days): int
    {
        return max(1, min(365, (int) $days ?: 7));
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    private function weekLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j').' - '.$end->format('M j, Y');
    }

    private function taskInWindow(BenchmarkCollectionTask $task, CarbonImmutable $start, CarbonImmutable $end, array $planIds): bool
    {
        $taskPlanId = $this->taskDailyPlanId($task);
        if ($taskPlanId && in_array($taskPlanId, $planIds, true)) {
            return true;
        }

        foreach ([$task->submitted_at, $task->reviewed_at, $task->promoted_at, $task->completed_at, $task->created_at, $task->updated_at] as $date) {
            if (! $date) {
                continue;
            }
            $candidate = CarbonImmutable::parse($date);
            if ($candidate->betweenIncluded($start, $end)) {
                return true;
            }
        }

        return false;
    }

    private function taskDailyPlanId(BenchmarkCollectionTask $task): ?string
    {
        foreach ([
            'daily_plan_id',
            'submitted_payload.daily_plan_id',
            'approved_payload.daily_plan_id',
            'payload.daily_plan_id',
            'payload.completion.daily_plan_id',
            'payload.progress.payload.daily_plan_id',
            'promotion_result.trusted_payload.daily_plan_id',
        ] as $path) {
            $value = Arr::get($this->taskArray($task), $path);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function submittedMetricValues(BenchmarkCollectionTask $task): array
    {
        return $this->metricValuesFromPayloads($task, ['submitted_payload', 'approved_payload', 'payload.completion']);
    }

    private function approvedMetricValues(BenchmarkCollectionTask $task): array
    {
        return $this->metricValuesFromPayloads($task, ['approved_payload']);
    }

    private function trustedPayloadValues(BenchmarkCollectionTask $task): array
    {
        foreach ([
            'promotion_result.trusted_payload.values',
            'promotion_result.trusted_payload.metric_values',
            'approved_payload.metric_values',
            'approved_payload.values',
        ] as $path) {
            $values = Arr::get($this->taskArray($task), $path);
            if (is_array($values)) {
                return collect($values)
                    ->reject(fn ($value): bool => $value === null || $value === '')
                    ->all();
            }
        }

        return [];
    }

    private function metricValuesFromPayloads(BenchmarkCollectionTask $task, array $payloadPaths): array
    {
        foreach ($payloadPaths as $base) {
            $payload = Arr::get($this->taskArray($task), $base, []);
            if (! is_array($payload)) {
                continue;
            }

            foreach (['metric_values', 'submitted_values', 'actuals', 'values', 'results'] as $key) {
                if (is_array($payload[$key] ?? null)) {
                    return collect($payload[$key])
                        ->reject(fn ($value): bool => $value === null || $value === '')
                        ->all();
                }
            }
        }

        return [];
    }

    private function submittedValuesSummary(BenchmarkCollectionTask $task): array
    {
        return collect($this->submittedMetricValues($task))
            ->map(fn ($value, string|int $key): array => [
                'key' => (string) $key,
                'label' => $this->displayName((string) $key),
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : $value,
            ])
            ->values()
            ->all();
    }

    private function taskIsPromoted(BenchmarkCollectionTask $task): bool
    {
        return in_array((string) ($task->promotion_status ?? ''), [
            BenchmarkCollectionTask::PROMOTION_PROMOTED,
            BenchmarkCollectionTask::PROMOTION_PARTIAL,
        ], true);
    }

    private function taskArray(BenchmarkCollectionTask $task): array
    {
        return [
            'daily_plan_id' => $task->payload['daily_plan_id'] ?? null,
            'payload' => $task->payload ?? [],
            'submitted_payload' => $task->submitted_payload ?? [],
            'approved_payload' => $task->approved_payload ?? [],
            'promotion_result' => $task->promotion_result ?? [],
        ];
    }

    private function completedPlanItemCount(?DailyPlanProgress $progress): int
    {
        if (! $progress || ! is_array($progress->items)) {
            return 0;
        }

        return collect($progress->items)->filter(fn ($item): bool => $this->itemIsCompleted($item))->count();
    }

    private function progressItemCount(?DailyPlanProgress $progress): int
    {
        return $progress && is_array($progress->items) ? count($progress->items) : 0;
    }

    private function itemIsCompleted(mixed $item): bool
    {
        if ($item === true) {
            return true;
        }

        if (! is_array($item)) {
            return false;
        }

        return (bool) ($item['done'] ?? $item['completed'] ?? false)
            || in_array((string) ($item['status'] ?? ''), ['complete', 'completed', 'done'], true)
            || ! empty($item['completed_at'])
            || ! empty($item['actuals'])
            || ! empty($item['values'])
            || ! empty($item['metric_values']);
    }

    private function countPlanItems(array $buckets): int
    {
        return collect($buckets)->sum(fn (array $bucket): int => count(Arr::wrap($bucket['items'] ?? [])));
    }

    private function benchmarkBlocksCount(array $buckets): int
    {
        return collect($buckets)->sum(function (array $bucket): int {
            return collect(Arr::wrap($bucket['items'] ?? []))
                ->filter(fn ($item): bool => is_array($item) && $this->arrayContainsBenchmarkSignal($item))
                ->count();
        });
    }

    private function hasBenchmarkGenerated(array $buckets): bool
    {
        foreach ($buckets as $bucket) {
            if (is_array($bucket) && $this->arrayContainsBenchmarkSignal($bucket)) {
                return true;
            }
        }

        return false;
    }

    private function arrayContainsBenchmarkSignal(array $value): bool
    {
        foreach ($value as $key => $child) {
            $key = strtolower((string) $key);
            if (str_contains($key, 'benchmark') || str_contains($key, 'metric')) {
                return true;
            }
            if (is_array($child) && $this->arrayContainsBenchmarkSignal($child)) {
                return true;
            }
            if (is_string($child) && str_contains(strtolower($child), 'benchmark')) {
                return true;
            }
        }

        return false;
    }

    private function planMinutes(DailyPlan $plan, int $totalItems): int
    {
        $explicit = (int) ($plan->estimated_minutes ?? 0);

        return $explicit > 0 ? $explicit : $totalItems * 4;
    }

    private function topMissingMetrics(string $teamId): array
    {
        try {
            $profile = $this->teamBenchmarkProfileService->build($teamId, 365);
        } catch (Throwable) {
            return [];
        }

        return collect(Arr::wrap($profile['missing_metrics'] ?? []))
            ->filter(fn (array $metric): bool => (int) ($metric['missing_count'] ?? 0) > 0)
            ->sortByDesc(fn (array $metric): int => (int) ($metric['missing_count'] ?? 0))
            ->take(5)
            ->map(fn (array $metric): array => [
                'metric_key' => (string) ($metric['metric_key'] ?? ''),
                'display_name' => $this->displayName((string) ($metric['display_name'] ?? $metric['metric_key'] ?? 'Benchmark Metric')),
                'category' => (string) ($metric['category'] ?? 'benchmark'),
                'missing_count' => (int) ($metric['missing_count'] ?? 0),
                'player_count' => (int) ($metric['player_count'] ?? $metric['eligible_count'] ?? 0),
            ])
            ->values()
            ->all();
    }

    private function playerNextAction(array $row): ?string
    {
        if ((int) ($row['correction_requested_count'] ?? 0) > 0) {
            return 'Correct flagged benchmark result';
        }
        if ((int) ($row['pending_review_count'] ?? 0) > 0) {
            return 'Waiting for coach review';
        }
        if ((float) ($row['completion_percentage'] ?? 0) < 100.0 && (int) ($row['plans_assigned'] ?? 0) > 0) {
            return 'Finish missed workout items';
        }
        if (! empty($row['trusted_metrics_added'])) {
            return 'Use updated trusted data next week';
        }

        return 'Ready for next plan';
    }

    private function pendingPlayers(array $review): array
    {
        return collect(Arr::wrap($review['tasks_pending_review'] ?? []))
            ->map(fn (array $task): array => [
                'player_id' => (string) ($task['player_id'] ?? ''),
                'player_name' => (string) ($task['player_name'] ?? 'Player'),
            ])
            ->filter(fn (array $player): bool => $player['player_id'] !== '')
            ->unique('player_id')
            ->values()
            ->all();
    }

    private function blockForMetric(string $metricKey): string
    {
        return match (true) {
            str_contains($metricKey, 'exit_velocity'), str_contains($metricKey, 'hit') => 'Exit Velocity Baseline',
            str_contains($metricKey, 'fastball'), str_contains($metricKey, 'strike') => 'Bullpen Baseline',
            str_contains($metricKey, 'mobility') => 'Mobility Screen',
            str_contains($metricKey, 'bench'), str_contains($metricKey, 'squat'), str_contains($metricKey, 'deadlift') => 'Strength Baseline',
            str_contains($metricKey, 'dash'), str_contains($metricKey, 'jump') => 'Athletic Testing',
            default => 'Benchmark Baseline',
        };
    }

    private function assignmentPlayerName(DailyPlanAssignment $assignment): string
    {
        $profile = $assignment->user?->profile;
        $name = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? '')));

        return $name !== '' ? $name : 'Player '.$assignment->user_id;
    }

    private function playerName(string $playerId): string
    {
        $profile = Profile::query()->where('user_id', $playerId)->first();
        $name = trim((string) (($profile?->first_name ?? '').' '.($profile?->last_name ?? '')));

        return $name !== '' ? $name : 'Player '.$playerId;
    }

    private function metricCategory(string $key): string
    {
        return match (true) {
            str_contains($key, 'exit_velocity'), str_contains($key, 'hit'), str_contains($key, 'line_drive'), str_contains($key, 'swing_miss') => 'hitting',
            str_contains($key, 'fastball'), str_contains($key, 'strike'), str_contains($key, 'long_toss'), str_contains($key, 'weighted_ball') => 'pitching',
            str_contains($key, 'bench'), str_contains($key, 'squat'), str_contains($key, 'deadlift'), str_contains($key, 'pull'), str_contains($key, 'push') => 'strength',
            str_contains($key, 'dash'), str_contains($key, 'jump') => 'athletic',
            str_contains($key, 'mobility') => 'mobility',
            default => 'benchmark',
        };
    }

    private function displayName(string $key): string
    {
        $map = [
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
            'mobility_score' => 'Mobility Score',
        ];

        return $map[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
    }

    private function percent(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function average(array $values): float
    {
        $values = array_values(array_filter($values, fn ($value): bool => is_numeric($value)));

        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 1);
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) $value, 1);
    }

    private function dateString(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        return CarbonImmutable::parse($date)->toIso8601String();
    }
}
