<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class WeeklyReportDeliveryAnalyticsService
{
    public function __construct(
        private readonly WeeklyReportTemplateService $templateService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTeamAnalytics(string $teamId, array $options = []): array
    {
        return $this->buildAnalytics('team', [
            ...$options,
            'team_id' => $teamId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCoachAnalytics(?string $coachUserId = null, array $options = []): array
    {
        return $this->buildAnalytics('coach', [
            ...$options,
            'coach_user_id' => $coachUserId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildGlobalAnalytics(array $options = []): array
    {
        return $this->buildAnalytics('global', $options);
    }

    /**
     * @param array<int, mixed> $deliveries
     * @return array<int, array<string, mixed>>
     */
    public function buildTemplateUsageSummary(array $deliveries): array
    {
        $rows = $this->normalizeDeliveries($deliveries);
        $total = max(1, count($rows));

        return collect($rows)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['template_key'] ?: 'unknown'))
            ->map(function ($items, string $templateKey) use ($total): array {
                $records = $items->values();
                $statusCounts = $this->statusCounts($records->all());

                return [
                    'template_key' => $templateKey,
                    'display_name' => $this->templateDisplayName($templateKey),
                    'count' => $records->count(),
                    'percent' => $this->percent($records->count(), $total),
                    'sent_count' => (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0)),
                    'blocked_count' => (int) (($statusCounts['blocked'] ?? 0) + ($statusCounts['unsupported'] ?? 0)),
                    'copy_only_count' => (int) ($statusCounts['copy_only'] ?? 0),
                    'status_counts' => $statusCounts,
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $deliveries
     * @return array<int, array<string, mixed>>
     */
    public function buildAudienceUsageSummary(array $deliveries): array
    {
        $rows = $this->normalizeDeliveries($deliveries);
        $total = max(1, count($rows));

        return collect($rows)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['audience'] ?: 'unknown'))
            ->map(function ($items, string $audience) use ($total): array {
                $records = $items->values();

                return [
                    'audience' => $audience,
                    'display_name' => $this->humanLabel($audience),
                    'count' => $records->count(),
                    'percent' => $this->percent($records->count(), $total),
                    'recipient_count' => $records->sum(fn (array $delivery): int => $this->recipientCount($delivery)),
                    'safe_recipient_count' => $records->sum(fn (array $delivery): int => $this->safeRecipientCount($delivery)),
                    'missing_contact_count' => $records->sum(fn (array $delivery): int => $this->missingContactCount($delivery)),
                    'unsafe_recipient_count' => $records->sum(fn (array $delivery): int => $this->unsafeRecipientCount($delivery)),
                    'blocked_count' => $records->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['blocked', 'unsupported'], true))->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $deliveries
     * @return array<string, mixed>
     */
    public function buildDeliveryHealthSummary(array $deliveries): array
    {
        $rows = $this->normalizeDeliveries($deliveries);
        $total = count($rows);
        $statusCounts = $this->statusCounts($rows);
        $sentOrPartial = (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0));
        $blockedOrUnsupported = (int) (($statusCounts['blocked'] ?? 0) + ($statusCounts['unsupported'] ?? 0));
        $failed = (int) ($statusCounts['failed'] ?? 0);
        $copyOnly = (int) ($statusCounts['copy_only'] ?? 0);
        $recipientTotal = array_sum(array_map(fn (array $delivery): int => $this->recipientCount($delivery), $rows));

        return [
            'total_deliveries' => $total,
            'delivery_success_rate' => $this->percent($sentOrPartial, $total),
            'blocked_rate' => $this->percent($blockedOrUnsupported, $total),
            'failed_rate' => $this->percent($failed, $total),
            'copy_only_rate' => $this->percent($copyOnly, $total),
            'privacy_block_count' => $this->privacyBlockCount($rows),
            'missing_contact_warning_count' => $this->missingContactWarningCount($rows),
            'unsafe_recipient_count' => array_sum(array_map(fn (array $delivery): int => $this->unsafeRecipientCount($delivery), $rows)),
            'total_recipients_targeted' => $recipientTotal,
            'total_recipients_sent' => array_sum(array_map(fn (array $delivery): int => $this->sentRecipientCount($delivery), $rows)),
            'average_recipients_per_delivery' => $total > 0 ? round($recipientTotal / $total, 1) : 0.0,
            'last_delivery_at' => $rows[0]['created_at'] ?? null,
            'last_successful_delivery_at' => collect($rows)
                ->first(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['sent', 'partial'], true))['sent_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $analytics
     * @return array<int, array<string, mixed>>
     */
    public function buildRecommendedActions(array $analytics): array
    {
        $health = Arr::wrap($analytics['delivery_health'] ?? []);
        $summary = Arr::wrap($analytics['summary'] ?? []);
        $privacy = Arr::wrap($analytics['privacy_safety_summary'] ?? []);
        $actions = [];

        if ((int) ($summary['total_deliveries'] ?? 0) === 0) {
            $actions[] = $this->action(
                'prepare_first_weekly_report',
                'medium',
                'Prepare a Weekly Report',
                'No weekly report deliveries were recorded in this window.',
                'Generate a weekly team report, review the audience, and prepare the copy-safe version.',
                ['total_deliveries' => 0],
            );
        }

        if ((int) ($health['privacy_block_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'review_privacy_blocks',
                'high',
                'Review Blocked Reports',
                'FMTRX blocked report sharing because the selected audience or template could expose private content.',
                'Use parent/player-safe templates for external audiences and keep staff/internal templates inside the staff workflow.',
                ['privacy_block_count' => (int) $health['privacy_block_count']],
            );
        }

        if ((int) ($health['missing_contact_warning_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'update_report_contacts',
                'medium',
                'Clean Up Contact Info',
                'Some report recipients were missing contact information or were not safe to send.',
                'Update parent/player contact records before using direct delivery channels.',
                [
                    'missing_contact_warning_count' => (int) $health['missing_contact_warning_count'],
                    'unsafe_recipient_count' => (int) ($health['unsafe_recipient_count'] ?? 0),
                ],
            );
        }

        if ((int) ($summary['unsupported_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'configure_delivery_channel',
                'medium',
                'Configure Delivery Channel',
                'At least one requested delivery channel was not configured.',
                'Use copy-only delivery for now or configure the channel before sending from FMTRX.',
                ['unsupported_count' => (int) $summary['unsupported_count']],
            );
        }

        if ((int) ($summary['failed_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'inspect_failed_delivery',
                'medium',
                'Inspect Failed Delivery',
                'A delivery attempt failed after it passed the report preparation step.',
                'Review the failed send result and retry with copy-only if the channel is unreliable.',
                ['failed_count' => (int) $summary['failed_count']],
            );
        }

        if ((int) ($summary['copy_only_count'] ?? 0) > (int) ($summary['sent_count'] ?? 0) && (int) ($summary['copy_only_count'] ?? 0) > 2) {
            $actions[] = $this->action(
                'standardize_weekly_report_delivery',
                'low',
                'Standardize Weekly Sharing',
                'Most weekly reports are being copied manually.',
                'Keep copy-only for sensitive audiences, but consider a configured delivery channel for staff-safe reports.',
                [
                    'copy_only_count' => (int) $summary['copy_only_count'],
                    'sent_count' => (int) $summary['sent_count'],
                ],
            );
        }

        if (! empty($privacy['parent_safe_reports_prepared']) || ! empty($privacy['player_safe_reports_prepared'])) {
            $actions[] = $this->action(
                'keep_safe_report_rhythm',
                'low',
                'Keep the Weekly Report Rhythm',
                'Parent/player-safe reports are being prepared without exposing internal review details.',
                'Continue using safe templates and check delivery history after each weekly report cycle.',
                [
                    'parent_safe_reports_prepared' => (int) ($privacy['parent_safe_reports_prepared'] ?? 0),
                    'player_safe_reports_prepared' => (int) ($privacy['player_safe_reports_prepared'] ?? 0),
                ],
            );
        }

        return collect($actions)
            ->sortBy(fn (array $action): int => ['high' => 0, 'medium' => 1, 'low' => 2][$action['priority']] ?? 3)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAnalytics(string $scope, array $options): array
    {
        [$startDate, $endDate] = $this->dateWindow($options);
        $query = $this->baseQuery($options, $startDate, $endDate);

        if ($scope === 'team') {
            $query->where('team_id', (string) ($options['team_id'] ?? ''));
        } elseif ($scope === 'coach' && ! empty($options['coach_user_id'])) {
            $coachUserId = (string) $options['coach_user_id'];
            $query->where(function (Builder $builder) use ($coachUserId): void {
                $builder->where('created_by_user_id', $coachUserId)
                    ->orWhere('sent_by_user_id', $coachUserId);
            });
        }

        $deliveries = $query
            ->latest('created_at')
            ->get()
            ->map(fn (WeeklyReportDelivery $delivery): array => $this->normalizeDelivery($delivery))
            ->values()
            ->all();

        $statusCounts = $this->statusCounts($deliveries);
        $health = $this->buildDeliveryHealthSummary($deliveries);
        $summary = $this->buildSummary($deliveries, $statusCounts, $health);
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'team_id' => $options['team_id'] ?? null,
            'coach_user_id' => $options['coach_user_id'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => $summary,
            'status_counts' => $statusCounts,
            'template_usage' => $this->buildTemplateUsageSummary($deliveries),
            'audience_usage' => $this->buildAudienceUsageSummary($deliveries),
            'channel_usage' => $this->buildChannelUsageSummary($deliveries),
            'delivery_health' => $health,
            'privacy_safety_summary' => $this->buildPrivacySafetySummary($deliveries),
            'recent_deliveries' => array_slice($deliveries, 0, 10),
            'recommended_actions' => [],
            'warnings' => $this->buildWarnings($deliveries, $health),
        ];
        $payload['recommended_actions'] = $this->buildRecommendedActions($payload);

        return $payload;
    }

    private function baseQuery(array $options, CarbonImmutable $startDate, CarbonImmutable $endDate): Builder
    {
        $query = WeeklyReportDelivery::query()
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        foreach (['audience', 'channel', 'delivery_status'] as $field) {
            $optionKey = $field === 'delivery_status' ? 'status' : $field;
            if (! empty($options[$optionKey])) {
                $query->where($field, (string) $options[$optionKey]);
            }
        }
        if (! empty($options['template'])) {
            $query->where('template_key', (string) $options['template']);
        }
        if (! empty($options['template_key'])) {
            $query->where('template_key', (string) $options['template_key']);
        }

        return $query;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateWindow(array $options): array
    {
        $end = ! empty($options['end_date'])
            ? CarbonImmutable::parse((string) $options['end_date'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();
        $start = ! empty($options['start_date'])
            ? CarbonImmutable::parse((string) $options['start_date'])->startOfDay()
            : $end->subDays(max(1, min(365, (int) ($options['days'] ?? 30))) - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            return [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildSummary(array $deliveries, array $statusCounts, array $health): array
    {
        $total = count($deliveries);

        return [
            'total_deliveries' => $total,
            'prepared_count' => (int) ($statusCounts['prepared'] ?? 0),
            'copy_only_count' => (int) ($statusCounts['copy_only'] ?? 0),
            'draft_created_count' => (int) ($statusCounts['draft_created'] ?? 0),
            'sent_count' => (int) ($statusCounts['sent'] ?? 0),
            'partial_count' => (int) ($statusCounts['partial'] ?? 0),
            'blocked_count' => (int) ($statusCounts['blocked'] ?? 0),
            'unsupported_count' => (int) ($statusCounts['unsupported'] ?? 0),
            'failed_count' => (int) ($statusCounts['failed'] ?? 0),
            'sent_or_partial_count' => (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0)),
            'recipients_targeted' => (int) ($health['total_recipients_targeted'] ?? 0),
            'recipients_sent' => (int) ($health['total_recipients_sent'] ?? 0),
            'last_delivery_at' => $deliveries[0]['created_at'] ?? null,
            'last_successful_delivery_at' => $health['last_successful_delivery_at'] ?? null,
            'delivery_success_rate' => $health['delivery_success_rate'] ?? 0.0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, array<string, mixed>>
     */
    private function buildChannelUsageSummary(array $deliveries): array
    {
        $total = max(1, count($deliveries));

        return collect($deliveries)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['channel'] ?: 'unknown'))
            ->map(function ($items, string $channel) use ($total): array {
                $records = $items->values();

                return [
                    'channel' => $channel,
                    'display_name' => $this->humanLabel($channel),
                    'count' => $records->count(),
                    'percent' => $this->percent($records->count(), $total),
                    'sent_count' => $records->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['sent', 'partial'], true))->count(),
                    'blocked_count' => $records->filter(fn (array $delivery): bool => in_array((string) $delivery['delivery_status'], ['blocked', 'unsupported'], true))->count(),
                    'failed_count' => $records->filter(fn (array $delivery): bool => (string) $delivery['delivery_status'] === 'failed')->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, mixed>
     */
    private function buildPrivacySafetySummary(array $deliveries): array
    {
        return [
            'blocked_internal_to_parent_or_player' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isExternalAudience($delivery) && $this->mentions($delivery, ['internal', 'private']) && $this->isBlocked($delivery))
                ->count(),
            'blocked_staff_report_to_parent_or_player' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isExternalAudience($delivery) && $this->isStaffTemplate($delivery) && $this->isBlocked($delivery))
                ->count(),
            'blocked_unsafe_recipients' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && ($this->unsafeRecipientCount($delivery) > 0 || $this->mentions($delivery, ['unsafe recipient', 'no safe recipients', 'missing contact'])))
                ->count(),
            'parent_safe_reports_prepared' => collect($deliveries)
                ->filter(fn (array $delivery): bool => (string) $delivery['audience'] === 'parents' && (string) $delivery['template_key'] === 'parent_update' && ! $this->isBlocked($delivery))
                ->count(),
            'player_safe_reports_prepared' => collect($deliveries)
                ->filter(fn (array $delivery): bool => (string) $delivery['audience'] === 'players' && (string) $delivery['template_key'] === 'player_development_summary' && ! $this->isBlocked($delivery))
                ->count(),
            'private_note_leak_prevented_count' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->mentions($delivery, ['private', 'staff', 'internal']))
                ->count(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, string>
     */
    private function buildWarnings(array $deliveries, array $health): array
    {
        $warnings = [];
        if (count($deliveries) === 0) {
            $warnings[] = 'No weekly report deliveries were found in the selected window.';
        }
        if ((int) ($health['privacy_block_count'] ?? 0) > 0) {
            $warnings[] = 'Some report deliveries were blocked by privacy checks.';
        }
        if ((int) ($health['missing_contact_warning_count'] ?? 0) > 0) {
            $warnings[] = 'Some report deliveries had missing contact or unsafe recipient warnings.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param array<int, mixed> $deliveries
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDeliveries(array $deliveries): array
    {
        return collect($deliveries)
            ->map(fn (mixed $delivery): array => $delivery instanceof WeeklyReportDelivery ? $this->normalizeDelivery($delivery) : Arr::wrap($delivery))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDelivery(WeeklyReportDelivery $delivery): array
    {
        $recipientSummary = Arr::wrap($delivery->recipient_summary);
        $sendResult = Arr::wrap($delivery->send_result);

        return [
            'delivery_id' => (string) $delivery->id,
            'team_id' => (string) $delivery->team_id,
            'template_key' => (string) ($delivery->template_key ?? ''),
            'template_display_name' => $this->templateDisplayName((string) ($delivery->template_key ?? '')),
            'audience' => (string) ($delivery->audience ?? ''),
            'channel' => (string) ($delivery->channel ?? ''),
            'format' => (string) ($delivery->format ?? ''),
            'delivery_status' => (string) ($delivery->delivery_status ?? 'prepared'),
            'recipient_summary' => $recipientSummary,
            'privacy_warning_count' => count(Arr::wrap($delivery->privacy_warnings)),
            'delivery_warning_count' => count(Arr::wrap($delivery->delivery_warnings)),
            'send_blocker_count' => count(Arr::wrap($delivery->send_blockers)),
            'warning_count' => count(Arr::wrap($delivery->privacy_warnings)) + count(Arr::wrap($delivery->delivery_warnings)) + count(Arr::wrap($delivery->send_blockers)),
            'send_result' => [
                'send_status' => $sendResult['send_status'] ?? null,
                'sent_count' => (int) ($sendResult['sent_count'] ?? 0),
                'failed_count' => (int) ($sendResult['failed_count'] ?? 0),
                'skipped_count' => (int) ($sendResult['skipped_count'] ?? 0),
            ],
            'created_by_user_id' => $delivery->created_by_user_id,
            'sent_by_user_id' => $delivery->sent_by_user_id,
            'created_at' => $delivery->created_at?->toIso8601String(),
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'copied_at' => $delivery->copied_at?->toIso8601String(),
            'draft_created_at' => $delivery->draft_created_at?->toIso8601String(),
            'blocked_at' => $delivery->blocked_at?->toIso8601String(),
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'display_timestamp' => $delivery->sent_at?->toIso8601String()
                ?: $delivery->copied_at?->toIso8601String()
                ?: $delivery->blocked_at?->toIso8601String()
                ?: $delivery->failed_at?->toIso8601String()
                ?: $delivery->created_at?->toIso8601String(),
            'privacy_flags' => $this->privacyFlags($delivery),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<string, int>
     */
    private function statusCounts(array $deliveries): array
    {
        $counts = array_fill_keys(WeeklyReportDeliveryHistoryService::STATUSES, 0);
        foreach ($deliveries as $delivery) {
            $status = (string) ($delivery['delivery_status'] ?? 'prepared');
            if (! array_key_exists($status, $counts)) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }

        return $counts;
    }

    private function templateDisplayName(string $templateKey): string
    {
        $template = collect($this->templateService->listTemplates())
            ->first(fn (array $template): bool => (string) ($template['template_key'] ?? '') === $templateKey);

        return (string) ($template['display_name'] ?? $this->humanLabel($templateKey ?: 'unknown'));
    }

    private function privacyFlags(WeeklyReportDelivery $delivery): array
    {
        $warnings = [
            ...Arr::wrap($delivery->privacy_warnings),
            ...Arr::wrap($delivery->delivery_warnings),
            ...Arr::wrap($delivery->send_blockers),
        ];

        return [
            'has_privacy_warning' => $this->containsAny($warnings, ['private', 'staff', 'internal']),
            'has_private_content_warning' => $this->containsAny($warnings, ['private']),
            'has_staff_content_warning' => $this->containsAny($warnings, ['staff']),
            'has_internal_content_warning' => $this->containsAny($warnings, ['internal']),
            'has_missing_contact_warning' => $this->containsAny($warnings, ['missing contact', 'missing email', 'no safe recipients', 'unsafe recipient']),
            'has_delivery_blocker' => count(Arr::wrap($delivery->send_blockers)) > 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     */
    private function privacyBlockCount(array $deliveries): int
    {
        return collect($deliveries)
            ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && ($delivery['privacy_flags']['has_privacy_warning'] ?? false))
            ->count();
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     */
    private function missingContactWarningCount(array $deliveries): int
    {
        return collect($deliveries)
            ->filter(fn (array $delivery): bool => (bool) ($delivery['privacy_flags']['has_missing_contact_warning'] ?? false) || $this->missingContactCount($delivery) > 0)
            ->count();
    }

    private function recipientCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['total_recipients'] ?? 0) ?: 0);
    }

    private function safeRecipientCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['safe_recipients'] ?? 0) ?: 0);
    }

    private function missingContactCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['missing_contact_count'] ?? 0) ?: 0);
    }

    private function unsafeRecipientCount(array $delivery): int
    {
        return (int) (($delivery['recipient_summary']['unsafe_recipient_count'] ?? 0) ?: 0);
    }

    private function sentRecipientCount(array $delivery): int
    {
        return (int) (($delivery['send_result']['sent_count'] ?? 0) ?: 0);
    }

    private function isBlocked(array $delivery): bool
    {
        return in_array((string) ($delivery['delivery_status'] ?? ''), ['blocked', 'unsupported'], true);
    }

    private function isExternalAudience(array $delivery): bool
    {
        return in_array((string) ($delivery['audience'] ?? ''), ['parents', 'players'], true);
    }

    private function isStaffTemplate(array $delivery): bool
    {
        return in_array((string) ($delivery['template_key'] ?? ''), ['staff_report', 'detailed_coach_report', 'internal_benchmark_qa'], true);
    }

    private function mentions(array $delivery, array $needles): bool
    {
        $text = strtolower(json_encode([
            $delivery['template_key'] ?? '',
            $delivery['delivery_status'] ?? '',
            $delivery['privacy_flags'] ?? [],
        ], JSON_UNESCAPED_SLASHES) ?: '');

        foreach ($needles as $needle) {
            if (str_contains($text, strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function containsAny(array $values, array $needles): bool
    {
        $text = strtolower(implode(' ', array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value), $values)));
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
    private function action(string $id, string $priority, string $title, string $why, string $action, array $evidence): array
    {
        return [
            'id' => $id,
            'priority' => $priority,
            'title' => $title,
            'why' => $why,
            'action' => $action,
            'evidence' => $evidence,
        ];
    }
}
