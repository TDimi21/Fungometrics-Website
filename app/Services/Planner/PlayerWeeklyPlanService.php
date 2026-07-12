<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\DailyPlanProgress;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlayerWeeklyPlanService
{
    public function __construct(
        private readonly DailyPlanPlayerUpdateService $updateService,
    ) {
    }

    public function buildForPlayer(string $playerId, array $options = []): array
    {
        $range = $this->dateRange($options);
        $warnings = [];

        $assignments = DailyPlanAssignment::query()
            ->where('user_id', $playerId)
            ->pluck('plan_id')
            ->unique()
            ->values();

        /** @var Collection<int, DailyPlan> $plans */
        $plans = DailyPlan::query()
            ->whereIn('id', $assignments)
            ->where('status', 'published')
            ->whereBetween('date', [$range['start']->toDateString(), $range['end']->toDateString()])
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $progress = DailyPlanProgress::query()
            ->where('user_id', $playerId)
            ->whereIn('plan_id', $plans->pluck('id'))
            ->get()
            ->keyBy('plan_id');

        $includeCompleted = array_key_exists('include_completed', $options)
            ? (bool) $options['include_completed']
            : true;

        $dayCards = $plans
            ->map(fn (DailyPlan $plan): array => $this->buildDayCardFromPlan(
                $plan,
                $playerId,
                $progress->get((string) $plan->id),
            ))
            ->filter(fn (array $card): bool => $includeCompleted || ($card['status'] ?? null) !== 'completed')
            ->values()
            ->all();

        $today = CarbonImmutable::now()->toDateString();
        $todayPlan = collect($dayCards)
            ->first(fn (array $card): bool => ($card['scheduled_for'] ?? null) === $today);

        if (empty($dayCards)) {
            $warnings[] = 'No published assigned Daily Plans were found in this week range.';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'player_id' => $playerId,
            'start_date' => $range['start']->toDateString(),
            'end_date' => $range['end']->toDateString(),
            'week_label' => $this->weekLabel($range['start'], $range['end']),
            'today_plan' => $todayPlan ?: null,
            'weekly_summary' => $this->buildWeeklySummary($dayCards),
            'days' => $dayCards,
            'next_action' => $this->buildNextAction($dayCards, $todayPlan),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function buildDayCard(array $dailyPlan, string $playerId): array
    {
        $dailyPlanId = (string) ($dailyPlan['daily_plan_id'] ?? $dailyPlan['id'] ?? '');
        $plan = $dailyPlanId !== ''
            ? DailyPlan::query()
                ->where('id', $dailyPlanId)
                ->where('status', 'published')
                ->first()
            : null;

        if (! $plan) {
            return [
                'daily_plan_id' => $dailyPlanId,
                'day_label' => null,
                'scheduled_for' => $dailyPlan['date'] ?? $dailyPlan['scheduled_for'] ?? null,
                'title' => (string) ($dailyPlan['name'] ?? $dailyPlan['title'] ?? 'Daily Plan'),
                'status' => 'unknown',
                'plan_status' => 'unknown',
                'estimated_total_minutes' => null,
                'completed_items' => 0,
                'total_items' => 0,
                'completion_percentage' => 0.0,
                'benchmark_generated' => false,
                'benchmark_block_count' => 0,
                'pending_review_count' => 0,
                'approved_result_count' => 0,
                'update_status' => null,
                'primary_focus' => null,
                'blocks_preview' => [],
                'next_step' => 'Workout is not available.',
            ];
        }

        $progress = DailyPlanProgress::query()
            ->where('plan_id', $dailyPlanId)
            ->where('user_id', $playerId)
            ->first();

        return $this->buildDayCardFromPlan($plan, $playerId, $progress);
    }

    public function buildWeeklySummary(array $dayCards): array
    {
        $assigned = count($dayCards);
        $completed = count(array_filter($dayCards, fn (array $card): bool => ($card['status'] ?? null) === 'completed'));
        $inProgress = count(array_filter($dayCards, fn (array $card): bool => ($card['status'] ?? null) === 'in_progress'));
        $notStarted = count(array_filter($dayCards, fn (array $card): bool => in_array(($card['status'] ?? null), ['not_started', 'updated'], true)));
        $benchmark = count(array_filter($dayCards, fn (array $card): bool => (bool) ($card['benchmark_generated'] ?? false)));
        $pending = array_sum(array_map(fn (array $card): int => (int) ($card['pending_review_count'] ?? 0), $dayCards));
        $updates = count(array_filter($dayCards, fn (array $card): bool => (bool) Arr::get($card, 'update_status.has_update')));

        return [
            'assigned_plan_count' => $assigned,
            'completed_plan_count' => $completed,
            'in_progress_plan_count' => $inProgress,
            'not_started_plan_count' => $notStarted,
            'benchmark_plan_count' => $benchmark,
            'pending_review_count' => $pending,
            'updates_to_acknowledge' => $updates,
            'weekly_completion_percentage' => $assigned > 0 ? round(($completed / $assigned) * 100, 1) : 0.0,
        ];
    }

    private function buildDayCardFromPlan(DailyPlan $plan, string $playerId, ?DailyPlanProgress $progress): array
    {
        $buckets = Arr::wrap($plan->buckets);
        $totalItems = $this->totalItems($buckets);
        $progressItems = is_array($progress?->items) ? $progress->items : [];
        $completedItems = $this->completedItems($progressItems);
        $completionPercentage = $totalItems > 0
            ? round(min(100, ($completedItems / $totalItems) * 100), 1)
            : 0.0;
        $updateStatus = $this->updateStatus((string) $plan->id, $playerId);
        $pendingReviewCount = $this->reviewCount($progressItems, 'pending');
        $approvedCount = $this->reviewCount($progressItems, 'approved');
        $benchmarkBlockCount = $this->benchmarkBlockCount($buckets);
        $benchmarkGenerated = $benchmarkBlockCount > 0 || $this->isWeeklyGeneratedPlan($plan, $buckets);
        $status = $this->dayStatus($progress, $completedItems, $totalItems, $updateStatus);

        return [
            'daily_plan_id' => (string) $plan->id,
            'day_label' => $this->dayLabel($plan->date),
            'scheduled_for' => $this->dateString($plan->date),
            'title' => (string) ($plan->name ?: 'Daily Plan'),
            'status' => $status,
            'plan_status' => (string) ($plan->status ?: 'unknown'),
            'estimated_total_minutes' => $this->estimatedMinutes($plan, $buckets),
            'completed_items' => $completedItems,
            'total_items' => $totalItems,
            'completion_percentage' => $completionPercentage,
            'benchmark_generated' => $benchmarkGenerated,
            'benchmark_block_count' => $benchmarkBlockCount,
            'pending_review_count' => $pendingReviewCount,
            'approved_result_count' => $approvedCount,
            'update_status' => $updateStatus,
            'primary_focus' => $this->primaryFocus($plan, $buckets),
            'blocks_preview' => $this->blocksPreview($buckets),
            'next_step' => $this->dayNextStep($status, $plan, $pendingReviewCount),
        ];
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

    private function dayLabel(mixed $date): ?string
    {
        $dateString = $this->dateString($date);
        if (! $dateString) {
            return null;
        }

        try {
            return CarbonImmutable::parse($dateString)->format('l');
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateString(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($date)->toDateString();
        }

        if (is_string($date) && trim($date) !== '') {
            return substr($date, 0, 10);
        }

        return null;
    }

    private function totalItems(array $buckets): int
    {
        return collect($buckets)
            ->sum(fn (array $bucket): int => count(Arr::wrap($bucket['items'] ?? [])));
    }

    private function completedItems(array $items): int
    {
        return collect($items)
            ->filter(fn ($item): bool => is_array($item) && ((bool) ($item['done'] ?? false) || (bool) ($item['completed'] ?? false)))
            ->count();
    }

    private function estimatedMinutes(DailyPlan $plan, array $buckets): ?int
    {
        $explicit = (int) ($plan->estimated_minutes ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $minutes = collect($buckets)->sum(function (array $bucket): int {
            return collect(Arr::wrap($bucket['items'] ?? []))
                ->sum(function (array $item): int {
                    $seconds = (int) ($item['durationSec'] ?? $item['duration_sec'] ?? 0);
                    if ($seconds > 0) {
                        return max(1, (int) round($seconds / 60));
                    }

                    return (int) ($item['duration_minutes'] ?? $item['durationMinutes'] ?? $item['estimated_minutes'] ?? $item['minutes'] ?? 0);
                });
        });

        return $minutes > 0 ? (int) $minutes : max(0, $this->totalItems($buckets) * 4);
    }

    private function updateStatus(string $dailyPlanId, string $playerId): ?array
    {
        try {
            return $this->updateService->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function dayStatus(?DailyPlanProgress $progress, int $completedItems, int $totalItems, ?array $updateStatus): string
    {
        if ((bool) Arr::get($updateStatus, 'has_update')) {
            return 'updated';
        }

        if ($progress?->completed_at || ($totalItems > 0 && $completedItems >= $totalItems)) {
            return 'completed';
        }

        if ($progress?->started_at || $completedItems > 0 || ! empty($progress?->items)) {
            return 'in_progress';
        }

        return 'not_started';
    }

    private function reviewCount(array $progressItems, string $type): int
    {
        return collect($progressItems)
            ->filter(function ($item) use ($type): bool {
                if (! is_array($item)) {
                    return false;
                }

                $status = Str::of((string) ($item['review_status'] ?? $item['reviewStatus'] ?? $item['review_state'] ?? $item['reviewState'] ?? ''))
                    ->lower()
                    ->replace(' ', '_')
                    ->toString();

                if ($type === 'approved') {
                    return $status === 'approved';
                }

                if (in_array($status, ['pending_review', 'submitted_for_review'], true)) {
                    return true;
                }

                return (bool) ($item['done'] ?? false)
                    && ! empty($item['submitted_at'])
                    && ! empty(array_filter(Arr::wrap($item['metric_values'] ?? $item['actuals'] ?? $item['submitted_values'] ?? [])));
            })
            ->count();
    }

    private function benchmarkBlockCount(array $buckets): int
    {
        return collect($buckets)
            ->filter(fn (array $bucket): bool => $this->isBenchmarkBucket($bucket))
            ->count();
    }

    private function isBenchmarkBucket(array $bucket): bool
    {
        if ((bool) Arr::get($bucket, 'generated_from.weekly_rollup') || (bool) Arr::get($bucket, 'generated_from.next_week_plan')) {
            return true;
        }

        $tags = collect(Arr::wrap($bucket['tags'] ?? []))
            ->map(fn ($tag): string => $this->token($tag));

        if ($tags->intersect(['fmtrx-generated', 'fmtrx_generated', 'benchmark-plan', 'benchmark_plan', 'weekly-draft', 'weekly_draft'])->isNotEmpty()) {
            return true;
        }

        return collect(Arr::wrap($bucket['items'] ?? []))
            ->contains(fn ($item): bool => is_array($item) && $this->isBenchmarkItem($item));
    }

    private function isBenchmarkItem(array $item): bool
    {
        $source = $this->token($item['source'] ?? '');
        $categoryGroup = $this->token($item['categoryGroup'] ?? $item['category_group'] ?? '');
        $tags = collect(Arr::wrap($item['tags'] ?? []))
            ->map(fn ($tag): string => $this->token($tag));

        return in_array($source, ['coach_action_practice_plan', 'benchmark_collection_plan', 'benchmark-generated', 'benchmark_generated'], true)
            || $categoryGroup === 'fmtrx_benchmark'
            || $tags->intersect(['benchmark-generated', 'benchmark_generated', 'coach_action_practice_plan', 'benchmark_collection_plan'])->isNotEmpty()
            || ! empty($item['metrics_to_collect'])
            || ! empty($item['metricsToCollect'])
            || ! empty($item['relatedMetrics'])
            || ! empty($item['related_metrics'])
            || ! empty($item['benchmark_task_type']);
    }

    private function isWeeklyGeneratedPlan(DailyPlan $plan, array $buckets): bool
    {
        $id = (string) $plan->id;
        if (str_starts_with($id, 'dp_weekly_')) {
            return true;
        }

        $phase = $this->token($plan->phase ?? '');
        if ($phase === 'weekly_plan') {
            return true;
        }

        return collect($buckets)->contains(fn (array $bucket): bool => $this->isBenchmarkBucket($bucket));
    }

    private function primaryFocus(DailyPlan $plan, array $buckets): ?string
    {
        $explicit = trim((string) ($plan->primary_goal ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $bucket = collect($buckets)
            ->map(fn (array $row): string => trim((string) ($row['title'] ?? $row['name'] ?? $this->humanize((string) ($row['type'] ?? '')))))
            ->first(fn (string $value): bool => $value !== '');

        return $bucket ?: null;
    }

    private function blocksPreview(array $buckets): array
    {
        return collect($buckets)
            ->take(6)
            ->map(function (array $bucket): array {
                $items = Arr::wrap($bucket['items'] ?? []);
                $metrics = collect($items)
                    ->flatMap(fn ($item): array => is_array($item) ? $this->itemMetrics($item) : [])
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'title' => (string) ($bucket['title'] ?? $bucket['name'] ?? $this->humanize((string) ($bucket['type'] ?? 'Block'))),
                    'category' => $bucket['type'] ?? null,
                    'duration_minutes' => $this->bucketMinutes($bucket),
                    'benchmark_generated' => $this->isBenchmarkBucket($bucket),
                    'metrics_to_collect' => $metrics,
                ];
            })
            ->values()
            ->all();
    }

    private function itemMetrics(array $item): array
    {
        return collect([
            ...Arr::wrap($item['relatedMetrics'] ?? []),
            ...Arr::wrap($item['related_metrics'] ?? []),
            ...Arr::wrap($item['metrics_to_collect'] ?? []),
            ...Arr::wrap($item['metricsToCollect'] ?? []),
            ...Arr::wrap($item['metrics'] ?? []),
            ...Arr::wrap($item['required_fields'] ?? []),
        ])
            ->map(fn ($metric): string => is_array($metric)
                ? (string) ($metric['metric_key'] ?? $metric['key'] ?? $metric['name'] ?? $metric['display_name'] ?? '')
                : (string) $metric)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function bucketMinutes(array $bucket): ?int
    {
        $explicit = (int) ($bucket['duration_minutes'] ?? $bucket['durationMinutes'] ?? $bucket['minutes'] ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $minutes = collect(Arr::wrap($bucket['items'] ?? []))
            ->sum(function (array $item): int {
                $seconds = (int) ($item['durationSec'] ?? $item['duration_sec'] ?? 0);
                if ($seconds > 0) {
                    return max(1, (int) round($seconds / 60));
                }

                return (int) ($item['duration_minutes'] ?? $item['durationMinutes'] ?? $item['estimated_minutes'] ?? $item['minutes'] ?? 0);
            });

        return $minutes > 0 ? (int) $minutes : null;
    }

    private function dayNextStep(string $status, DailyPlan $plan, int $pendingReviewCount): ?string
    {
        if ($status === 'updated') {
            return 'Acknowledge the coach update before starting.';
        }

        if ($pendingReviewCount > 0) {
            return 'Results are waiting for coach review.';
        }

        if ($status === 'completed') {
            return 'Completed.';
        }

        if ($status === 'in_progress') {
            return 'Continue Workout.';
        }

        return $this->dateString($plan->date) === CarbonImmutable::now()->toDateString()
            ? 'Start Workout.'
            : 'Preview upcoming workout.';
    }

    private function buildNextAction(array $dayCards, ?array $todayPlan): array
    {
        $updated = collect($dayCards)->first(fn (array $card): bool => (bool) Arr::get($card, 'update_status.has_update'));
        if ($todayPlan && (bool) Arr::get($todayPlan, 'update_status.has_update')) {
            return [
                'title' => 'Acknowledge your updated plan',
                'message' => 'Your coach changed today\'s workout. Tap Got it before starting.',
                'daily_plan_id' => $todayPlan['daily_plan_id'],
                'action_type' => 'acknowledge_update',
            ];
        }

        if ($updated) {
            return [
                'title' => 'Acknowledge your updated plan',
                'message' => 'Your coach changed an assigned workout. Review the update when you can.',
                'daily_plan_id' => $updated['daily_plan_id'],
                'action_type' => 'acknowledge_update',
            ];
        }

        if ($todayPlan) {
            $status = $todayPlan['status'] ?? 'unknown';
            if ($status === 'completed') {
                return [
                    'title' => 'Today is complete',
                    'message' => 'Your workout for today is finished.',
                    'daily_plan_id' => $todayPlan['daily_plan_id'],
                    'action_type' => 'none',
                ];
            }

            return [
                'title' => $status === 'in_progress' ? 'Continue today\'s workout' : 'Start today\'s workout',
                'message' => $status === 'in_progress' ? 'Pick up where you left off.' : 'Open today\'s assigned plan.',
                'daily_plan_id' => $todayPlan['daily_plan_id'],
                'action_type' => $status === 'in_progress' ? 'continue_today' : 'start_today',
            ];
        }

        $pending = collect($dayCards)->first(fn (array $card): bool => (int) ($card['pending_review_count'] ?? 0) > 0);
        if ($pending) {
            return [
                'title' => 'Results waiting for coach review',
                'message' => 'You have submitted benchmark results waiting for coach review.',
                'daily_plan_id' => $pending['daily_plan_id'],
                'action_type' => 'review_pending',
            ];
        }

        $next = collect($dayCards)
            ->first(fn (array $card): bool => in_array(($card['status'] ?? null), ['not_started', 'in_progress'], true));

        if ($next) {
            return [
                'title' => 'Preview your next workout',
                'message' => 'No workout is assigned for today, but an upcoming plan is available.',
                'daily_plan_id' => $next['daily_plan_id'],
                'action_type' => 'complete_next',
            ];
        }

        return [
            'title' => 'No workout assigned for today',
            'message' => empty($dayCards)
                ? 'Your coach has not published this week\'s plans yet.'
                : 'Select a plan to view your assigned work.',
            'daily_plan_id' => null,
            'action_type' => 'none',
        ];
    }

    private function token(mixed $value): string
    {
        return Str::of((string) $value)->lower()->replace([' ', '-'], '_')->toString();
    }

    private function humanize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return Str::of($value)->replace(['_', '-'], ' ')->title()->toString();
    }
}
