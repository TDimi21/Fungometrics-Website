<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class CommunicationRhythmService
{
    private const ACTIVITY_STATUSES = ['prepared', 'copy_only', 'draft_created', 'sent', 'partial'];

    public function __construct(
        private readonly WeeklyReportTemplateService $templateService,
        private readonly WeeklyReportDeliveryAnalyticsService $deliveryAnalyticsService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamRhythm(string $teamId, array $options = []): array
    {
        return $this->buildRhythm('team', [
            ...$options,
            'team_id' => $teamId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCoachRhythm(?string $coachUserId = null, array $options = []): array
    {
        return $this->buildRhythm('coach', [
            ...$options,
            'coach_user_id' => $coachUserId,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, array<string, mixed>>
     */
    public function buildWeeklyRhythmRows(array $deliveries, array $options = []): array
    {
        [$start, $end] = $this->dateWindow($options);
        $rows = [];
        for ($weekStart = $start->startOfWeek(); $weekStart->lessThanOrEqualTo($end); $weekStart = $weekStart->addWeek()) {
            $weekEnd = $weekStart->endOfWeek();
            $weekDeliveries = collect($deliveries)
                ->filter(function (array $delivery) use ($weekStart, $weekEnd): bool {
                    $createdAt = $delivery['created_at'] ?? null;
                    if (! $createdAt) {
                        return false;
                    }
                    $date = CarbonImmutable::parse((string) $createdAt);

                    return $date->betweenIncluded($weekStart->startOfDay(), $weekEnd->endOfDay());
                })
                ->values()
                ->all();

            $rows[] = $this->buildWeekRow($weekStart, $weekEnd, $weekDeliveries, $options);
        }

        return array_reverse($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $weeklyRows
     * @return array<string, array<string, mixed>>
     */
    public function buildAudienceRhythmSummary(array $weeklyRows): array
    {
        $audiences = [
            'parents' => 'has_parent_update',
            'staff' => 'has_staff_report',
            'players' => 'has_player_summary',
            'coach' => 'has_internal_qa',
        ];
        $weeks = max(1, count($weeklyRows));
        $summary = [];

        foreach ($audiences as $audience => $field) {
            $reached = collect($weeklyRows)->filter(fn (array $row): bool => (bool) ($row[$field] ?? false));
            $percentage = $this->percent($reached->count(), $weeks);

            $summary[$audience] = [
                'weeks_reached' => $reached->count(),
                'last_reached_at' => $reached->first()['week_end_date'] ?? null,
                'percentage' => $percentage,
                'status' => $reached->count() === 0
                    ? 'not_reached'
                    : ($percentage >= 75.0 ? 'consistent' : 'inconsistent'),
            ];
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $weeklyRows
     * @return array<string, mixed>
     */
    public function buildRhythmScore(array $weeklyRows): array
    {
        $weeks = max(1, count($weeklyRows));
        $weeksWithAny = collect($weeklyRows)->where('has_any_report', true)->count();
        $weeksWithParent = collect($weeklyRows)->where('has_parent_update', true)->count();
        $weeksWithStaff = collect($weeklyRows)->where('has_staff_report', true)->count();
        $weeksWithPlayer = collect($weeklyRows)->where('has_player_summary', true)->count();
        $blockedOrFailedWeeks = collect($weeklyRows)->filter(fn (array $row): bool => ((int) ($row['blocked_count'] ?? 0) + (int) ($row['failed_count'] ?? 0)) > 0)->count();
        $consistency = $this->percent($weeksWithAny, $weeks);
        $parentPercentage = $this->percent($weeksWithParent, $weeks);
        $staffPercentage = $this->percent($weeksWithStaff, $weeks);
        $playerPercentage = $this->percent($weeksWithPlayer, $weeks);
        $blockedFailedRate = $this->percent($blockedOrFailedWeeks, $weeks);

        $score = round(($consistency * 0.45) + ($parentPercentage * 0.25) + ($staffPercentage * 0.15) + ($playerPercentage * 0.15) - min(25.0, $blockedFailedRate * 0.35), 1);
        $score = max(0.0, min(100.0, $score));

        $label = match (true) {
            $weeksWithAny === 0 => 'no_activity',
            $consistency >= 90.0 && $parentPercentage >= 75.0 && $blockedFailedRate <= 15.0 => 'excellent',
            $consistency >= 70.0 && max($parentPercentage, $staffPercentage) >= 50.0 && $blockedFailedRate <= 30.0 => 'good',
            $consistency >= 40.0 => 'inconsistent',
            default => 'needs_attention',
        };

        if ($weeksWithAny > 0 && $blockedFailedRate >= 40.0) {
            $label = 'needs_attention';
            $score = min($score, 45.0);
        }

        return [
            'score_0_100' => $score,
            'label' => $label,
            'weeks_with_any_report' => $weeksWithAny,
            'weeks_with_parent_update' => $weeksWithParent,
            'weeks_with_staff_report' => $weeksWithStaff,
            'weeks_with_player_summary' => $weeksWithPlayer,
            'consistency_percentage' => $consistency,
            'parent_update_percentage' => $parentPercentage,
            'staff_report_percentage' => $staffPercentage,
            'player_summary_percentage' => $playerPercentage,
        ];
    }

    /**
     * @param array<string, mixed> $rhythm
     * @return array<int, array<string, mixed>>
     */
    public function buildCommunicationRecommendations(array $rhythm): array
    {
        $actions = [];
        $weeklyRows = Arr::wrap($rhythm['weekly_rows'] ?? []);
        $latestWeek = $weeklyRows[0] ?? [];
        $score = Arr::wrap($rhythm['rhythm_score'] ?? []);
        $audience = Arr::wrap($rhythm['audience_summary'] ?? []);
        $health = Arr::wrap($rhythm['delivery_health_summary'] ?? []);

        if (! (bool) ($latestWeek['has_any_report'] ?? false)) {
            $actions[] = $this->action(
                'create_this_weeks_update',
                'high',
                "Create This Week's Update",
                'No weekly report activity is recorded for the current week.',
                'Generate a weekly report and choose a parent or staff template.',
                'create_parent_update',
                'parent_update',
                'parents',
            );
        }

        if (($audience['parents']['status'] ?? 'not_reached') !== 'consistent') {
            $actions[] = $this->action(
                'send_parent_safe_update',
                'high',
                'Send Parent-Safe Update',
                'Parent updates are not consistent across the analyzed weeks.',
                'Use the Parent Update template to share development progress without private review details.',
                'create_parent_update',
                'parent_update',
                'parents',
            );
        }

        if (($audience['staff']['status'] ?? 'not_reached') !== 'consistent') {
            $actions[] = $this->action(
                'create_staff_report',
                'medium',
                'Create Staff Report',
                'Staff reports are not being created consistently.',
                'Use the Staff Report template to align coaches on player follow-ups and next week priorities.',
                'create_staff_report',
                'staff_report',
                'staff',
            );
        }

        if ((int) ($score['weeks_with_player_summary'] ?? 0) === 0) {
            $actions[] = $this->action(
                'share_player_development_summary',
                'medium',
                'Share Player Development Summary',
                'No player-facing development summaries were recorded in this window.',
                'Use the Player Development Summary template for player-facing progress.',
                'create_player_summary',
                'player_development_summary',
                'players',
            );
        }

        if ((float) ($health['copy_only_rate'] ?? 0) >= 50.0 && (int) ($health['copy_only_count'] ?? 0) > 1) {
            $actions[] = $this->action(
                'improve_delivery_setup',
                'medium',
                'Improve Delivery Setup',
                'Reports are being prepared but many are still copy-only.',
                'Use copy/share for now or configure a supported channel when direct delivery is ready.',
                'configure_delivery',
                null,
                null,
            );
        }

        if ((int) ($health['blocked_count'] ?? 0) > 0 || (int) ($health['unsupported_count'] ?? 0) > 0 || (int) ($health['failed_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'review_blocked_reports',
                'high',
                'Review Blocked Reports',
                'Some reports were blocked, unsupported, or failed.',
                'Use parent-safe templates for parents and staff templates for staff before delivery.',
                'review_blocked_reports',
                null,
                null,
            );
        }

        if (($score['label'] ?? '') === 'excellent') {
            $actions[] = $this->action(
                'keep_weekly_communication_rhythm',
                'low',
                'Keep Weekly Communication Rhythm',
                'Weekly communication rhythm is strong and parent updates are consistent.',
                'Continue sending weekly development updates after coach review is complete.',
                'none',
                null,
                null,
            );
        }

        return collect($actions)
            ->unique('id')
            ->sortBy(fn (array $action): int => ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$action['priority']] ?? 4)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRhythm(string $scope, array $options): array
    {
        [$start, $end] = $this->dateWindow($options);
        $deliveries = $this->deliveryQuery($scope, $options, $start, $end)
            ->latest('created_at')
            ->get()
            ->map(fn (WeeklyReportDelivery $delivery): array => $this->normalizeDelivery($delivery))
            ->values()
            ->all();

        $weeks = $this->weeksBetween($start, $end);
        $weeklyRows = $this->buildWeeklyRhythmRows($deliveries, [
            ...$options,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'weeks' => $weeks,
        ]);
        $rhythmScore = $this->buildRhythmScore($weeklyRows);
        $health = $this->buildDeliveryHealthSummary($deliveries);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'team_id' => $options['team_id'] ?? null,
            'coach_user_id' => $options['coach_user_id'] ?? null,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'weeks_analyzed' => count($weeklyRows),
            'rhythm_score' => $rhythmScore,
            'weekly_rows' => $weeklyRows,
            'audience_summary' => $this->buildAudienceRhythmSummary($weeklyRows),
            'template_summary' => $this->buildTemplateSummary($deliveries, $weeklyRows),
            'delivery_health_summary' => $health,
            'missed_weeks' => $this->buildMissedWeeks($weeklyRows),
            'streaks' => $this->buildStreaks($weeklyRows),
            'recommended_actions' => [],
            'warnings' => $this->warnings($deliveries, $weeklyRows, $health),
        ];
        $payload['recommended_actions'] = $this->buildCommunicationRecommendations($payload);

        return $payload;
    }

    private function deliveryQuery(string $scope, array $options, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = WeeklyReportDelivery::query()
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString());

        if ($scope === 'team') {
            $query->where('team_id', (string) ($options['team_id'] ?? ''));
        } elseif ($scope === 'coach' && ! empty($options['coach_user_id'])) {
            $coachUserId = (string) $options['coach_user_id'];
            $query->where(function (Builder $builder) use ($coachUserId): void {
                $builder->where('created_by_user_id', $coachUserId)
                    ->orWhere('sent_by_user_id', $coachUserId);
            });
        }

        return $query;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateWindow(array $options): array
    {
        $weeks = max(1, min(52, (int) ($options['weeks'] ?? 8)));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfWeek()
            : CarbonImmutable::now()->endOfWeek();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfWeek()
            : $end->startOfWeek()->subWeeks($weeks - 1);

        if ($start->greaterThan($end)) {
            return [$end->startOfWeek(), $start->endOfWeek()];
        }

        return [$start, $end];
    }

    private function weeksBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(1, (int) floor($start->startOfWeek()->diffInDays($end->endOfWeek()) / 7) + 1);
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildWeekRow(CarbonImmutable $weekStart, CarbonImmutable $weekEnd, array $deliveries, array $options): array
    {
        $activeDeliveries = collect($deliveries)->filter(fn (array $delivery): bool => $this->isActiveReport($delivery));
        $templates = collect($deliveries)->pluck('template_key')->filter()->unique()->values()->all();
        $audiences = collect($activeDeliveries)->pluck('audience')->filter()->unique()->values()->all();
        $channels = collect($deliveries)->pluck('channel')->filter()->unique()->values()->all();
        $preparedCount = collect($deliveries)->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['prepared', 'draft_created'], true))->count();
        $copyOnlyCount = collect($deliveries)->where('delivery_status', 'copy_only')->count();
        $sentCount = collect($deliveries)->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['sent', 'partial'], true))->count();
        $blockedCount = collect($deliveries)->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['blocked', 'unsupported'], true))->count();
        $failedCount = collect($deliveries)->where('delivery_status', 'failed')->count();
        $hasParent = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'parents' || (string) $delivery['template_key'] === 'parent_update');
        $hasStaff = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'staff' || (string) $delivery['template_key'] === 'staff_report');
        $hasPlayer = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'players' || (string) $delivery['template_key'] === 'player_development_summary');
        $hasInternal = $activeDeliveries->contains(fn (array $delivery): bool => in_array((string) $delivery['audience'], ['coach', 'internal'], true) || in_array((string) $delivery['template_key'], ['detailed_coach_report', 'internal_benchmark_qa'], true));
        $hasAny = $activeDeliveries->isNotEmpty();
        $missedAudiences = $this->missedAudiences($hasParent, $hasStaff, $hasPlayer, $options);

        return [
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekEnd->toDateString(),
            'week_label' => $weekStart->format('M j').' - '.$weekEnd->format('M j'),
            'has_any_report' => $hasAny,
            'has_parent_update' => $hasParent,
            'has_staff_report' => $hasStaff,
            'has_player_summary' => $hasPlayer,
            'has_internal_qa' => $hasInternal,
            'prepared_count' => $preparedCount,
            'copy_only_count' => $copyOnlyCount,
            'sent_count' => $sentCount,
            'blocked_count' => $blockedCount,
            'failed_count' => $failedCount,
            'templates_used' => $templates,
            'audiences_reached' => $audiences,
            'channels_used' => $channels,
            'status_label' => $this->weekStatus($hasAny, $copyOnlyCount, $sentCount, $blockedCount, $failedCount, $missedAudiences),
            'recommended_action' => $this->weekAction($hasAny, $missedAudiences, $blockedCount, $failedCount, $copyOnlyCount),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @param array<int, array<string, mixed>> $weeklyRows
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateSummary(array $deliveries, array $weeklyRows): array
    {
        return collect($deliveries)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['template_key'] ?: 'unknown'))
            ->map(function ($records, string $templateKey) use ($weeklyRows): array {
                $weeksUsed = collect($weeklyRows)
                    ->filter(fn (array $row): bool => in_array($templateKey, Arr::wrap($row['templates_used'] ?? []), true))
                    ->count();

                return [
                    'template_key' => $templateKey,
                    'display_name' => $this->templateDisplayName($templateKey),
                    'weeks_used' => $weeksUsed,
                    'total_uses' => $records->count(),
                    'last_used_at' => $records->sortByDesc('created_at')->first()['created_at'] ?? null,
                ];
            })
            ->sortByDesc('total_uses')
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildDeliveryHealthSummary(array $deliveries): array
    {
        $analyticsHealth = $this->deliveryAnalyticsService->buildDeliveryHealthSummary($deliveries);
        $total = count($deliveries);
        $sent = collect($deliveries)->where('delivery_status', 'sent')->count();
        $partial = collect($deliveries)->where('delivery_status', 'partial')->count();
        $copyOnly = collect($deliveries)->where('delivery_status', 'copy_only')->count();
        $blocked = collect($deliveries)->where('delivery_status', 'blocked')->count();
        $unsupported = collect($deliveries)->where('delivery_status', 'unsupported')->count();
        $failed = collect($deliveries)->where('delivery_status', 'failed')->count();

        return [
            'total_records' => $total,
            'sent_count' => $sent + $partial,
            'copy_only_count' => $copyOnly,
            'blocked_count' => $blocked,
            'unsupported_count' => $unsupported,
            'failed_count' => $failed,
            'copy_only_rate' => $this->percent($copyOnly, $total),
            'blocked_rate' => $this->percent($blocked + $unsupported, $total),
            'send_success_rate' => (float) ($analyticsHealth['delivery_success_rate'] ?? $this->percent($sent + $partial, $total)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $weeklyRows
     * @return array<int, array<string, mixed>>
     */
    private function buildMissedWeeks(array $weeklyRows): array
    {
        return collect($weeklyRows)
            ->filter(fn (array $row): bool => ! (bool) ($row['has_any_report'] ?? false) || ! empty($this->rowMissedAudiences($row)))
            ->map(fn (array $row): array => [
                'week_start_date' => $row['week_start_date'],
                'week_end_date' => $row['week_end_date'],
                'week_label' => $row['week_label'],
                'missed_audiences' => ! (bool) ($row['has_any_report'] ?? false) ? ['weekly_report'] : $this->rowMissedAudiences($row),
                'recommended_action' => (string) ($row['recommended_action'] ?? 'Create a weekly development update.'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $weeklyRows
     * @return array<string, int>
     */
    private function buildStreaks(array $weeklyRows): array
    {
        return [
            'current_any_report_streak' => $this->currentStreak($weeklyRows, 'has_any_report'),
            'current_parent_update_streak' => $this->currentStreak($weeklyRows, 'has_parent_update'),
            'current_staff_report_streak' => $this->currentStreak($weeklyRows, 'has_staff_report'),
            'longest_any_report_streak' => $this->longestStreak($weeklyRows, 'has_any_report'),
            'longest_parent_update_streak' => $this->longestStreak($weeklyRows, 'has_parent_update'),
        ];
    }

    private function currentStreak(array $weeklyRows, string $field): int
    {
        $streak = 0;
        foreach ($weeklyRows as $row) {
            if (! (bool) ($row[$field] ?? false)) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function longestStreak(array $weeklyRows, string $field): int
    {
        $longest = 0;
        $current = 0;
        foreach (array_reverse($weeklyRows) as $row) {
            if ((bool) ($row[$field] ?? false)) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }

        return $longest;
    }

    /**
     * @return array<int, string>
     */
    private function warnings(array $deliveries, array $weeklyRows, array $health): array
    {
        $warnings = [];
        if (empty($deliveries)) {
            $warnings[] = 'No communication history was found for this date range.';
        }
        if (collect($weeklyRows)->where('has_any_report', false)->isNotEmpty()) {
            $warnings[] = 'One or more weeks have no weekly report activity.';
        }
        if ((int) ($health['blocked_count'] ?? 0) > 0 || (int) ($health['unsupported_count'] ?? 0) > 0) {
            $warnings[] = 'Some weekly report deliveries were blocked or unsupported.';
        }
        if ((float) ($health['copy_only_rate'] ?? 0) >= 50.0 && (int) ($health['copy_only_count'] ?? 0) > 1) {
            $warnings[] = 'Many reports are copy-only. Direct delivery setup may need review.';
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDelivery(WeeklyReportDelivery $delivery): array
    {
        return [
            'delivery_id' => (string) $delivery->id,
            'team_id' => (string) $delivery->team_id,
            'template_key' => (string) ($delivery->template_key ?? ''),
            'template_display_name' => $this->templateDisplayName((string) ($delivery->template_key ?? '')),
            'audience' => (string) ($delivery->audience ?? ''),
            'channel' => (string) ($delivery->channel ?? ''),
            'format' => (string) ($delivery->format ?? ''),
            'delivery_status' => (string) ($delivery->delivery_status ?? 'prepared'),
            'created_at' => $delivery->created_at?->toIso8601String(),
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'copied_at' => $delivery->copied_at?->toIso8601String(),
            'blocked_at' => $delivery->blocked_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'recipient_summary' => Arr::wrap($delivery->recipient_summary),
        ];
    }

    private function missedAudiences(bool $hasParent, bool $hasStaff, bool $hasPlayer, array $options): array
    {
        $missing = [];
        if ($this->optionBool($options, 'include_parent_updates', true) && ! $hasParent) {
            $missing[] = 'parents';
        }
        if ($this->optionBool($options, 'include_staff_reports', true) && ! $hasStaff) {
            $missing[] = 'staff';
        }
        if ($this->optionBool($options, 'include_player_summaries', true) && ! $hasPlayer) {
            $missing[] = 'players';
        }

        return $missing;
    }

    private function rowMissedAudiences(array $row): array
    {
        $missing = [];
        if (! (bool) ($row['has_parent_update'] ?? false)) {
            $missing[] = 'parents';
        }
        if (! (bool) ($row['has_staff_report'] ?? false)) {
            $missing[] = 'staff';
        }
        if (! (bool) ($row['has_player_summary'] ?? false)) {
            $missing[] = 'players';
        }

        return $missing;
    }

    private function weekStatus(bool $hasAny, int $copyOnlyCount, int $sentCount, int $blockedCount, int $failedCount, array $missedAudiences): string
    {
        if (($blockedCount + $failedCount) > 0 && $sentCount === 0 && $copyOnlyCount === 0) {
            return 'blocked';
        }
        if (! $hasAny) {
            return 'missed';
        }
        if ($copyOnlyCount > 0 && $sentCount === 0) {
            return 'copy_only';
        }

        return empty($missedAudiences) ? 'complete' : 'partial';
    }

    private function weekAction(bool $hasAny, array $missedAudiences, int $blockedCount, int $failedCount, int $copyOnlyCount): ?string
    {
        if (($blockedCount + $failedCount) > 0) {
            return 'Review blocked or failed reports before sharing externally.';
        }
        if (! $hasAny) {
            return 'Create and share this week’s development update.';
        }
        if ($copyOnlyCount > 0) {
            return 'Confirm the copied report was shared outside FMTRX.';
        }
        if (! empty($missedAudiences)) {
            return 'Add a '.implode(', ', $missedAudiences).' update for this week.';
        }

        return null;
    }

    private function isActiveReport(array $delivery): bool
    {
        return in_array((string) ($delivery['delivery_status'] ?? ''), self::ACTIVITY_STATUSES, true);
    }

    private function optionBool(array $options, string $key, bool $default): bool
    {
        $value = $options[$key] ?? $default;
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function templateDisplayName(string $templateKey): string
    {
        $template = collect($this->templateService->listTemplates())
            ->first(fn (array $template): bool => (string) ($template['template_key'] ?? '') === $templateKey);

        return (string) ($template['display_name'] ?? $this->humanLabel($templateKey ?: 'unknown'));
    }

    private function percent(int|float $value, int|float $total): float
    {
        return $total > 0 ? round(((float) $value / (float) $total) * 100, 1) : 0.0;
    }

    private function humanLabel(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }

    /**
     * @return array<string, mixed>
     */
    private function action(string $id, string $priority, string $title, string $why, string $action, string $actionType, ?string $templateKey, ?string $audience): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'priority' => $priority,
            'why' => $why,
            'action' => $action,
            'action_type' => $actionType,
            'template_key' => $templateKey,
            'audience' => $audience,
        ];
    }
}
