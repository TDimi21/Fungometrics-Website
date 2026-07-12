<?php

declare(strict_types=1);

namespace App\Services\Planner;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PlayerWeeklyCompletionSummaryService
{
    public function __construct(
        private readonly PlayerWeeklyPlanService $weeklyPlanService,
        private readonly DailyPlanCompletionSummaryService $dailyPlanSummaryService,
    ) {
    }

    public function buildForPlayer(string $playerId, array $options = []): array
    {
        $range = $this->dateRange($options);
        $planRows = $this->buildPlanRows($playerId, $range['start']->toDateString(), $range['end']->toDateString());
        $benchmarkSummary = $this->buildBenchmarkSubmissionSummary($playerId, $range['start']->toDateString(), $range['end']->toDateString());
        $weeklyCompletion = $this->buildWeeklyCompletion($planRows, $benchmarkSummary);
        $reviewStatus = [
            'waiting_for_coach_review' => $benchmarkSummary['metrics_submitted_by_status']['pending_review'] ?? [],
            'approved_results' => $benchmarkSummary['metrics_submitted_by_status']['approved'] ?? [],
            'needs_correction' => $benchmarkSummary['metrics_submitted_by_status']['correction_requested'] ?? [],
            'rejected_results' => $benchmarkSummary['metrics_submitted_by_status']['rejected'] ?? [],
        ];
        $approved = $reviewStatus['approved_results'];
        $corrections = array_values([
            ...$reviewStatus['needs_correction'],
            ...$reviewStatus['rejected_results'],
        ]);
        $missedItems = $this->missedItems($planRows);
        $summary = [
            'player_id' => $playerId,
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
            'weekly_completion' => $weeklyCompletion,
            'plan_rows' => $planRows,
            'benchmark_summary' => $benchmarkSummary,
            'review_status_summary' => $reviewStatus,
            'approved_results' => $approved,
            'corrections_requested' => $corrections,
            'missed_items' => $missedItems,
        ];
        $nextStep = $this->buildPlayerNextStep($summary);
        $summaryStatus = $this->summaryStatus($weeklyCompletion, $benchmarkSummary);

        return [
            'generated_at' => now()->toIso8601String(),
            'player_id' => $playerId,
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
            'week_label' => $this->weekLabel($range['start'], $range['end']),
            'summary_status' => $summaryStatus,
            'weekly_completion' => $weeklyCompletion,
            'plan_rows' => $planRows,
            'benchmark_summary' => Arr::except($benchmarkSummary, ['metrics_submitted_by_status']),
            'review_status_summary' => $reviewStatus,
            'approved_results' => $approved,
            'corrections_requested' => $corrections,
            'missed_items' => $missedItems,
            'next_step' => $nextStep,
            'player_message' => $this->playerMessage($summaryStatus, $weeklyCompletion, $benchmarkSummary),
            'warnings' => $this->warnings($planRows),
        ];
    }

    public function buildPlanRows(string $playerId, string $startDate, string $endDate): array
    {
        $weekly = $this->weeklyPlanService->buildForPlayer($playerId, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'include_completed' => true,
        ]);

