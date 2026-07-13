<?php

declare(strict_types=1);

namespace App\Services\Planner;

use Illuminate\Support\Arr;

class WeeklyReportDeliveryReviewService
{
    private const CHANNELS_WITH_SEND_SUPPORT = [];

    public function __construct(
        private readonly WeeklyReportDeliveryPrepService $prepService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDraftReview(string $teamId, array $options = []): array
    {
        $prepared = $this->prepService->prepareDelivery($teamId, $options);
        $review = $this->reviewFromPrepared($prepared, $options);

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    public function validateDraftForSending(array $draftPayload, array $options = []): array
    {
        $channel = (string) ($draftPayload['channel'] ?? $options['channel'] ?? 'copy');
        $audience = (string) ($draftPayload['audience'] ?? $options['audience'] ?? 'coach');
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
        if ($this->unsafeTemplateAudienceReason($template, $audience) !== null) {
            $blockers[] = $this->unsafeTemplateAudienceReason($template, $audience);
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
        if ($this->publicAudienceHasPrivateWarnings($audience, $privacyWarnings)) {
            $blockers[] = 'Parent/player draft contains private or staff-only warning context.';
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
            'can_send' => $blockers === [] && $channel !== 'copy' && $this->channelHasSender($channel),
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

        return [
            ...$review,
            'delivery_status' => $this->statusFromValidation($review, $validation, false),
            'can_send' => false,
            'send_blockers' => $this->displayBlockers($validation['send_blockers']),
            'delivery_warnings' => array_values(array_unique(array_filter([
                ...Arr::wrap($review['delivery_warnings'] ?? []),
                ...Arr::wrap($validation['warnings'] ?? []),
            ]))),
            'preview' => [
                'edited_by_user_id' => $userId,
                'edited_at' => now()->toIso8601String(),
                'validation' => [
                    'safe_recipient_count' => count($validation['safe_recipients'] ?? []),
                    'unsafe_recipient_count' => count($validation['unsafe_recipients'] ?? []),
                    'channel_supported' => (bool) ($validation['channel_supported'] ?? false),
                ],
            ],
        ];
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
        $validation = $this->validateDraftForSending($review, [
            ...$options,
            'confirm_send' => (bool) ($options['confirm_send'] ?? $draftPayload['confirm_send'] ?? false),
        ]);

        if (! (bool) ($options['confirm_send'] ?? $draftPayload['confirm_send'] ?? false)) {
            return $this->buildSendResult([
                'send_status' => 'blocked',
                'warnings' => ['Coach confirmation is required before sending.'],
                'evidence' => ['send endpoint called without confirm_send=true'],
            ], $review);
        }

        $blockers = $this->actionableBlockers($validation['send_blockers'] ?? []);
        $privacyOrContentBlockers = collect($blockers)
            ->reject(fn (string $blocker): bool => $blocker === 'No supported delivery sender found. Use Copy Message.')
            ->values()
            ->all();

        if (($validation['copy_only'] ?? false) === true) {
            return $this->buildSendResult([
                'send_status' => 'unsupported',
                'warnings' => ['Copy-only delivery. Nothing was sent by FMTRX.'],
                'evidence' => ['selected channel was copy'],
            ], $review);
        }

        if ($privacyOrContentBlockers !== []) {
            return $this->buildSendResult([
                'send_status' => 'blocked',
                'warnings' => $this->displayBlockers($privacyOrContentBlockers),
                'evidence' => ['privacy and content validation blocked sending before channel support was considered'],
            ], $review);
        }

        if (($validation['channel_supported'] ?? false) === false) {
            return $this->buildSendResult([
                'send_status' => 'unsupported',
                'warnings' => ['No supported delivery sender found. Use Copy Message.'],
                'evidence' => ['no existing weekly report sender was found for this channel'],
            ], $review);
        }

        if (! (bool) ($validation['can_send'] ?? false)) {
            return $this->buildSendResult([
                'send_status' => 'blocked',
                'warnings' => $this->displayBlockers($validation['send_blockers'] ?? []),
                'evidence' => ['privacy and recipient validation blocked sending'],
            ], $review);
        }

        return $this->buildSendResult([
            'send_status' => 'unsupported',
            'warnings' => ['No sender implementation is wired for weekly reports. Nothing was sent.'],
            'evidence' => ['send path is intentionally disabled until a supported sender exists'],
        ], $review);
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
            'audience' => (string) ($draftPayload['audience'] ?? 'coach'),
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

    private function reviewFromPrepared(array $prepared, array $options = []): array
    {
        $validation = $this->validateDraftForSending($prepared, [
            ...$options,
            'confirm_send' => false,
        ]);

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => (string) ($prepared['team_id'] ?? ''),
            'audience' => (string) ($prepared['audience'] ?? 'coach'),
            'template' => (string) ($prepared['template'] ?? ''),
            'channel' => (string) ($prepared['channel'] ?? 'copy'),
            'delivery_status' => $this->statusFromValidation($prepared, $validation, false),
            'can_send' => false,
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
            'send_blockers' => $this->displayBlockers($validation['send_blockers']),
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
            ],
        ];
    }

    private function statusFromValidation(array $draft, array $validation, bool $confirmed): string
    {
        if ((string) ($draft['channel'] ?? 'copy') === 'copy') {
            return 'copy_only';
        }

        $blockers = $this->actionableBlockers($validation['send_blockers'] ?? []);
        if ($blockers !== []) {
            return 'blocked';
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

    private function unsafeTemplateAudienceReason(string $templateKey, string $audience): ?string
    {
        if (in_array($audience, ['parents', 'players'], true) && in_array($templateKey, ['staff_report', 'internal_benchmark_qa', WeeklyReportTemplateService::DEFAULT_TEMPLATE], true)) {
            return 'Internal, staff, and detailed coach reports cannot be sent to parent/player audiences.';
        }

        return null;
    }

    private function publicAudienceHasPrivateWarnings(string $audience, array $warnings): bool
    {
        if (! in_array($audience, ['parents', 'players'], true)) {
            return false;
        }

        return collect($warnings)
            ->contains(function (mixed $warning): bool {
                $text = strtolower((string) $warning);

                return str_contains($text, 'staff note')
                    || str_contains($text, 'private note')
                    || str_contains($text, 'internal qa cannot')
                    || str_contains($text, 'cannot be delivered')
                    || str_contains($text, 'cannot be sent');
            });
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }
}
