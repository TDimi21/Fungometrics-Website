<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class WeeklyReportDeliveryHistoryService
{
    public const STATUSES = [
        'prepared',
        'copy_only',
        'draft_created',
        'sent',
        'partial',
        'blocked',
        'unsupported',
        'failed',
    ];

    /**
     * @return array<string, mixed>
     */
    public function recordPrepared(array $draftReview, ?string $createdByUserId = null): array
    {
        $status = (string) ($draftReview['delivery_status'] ?? 'prepared');
        if (! in_array($status, self::STATUSES, true)) {
            $status = $status === 'review_ready' ? 'prepared' : 'prepared';
        }

        $delivery = WeeklyReportDelivery::query()->create($this->payloadFromDraft(
            $draftReview,
            $status,
            $createdByUserId,
        ));

        return [
            ...$this->formatDelivery($delivery),
            'recorded' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordCopyAction(string $deliveryId, ?string $userId = null): array
    {
        $delivery = WeeklyReportDelivery::query()->find($deliveryId);
        if (! $delivery) {
            return [
                'delivery_id' => null,
                'delivery_status' => 'missing',
                'recorded' => false,
                'message' => 'Delivery history record was not found.',
            ];
        }

        $delivery->forceFill([
            'delivery_status' => 'copy_only',
            'sent_by_user_id' => $userId ?: $delivery->sent_by_user_id,
            'copied_at' => now(),
        ])->save();

        return [
            ...$this->formatDelivery($delivery->refresh()),
            'recorded' => true,
            'message' => 'Copy action recorded.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDraftCreated(array $draftResult, ?string $createdByUserId = null): array
    {
        $status = (bool) ($draftResult['draft']['created'] ?? false) ? 'draft_created' : 'prepared';
        if ((string) ($draftResult['delivery_status'] ?? '') === 'unsupported') {
            $status = 'unsupported';
        }

        $delivery = WeeklyReportDelivery::query()->create([
            ...$this->payloadFromDraft($draftResult, $status, $createdByUserId),
            'draft_created_at' => $status === 'draft_created' ? now() : null,
        ]);

        return [
            ...$this->formatDelivery($delivery),
            'recorded' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSendAttempt(array $draftPayload, array $sendResult, ?string $sentByUserId = null): array
    {
        $status = $this->statusFromSendResult($sendResult);
        $delivery = WeeklyReportDelivery::query()->create([
            ...$this->payloadFromDraft($draftPayload, $status, $sentByUserId),
            'sent_by_user_id' => $sentByUserId,
            'send_result' => $this->sanitizeSendResult($sendResult),
            'sent_at' => $status === 'sent' || $status === 'partial' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'blocked_at' => in_array($status, ['blocked', 'unsupported'], true) ? now() : null,
        ]);

        return [
            ...$this->formatDelivery($delivery),
            'recorded' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordBlockedAttempt(array $draftPayload, array $blockers, ?string $userId = null): array
    {
        $delivery = WeeklyReportDelivery::query()->create([
            ...$this->payloadFromDraft([
                ...$draftPayload,
                'send_blockers' => $blockers,
            ], 'blocked', $userId),
            'sent_by_user_id' => $userId,
            'blocked_at' => now(),
        ]);

        return [
            ...$this->formatDelivery($delivery),
            'recorded' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTeamDeliveries(string $teamId, array $filters = []): array
    {
        return $this->queryForTeam($teamId, $filters)
            ->latest('created_at')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->map(fn (WeeklyReportDelivery $delivery): array => $this->formatDelivery($delivery))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDelivery(string $deliveryId): array
    {
        $delivery = WeeklyReportDelivery::query()
            ->with(['createdBy.profile', 'sentBy.profile'])
            ->find($deliveryId);

        return $delivery ? $this->formatDelivery($delivery, true) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDeliverySummary(string $teamId, array $filters = []): array
    {
        $deliveries = $this->queryForTeam($teamId, $filters)->latest('created_at')->get();
        $counts = $deliveries->countBy('delivery_status');
        $lastSent = $deliveries
            ->filter(fn (WeeklyReportDelivery $delivery): bool => in_array((string) $delivery->delivery_status, ['sent', 'partial'], true))
            ->sortByDesc('sent_at')
            ->first();

        return [
            'team_id' => $teamId,
            'total_deliveries' => $deliveries->count(),
            'prepared_count' => (int) ($counts['prepared'] ?? 0),
            'draft_created_count' => (int) ($counts['draft_created'] ?? 0),
            'sent_count' => (int) ($counts['sent'] ?? 0),
            'partial_count' => (int) ($counts['partial'] ?? 0),
            'copy_only_count' => (int) ($counts['copy_only'] ?? 0),
            'blocked_count' => (int) ($counts['blocked'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
            'unsupported_count' => (int) ($counts['unsupported'] ?? 0),
            'last_sent_at' => $lastSent?->sent_at?->toIso8601String(),
            'recent_deliveries' => $deliveries
                ->take(10)
                ->map(fn (WeeklyReportDelivery $delivery): array => $this->formatDelivery($delivery))
                ->values()
                ->all(),
            'warnings' => $deliveries
                ->flatMap(fn (WeeklyReportDelivery $delivery): array => [
                    ...Arr::wrap($delivery->privacy_warnings),
                    ...Arr::wrap($delivery->delivery_warnings),
                    ...Arr::wrap($delivery->send_blockers),
                ])
                ->filter()
                ->unique()
                ->take(12)
                ->values()
                ->all(),
        ];
    }

    private function queryForTeam(string $teamId, array $filters = []): Builder
    {
        $query = WeeklyReportDelivery::query()
            ->with(['createdBy.profile', 'sentBy.profile'])
            ->where('team_id', $teamId);

        if (! empty($filters['source'])) {
            $query->where('source', (string) $filters['source']);
        } else {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('source')->orWhere('source', 'weekly_report');
            });
        }

        $days = (int) ($filters['days'] ?? 30);
        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', CarbonImmutable::parse((string) $filters['start_date'])->toDateString());
        } else {
            $query->where('created_at', '>=', now()->subDays(max(1, min(365, $days))));
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', CarbonImmutable::parse((string) $filters['end_date'])->toDateString());
        }
        foreach (['audience', 'channel', 'template_key'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, (string) $filters[$field]);
            }
        }
        if (! empty($filters['template'])) {
            $query->where('template_key', (string) $filters['template']);
        }
        if (! empty($filters['status'])) {
            $query->where('delivery_status', (string) $filters['status']);
        }
        if (! empty($filters['archive_type'])) {
            $query->where('archive_type', (string) $filters['archive_type']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFromDraft(array $draft, string $status, ?string $userId): array
    {
        [$weekStart, $weekEnd] = $this->weekDates($draft);
        $blockers = Arr::wrap($draft['send_blockers'] ?? []);
        $privacyWarnings = Arr::wrap($draft['privacy_warnings'] ?? []);
        $deliveryWarnings = Arr::wrap($draft['delivery_warnings'] ?? []);
        $unsafe = $status === 'blocked' || $this->containsPrivateWarning([
            ...$blockers,
            ...$privacyWarnings,
            ...$deliveryWarnings,
        ]);

        return [
            'team_id' => (string) ($draft['team_id'] ?? ''),
            'created_by_user_id' => $userId,
            'source' => (string) ($draft['source'] ?? 'weekly_report'),
            'archive_type' => $draft['archive_type'] ?? $draft['template'] ?? $draft['template_key'] ?? null,
            'week_start_date' => $weekStart,
            'week_end_date' => $weekEnd,
            'season_start_date' => $this->dateOrNull($draft['season_start_date'] ?? null),
            'season_end_date' => $this->dateOrNull($draft['season_end_date'] ?? null),
            'template_key' => (string) ($draft['template'] ?? $draft['template_key'] ?? ''),
            'audience' => (string) ($draft['audience'] ?? 'coach'),
            'channel' => (string) ($draft['channel'] ?? 'copy'),
            'format' => (string) ($draft['format'] ?? 'text'),
            'delivery_status' => $status,
            'subject' => $this->cleanText($draft['subject'] ?? null),
            'message_preview' => $this->messagePreview($draft, $unsafe),
            'recipient_summary' => Arr::wrap($draft['recipient_summary'] ?? []),
            'recipients' => $this->safeRecipients(Arr::wrap($draft['recipients'] ?? [])),
            'privacy_warnings' => $privacyWarnings,
            'delivery_warnings' => $deliveryWarnings,
            'send_blockers' => $blockers,
            'export_payload' => $this->safeExportPayload($draft),
            'draft_payload' => $this->safeDraftPayload($draft),
            'blocked_at' => $status === 'blocked' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ];
    }

    private function statusFromSendResult(array $sendResult): string
    {
        $status = (string) ($sendResult['send_status'] ?? 'unsupported');

        return in_array($status, self::STATUSES, true) ? $status : 'unsupported';
    }

    private function messagePreview(array $draft, bool $unsafe): ?string
    {
        if ($unsafe) {
            return 'Message preview hidden because privacy validation blocked this delivery.';
        }

        $text = $this->cleanText($draft['message_text'] ?? null)
            ?: $this->cleanText(strip_tags((string) ($draft['message_html'] ?? '')));

        return $text !== null ? mb_substr($text, 0, 1500) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safeRecipients(array $recipients): array
    {
        return collect($recipients)
            ->map(fn (array $recipient): array => [
                'recipient_id' => $recipient['recipient_id'] ?? null,
                'recipient_type' => $recipient['recipient_type'] ?? null,
                'name' => $recipient['name'] ?? null,
                'player_id' => $recipient['player_id'] ?? null,
                'contact_present' => $this->cleanText($recipient['email'] ?? null) !== null,
                'safe_to_send' => (bool) ($recipient['safe_to_send'] ?? false),
                'warning' => $recipient['warning'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeExportPayload(array $draft): ?array
    {
        if (empty($draft['export_payload'])) {
            return null;
        }

        $payload = Arr::wrap($draft['export_payload']);

        return [
            'generated_at' => $payload['generated_at'] ?? null,
            'team_id' => $payload['team_id'] ?? null,
            'source' => $payload['source'] ?? null,
            'format' => $payload['format'] ?? null,
            'audience' => $payload['audience'] ?? null,
            'template_key' => $payload['template']['template_key'] ?? $payload['template_key'] ?? null,
            'warnings' => Arr::wrap($payload['warnings'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeDraftPayload(array $draft): array
    {
        return [
            'generated_at' => $draft['generated_at'] ?? null,
            'team_id' => $draft['team_id'] ?? null,
            'source' => $draft['source'] ?? null,
            'archive_type' => $draft['archive_type'] ?? null,
            'audience' => $draft['audience'] ?? null,
            'template' => $draft['template'] ?? $draft['template_key'] ?? null,
            'channel' => $draft['channel'] ?? null,
            'format' => $draft['format'] ?? null,
            'delivery_status' => $draft['delivery_status'] ?? null,
            'requires_confirmation' => $draft['requires_confirmation'] ?? $draft['requires_coach_approval'] ?? null,
            'preview' => Arr::wrap($draft['preview'] ?? []),
            'draft' => Arr::wrap($draft['draft'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeSendResult(array $sendResult): array
    {
        return [
            'send_status' => $sendResult['send_status'] ?? null,
            'sent_count' => $sendResult['sent_count'] ?? 0,
            'failed_count' => $sendResult['failed_count'] ?? 0,
            'skipped_count' => $sendResult['skipped_count'] ?? 0,
            'warnings' => Arr::wrap($sendResult['warnings'] ?? []),
            'evidence' => Arr::wrap($sendResult['evidence'] ?? []),
            'sent_at' => $sendResult['sent_at'] ?? null,
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function weekDates(array $draft): array
    {
        $start = $draft['week_start_date'] ?? $draft['start_date'] ?? null;
        $end = $draft['week_end_date'] ?? $draft['end_date'] ?? null;
        if ($start || $end) {
            return [
                $start ? CarbonImmutable::parse((string) $start)->toDateString() : null,
                $end ? CarbonImmutable::parse((string) $end)->toDateString() : null,
            ];
        }

        $days = max(1, min(365, (int) ($draft['days'] ?? 7)));

        return [
            now()->subDays($days - 1)->toDateString(),
            now()->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDelivery(WeeklyReportDelivery $delivery, bool $includeDetail = false): array
    {
        $sentAt = $delivery->sent_at?->toIso8601String();
        $createdAt = $delivery->created_at?->toIso8601String();
        $timestamp = $sentAt
            ?: $delivery->copied_at?->toIso8601String()
            ?: $delivery->blocked_at?->toIso8601String()
            ?: $delivery->failed_at?->toIso8601String()
            ?: $createdAt;

        $payload = [
            'delivery_id' => (string) $delivery->id,
            'team_id' => (string) $delivery->team_id,
            'source' => $delivery->source ?: 'weekly_report',
            'archive_type' => $delivery->archive_type,
            'template_key' => $delivery->template_key,
            'audience' => $delivery->audience,
            'channel' => $delivery->channel,
            'format' => $delivery->format,
            'delivery_status' => $delivery->delivery_status,
            'subject' => $delivery->subject,
            'recipient_summary' => Arr::wrap($delivery->recipient_summary),
            'privacy_warnings' => Arr::wrap($delivery->privacy_warnings),
            'delivery_warnings' => Arr::wrap($delivery->delivery_warnings),
            'send_blockers' => Arr::wrap($delivery->send_blockers),
            'copied_at' => $delivery->copied_at?->toIso8601String(),
            'draft_created_at' => $delivery->draft_created_at?->toIso8601String(),
            'season_start_date' => $delivery->season_start_date?->toDateString(),
            'season_end_date' => $delivery->season_end_date?->toDateString(),
            'sent_at' => $sentAt,
            'failed_at' => $delivery->failed_at?->toIso8601String(),
            'blocked_at' => $delivery->blocked_at?->toIso8601String(),
            'created_at' => $createdAt,
            'display_timestamp' => $timestamp,
            'created_by_user_id' => $delivery->created_by_user_id,
            'sent_by_user_id' => $delivery->sent_by_user_id,
            'created_by_name' => $this->userName($delivery->createdBy),
            'sent_by_name' => $this->userName($delivery->sentBy),
            'message' => $this->statusMessage((string) $delivery->delivery_status, $delivery),
        ];

        if ($includeDetail) {
            $payload['message_preview'] = $delivery->message_preview;
            $payload['recipients'] = Arr::wrap($delivery->recipients);
            $payload['send_result'] = Arr::wrap($delivery->send_result);
            $payload['draft_payload'] = Arr::wrap($delivery->draft_payload);
        }

        return $payload;
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

    private function statusMessage(string $status, ?WeeklyReportDelivery $delivery = null): string
    {
        return match ($status) {
            'sent' => 'Weekly report delivery was sent.',
            'partial' => 'Weekly report delivery was partially sent.',
            'copy_only' => $delivery?->copied_at ? 'Coach copied the weekly report message.' : 'Copy-only weekly report delivery was prepared.',
            'draft_created' => 'Weekly report draft was created.',
            'blocked' => 'Weekly report delivery was blocked by safety checks.',
            'unsupported' => 'Selected delivery channel is not configured.',
            'failed' => 'Weekly report delivery failed.',
            default => 'Weekly report delivery was prepared.',
        };
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text ? CarbonImmutable::parse($text)->toDateString() : null;
    }

    private function containsPrivateWarning(array $warnings): bool
    {
        return collect($warnings)
            ->contains(function (mixed $warning): bool {
                $text = strtolower((string) $warning);

                return str_contains($text, 'private')
                    || str_contains($text, 'staff')
                    || str_contains($text, 'internal')
                    || str_contains($text, 'cannot be sent')
                    || str_contains($text, 'cannot be delivered')
                    || str_contains($text, 'blocked');
            });
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
