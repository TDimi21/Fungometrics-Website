<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class SeasonCommunicationRhythmService
{
    private const ACTIVE_STATUSES = ['prepared', 'copy_only', 'draft_created', 'sent', 'partial'];

    private const TEMPLATE_LABELS = [
        'staff_review_packet' => 'Staff Review Packet',
        'director_packet' => 'Director Packet',
        'parent_safe_season_summary' => 'Parent-Safe Season Summary',
        'player_development_summary' => 'Player Development Summary',
        'internal_qa_packet' => 'Internal QA Packet',
    ];

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
    public function buildSeasonRows(array $deliveries, array $options = []): array
    {
        [$start, $end] = $this->dateWindow($options);
        $rows = [];

        for ($periodStart = $start->startOfMonth(); $periodStart->lessThanOrEqualTo($end); $periodStart = $periodStart->addMonth()->startOfMonth()) {
            $periodEnd = $periodStart->endOfMonth();
            $rowStart = $periodStart->lessThan($start) ? $start : $periodStart;
            $rowEnd = $periodEnd->greaterThan($end) ? $end : $periodEnd;
            $periodDeliveries = collect($deliveries)
                ->filter(function (array $delivery) use ($rowStart, $rowEnd): bool {
                    $createdAt = $delivery['created_at'] ?? null;
                    if (! $createdAt) {
                        return false;
                    }

                    return CarbonImmutable::parse((string) $createdAt)->betweenIncluded($rowStart->startOfDay(), $rowEnd->endOfDay());
                })
                ->values()
                ->all();

            $rows[] = $this->buildPeriodRow($rowStart, $rowEnd, $periodDeliveries, $options);
        }

        return array_reverse($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $seasonRows
     * @return array<string, array<string, mixed>>
     */
    public function buildAudienceRhythmSummary(array $seasonRows): array
    {
        $audiences = [
            'staff' => 'has_staff_review_packet',
            'director' => 'has_director_packet',
            'parents' => 'has_parent_safe_summary',
            'players' => 'has_player_development_summary',
            'coach' => 'has_internal_qa_packet',
        ];
        $periods = max(1, count($seasonRows));
        $summary = [];

        foreach ($audiences as $audience => $field) {
            $reached = collect($seasonRows)->filter(fn (array $row): bool => (bool) ($row[$field] ?? false));
            $percentage = $this->percent($reached->count(), $periods);

            $summary[$audience] = [
                'periods_reached' => $reached->count(),
                'last_reached_at' => $reached->first()['period_end_date'] ?? null,
                'percentage' => $percentage,
                'status' => $reached->count() === 0
                    ? 'not_reached'
                    : ($percentage >= 75.0 ? 'consistent' : 'inconsistent'),
            ];
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $seasonRows
     * @return array<int, array<string, mixed>>
     */
    public function buildTemplateRhythmSummary(array $seasonRows): array
    {
        return collect($seasonRows)
            ->flatMap(fn (array $row) => Arr::wrap($row['template_records'] ?? []))
            ->groupBy(fn (array $record): string => (string) ($record['template_key'] ?? 'unknown'))
            ->map(function ($records, string $templateKey): array {
                $lastUsedAt = $records->max('last_used_at');

                return [
                    'template_key' => $templateKey,
                    'display_name' => $this->templateDisplayName($templateKey),
                    'periods_used' => $records->pluck('period_label')->filter()->unique()->count(),
                    'total_uses' => $records->sum(fn (array $record): int => (int) ($record['total_uses'] ?? 0)),
                    'sent_count' => $records->sum(fn (array $record): int => (int) ($record['sent_count'] ?? 0)),
                    'blocked_count' => $records->sum(fn (array $record): int => (int) ($record['blocked_count'] ?? 0)),
                    'last_used_at' => $lastUsedAt,
                ];
            })
            ->sortByDesc('total_uses')
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $seasonRows
     * @return array<string, mixed>
     */
    public function buildRhythmScore(array $seasonRows): array
    {
        $periods = max(1, count($seasonRows));
        $withAny = collect($seasonRows)->where('has_any_packet', true)->count();
        $withStaff = collect($seasonRows)->where('has_staff_review_packet', true)->count();
        $withParent = collect($seasonRows)->where('has_parent_safe_summary', true)->count();
        $withPlayer = collect($seasonRows)->where('has_player_development_summary', true)->count();
        $blockedOrFailedPeriods = collect($seasonRows)->filter(fn (array $row): bool => ((int) ($row['blocked_count'] ?? 0) + (int) ($row['failed_count'] ?? 0) + (int) ($row['unsupported_count'] ?? 0)) > 0)->count();

        $communicationPercentage = $this->percent($withAny, $periods);
        $staffPercentage = $this->percent($withStaff, $periods);
        $parentPercentage = $this->percent($withParent, $periods);
        $playerPercentage = $this->percent($withPlayer, $periods);
        $blockedFailedRate = $this->percent($blockedOrFailedPeriods, $periods);

        $score = round(($communicationPercentage * 0.4) + ($parentPercentage * 0.25) + ($staffPercentage * 0.2) + ($playerPercentage * 0.15) - min(25.0, $blockedFailedRate * 0.35), 1);
        $score = max(0.0, min(100.0, $score));

        $label = match (true) {
            $withAny === 0 => 'no_activity',
            $communicationPercentage >= 90.0 && $parentPercentage >= 75.0 && $staffPercentage >= 75.0 && $blockedFailedRate <= 15.0 => 'excellent',
            $communicationPercentage >= 70.0 && max($parentPercentage, $staffPercentage, $playerPercentage) >= 50.0 && $blockedFailedRate <= 30.0 => 'good',
            $communicationPercentage >= 40.0 => 'inconsistent',
            default => 'needs_attention',
        };

        if ($withAny > 0 && $blockedFailedRate >= 40.0) {
            $label = 'needs_attention';
            $score = min($score, 45.0);
        }

        return [
            'score_0_100' => $score,
            'label' => $label,
            'periods_with_any_packet' => $withAny,
            'periods_with_staff_packet' => $withStaff,
            'periods_with_parent_summary' => $withParent,
            'periods_with_player_summary' => $withPlayer,
            'communication_percentage' => $communicationPercentage,
            'staff_packet_percentage' => $staffPercentage,
            'parent_summary_percentage' => $parentPercentage,
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
        $rows = Arr::wrap($rhythm['season_rows'] ?? []);
        $latestPeriod = $rows[0] ?? [];
        $score = Arr::wrap($rhythm['rhythm_score'] ?? []);
        $audience = Arr::wrap($rhythm['audience_summary'] ?? []);
        $health = Arr::wrap($rhythm['delivery_health_summary'] ?? []);

        if (! (bool) ($latestPeriod['has_any_packet'] ?? false)) {
            $actions[] = $this->action(
                'prepare_season_review_packet',
                'high',
                'Prepare Season Review Packet',
                'No season archive packet activity is recorded for the current period.',
                'Generate a staff review packet from the Season Development Archive.',
                'create_staff_packet',
                'staff_review_packet',
                'staff',
            );
        }

        if (($audience['parents']['status'] ?? 'not_reached') !== 'consistent') {
            $actions[] = $this->action(
                'create_parent_safe_season_summary',
                'high',
                'Create Parent-Safe Season Summary',
                'Parent-safe season summaries are not consistent across analyzed periods.',
                'Use the Parent-Safe Season Summary template to share development progress without private review details.',
                'create_parent_summary',
                'parent_safe_season_summary',
                'parents',
            );
        }

        if (($audience['staff']['status'] ?? 'not_reached') !== 'consistent') {
            $actions[] = $this->action(
                'create_staff_review_packet',
                'medium',
                'Create Staff Review Packet',
                'Staff season review packets are not being prepared consistently.',
                'Use the Staff Review Packet template to align coaches on player development progress.',
                'create_staff_packet',
                'staff_review_packet',
                'staff',
            );
        }

        if ((int) ($score['periods_with_player_summary'] ?? 0) === 0) {
            $actions[] = $this->action(
                'create_player_development_summary',
                'medium',
                'Create Player Development Summary',
                'No player-facing season development summaries were recorded in this window.',
                'Use the Player Development Summary template for player-facing season progress.',
                'create_player_summary',
                'player_development_summary',
                'players',
            );
        }

        if ((int) ($health['privacy_block_count'] ?? 0) > 0 || (int) ($health['blocked_count'] ?? 0) > 0 || (int) ($health['unsupported_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'review_audience_safety',
                'high',
                'Review Audience Safety',
                'Some season packets were blocked, unsupported, or had safety warnings.',
                'Use parent-safe templates for parents and staff/director packets for internal review.',
                'review_blocked_packets',
                null,
                null,
            );
        }

        if ((float) ($health['copy_only_rate'] ?? 0) >= 50.0 && (int) ($health['copy_only_count'] ?? 0) > 1) {
            $actions[] = $this->action(
                'improve_delivery_setup',
                'medium',
                'Improve Delivery Setup',
                'Many season packets are copy-only instead of sent or drafted through FMTRX.',
                'Use copy/share for now or configure a supported delivery channel when available.',
                'configure_delivery',
                null,
                null,
            );
        }

        if ((int) ($health['missing_contact_warning_count'] ?? 0) > 0 || (int) ($health['unsafe_recipient_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'update_recipient_contacts',
                'medium',
                'Update Recipient Contacts',
                'Some season packets could not include all intended recipients.',
                'Update staff, player, or parent contact information before the next season packet.',
                'configure_contacts',
                null,
                null,
            );
        }

        if (($score['label'] ?? '') === 'excellent') {
            $actions[] = $this->action(
                'keep_season_communication_rhythm',
                'low',
                'Keep Season Communication Rhythm',
                'Season communication rhythm is strong across staff and parent-safe summaries.',
                'Continue preparing season archive packets at the end of each development block.',
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
        $months = $this->monthsBetween($start, $end);
        $seasonRows = $this->buildSeasonRows($deliveries, [
            ...$options,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'months' => $months,
        ]);
        $health = $this->buildDeliveryHealthSummary($deliveries);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'team_id' => $options['team_id'] ?? null,
            'coach_user_id' => $options['coach_user_id'] ?? null,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'months_analyzed' => count($seasonRows),
            'rhythm_score' => $this->buildRhythmScore($seasonRows),
            'season_rows' => $seasonRows,
            'audience_summary' => $this->buildAudienceRhythmSummary($seasonRows),
            'template_summary' => $this->buildTemplateRhythmSummary($seasonRows),
            'delivery_health_summary' => $health,
            'missed_periods' => $this->buildMissedPeriods($seasonRows),
            'streaks' => $this->buildStreaks($seasonRows),
            'recommended_actions' => [],
            'warnings' => $this->warnings($deliveries, $seasonRows, $health),
        ];
        $payload['recommended_actions'] = $this->buildCommunicationRecommendations($payload);

        return $payload;
    }

    private function deliveryQuery(string $scope, array $options, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        $query = WeeklyReportDelivery::query()
            ->where('source', 'season_archive')
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
        $months = max(1, min(24, (int) ($options['months'] ?? 6)));
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->startOfMonth()->subMonths($months - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            return [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    private function monthsBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return max(1, ((int) $start->startOfMonth()->diffInMonths($end->endOfMonth())) + 1);
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildPeriodRow(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, array $deliveries, array $options): array
    {
        $activeDeliveries = collect($deliveries)->filter(fn (array $delivery): bool => $this->isActivePacket($delivery));
        $templates = collect($deliveries)->pluck('template_key')->filter()->unique()->values()->all();
        $audiences = collect($activeDeliveries)->pluck('audience')->filter()->unique()->values()->all();
        $channels = collect($deliveries)->pluck('channel')->filter()->unique()->values()->all();
        $preparedCount = collect($deliveries)->where('delivery_status', 'prepared')->count();
        $draftCreatedCount = collect($deliveries)->where('delivery_status', 'draft_created')->count();
        $copyOnlyCount = collect($deliveries)->where('delivery_status', 'copy_only')->count();
        $sentCount = collect($deliveries)->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['sent', 'partial'], true))->count();
        $blockedCount = collect($deliveries)->where('delivery_status', 'blocked')->count();
        $unsupportedCount = collect($deliveries)->where('delivery_status', 'unsupported')->count();
        $failedCount = collect($deliveries)->where('delivery_status', 'failed')->count();
        $hasStaff = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'staff' || (string) $delivery['template_key'] === 'staff_review_packet');
        $hasDirector = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'director' || (string) $delivery['template_key'] === 'director_packet');
        $hasParent = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'parents' || (string) $delivery['template_key'] === 'parent_safe_season_summary');
        $hasPlayer = $activeDeliveries->contains(fn (array $delivery): bool => (string) $delivery['audience'] === 'players' || (string) $delivery['template_key'] === 'player_development_summary');
        $hasInternal = $activeDeliveries->contains(fn (array $delivery): bool => in_array((string) $delivery['audience'], ['coach', 'internal'], true) || (string) $delivery['template_key'] === 'internal_qa_packet');
        $hasAny = $activeDeliveries->isNotEmpty();
        $missedAudiences = $this->missedAudiences($hasStaff, $hasParent, $hasPlayer, $hasInternal, $options);

        return [
            'period_start_date' => $periodStart->toDateString(),
            'period_end_date' => $periodEnd->toDateString(),
            'period_label' => $periodStart->format('M Y'),
            'has_any_packet' => $hasAny,
            'has_staff_review_packet' => $hasStaff,
            'has_director_packet' => $hasDirector,
            'has_parent_safe_summary' => $hasParent,
            'has_player_development_summary' => $hasPlayer,
            'has_internal_qa_packet' => $hasInternal,
            'prepared_count' => $preparedCount,
            'copy_only_count' => $copyOnlyCount,
            'draft_created_count' => $draftCreatedCount,
            'sent_count' => $sentCount,
            'blocked_count' => $blockedCount,
            'unsupported_count' => $unsupportedCount,
            'failed_count' => $failedCount,
            'templates_used' => $templates,
            'template_records' => $this->buildTemplateRecords($deliveries, $periodStart->format('M Y')),
            'audiences_reached' => $audiences,
            'channels_used' => $channels,
            'missed_audiences' => $missedAudiences,
            'status_label' => $this->periodStatus($hasAny, $copyOnlyCount, $sentCount, $blockedCount, $unsupportedCount, $failedCount, $missedAudiences),
            'recommended_action' => $this->periodAction($hasAny, $missedAudiences, $blockedCount, $unsupportedCount, $failedCount, $copyOnlyCount),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateRecords(array $deliveries, string $periodLabel): array
    {
        return collect($deliveries)
            ->groupBy(fn (array $delivery): string => (string) (($delivery['template_key'] ?? '') ?: 'unknown'))
            ->map(function ($records, string $templateKey) use ($periodLabel): array {
                return [
                    'period_label' => $periodLabel,
                    'template_key' => $templateKey,
                    'display_name' => $this->templateDisplayName($templateKey),
                    'total_uses' => $records->count(),
                    'sent_count' => $records->filter(fn (array $record): bool => in_array((string) ($record['delivery_status'] ?? ''), ['sent', 'partial'], true))->count(),
                    'blocked_count' => $records->filter(fn (array $record): bool => in_array((string) ($record['delivery_status'] ?? ''), ['blocked', 'unsupported'], true))->count(),
                    'last_used_at' => $records->max('created_at'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildDeliveryHealthSummary(array $deliveries): array
    {
        $total = count($deliveries);
        $sent = collect($deliveries)->where('delivery_status', 'sent')->count();
        $partial = collect($deliveries)->where('delivery_status', 'partial')->count();
        $copyOnly = collect($deliveries)->where('delivery_status', 'copy_only')->count();
        $draftCreated = collect($deliveries)->where('delivery_status', 'draft_created')->count();
        $prepared = collect($deliveries)->where('delivery_status', 'prepared')->count();
        $blocked = collect($deliveries)->where('delivery_status', 'blocked')->count();
        $unsupported = collect($deliveries)->where('delivery_status', 'unsupported')->count();
        $failed = collect($deliveries)->where('delivery_status', 'failed')->count();

        return [
            'total_records' => $total,
            'sent_count' => $sent + $partial,
            'copy_only_count' => $copyOnly,
            'draft_created_count' => $draftCreated,
            'prepared_count' => $prepared,
            'blocked_count' => $blocked,
            'unsupported_count' => $unsupported,
            'failed_count' => $failed,
            'copy_only_rate' => $this->percent($copyOnly, $total),
            'blocked_rate' => $this->percent($blocked + $unsupported, $total),
            'unsupported_rate' => $this->percent($unsupported, $total),
            'failed_rate' => $this->percent($failed, $total),
            'send_success_rate' => $this->percent($sent + $partial, $total),
            'privacy_block_count' => collect($deliveries)->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->containsAny(Arr::wrap($delivery['warning_text'] ?? []), ['private', 'staff', 'internal']))->count(),
            'missing_contact_warning_count' => collect($deliveries)->filter(fn (array $delivery): bool => $this->missingContactCount($delivery) > 0 || $this->containsAny(Arr::wrap($delivery['warning_text'] ?? []), ['missing contact', 'missing email', 'no safe recipients']))->count(),
            'unsafe_recipient_count' => collect($deliveries)->sum(fn (array $delivery): int => $this->unsafeRecipientCount($delivery)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $seasonRows
     * @return array<int, array<string, mixed>>
     */
    private function buildMissedPeriods(array $seasonRows): array
    {
        return collect($seasonRows)
            ->filter(fn (array $row): bool => ! (bool) ($row['has_any_packet'] ?? false) || ! empty(Arr::wrap($row['missed_audiences'] ?? [])))
            ->map(fn (array $row): array => [
                'period_start_date' => $row['period_start_date'],
                'period_end_date' => $row['period_end_date'],
                'period_label' => $row['period_label'],
                'missed_audiences' => ! (bool) ($row['has_any_packet'] ?? false) ? ['season_packet'] : Arr::wrap($row['missed_audiences'] ?? []),
                'recommended_action' => (string) ($row['recommended_action'] ?? 'Prepare a season development packet.'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $seasonRows
     * @return array<string, int>
     */
    private function buildStreaks(array $seasonRows): array
    {
        return [
            'current_any_packet_streak' => $this->currentStreak($seasonRows, 'has_any_packet'),
            'current_staff_packet_streak' => $this->currentStreak($seasonRows, 'has_staff_review_packet'),
            'current_parent_summary_streak' => $this->currentStreak($seasonRows, 'has_parent_safe_summary'),
            'longest_any_packet_streak' => $this->longestStreak($seasonRows, 'has_any_packet'),
            'longest_parent_summary_streak' => $this->longestStreak($seasonRows, 'has_parent_safe_summary'),
        ];
    }

    private function currentStreak(array $seasonRows, string $field): int
    {
        $streak = 0;
        foreach ($seasonRows as $row) {
            if (! (bool) ($row[$field] ?? false)) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function longestStreak(array $seasonRows, string $field): int
    {
        $longest = 0;
        $current = 0;
        foreach (array_reverse($seasonRows) as $row) {
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
    private function warnings(array $deliveries, array $seasonRows, array $health): array
    {
        $warnings = [];
        if (empty($deliveries)) {
            $warnings[] = 'No season communication history was found for this date range.';
        }
        if (collect($seasonRows)->where('has_any_packet', false)->isNotEmpty()) {
            $warnings[] = 'One or more periods have no season archive packet activity.';
        }
        if ((int) ($health['blocked_count'] ?? 0) > 0 || (int) ($health['unsupported_count'] ?? 0) > 0) {
            $warnings[] = 'Some season archive deliveries were blocked or unsupported.';
        }
        if ((float) ($health['copy_only_rate'] ?? 0) >= 50.0 && (int) ($health['copy_only_count'] ?? 0) > 1) {
            $warnings[] = 'Many season archive packets are copy-only. Delivery setup may need review.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDelivery(WeeklyReportDelivery $delivery): array
    {
        $templateKey = (string) ($delivery->archive_type ?: $delivery->template_key ?: '');
        $warningText = [
            ...Arr::wrap($delivery->privacy_warnings),
            ...Arr::wrap($delivery->delivery_warnings),
            ...Arr::wrap($delivery->send_blockers),
        ];

        return [
            'delivery_id' => (string) $delivery->id,
            'team_id' => (string) $delivery->team_id,
            'source' => 'season_archive',
            'template_key' => $templateKey,
            'archive_type' => $templateKey,
            'template_display_name' => $this->templateDisplayName($templateKey),
            'audience' => (string) ($delivery->audience ?? ''),
            'channel' => (string) ($delivery->channel ?? ''),
            'format' => (string) ($delivery->format ?? ''),
            'delivery_status' => (string) ($delivery->delivery_status ?? 'prepared'),
            'recipient_summary' => Arr::wrap($delivery->recipient_summary),
            'privacy_warning_count' => count(Arr::wrap($delivery->privacy_warnings)),
            'delivery_warning_count' => count(Arr::wrap($delivery->delivery_warnings)),
            'send_blocker_count' => count(Arr::wrap($delivery->send_blockers)),
            'warning_count' => count($warningText),
            'created_at' => $delivery->created_at?->toIso8601String(),
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'copied_at' => $delivery->copied_at?->toIso8601String(),
            'draft_created_at' => $delivery->draft_created_at?->toIso8601String(),
            'blocked_at' => $delivery->blocked_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'warning_text' => $warningText,
        ];
    }

    private function missedAudiences(bool $hasStaff, bool $hasParent, bool $hasPlayer, bool $hasInternal, array $options): array
    {
        $missing = [];
        if ($this->optionBool($options, 'include_staff_packets', true) && ! $hasStaff) {
            $missing[] = 'staff';
        }
        if ($this->optionBool($options, 'include_parent_summaries', true) && ! $hasParent) {
            $missing[] = 'parents';
        }
        if ($this->optionBool($options, 'include_player_summaries', true) && ! $hasPlayer) {
            $missing[] = 'players';
        }
        if ($this->optionBool($options, 'include_internal_qa', true) && ! $hasInternal) {
            $missing[] = 'coach';
        }

        return $missing;
    }

    private function periodStatus(bool $hasAny, int $copyOnlyCount, int $sentCount, int $blockedCount, int $unsupportedCount, int $failedCount, array $missedAudiences): string
    {
        if ($unsupportedCount > 0 && $sentCount === 0 && $copyOnlyCount === 0) {
            return 'unsupported';
        }
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

    private function periodAction(bool $hasAny, array $missedAudiences, int $blockedCount, int $unsupportedCount, int $failedCount, int $copyOnlyCount): ?string
    {
        if (($blockedCount + $unsupportedCount + $failedCount) > 0) {
            return 'Review blocked, unsupported, or failed season packet attempts before sharing externally.';
        }
        if (! $hasAny) {
            return 'Prepare a season development packet for this period.';
        }
        if ($copyOnlyCount > 0) {
            return 'Confirm copied season packets were shared outside FMTRX.';
        }
        if (! empty($missedAudiences)) {
            return 'Add a '.implode(', ', $missedAudiences).' season summary for this period.';
        }

        return null;
    }

    private function isActivePacket(array $delivery): bool
    {
        return in_array((string) ($delivery['delivery_status'] ?? ''), self::ACTIVE_STATUSES, true);
    }

    private function isBlocked(array $delivery): bool
    {
        return in_array((string) ($delivery['delivery_status'] ?? ''), ['blocked', 'unsupported'], true);
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
        return self::TEMPLATE_LABELS[$templateKey] ?? $this->humanLabel($templateKey ?: 'unknown');
    }

    private function missingContactCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['missing_contact_count'] ?? 0) ?: 0);
    }

    private function unsafeRecipientCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['unsafe_recipient_count'] ?? 0) ?: 0);
    }

    private function containsAny(array $values, array $needles): bool
    {
        $text = strtolower(implode(' ', array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES), $values)));
        foreach ($needles as $needle) {
            if (str_contains($text, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
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
            'priority' => $priority,
            'title' => $title,
            'why' => $why,
            'action' => $action,
            'action_type' => $actionType,
            'template_key' => $templateKey,
            'audience' => $audience,
        ];
    }
}
