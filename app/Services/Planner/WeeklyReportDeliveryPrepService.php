<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;

class WeeklyReportDeliveryPrepService
{
    private const AUDIENCES = ['coach', 'staff', 'players', 'parents'];

    private const CHANNELS = ['copy', 'email', 'message', 'announcement', 'notification'];

    private const FORMATS = ['text', 'html'];

    public function __construct(
        private readonly CoachWeeklyReportExportService $exportService,
        private readonly WeeklyReportTemplateService $templateService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareDelivery(string $teamId, array $options = []): array
    {
        $requestedAudience = $this->optionIn((string) ($options['audience'] ?? 'coach'), self::AUDIENCES, 'coach');
        $channel = $this->optionIn((string) ($options['channel'] ?? 'copy'), self::CHANNELS, 'copy');
        $format = $this->optionIn((string) ($options['format'] ?? 'text'), self::FORMATS, 'text');
        $template = $this->templateService->resolveTemplate($options['template'] ?? null, $requestedAudience);
        $templateKey = (string) ($template['template_key'] ?? WeeklyReportTemplateService::DEFAULT_TEMPLATE);
        $privacyWarnings = [];
        $deliveryWarnings = [];
        $unsafeReason = $this->unsafeTemplateAudienceReason($templateKey, $requestedAudience);

        if ($unsafeReason !== null) {
            $privacyWarnings[] = $unsafeReason;
            $recipients = $this->buildRecipientPreview($teamId, $requestedAudience, $options)['recipients'];
            $recipients = array_map(fn (array $recipient): array => [
                ...$recipient,
                'safe_to_send' => false,
                'warning' => $recipient['warning'] ?: $unsafeReason,
            ], $recipients);

            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'audience' => $requestedAudience,
                'template' => $templateKey,
                'channel' => $channel,
                'format' => $format,
                'delivery_status' => 'unsupported',
                'subject' => $this->subjectFor($teamId, $templateKey, $requestedAudience, $options),
                'message_text' => null,
                'message_html' => null,
                'recipients' => $recipients,
                'recipient_summary' => $this->recipientSummary($recipients),
                'privacy_warnings' => $privacyWarnings,
                'delivery_warnings' => ['Delivery blocked until the audience/template combination is changed.'],
                'draft' => [],
                'requires_coach_approval' => true,
            ];
        }

        $textExport = $this->exportService->buildExport($teamId, [
            ...$options,
            'format' => 'text',
            'audience' => $requestedAudience,
            'template' => $templateKey,
            'include_private_notes' => false,
        ]);
        $htmlExport = $format === 'html'
            ? $this->exportService->buildExport($teamId, [
                ...$options,
                'format' => 'html',
                'audience' => $requestedAudience,
                'template' => $templateKey,
                'include_private_notes' => false,
            ])
            : null;
        $export = $htmlExport ?: $textExport;
        $recipientPreview = $this->buildRecipientPreview($teamId, $requestedAudience, $options);
        $message = $this->buildDeliveryMessage($export, $recipientPreview['recipients'], [
            ...$options,
            'format' => $format,
            'channel' => $channel,
            'message_text' => $textExport['share_text'] ?? null,
            'message_html' => $htmlExport['html'] ?? null,
            'subject' => $this->subjectFor($teamId, $templateKey, $requestedAudience, $options),
        ]);

        if ($channel !== 'copy') {
            $deliveryWarnings[] = $this->unsupportedChannelWarning($channel);
        }
        $privacyWarnings = array_values(array_unique(array_filter([
            ...$this->privacyWarnings($requestedAudience, $templateKey),
            ...Arr::wrap($export['warnings'] ?? []),
        ])));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'audience' => $requestedAudience,
            'template' => $templateKey,
            'channel' => $channel === 'copy' ? 'copy' : $channel,
            'format' => $format,
            'delivery_status' => $channel === 'copy' ? 'prepared' : 'unsupported',
            'subject' => $message['subject'],
            'message_text' => $message['message_text'],
            'message_html' => $message['message_html'],
            'recipients' => $recipientPreview['recipients'],
            'recipient_summary' => $recipientPreview['recipient_summary'],
            'privacy_warnings' => $privacyWarnings,
            'delivery_warnings' => array_values(array_unique(array_filter([
                ...$deliveryWarnings,
                ...Arr::wrap($recipientPreview['warnings'] ?? []),
                $channel !== 'copy' ? 'No delivery draft system found. Copy message manually.' : null,
            ]))),
            'draft' => [],
            'requires_coach_approval' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildRecipientPreview(string $teamId, string $audience, array $options = []): array
    {
        $audience = $this->optionIn($audience, self::AUDIENCES, 'coach');
        $warnings = [];
        $recipients = match ($audience) {
            'coach', 'staff' => $this->coachRecipients($teamId, $audience),
            'players' => $this->playerRecipients($teamId),
            'parents' => [],
            default => [],
        };

        if ($audience === 'parents') {
            $warnings[] = 'Parent contact delivery is not configured yet. Copy the parent-safe message manually.';
        }
        if ($recipients === [] && $audience !== 'parents') {
            $warnings[] = 'No recipients found for this audience.';
        }

        $recipients = $this->applyRecipientFilters($recipients, $options);
        $manualEmails = $this->manualEmailRecipients(Arr::wrap($options['recipient_emails'] ?? []));
        if ($manualEmails !== []) {
            $warnings[] = 'Raw email delivery is not configured yet. Manual copy fallback is active.';
            $recipients = array_values([...$recipients, ...$manualEmails]);
        }

        return [
            'team_id' => $teamId,
            'audience' => $audience,
            'recipients' => $recipients,
            'recipient_summary' => $this->recipientSummary($recipients),
            'warnings' => array_values(array_unique(array_filter($warnings))),
        ];
    }

    /**
     * @param array<string, mixed> $export
     * @param array<int, array<string, mixed>> $recipients
     * @return array<string, mixed>
     */
    public function buildDeliveryMessage(array $export, array $recipients, array $options = []): array
    {
        $format = $this->optionIn((string) ($options['format'] ?? 'text'), self::FORMATS, 'text');
        $subject = $this->cleanText($options['subject'] ?? null) ?: 'FMTRX Weekly Report';

        return [
            'subject' => $subject,
            'message_text' => $this->cleanText($options['message_text'] ?? null) ?: $this->cleanText($export['share_text'] ?? null),
            'message_html' => $format === 'html'
                ? ($this->cleanText($options['message_html'] ?? null) ?: $this->cleanText($export['html'] ?? null))
                : null,
            'recipient_count' => count($recipients),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createDraftDelivery(string $teamId, array $deliveryPayload, ?string $createdByUserId = null): array
    {
        $prepared = $this->prepareDelivery($teamId, [
            ...$deliveryPayload,
            'current_user_id' => $createdByUserId,
        ]);

        $overrides = Arr::wrap($deliveryPayload['message_overrides'] ?? []);
        if (! empty($overrides)) {
            $prepared['subject'] = $this->cleanText($overrides['subject'] ?? null) ?: $prepared['subject'];
            $prepared['message_text'] = $this->cleanText($overrides['message_text'] ?? null) ?: $prepared['message_text'];
            $prepared['message_html'] = $this->cleanText($overrides['message_html'] ?? null) ?: $prepared['message_html'];
        }

        if ($prepared['delivery_status'] === 'unsupported') {
            $prepared['draft'] = [
                'created' => false,
                'draft_supported' => false,
                'created_by_user_id' => $createdByUserId,
                'message' => 'Draft creation is not supported for this delivery request.',
            ];

            return $prepared;
        }

        $prepared['delivery_status'] = 'prepared';
        $prepared['draft'] = [
            'created' => false,
            'draft_supported' => false,
            'created_by_user_id' => $createdByUserId,
            'message' => 'No reusable weekly report delivery draft system was found. Copy message manually.',
        ];
        $prepared['delivery_warnings'] = array_values(array_unique([
            ...Arr::wrap($prepared['delivery_warnings'] ?? []),
            'No delivery draft system found. Copy message manually.',
        ]));

        return $prepared;
    }

    private function coachRecipients(string $teamId, string $audience): array
    {
        return CoachTeam::query()
            ->with(['profile.user'])
            ->where('team_id', $teamId)
            ->get()
            ->map(function (CoachTeam $row) use ($audience): array {
                $user = $row->profile?->user ?? User::query()->find($row->coach_id);
                $name = $this->profileName($row->profile?->first_name, $row->profile?->last_name) ?: 'Coach';
                $email = $this->cleanText($user?->email ?? null);

                return [
                    'recipient_id' => (string) $row->coach_id,
                    'recipient_type' => $audience === 'staff' ? 'staff' : 'coach',
                    'name' => $name,
                    'email' => $email,
                    'player_id' => null,
                    'safe_to_send' => $email !== null,
                    'warning' => $email === null ? 'Missing coach email.' : null,
                ];
            })
            ->values()
            ->all();
    }

    private function playerRecipients(string $teamId): array
    {
        return PlayerTeam::query()
            ->with(['user.profile'])
            ->where('team_id', $teamId)
            ->get()
            ->map(function (PlayerTeam $row): array {
                $user = $row->user;
                $profile = $user?->profile;
                $email = $this->cleanText($user?->email ?? null);
                $name = $this->profileName($profile?->first_name, $profile?->last_name) ?: 'Player';

                return [
                    'recipient_id' => $user?->id ? (string) $user->id : null,
                    'recipient_type' => 'player',
                    'name' => $name,
                    'email' => $email,
                    'player_id' => (string) $row->user_id,
                    'safe_to_send' => $email !== null,
                    'warning' => $email === null ? 'Missing player email.' : null,
                ];
            })
            ->values()
            ->all();
    }

    private function applyRecipientFilters(array $recipients, array $options): array
    {
        $userIds = $this->stringList(Arr::wrap($options['recipient_user_ids'] ?? []));
        $playerIds = $this->stringList(Arr::wrap($options['recipient_player_ids'] ?? []));

        if ($userIds === [] && $playerIds === []) {
            return $recipients;
        }

        return collect($recipients)
            ->filter(function (array $recipient) use ($userIds, $playerIds): bool {
                $recipientId = (string) ($recipient['recipient_id'] ?? '');
                $playerId = (string) ($recipient['player_id'] ?? '');

                return ($userIds !== [] && in_array($recipientId, $userIds, true))
                    || ($playerIds !== [] && in_array($playerId, $playerIds, true));
            })
            ->values()
            ->all();
    }

    private function manualEmailRecipients(array $emails): array
    {
        return collect($emails)
            ->map(fn ($email): string => trim((string) $email))
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->values()
            ->map(fn (string $email): array => [
                'recipient_id' => null,
                'recipient_type' => 'email',
                'name' => null,
                'email' => $email,
                'player_id' => null,
                'safe_to_send' => false,
                'warning' => 'Raw email sending is not configured for weekly reports.',
            ])
            ->all();
    }

    private function recipientSummary(array $recipients): array
    {
        $types = collect($recipients)->groupBy(fn (array $recipient): string => (string) ($recipient['recipient_type'] ?? 'unknown'));
        $safe = collect($recipients)->filter(fn (array $recipient): bool => (bool) ($recipient['safe_to_send'] ?? false))->count();
        $missing = collect($recipients)->filter(fn (array $recipient): bool => empty($recipient['email']))->count();

        return [
            'total_recipients' => count($recipients),
            'safe_recipients' => $safe,
            'missing_contact_count' => $missing,
            'unsafe_recipient_count' => max(0, count($recipients) - $safe),
            'recipient_types' => $types
                ->map(fn ($rows): int => count($rows))
                ->all(),
        ];
    }

    private function subjectFor(string $teamId, string $templateKey, string $audience, array $options = []): string
    {
        $teamName = Team::query()->whereKey($teamId)->value('name') ?: 'Team';
        $range = $this->dateRangeLabel($options);

        return match ($templateKey) {
            'parent_update' => "FMTRX Weekly Update — {$teamName} — Week of {$range}",
            'staff_report' => "FMTRX Staff Weekly Report — {$teamName} — {$range}",
            'player_development_summary' => 'Your FMTRX Weekly Development Summary',
            'short_text_summary' => 'FMTRX Weekly Summary',
            'internal_benchmark_qa' => "FMTRX Internal Benchmark QA — {$teamName}",
            default => $audience === 'staff'
                ? "FMTRX Staff Weekly Report — {$teamName} — {$range}"
                : "FMTRX Weekly Team Report — {$teamName} — {$range}",
        };
    }

    private function dateRangeLabel(array $options): string
    {
        $start = $this->cleanText($options['start_date'] ?? null);
        $end = $this->cleanText($options['end_date'] ?? null);

        if ($start && $end) {
            return $start === $end ? $start : "{$start} to {$end}";
        }

        return now()->subDays(max(1, (int) ($options['days'] ?? 7)) - 1)->format('M j');
    }

    private function unsafeTemplateAudienceReason(string $templateKey, string $audience): ?string
    {
        $parentAllowed = ['parent_update', 'short_text_summary'];
        $playerAllowed = ['player_development_summary'];
        $staffAllowed = ['staff_report', WeeklyReportTemplateService::DEFAULT_TEMPLATE, 'internal_benchmark_qa', 'short_text_summary'];

        if ($audience === 'parents' && ! in_array($templateKey, $parentAllowed, true)) {
            return 'Staff, coach, and internal report templates cannot be delivered to parents.';
        }
        if ($audience === 'players' && ! in_array($templateKey, $playerAllowed, true)) {
            return 'Staff, coach, parent, and internal report templates cannot be delivered to players.';
        }
        if ($audience === 'staff' && ! in_array($templateKey, $staffAllowed, true)) {
            return 'Parent or player templates should not be delivered as staff reports.';
        }

        return null;
    }

    private function privacyWarnings(string $audience, string $templateKey): array
    {
        return array_values(array_filter([
            $audience === 'parents' ? 'Parent version hides private player review details.' : null,
            $audience === 'players' ? 'Player version hides other players’ details.' : null,
            $templateKey === 'internal_benchmark_qa' ? 'Internal QA reports are coach/staff only.' : null,
        ]));
    }

    private function unsupportedChannelWarning(string $channel): string
    {
        return match ($channel) {
            'email' => 'Email draft delivery is not configured yet. Copy the message manually.',
            'message' => 'In-app message draft delivery is not configured yet. Copy the message manually.',
            'announcement' => 'Team announcement draft delivery is not configured yet. Copy the message manually.',
            'notification' => 'Full-report notification delivery is not configured. Copy the message manually.',
            default => 'No delivery draft system found. Copy message manually.',
        };
    }

    private function optionIn(string $value, array $allowed, string $fallback): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function profileName(mixed $first, mixed $last): ?string
    {
        $name = trim((string) $first.' '.(string) $last);

        return $name !== '' ? $name : null;
    }

    private function cleanText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private function stringList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value): string => trim((string) $value),
            $values
        ))));
    }
}
