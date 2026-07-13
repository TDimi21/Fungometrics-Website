<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class SeasonArchiveDeliveryAnalyticsService
{
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
     * @param array<int, mixed> $deliveries
     * @return array<int, array<string, mixed>>
     */
    public function buildTemplateUsageSummary(array $deliveries): array
    {
        $rows = $this->normalizeDeliveries($deliveries);

        return collect($rows)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['template_key'] ?: 'unknown'))
            ->map(function ($items, string $templateKey): array {
                $records = $items->values();
                $statusCounts = $this->statusCounts($records->all());

                return [
                    'template_key' => $templateKey,
                    'display_name' => $this->templateDisplayName($templateKey),
                    'count' => $records->count(),
                    'sent_count' => (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0)),
                    'blocked_count' => (int) (($statusCounts['blocked'] ?? 0) + ($statusCounts['unsupported'] ?? 0)),
                    'last_used_at' => $records->max('created_at'),
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

        return collect($rows)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['audience'] ?: 'unknown'))
            ->map(function ($items, string $audience): array {
                $records = $items->values();
                $statusCounts = $this->statusCounts($records->all());

                return [
                    'audience' => $audience,
                    'display_name' => $this->humanLabel($audience),
                    'count' => $records->count(),
                    'sent_count' => (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0)),
                    'blocked_count' => (int) (($statusCounts['blocked'] ?? 0) + ($statusCounts['unsupported'] ?? 0)),
                    'recipient_count' => $records->sum(fn (array $delivery): int => $this->recipientCount($delivery)),
                    'last_used_at' => $records->max('created_at'),
                    'status_counts' => $statusCounts,
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
        $copyOnly = (int) ($statusCounts['copy_only'] ?? 0);
        $failed = (int) ($statusCounts['failed'] ?? 0);

        return [
            'send_success_rate' => $this->percent($sentOrPartial, $total),
            'block_rate' => $this->percent($blockedOrUnsupported, $total),
            'unsupported_rate' => $this->percent((int) ($statusCounts['unsupported'] ?? 0), $total),
            'copy_only_rate' => $this->percent($copyOnly, $total),
            'failed_rate' => $this->percent($failed, $total),
            'privacy_block_count' => $this->privacyBlockCount($rows),
            'missing_contact_warning_count' => $this->missingContactWarningCount($rows),
            'unsafe_recipient_count' => array_sum(array_map(fn (array $delivery): int => $this->unsafeRecipientCount($delivery), $rows)),
            'total_recipients_attempted' => array_sum(array_map(fn (array $delivery): int => $this->recipientCount($delivery), $rows)),
            'total_recipients_sent' => array_sum(array_map(fn (array $delivery): int => $this->sentRecipientCount($delivery), $rows)),
        ];
    }

    /**
     * @param array<string, mixed> $analytics
     * @return array<int, array<string, mixed>>
     */
    public function buildRecommendedActions(array $analytics): array
    {
        $summary = Arr::wrap($analytics['summary'] ?? []);
        $health = Arr::wrap($analytics['delivery_health'] ?? []);
        $privacy = Arr::wrap($analytics['privacy_safety_summary'] ?? []);
        $actions = [];

        if ((int) ($summary['total_delivery_records'] ?? 0) === 0) {
            $actions[] = $this->action(
                'prepare_season_review_packet',
                'medium',
                'Prepare Season Review Packet',
                'Season archives help staff review development progress and plan the next training block.',
                'Generate a Staff Review Packet from the Season Development Archive.',
                'prepare_staff_packet',
            );
        }

        if ((int) ($privacy['parent_safe_packets_prepared'] ?? 0) === 0 && (int) ($summary['total_delivery_records'] ?? 0) > 0) {
            $actions[] = $this->action(
                'create_parent_safe_season_summary',
                'medium',
                'Create Parent-Safe Season Summary',
                'Parent-safe summaries communicate development progress without exposing private player review details.',
                'Use the Parent-Safe Season Summary template.',
                'prepare_parent_summary',
            );
        }

        if ((int) ($summary['copy_only_count'] ?? 0) > max(1, (int) ($summary['sent_count'] ?? 0))) {
            $actions[] = $this->action(
                'configure_season_packet_delivery',
                'low',
                'Configure Season Packet Delivery',
                'Season packets are being prepared but not sent through FMTRX.',
                'Use copy/share for now or configure a supported delivery channel.',
                'configure_delivery',
            );
        }

        if ((int) ($health['missing_contact_warning_count'] ?? 0) > 0 || (int) ($health['unsafe_recipient_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'update_recipient_contacts',
                'medium',
                'Update Recipient Contacts',
                'Some season packets could not include all intended recipients.',
                'Add missing staff, player, or parent contacts.',
                'configure_contacts',
            );
        }

        if ((int) ($health['privacy_block_count'] ?? 0) > 0 || (int) ($summary['blocked_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'review_audience_safety',
                'high',
                'Review Audience Safety',
                'Some packets were blocked because the selected template did not match the audience.',
                'Use parent-safe templates for parents and staff/director packets for internal review.',
                'review_blocked_packets',
            );
        }

        if ((int) ($summary['sent_count'] ?? 0) > 0 || (int) ($summary['partial_count'] ?? 0) > 0) {
            $actions[] = $this->action(
                'keep_season_review_rhythm',
                'low',
                'Keep Season Review Rhythm',
                'Season development summaries are being shared consistently.',
                'Continue sending season packets at the end of each development block.',
                'none',
            );
        }

        return collect($actions)
            ->unique('title')
            ->sortBy(fn (array $action): int => ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3][$action['priority']] ?? 4)
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
        $templateUsage = $this->buildTemplateUsageSummary($deliveries);
        $audienceUsage = $this->buildAudienceUsageSummary($deliveries);
        $channelUsage = $this->buildChannelUsageSummary($deliveries);
        $health = $this->buildDeliveryHealthSummary($deliveries);
        $summary = $this->buildSummary($deliveries, $statusCounts, $templateUsage, $audienceUsage, $channelUsage, $health);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'team_id' => $options['team_id'] ?? null,
            'coach_user_id' => $options['coach_user_id'] ?? null,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'summary' => $summary,
            'status_counts' => $statusCounts,
            'template_usage' => $templateUsage,
            'audience_usage' => $audienceUsage,
            'channel_usage' => $channelUsage,
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
            ->with(['createdBy.profile', 'sentBy.profile'])
            ->where('source', 'season_archive')
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
        if (! empty($options['archive_type'])) {
            $query->where('archive_type', (string) $options['archive_type']);
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
            : $end->subDays(max(1, min(365, (int) ($options['days'] ?? 365))) - 1)->startOfDay();

        if ($start->greaterThan($end)) {
            return [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @param array<int, array<string, mixed>> $templateUsage
     * @param array<int, array<string, mixed>> $audienceUsage
     * @param array<int, array<string, mixed>> $channelUsage
     * @return array<string, mixed>
     */
    private function buildSummary(array $deliveries, array $statusCounts, array $templateUsage, array $audienceUsage, array $channelUsage, array $health): array
    {
        $sentPackets = (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0));

        return [
            'total_delivery_records' => count($deliveries),
            'total_deliveries' => count($deliveries),
            'prepared_count' => (int) ($statusCounts['prepared'] ?? 0),
            'copy_only_count' => (int) ($statusCounts['copy_only'] ?? 0),
            'draft_created_count' => (int) ($statusCounts['draft_created'] ?? 0),
            'sent_count' => (int) ($statusCounts['sent'] ?? 0),
            'partial_count' => (int) ($statusCounts['partial'] ?? 0),
            'blocked_count' => (int) ($statusCounts['blocked'] ?? 0),
            'unsupported_count' => (int) ($statusCounts['unsupported'] ?? 0),
            'failed_count' => (int) ($statusCounts['failed'] ?? 0),
            'total_recipients_attempted' => (int) ($health['total_recipients_attempted'] ?? 0),
            'total_recipients_sent' => (int) ($health['total_recipients_sent'] ?? 0),
            'average_recipients_per_sent_packet' => $sentPackets > 0 ? round(((int) ($health['total_recipients_sent'] ?? 0)) / $sentPackets, 1) : null,
            'last_delivery_at' => $deliveries[0]['created_at'] ?? null,
            'most_used_template' => $templateUsage[0]['display_name'] ?? null,
            'most_used_audience' => $audienceUsage[0]['display_name'] ?? null,
            'most_used_channel' => $channelUsage[0]['display_name'] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, array<string, mixed>>
     */
    private function buildChannelUsageSummary(array $deliveries): array
    {
        return collect($deliveries)
            ->groupBy(fn (array $delivery): string => (string) ($delivery['channel'] ?: 'unknown'))
            ->map(function ($items, string $channel): array {
                $records = $items->values();
                $statusCounts = $this->statusCounts($records->all());

                return [
                    'channel' => $channel,
                    'display_name' => $this->humanLabel($channel),
                    'count' => $records->count(),
                    'sent_count' => (int) (($statusCounts['sent'] ?? 0) + ($statusCounts['partial'] ?? 0)),
                    'blocked_count' => (int) (($statusCounts['blocked'] ?? 0) + ($statusCounts['unsupported'] ?? 0)),
                    'failed_count' => (int) ($statusCounts['failed'] ?? 0),
                    'last_used_at' => $records->max('created_at'),
                    'status_counts' => $statusCounts,
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
        $summary = [
            'blocked_internal_qa_to_parent_or_player' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->isExternalAudience($delivery) && (string) ($delivery['template_key'] ?? '') === 'internal_qa_packet')
                ->count(),
            'blocked_staff_packet_to_parent_or_player' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->isExternalAudience($delivery) && (string) ($delivery['template_key'] ?? '') === 'staff_review_packet')
                ->count(),
            'blocked_director_packet_to_parent_or_player' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->isExternalAudience($delivery) && (string) ($delivery['template_key'] ?? '') === 'director_packet')
                ->count(),
            'blocked_unsafe_recipients' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && ($this->unsafeRecipientCount($delivery) > 0 || $this->missingContactCount($delivery) > 0))
                ->count(),
            'parent_safe_packets_prepared' => collect($deliveries)
                ->filter(fn (array $delivery): bool => (string) ($delivery['template_key'] ?? '') === 'parent_safe_season_summary' && ! $this->isBlocked($delivery))
                ->count(),
            'player_safe_packets_prepared' => collect($deliveries)
                ->filter(fn (array $delivery): bool => (string) ($delivery['template_key'] ?? '') === 'player_development_summary' && ! $this->isBlocked($delivery))
                ->count(),
            'private_note_leak_prevented_count' => collect($deliveries)
                ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && $this->containsAny($delivery['warning_text'] ?? [], ['private', 'staff', 'internal']))
                ->count(),
        ];
        $warnings = [];
        if ($summary['blocked_internal_qa_to_parent_or_player'] > 0) {
            $warnings[] = 'Internal QA packet attempts were blocked for parent/player audiences.';
        }
        if ($summary['blocked_staff_packet_to_parent_or_player'] > 0 || $summary['blocked_director_packet_to_parent_or_player'] > 0) {
            $warnings[] = 'Internal season archive packets were blocked from external audiences.';
        }
        if ($summary['blocked_unsafe_recipients'] > 0) {
            $warnings[] = 'Some season packets had unsafe or missing-contact recipients.';
        }

        return [
            ...$summary,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     * @return array<int, string>
     */
    private function buildWarnings(array $deliveries, array $health): array
    {
        $warnings = [];
        if (empty($deliveries)) {
            $warnings[] = 'No season packet delivery history was found in the selected window.';
        }
        if ((int) ($health['privacy_block_count'] ?? 0) > 0) {
            $warnings[] = 'Some season packet delivery attempts were blocked by safety checks.';
        }
        if ((int) ($health['missing_contact_warning_count'] ?? 0) > 0) {
            $warnings[] = 'Some season packets had missing contact or unsafe recipient warnings.';
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
            'subject' => $delivery->subject,
            'recipient_summary' => $recipientSummary,
            'privacy_warning_count' => count(Arr::wrap($delivery->privacy_warnings)),
            'delivery_warning_count' => count(Arr::wrap($delivery->delivery_warnings)),
            'send_blocker_count' => count(Arr::wrap($delivery->send_blockers)),
            'warning_count' => count($warningText),
            'send_result' => [
                'send_status' => $sendResult['send_status'] ?? null,
                'sent_count' => (int) ($sendResult['sent_count'] ?? 0),
                'failed_count' => (int) ($sendResult['failed_count'] ?? 0),
                'skipped_count' => (int) ($sendResult['skipped_count'] ?? 0),
            ],
            'created_by_user_id' => $delivery->created_by_user_id,
            'sent_by_user_id' => $delivery->sent_by_user_id,
            'created_by_name' => $this->userName($delivery->createdBy),
            'sent_by_name' => $this->userName($delivery->sentBy),
            'season_start_date' => $delivery->season_start_date?->toDateString(),
            'season_end_date' => $delivery->season_end_date?->toDateString(),
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
            'privacy_flags' => [
                'has_privacy_warning' => $this->containsAny($warningText, ['private', 'staff', 'internal']),
                'has_missing_contact_warning' => $this->containsAny($warningText, ['missing contact', 'missing email', 'no safe recipients', 'unsafe recipient']),
                'has_delivery_blocker' => count(Arr::wrap($delivery->send_blockers)) > 0,
            ],
            'warning_text' => $warningText,
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
        return self::TEMPLATE_LABELS[$templateKey] ?? $this->humanLabel($templateKey ?: 'unknown');
    }

    private function userName(mixed $user): ?string
    {
        if (! $user) {
            return null;
        }

        $profile = $user->profile ?? null;
        $name = trim((string) ($profile?->first_name ?? '').' '.(string) ($profile?->last_name ?? ''));

        return $name !== '' ? $name : ($user->email ?? null);
    }

    /**
     * @param array<int, array<string, mixed>> $deliveries
     */
    private function privacyBlockCount(array $deliveries): int
    {
        return collect($deliveries)
            ->filter(fn (array $delivery): bool => $this->isBlocked($delivery) && (bool) ($delivery['privacy_flags']['has_privacy_warning'] ?? false))
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
    private function action(string $id, string $priority, string $title, string $why, string $action, string $actionType): array
    {
        return [
            'id' => $id,
            'priority' => $priority,
            'title' => $title,
            'why' => $why,
            'action' => $action,
            'action_type' => $actionType,
        ];
    }
}
