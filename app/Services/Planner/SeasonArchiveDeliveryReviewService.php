<?php

declare(strict_types=1);

namespace App\Services\Planner;

use Illuminate\Support\Arr;

class SeasonArchiveDeliveryReviewService
{
    private const CHANNELS_WITH_SEND_SUPPORT = [];

    public function __construct(
        private readonly SeasonArchiveDeliveryPrepService $prepService,
        private readonly WeeklyReportDeliveryHistoryService $historyService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftReview(string $teamId, array $options = []): array
    {
        $prepared = $this->prepService->prepareDelivery($teamId, $options);
        $review = $this->reviewFromPrepared([
            ...$prepared,
            'season_start_date' => $options['season_start_date'] ?? $options['start_date'] ?? null,
            'season_end_date' => $options['season_end_date'] ?? $options['end_date'] ?? null,
            'weeks' => $options['weeks'] ?? 12,
            'source' => 'season_archive',
        ], $options);

        if ((bool) ($options['record_history'] ?? false)) {
            $review['delivery_history'] = $this->historyService->recordPrepared(
                $review,
                $this->cleanText($options['current_user_id'] ?? null),
            );
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateDraftForSending(array $draftPayload, array $options = []): array
    {
        $channel = (string) ($draftPayload['channel'] ?? $options['channel'] ?? 'copy');
        $audience = (string) ($draftPayload['audience'] ?? $options['audience'] ?? 'staff');
        $template = (string) ($draftPayload['template'] ?? $options['template'] ?? '');
        $subject = $this->cleanText($draftPayload['subject'] ?? null);
        $messageText = $this->cleanText($draftPayload['message_text'] ?? null);
        $messageHtml = $this->cleanText($draftPayload['message_html'] ?? null);
        $recipients = Arr::wrap($draftPayload['recipients'] ?? []);
        $privacyWarnings = Arr::wrap($draftPayload['privacy_warnings'] ?? []);
        $deliveryWarnings = Arr::wrap($draftPayload['delivery_warnings'] ?? []);
        $blockers = [];

        if (! (bool) ($options['confirm_send'] ?? false)) {
            $blockers[] = 'Coach confirmation is required before sending.';
        }

        $unsafeReason = $this->unsafeTemplateAudienceReason($template, $audience);
        if ($unsafeReason !== null) {
            $blockers[] = $unsafeReason;
        }

        if ($channel === 'copy') {
            $blockers[] = 'Copy-only delivery. Nothing will be sent by FMTRX.';
        }

        if ($channel !== 'copy' && ! $this->channelHasSender($channel)) {
            $blockers[] = 'No supported delivery sender found. Use Copy Message.';
        }

        if ($subject === null) {
            $blockers[] = 'Missing subject.';
        }

        if ($messageText === null && $messageHtml === null) {
            $blockers[] = 'Message body is empty.';
        }

        if ($this->publicAudienceHasPrivateContent($audience, [$subject, $messageText, $messageHtml])) {
            $blockers[] = 'Unfiltered private player, staff, correction, or internal QA details detected.';
        }

        if ($this->publicAudienceHasBlockingWarning($audience, $privacyWarnings)) {
            $blockers[] = 'Parent/player draft contains staff-only or internal warning context.';
        }

        $safeRecipients = collect($recipients)
            ->filter(fn (array $recipient): bool => (bool) ($recipient['safe_to_send'] ?? false))
            ->values()
            ->all();
        $unsafeRecipients = collect($recipients)
            ->reject(fn (array $recipient): bool => (bool) ($recipient['safe_to_send'] ?? false))
            ->values()
            ->all();

        if ($channel !== 'copy' && count($safeRecipients) < 1) {
            $blockers[] = 'No safe recipients available.';
        }

        if (count($unsafeRecipients) > 0) {
            $deliveryWarnings[] = count($unsafeRecipients).' recipient(s) are missing contact info or marked unsafe.';
        }

        $blockers = array_values(array_unique(array_filter($blockers)));
        $warnings = array_values(array_unique(array_filter([
            ...$privacyWarnings,
            ...$deliveryWarnings,
        ])));

        return [
            'can_send' => $this->actionableBlockers($blockers) === [] && $channel !== 'copy' && $this->channelHasSender($channel),
            'send_blockers' => $blockers,
            'warnings' => $warnings,
            'safe_recipients' => $safeRecipients,
            'unsafe_recipients' => $unsafeRecipients,
            'channel_supported' => $this->channelHasSender($channel),
            'copy_only' => $channel === 'copy',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDraftContent(string $teamId, array $draftPayload, array $edits = [], ?string $userId = null): array
    {
        $review = $this->buildDraftReview($teamId, [
            ...$draftPayload,
            'current_user_id' => $userId,
        ]);

        $subject = $this->cleanText($edits['subject'] ?? null);
        $messageText = $this->cleanText($edits['message_text'] ?? null);
        $messageHtml = $this->cleanText($edits['message_html'] ?? null);

        if ($subject !== null) {
            $review['subject'] = $subject;
        }
        if ($messageText !== null) {
            $review['message_text'] = $messageText;
        }
        if ($messageHtml !== null) {
            $review['message_html'] = $messageHtml;
        }

        $validation = $this->validateDraftForSending($review, [
            'confirm_send' => false,
        ]);

        $updated = [
            ...$review,
            'delivery_status' => $this->statusFromValidation($review, $validation, false),
            'can_send' => (bool) ($validation['can_send'] ?? false),
            'send_blockers' => $this->displayBlockers($validation['send_blockers'] ?? []),
            'delivery_warnings' => array_values(array_unique(array_filter([
                ...Arr::wrap($review['delivery_warnings'] ?? []),
                ...Arr::wrap($validation['warnings'] ?? []),
            ]))),
            'preview' => [
                ...Arr::wrap($review['preview'] ?? []),
                'edited_by_user_id' => $userId,
                'edited_at' => now()->toIso8601String(),
                'validation' => [
                    'safe_recipient_count' => count($validation['safe_recipients'] ?? []),
                    'unsafe_recipient_count' => count($validation['unsafe_recipients'] ?? []),
                    'channel_supported' => (bool) ($validation['channel_supported'] ?? false),
                    'copy_only' => (bool) ($validation['copy_only'] ?? false),
                ],
            ],
        ];

        if ((bool) ($draftPayload['record_history'] ?? false)) {
            $updated['delivery_history'] = $this->historyService->recordPrepared($updated, $userId);
        }

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendDraft(string $teamId, array $draftPayload, ?string $sentByUserId = null, array $options = []): array
    {
        $review = $this->updateDraftContent($teamId, $draftPayload, [
            'subject' => $draftPayload['subject'] ?? null,
            'message_text' => $draftPayload['message_text'] ?? null,
            'message_html' => $draftPayload['message_html'] ?? null,
        ], $sentByUserId);
        $confirmed = (bool) ($options['confirm_send'] ?? $draftPayload['confirm_send'] ?? false);
        $validation = $this->validateDraftForSending($review, [
            ...$options,
            'confirm_send' => $confirmed,
        ]);

        if (! $confirmed) {
            return $this->sendResultWithHistory($review, [
                'send_status' => 'blocked',
                'warnings' => ['Coach confirmation is required before sending.'],
                'evidence' => ['send endpoint called without confirm_send=true'],
            ], $sentByUserId);
        }

        $blockers = $this->actionableBlockers($validation['send_blockers'] ?? []);
        $privacyOrContentBlockers = collect($blockers)
            ->reject(fn (string $blocker): bool => in_array($blocker, [
                'Copy-only delivery. Nothing will be sent by FMTRX.',
                'No supported delivery sender found. Use Copy Message.',
            ], true))
            ->values()
            ->all();

        if ($privacyOrContentBlockers !== []) {
            return $this->sendResultWithHistory($review, [
                'send_status' => 'blocked',
                'warnings' => $this->displayBlockers($privacyOrContentBlockers),
                'evidence' => ['privacy and content validation blocked sending before channel support was considered'],
            ], $sentByUserId);
        }

        if (($validation['copy_only'] ?? false) === true) {
            return $this->sendResultWithHistory($review, [
                'send_status' => 'copy_only',
                'warnings' => ['Copy-only delivery. Nothing was sent by FMTRX.'],
                'evidence' => ['selected channel was copy'],
            ], $sentByUserId);
        }

        if (($validation['channel_supported'] ?? false) === false) {
            return $this->sendResultWithHistory($review, [
                'send_status' => 'unsupported',
                'warnings' => ['No supported delivery sender found. Use Copy Message.'],
                'evidence' => ['no existing season archive sender was found for this channel'],
            ], $sentByUserId);
        }

        if (! (bool) ($validation['can_send'] ?? false)) {
            return $this->sendResultWithHistory($review, [
                'send_status' => 'blocked',
                'warnings' => $this->displayBlockers($validation['send_blockers'] ?? []),
                'evidence' => ['privacy and recipient validation blocked sending'],
            ], $sentByUserId);
        }

        return $this->sendResultWithHistory($review, [
            'send_status' => 'unsupported',
            'warnings' => ['No sender implementation is wired for season archive packets. Nothing was sent.'],
            'evidence' => ['send path is intentionally disabled until a supported sender exists'],
        ], $sentByUserId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSendResult(array $sendAttempt, array $draftPayload): array
    {
        $safeRecipients = collect(Arr::wrap($draftPayload['recipients'] ?? []))
            ->filter(fn (array $recipient): bool => (bool) ($recipient['safe_to_send'] ?? false))
            ->values()
            ->all();
        $skipped = collect(Arr::wrap($draftPayload['recipients'] ?? []))
            ->reject(fn (array $recipient): bool => (bool) ($recipient['safe_to_send'] ?? false))
            ->values()
            ->all();
        $status = (string) ($sendAttempt['send_status'] ?? 'unsupported');

        return [
            'team_id' => (string) ($draftPayload['team_id'] ?? ''),
            'audience' => (string) ($draftPayload['audience'] ?? 'staff'),
            'template' => (string) ($draftPayload['template'] ?? ''),
            'channel' => (string) ($draftPayload['channel'] ?? 'copy'),
            'send_status' => $status,
            'sent_count' => $status === 'sent' ? count($safeRecipients) : 0,
            'failed_count' => 0,
            'skipped_count' => count($skipped) + ($status === 'sent' ? 0 : count($safeRecipients)),
            'recipients_sent' => $status === 'sent' ? $safeRecipients : [],
            'recipients_failed' => [],
            'recipients_skipped' => $status === 'sent' ? $skipped : Arr::wrap($draftPayload['recipients'] ?? []),
            'sent_at' => now()->toIso8601String(),
            'sent_by_user_id' => $sendAttempt['sent_by_user_id'] ?? null,
            'warnings' => Arr::wrap($sendAttempt['warnings'] ?? []),
            'evidence' => Arr::wrap($sendAttempt['evidence'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sendResultWithHistory(array $review, array $sendAttempt, ?string $sentByUserId): array
    {
        $result = $this->buildSendResult($sendAttempt, $review);
        $history = $result['send_status'] === 'blocked'
            ? $this->historyService->recordBlockedAttempt([
                ...$review,
                'send_blockers' => $result['warnings'] ?? [],
            ], Arr::wrap($result['warnings'] ?? []), $sentByUserId)
            : $this->historyService->recordSendAttempt($review, $result, $sentByUserId);

        return [
            ...$result,
            'delivery_history' => [
                'delivery_id' => $history['delivery_id'] ?? null,
                'delivery_status' => $history['delivery_status'] ?? $result['send_status'],
                'recorded' => (bool) ($history['recorded'] ?? false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewFromPrepared(array $prepared, array $options = []): array
    {
        $validation = $this->validateDraftForSending($prepared, [
            ...$options,
            'confirm_send' => false,
        ]);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => (string) ($prepared['team_id'] ?? ''),
            'audience' => (string) ($prepared['audience'] ?? 'staff'),
            'template' => (string) ($prepared['template'] ?? ''),
            'channel' => (string) ($prepared['channel'] ?? 'copy'),
            'format' => (string) ($prepared['format'] ?? 'text'),
            'season_start_date' => $prepared['season_start_date'] ?? null,
            'season_end_date' => $prepared['season_end_date'] ?? null,
            'start_date' => $prepared['season_start_date'] ?? null,
            'end_date' => $prepared['season_end_date'] ?? null,
            'weeks' => $prepared['weeks'] ?? ($options['weeks'] ?? 12),
            'source' => 'season_archive',
            'delivery_status' => $this->statusFromValidation($prepared, $validation, false),
            'can_send' => (bool) ($validation['can_send'] ?? false),
            'requires_confirmation' => true,
            'subject' => (string) ($prepared['subject'] ?? ''),
            'message_text' => $prepared['message_text'] ?? null,
            'message_html' => $prepared['message_html'] ?? null,
            'recipients' => Arr::wrap($prepared['recipients'] ?? []),
            'recipient_summary' => Arr::wrap($prepared['recipient_summary'] ?? []),
            'privacy_warnings' => Arr::wrap($prepared['privacy_warnings'] ?? []),
            'delivery_warnings' => array_values(array_unique(array_filter([
                ...Arr::wrap($prepared['delivery_warnings'] ?? []),
                ...Arr::wrap($validation['warnings'] ?? []),
            ]))),
            'send_blockers' => $this->displayBlockers($validation['send_blockers'] ?? []),
            'editable_fields' => [
                'subject',
                'message_text',
                'message_html',
            ],
            'preview' => [
                'delivery_status_from_prep' => $prepared['delivery_status'] ?? null,
                'safe_recipient_count' => count($validation['safe_recipients'] ?? []),
                'unsafe_recipient_count' => count($validation['unsafe_recipients'] ?? []),
                'channel_supported' => (bool) ($validation['channel_supported'] ?? false),
                'copy_only' => (bool) ($validation['copy_only'] ?? false),
                'source' => 'season_archive',
            ],
        ];
    }

    private function statusFromValidation(array $draft, array $validation, bool $confirmed): string
    {
        $blockers = $this->actionableBlockers($validation['send_blockers'] ?? []);
        if ($blockers !== []) {
            $copyOnly = in_array('Copy-only delivery. Nothing will be sent by FMTRX.', $blockers, true);
            $nonCopyBlockers = collect($blockers)
                ->reject(fn (string $blocker): bool => $blocker === 'Copy-only delivery. Nothing will be sent by FMTRX.')
                ->values()
                ->all();

            if ($copyOnly && $nonCopyBlockers === []) {
                return 'copy_only';
            }

            return in_array('No supported delivery sender found. Use Copy Message.', $nonCopyBlockers, true)
                && count($nonCopyBlockers) === 1
                && count($blockers) === 1
                ? 'unsupported'
                : 'blocked';
        }

        if (($validation['channel_supported'] ?? false) === false) {
            return 'unsupported';
        }

        return $confirmed && (bool) ($validation['can_send'] ?? false) ? 'send_ready' : 'review_ready';
    }

    private function displayBlockers(array $blockers): array
    {
        return array_values(array_unique(array_filter($blockers)));
    }

    private function actionableBlockers(array $blockers): array
    {
        return collect($blockers)
            ->reject(fn (string $blocker): bool => $blocker === 'Coach confirmation is required before sending.')
            ->values()
            ->all();
    }

    private function channelHasSender(string $channel): bool
    {
        return in_array($channel, self::CHANNELS_WITH_SEND_SUPPORT, true);
    }

    private function unsafeTemplateAudienceReason(string $template, string $audience): ?string
    {
        if ($template === 'internal_qa_packet' && ! in_array($audience, ['coach', 'staff', 'director'], true)) {
            return 'Internal QA packets cannot be sent to parent/player audiences.';
        }
        if (in_array($audience, ['parents', 'players'], true) && $template === 'staff_review_packet') {
            return 'Staff Review Packet cannot be sent to parent/player audiences.';
        }
        if (in_array($audience, ['parents', 'players'], true) && $template === 'director_packet') {
            return 'Director Packet cannot be sent to parent/player audiences.';
        }
        if ($audience === 'parents' && $template !== 'parent_safe_season_summary') {
            return 'Only the parent-safe season summary can be sent to parents.';
        }
        if ($audience === 'players' && $template !== 'player_development_summary') {
            return 'Only the player development summary can be sent to players.';
        }
        if (in_array($audience, ['coach', 'staff', 'director'], true)
            && in_array($template, ['parent_safe_season_summary', 'player_development_summary'], true)
        ) {
            return 'Parent or player season templates should not be delivered as staff review packets.';
        }

        return null;
    }

    private function publicAudienceHasBlockingWarning(string $audience, array $warnings): bool
    {
        if (! in_array($audience, ['parents', 'players'], true)) {
            return false;
        }

        return collect($warnings)
            ->contains(function (mixed $warning): bool {
                $text = strtolower((string) $warning);

                return str_contains($text, 'cannot be sent')
                    || str_contains($text, 'cannot be delivered')
                    || str_contains($text, 'blocked until')
                    || str_contains($text, 'internal qa packets are coach/staff/director only');
            });
    }

    private function publicAudienceHasPrivateContent(string $audience, array $values): bool
    {
        if (! in_array($audience, ['parents', 'players'], true)) {
            return false;
        }

        $text = collect($values)
            ->flatMap(fn (mixed $value): array => preg_split('/\R/', (string) ($value ?? '')) ?: [])
            ->map(fn (string $line): string => strtolower(trim($line)))
            ->reject(fn (string $line): bool => str_contains($line, 'hides ') || str_contains($line, 'removed for this audience'))
            ->implode("\n");

        foreach ([
            'staff notes',
            'staff note',
            'internal qa',
            'private player comparison',
            'private player comparisons',
            'raw benchmark payload',
            'raw payload',
            'rejected/correction',
            'correction details',
            'private note',
            'user_id',
            'team_id',
            'player_id',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
