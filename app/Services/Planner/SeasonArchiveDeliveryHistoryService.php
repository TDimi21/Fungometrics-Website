<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\WeeklyReportDelivery;
use Illuminate\Support\Arr;

class SeasonArchiveDeliveryHistoryService
{
    public const STATUSES = WeeklyReportDeliveryHistoryService::STATUSES;

    public function __construct(
        private readonly WeeklyReportDeliveryHistoryService $historyService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function recordPrepared(array $draftReview, ?string $createdByUserId = null): array
    {
        return $this->seasonize($this->historyService->recordPrepared(
            $this->payload($draftReview),
            $createdByUserId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function recordCopyAction(string $deliveryId, ?string $userId = null): array
    {
        $delivery = WeeklyReportDelivery::query()->find($deliveryId);
        if (! $delivery || (string) ($delivery->source ?? '') !== 'season_archive') {
            return [
                'delivery_id' => null,
                'delivery_status' => 'missing',
                'recorded' => false,
                'message' => 'Season archive delivery history record was not found.',
            ];
        }

        return $this->seasonize($this->historyService->recordCopyAction($deliveryId, $userId));
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDraftCreated(array $draftResult, ?string $createdByUserId = null): array
    {
        return $this->seasonize($this->historyService->recordDraftCreated(
            $this->payload($draftResult),
            $createdByUserId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSendAttempt(array $draftPayload, array $sendResult, ?string $sentByUserId = null): array
    {
        return $this->seasonize($this->historyService->recordSendAttempt(
            $this->payload($draftPayload),
            $sendResult,
            $sentByUserId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function recordBlockedAttempt(array $draftPayload, array $blockers, ?string $userId = null): array
    {
        return $this->seasonize($this->historyService->recordBlockedAttempt(
            $this->payload($draftPayload),
            $blockers,
            $userId,
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTeamDeliveries(string $teamId, array $filters = []): array
    {
        return collect($this->historyService->listTeamDeliveries($teamId, $this->filters($filters)))
            ->map(fn (array $delivery): array => $this->seasonize($delivery))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getDelivery(string $deliveryId): array
    {
        $delivery = $this->historyService->getDelivery($deliveryId);
        if (($delivery['source'] ?? null) !== 'season_archive') {
            return [];
        }

        return $this->seasonize($delivery, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDeliverySummary(string $teamId, array $filters = []): array
    {
        $summary = $this->historyService->buildDeliverySummary($teamId, $this->filters($filters));
        $deliveries = collect(Arr::wrap($summary['recent_deliveries'] ?? []))
            ->map(fn (array $delivery): array => $this->seasonize($delivery))
            ->values()
            ->all();

        return [
            'team_id' => $teamId,
            'source' => 'season_archive',
            'total_deliveries' => (int) ($summary['total_deliveries'] ?? 0),
            'prepared_count' => (int) ($summary['prepared_count'] ?? 0),
            'copy_only_count' => (int) ($summary['copy_only_count'] ?? 0),
            'draft_created_count' => (int) ($summary['draft_created_count'] ?? 0),
            'sent_count' => (int) ($summary['sent_count'] ?? 0),
            'partial_count' => (int) ($summary['partial_count'] ?? 0),
            'blocked_count' => (int) ($summary['blocked_count'] ?? 0),
            'unsupported_count' => (int) ($summary['unsupported_count'] ?? 0),
            'failed_count' => (int) ($summary['failed_count'] ?? 0),
            'last_sent_at' => $summary['last_sent_at'] ?? null,
            'recent_deliveries' => $deliveries,
            'warnings' => Arr::wrap($summary['warnings'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $draft): array
    {
        $template = (string) ($draft['template'] ?? $draft['template_key'] ?? $draft['archive_type'] ?? '');

        return [
            ...$draft,
            'source' => 'season_archive',
            'archive_type' => $template,
            'template' => $template,
            'start_date' => $draft['season_start_date'] ?? $draft['start_date'] ?? null,
            'end_date' => $draft['season_end_date'] ?? $draft['end_date'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        return [
            ...$filters,
            'source' => 'season_archive',
            'template' => $filters['template'] ?? $filters['template_key'] ?? $filters['archive_type'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonize(array $delivery, bool $detail = false): array
    {
        $payload = [
            'delivery_id' => $delivery['delivery_id'] ?? null,
            'team_id' => $delivery['team_id'] ?? null,
            'source' => 'season_archive',
            'template_key' => $delivery['template_key'] ?? $delivery['archive_type'] ?? null,
            'archive_type' => $delivery['archive_type'] ?? $delivery['template_key'] ?? null,
            'audience' => $delivery['audience'] ?? null,
            'channel' => $delivery['channel'] ?? null,
            'format' => $delivery['format'] ?? null,
            'delivery_status' => $delivery['delivery_status'] ?? 'prepared',
            'subject' => $delivery['subject'] ?? null,
            'recipient_summary' => Arr::wrap($delivery['recipient_summary'] ?? []),
            'privacy_warnings' => Arr::wrap($delivery['privacy_warnings'] ?? []),
            'delivery_warnings' => Arr::wrap($delivery['delivery_warnings'] ?? []),
            'send_blockers' => Arr::wrap($delivery['send_blockers'] ?? []),
            'season_start_date' => $delivery['season_start_date'] ?? $delivery['week_start_date'] ?? null,
            'season_end_date' => $delivery['season_end_date'] ?? $delivery['week_end_date'] ?? null,
            'sent_at' => $delivery['sent_at'] ?? null,
            'copied_at' => $delivery['copied_at'] ?? null,
            'draft_created_at' => $delivery['draft_created_at'] ?? null,
            'blocked_at' => $delivery['blocked_at'] ?? null,
            'failed_at' => $delivery['failed_at'] ?? null,
            'created_at' => $delivery['created_at'] ?? null,
            'display_timestamp' => $delivery['display_timestamp'] ?? null,
            'created_by_user_id' => $delivery['created_by_user_id'] ?? null,
            'sent_by_user_id' => $delivery['sent_by_user_id'] ?? null,
            'created_by_name' => $delivery['created_by_name'] ?? null,
            'sent_by_name' => $delivery['sent_by_name'] ?? null,
            'message' => $this->statusMessage((string) ($delivery['delivery_status'] ?? 'prepared')),
        ];

        if ($detail) {
            $payload['message_preview'] = $delivery['message_preview'] ?? null;
            $payload['recipients'] = $this->safeRecipientsForHistory(Arr::wrap($delivery['recipients'] ?? []));
            $payload['send_result'] = Arr::wrap($delivery['send_result'] ?? []);
            $payload['draft_payload'] = Arr::wrap($delivery['draft_payload'] ?? []);
        }

        return $payload;
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'sent' => 'Season archive packet was sent.',
            'partial' => 'Season archive packet was partially sent.',
            'copy_only' => 'Copy-only season archive packet was prepared or copied.',
            'draft_created' => 'Season archive delivery draft was created.',
            'blocked' => 'Season archive delivery was blocked by safety checks.',
            'unsupported' => 'Selected season archive delivery channel is not configured.',
            'failed' => 'Season archive delivery failed.',
            default => 'Season archive delivery was prepared.',
        };
    }

    /**
     * @param array<int, mixed> $recipients
     * @return array<int, array<string, mixed>>
     */
    private function safeRecipientsForHistory(array $recipients): array
    {
        return collect($recipients)
            ->map(fn (mixed $recipient): array => [
                'recipient_type' => Arr::get(Arr::wrap($recipient), 'recipient_type'),
                'name' => Arr::get(Arr::wrap($recipient), 'name'),
                'contact_present' => (bool) Arr::get(Arr::wrap($recipient), 'contact_present', false),
                'safe_to_send' => (bool) Arr::get(Arr::wrap($recipient), 'safe_to_send', false),
                'warning' => Arr::get(Arr::wrap($recipient), 'warning'),
            ])
            ->values()
            ->all();
    }
}