        return collect(Arr::wrap($weekly['days'] ?? []))
            ->map(function (array $day) use ($playerId): array {
                $dailyPlanId = (string) ($day['daily_plan_id'] ?? '');
                $completion = $dailyPlanId !== ''
                    ? $this->dailyPlanSummaryService->buildPlayerSummary($dailyPlanId, $playerId)
                    : [];
                $pending = count(Arr::wrap($completion['pending_review'] ?? []));
                $approved = count(Arr::wrap($completion['approved_results'] ?? []));
                $corrections = count(Arr::wrap($completion['corrections_requested'] ?? []));

                return [
                    'daily_plan_id' => $dailyPlanId,
                    'title' => (string) ($day['title'] ?? $completion['plan_title'] ?? 'Daily Plan'),
                    'scheduled_for' => $day['scheduled_for'] ?? null,
                    'day_label' => $day['day_label'] ?? null,
                    'status' => $day['status'] ?? 'unknown',
                    'completed_items' => (int) ($completion['completed_items'] ?? $day['completed_items'] ?? 0),
                    'total_items' => (int) ($completion['total_items'] ?? $day['total_items'] ?? 0),
                    'completion_percentage' => (float) ($completion['completion_percentage'] ?? $day['completion_percentage'] ?? 0),
                    'benchmark_generated' => (bool) ($day['benchmark_generated'] ?? false),
                    'benchmark_items_completed' => (int) ($completion['benchmark_items_completed'] ?? 0),
                    'submitted_metric_count' => count(Arr::wrap($completion['metric_values_submitted'] ?? [])),
                    'pending_review_count' => $pending,
                    'approved_count' => $approved,
                    'correction_requested_count' => $corrections,
                    'next_action' => $this->planNextAction($day, $completion, $pending, $approved, $corrections),
                ];
            })
            ->values()
            ->all();
    }

    public function buildBenchmarkSubmissionSummary(string $playerId, string $startDate, string $endDate): array
    {
        $weekly = $this->weeklyPlanService->buildForPlayer($playerId, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'include_completed' => true,
        ]);
        $submitted = collect();

        foreach (Arr::wrap($weekly['days'] ?? []) as $day) {
            if (! is_array($day) || empty($day['daily_plan_id'])) {
                continue;
            }

            $dailyPlanId = (string) $day['daily_plan_id'];
            $completion = $this->dailyPlanSummaryService->buildPlayerSummary($dailyPlanId, $playerId);
            $planTitle = (string) ($completion['plan_title'] ?? $day['title'] ?? 'Daily Plan');

            foreach ([
                'pending_review' => 'pending_review',
                'approved_results' => 'approved',
                'corrections_requested' => null,
            ] as $key => $forcedStatus) {
                foreach (Arr::wrap($completion[$key] ?? []) as $task) {
                    if (! is_array($task)) {
                        continue;
                    }

                    $status = $forcedStatus ?: ((string) ($task['review_status'] ?? '') === 'rejected' ? 'rejected' : 'correction_requested');
                    foreach (Arr::wrap($task['metric_values'] ?? []) as $metricKey => $value) {
                        $submitted->push($this->metricRow((string) $metricKey, $value, $dailyPlanId, $planTitle, $status, $task));
                    }
                }
            }

            foreach (Arr::wrap($completion['metric_values_submitted'] ?? []) as $metric) {
                if (! is_array($metric)) {
                    continue;
                }

                $metricKey = (string) ($metric['metric_key'] ?? '');
                if ($metricKey === '' || $this->hasTaskMetricRow($submitted, $dailyPlanId, $metricKey)) {
                    continue;
                }

                $submitted->push($this->metricRow($metricKey, $metric['value'] ?? null, $dailyPlanId, $planTitle, 'submitted', [
                    'submitted_at' => null,
                    'reviewed_at' => null,
                ]));
            }
        }

        $rows = $submitted->values();
        $byStatus = [
            'pending_review' => $rows->where('status', 'pending_review')->values()->all(),
            'approved' => $rows->where('status', 'approved')->values()->all(),
            'rejected' => $rows->where('status', 'rejected')->values()->all(),
            'correction_requested' => $rows->where('status', 'correction_requested')->values()->all(),
            'submitted' => $rows->where('status', 'submitted')->values()->all(),
        ];

        return [
            'submitted_metric_count' => $rows->count(),
            'pending_review_count' => count($byStatus['pending_review']),
            'approved_count' => count($byStatus['approved']),
            'rejected_count' => count($byStatus['rejected']),
            'correction_requested_count' => count($byStatus['correction_requested']),
            'metrics_submitted' => $rows->all(),
            'metrics_submitted_by_status' => $byStatus,
        ];
    }

    public function buildPlayerNextStep(array $summary): array
    {
        $benchmark = $summary['benchmark_summary'] ?? [];
        $planRows = collect(Arr::wrap($summary['plan_rows'] ?? []));
        $today = CarbonImmutable::now()->toDateString();

        if ((int) ($benchmark['correction_requested_count'] ?? 0) > 0 || (int) ($benchmark['rejected_count'] ?? 0) > 0) {
            $row = collect($summary['corrections_requested'] ?? [])->first();

            return [
                'title' => 'Review Coach Correction',
                'message' => 'Your coach asked you to update one submitted result.',
                'action_type' => 'review_correction',
                'daily_plan_id' => is_array($row) ? ($row['daily_plan_id'] ?? null) : null,
                'button_label' => 'Review Correction',
            ];
        }

        $todayPlan = $planRows->first(fn (array $row): bool => ($row['scheduled_for'] ?? null) === $today && ($row['status'] ?? null) !== 'completed');
        if ($todayPlan) {
            return [
                'title' => 'Finish Today\'s Workout',
                'message' => 'You still have blocks left in today\'s plan.',
                'action_type' => 'continue_workout',
                'daily_plan_id' => $todayPlan['daily_plan_id'] ?? null,
                'button_label' => 'Continue Workout',
            ];
        }

        if ((int) ($benchmark['pending_review_count'] ?? 0) > 0) {
            $row = collect(Arr::wrap($summary['review_status_summary']['waiting_for_coach_review'] ?? []))->first();

            return [
                'title' => 'Waiting for Coach Review',
                'message' => 'Your submitted results are waiting for coach review.',
                'action_type' => 'wait_for_review',
                'daily_plan_id' => is_array($row) ? ($row['daily_plan_id'] ?? null) : null,
                'button_label' => null,
            ];
        }

        $upcoming = $planRows
            ->filter(fn (array $row): bool => ($row['scheduled_for'] ?? '') > $today && ($row['status'] ?? null) !== 'completed')
            ->sortBy('scheduled_for')
            ->first();
        if ($upcoming) {
            return [
                'title' => 'View Next Workout',
                'message' => 'Your next assigned workout is ready.',
                'action_type' => 'view_next_plan',
                'daily_plan_id' => $upcoming['daily_plan_id'] ?? null,
                'button_label' => 'Preview',
            ];
        }

        $incomplete = $planRows->first(fn (array $row): bool => ($row['status'] ?? null) !== 'completed');
        if ($incomplete) {
            return [
                'title' => 'Complete Missing Work',
                'message' => 'You still have assigned work to finish this week.',
                'action_type' => 'complete_missing_work',
                'daily_plan_id' => $incomplete['daily_plan_id'] ?? null,
                'button_label' => 'Open Workout',
            ];
        }

        return [
            'title' => 'Week Complete',
            'message' => 'Nice work. Check back when your coach publishes the next plan.',
            'action_type' => 'none',
            'daily_plan_id' => null,
            'button_label' => null,
        ];
    }

    private function buildWeeklyCompletion(array $planRows, array $benchmarkSummary): array
    {
        $rows = collect($planRows);
        $assigned = $rows->count();
        $completed = $rows->where('status', 'completed')->count();
        $inProgress = $rows->where('status', 'in_progress')->count();
        $notStarted = $rows->filter(fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['not_started', 'updated', 'unknown'], true))->count();
        $completedItems = (int) $rows->sum('completed_items');
        $totalItems = (int) $rows->sum('total_items');

        return [
            'assigned_plan_count' => $assigned,
            'completed_plan_count' => $completed,
            'in_progress_plan_count' => $inProgress,
            'not_started_plan_count' => $notStarted,
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'completion_percentage' => $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0.0,
            'benchmark_items_completed' => (int) $rows->sum('benchmark_items_completed'),
            'benchmark_values_submitted' => (int) ($benchmarkSummary['submitted_metric_count'] ?? 0),
        ];
    }

    private function metricRow(string $metricKey, mixed $value, string $dailyPlanId, string $planTitle, string $status, array $task): array
    {
        return [
            'metric_key' => $metricKey,
            'display_name' => $this->metricLabel($metricKey),
            'value' => $value,
            'unit' => $this->metricUnit($metricKey),
            'daily_plan_id' => $dailyPlanId,
            'daily_plan_title' => $planTitle,
            'status' => $status,
            'submitted_at' => $task['submitted_at'] ?? null,
            'reviewed_at' => $task['reviewed_at'] ?? null,
            'note' => $task['note'] ?? null,
            'task_id' => $task['task_id'] ?? null,
        ];
    }

    private function hasTaskMetricRow(Collection $rows, string $dailyPlanId, string $metricKey): bool
    {
        return $rows->contains(fn (array $row): bool => ($row['daily_plan_id'] ?? null) === $dailyPlanId
            && ($row['metric_key'] ?? null) === $metricKey
            && in_array(($row['status'] ?? null), ['pending_review', 'approved', 'rejected', 'correction_requested'], true));
    }

    private function planNextAction(array $day, array $completion, int $pending, int $approved, int $corrections): ?string
    {
        if ($corrections > 0) {
            return 'Review coach correction.';
        }
        if ($pending > 0) {
            return 'Waiting for coach review.';
        }
        if ($approved > 0) {
            return 'Coach approved submitted results.';
        }
        if (($day['status'] ?? null) === 'completed') {
            return 'Workout complete.';
        }
        if (($day['status'] ?? null) === 'in_progress') {
            return 'Continue workout.';
        }

        return $completion['next_step'] ?? $day['next_step'] ?? null;
    }

    private function missedItems(array $planRows): array
    {
        return collect($planRows)
            ->filter(fn (array $row): bool => ($row['status'] ?? null) !== 'completed')
            ->map(fn (array $row): array => [
                'daily_plan_id' => $row['daily_plan_id'] ?? null,
                'title' => $row['title'] ?? 'Daily Plan',
                'scheduled_for' => $row['scheduled_for'] ?? null,
                'completed_items' => $row['completed_items'] ?? 0,
                'total_items' => $row['total_items'] ?? 0,
                'missing_items' => max(0, (int) ($row['total_items'] ?? 0) - (int) ($row['completed_items'] ?? 0)),
            ])
            ->values()
            ->all();
    }

    private function summaryStatus(array $weeklyCompletion, array $benchmarkSummary): string
    {
        if ((int) ($weeklyCompletion['assigned_plan_count'] ?? 0) === 0) {
            return 'empty';
        }

        if ((float) ($weeklyCompletion['completion_percentage'] ?? 0) >= 100.0
            && (int) ($benchmarkSummary['correction_requested_count'] ?? 0) === 0
            && (int) ($benchmarkSummary['rejected_count'] ?? 0) === 0) {
            return 'complete';
        }

        return 'partial';
    }

    private function playerMessage(string $summaryStatus, array $weeklyCompletion, array $benchmarkSummary): string
    {
        if ($summaryStatus === 'empty') {
            return 'No workouts were assigned this week.';
        }

        if ((int) ($benchmarkSummary['correction_requested_count'] ?? 0) > 0 || (int) ($benchmarkSummary['rejected_count'] ?? 0) > 0) {
            return 'Your coach requested a correction on one result.';
        }

        if ((int) ($benchmarkSummary['pending_review_count'] ?? 0) > 0) {
            return 'Your submitted results are waiting for coach review.';
        }

        if ((int) ($benchmarkSummary['approved_count'] ?? 0) > 0) {
            return 'Your coach approved your submitted benchmark results.';
        }

        if ($summaryStatus === 'complete') {
            return 'Great week. You completed all assigned workouts.';
        }

        return 'You still have assigned work to finish this week.';
    }

    private function warnings(array $planRows): array
    {
        if (empty($planRows)) {
            return ['No published assigned Daily Plans were found in this week range.'];
        }

        return [];
    }

    private function dateRange(array $options): array
    {
        $start = $this->parseDate($options['start_date'] ?? $options['start'] ?? null)
            ?? CarbonImmutable::now()->startOfWeek(CarbonInterface::MONDAY);
        $days = max(1, min(31, (int) ($options['days'] ?? 7)));
        $end = $this->parseDate($options['end_date'] ?? $options['end'] ?? null)
            ?? $start->addDays($days - 1);

        if ($end->lt($start)) {
            $end = $start->addDays($days - 1);
        }

        return [
            'start' => $start->startOfDay(),
            'end' => $end->startOfDay(),
        ];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function weekLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->isSameMonth($end)) {
            return $start->format('M j').' - '.$end->format('j, Y');
        }

        return $start->format('M j').' - '.$end->format('M j, Y');
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
}
