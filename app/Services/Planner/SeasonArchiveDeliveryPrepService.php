<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\CoachTeam;
use App\Models\PlayerTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;

class SeasonArchiveDeliveryPrepService
{
    private const AUDIENCES = ['coach', 'staff', 'director', 'players', 'parents'];

    private const CHANNELS = ['copy', 'email', 'message', 'announcement', 'notification'];

    private const FORMATS = ['text', 'html'];

    private const TEMPLATES = [
        'staff_review_packet',
        'director_packet',
        'parent_safe_season_summary',
        'player_development_summary',
        'internal_qa_packet',
    ];

    public function __construct(
        private readonly SeasonArchiveExportService $exportService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareDelivery(string $teamId, array $options = []): array
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? 'staff'), self::AUDIENCES, 'staff');
        $channel = $this->optionIn((string) ($options['channel'] ?? 'copy'), self::CHANNELS, 'copy');
        $format = $this->optionIn((string) ($options['format'] ?? 'text'), self::FORMATS, 'text');
        $template = $this->resolveTemplate((string) ($options['template'] ?? ''), $audience);
        $unsafeReason = $this->unsafeTemplateAudienceReason($template, $audience);
        $recipientPreview = $this->buildRecipientPreview($teamId, $audience, $options);
        $privacyWarnings = $this->privacyWarnings($audience, $template);
        $deliveryWarnings = [];

        if ($unsafeReason !== null) {
            $recipients = array_map(fn (array $recipient): array => [
                ...$recipient,
                'safe_to_send' => false,
                'warning' => $recipient['warning'] ?: $unsafeReason,
            ], $recipientPreview['recipients']);

            return [
                'generated_at' => now()->toIso8601String(),
                'team_id' => $teamId,
                'audience' => $audience,
                'template' => $template,
                'channel' => $channel,
                'format' => $format,
                'delivery_status' => 'unsupported',
                'subject' => $this->subjectFor($teamId, $template, $audience, $options),
                'message_text' => null,
                'message_html' => null,
                'recipients' => $recipients,
                'recipient_summary' => $this->recipientSummary($recipients),
                'privacy_warnings' => array_values(array_unique(array_filter([
                    ...$privacyWarnings,
                    $unsafeReason,
                ]))),
                'delivery_warnings' => ['Delivery blocked until the audience/template combination is changed.'],
                'draft' => [],
                'requires_coach_approval' => true,
            ];
        }

        $textExport = $this->exportService->buildExport($teamId, [
            ...$options,
            ...$this->templateExportOptions($template, $audience),
            'format' => 'text',
            'audience' => $audience,
            'include_private_notes' => false,
            'include_internal_qa' => $template === 'internal_qa_packet',
        ]);
        $htmlExport = $format === 'html'
            ? $this->exportService->buildExport($teamId, [
                ...$options,
                ...$this->templateExportOptions($template, $audience),
                'format' => 'html',
                'audience' => $audience,
                'include_private_notes' => false,
                'include_internal_qa' => $template === 'internal_qa_packet',
            ])
            : null;
        $export = $htmlExport ?: $textExport;
        $message = $this->buildDeliveryMessage($export, $recipientPreview['recipients'], [
            ...$options,
            'format' => $format,
            'channel' => $channel,
            'subject' => $this->subjectFor($teamId, $template, $audience, [
                ...$options,
                'season_range' => $export['packet']['season_range'] ?? null,
            ]),
            'message_text' => $textExport['share_text'] ?? null,
            'message_html' => $htmlExport['html'] ?? null,
        ]);

        if ($channel !== 'copy') {
            $deliveryWarnings[] = $this->unsupportedChannelWarning($channel);
            $deliveryWarnings[] = 'No delivery draft system found. Copy message manually.';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'audience' => $audience,
            'template' => $template,
            'channel' => $channel,
            'format' => $format,
            'delivery_status' => $channel === 'copy' ? 'prepared' : 'unsupported',
            'subject' => $message['subject'],
            'message_text' => $message['message_text'],
            'message_html' => $message['message_html'],
            'recipients' => $recipientPreview['recipients'],
            'recipient_summary' => $recipientPreview['recipient_summary'],
            'privacy_warnings' => array_values(array_unique(array_filter([
                ...$privacyWarnings,
                ...Arr::wrap($export['warnings'] ?? []),
            ]))),
            'delivery_warnings' => array_values(array_unique(array_filter([
                ...$deliveryWarnings,
                ...Arr::wrap($recipientPreview['warnings'] ?? []),
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
        $audience = $this->optionIn($audience, self::AUDIENCES, 'staff');
        $warnings = [];
        $recipients = match ($audience) {
            'coach', 'staff' => $this->coachRecipients($teamId, $audience),
            'director' => [],
            'players' => $this->playerRecipients($teamId),
            'parents' => [],
            default => [],
        };

        if ($audience === 'director') {
            $warnings[] = 'Director delivery contacts are not configured yet. Copy the director packet manually.';
        }
        if ($audience === 'parents') {
            $warnings[] = 'Parent contact delivery is not configured yet. Copy the parent-safe message manually.';
        }
        if ($recipients === [] && ! in_array($audience, ['director', 'parents'], true)) {
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
        $subject = $this->cleanText($options['subject'] ?? null) ?: 'FMTRX Season Development Review';

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
        if ($overrides !== []) {
            $prepared['subject'] = $this->cleanText($overrides['subject'] ?? null) ?: $prepared['subject'];
            $prepared['message_text'] = $this->cleanText($overrides['message_text'] ?? null) ?: $prepared['message_text'];
            $prepared['message_html'] = $this->cleanText($overrides['message_html'] ?? null) ?: $prepared['message_html'];
        }

        $prepared['draft'] = [
            'created' => false,
            'draft_supported' => false,
            'created_by_user_id' => $createdByUserId,
            'message' => 'No season archive delivery draft system was found. Copy message manually.',
        ];
        $prepared['delivery_warnings'] = array_values(array_unique(array_filter([
            ...Arr::wrap($prepared['delivery_warnings'] ?? []),
            'No delivery draft system found. Copy message manually.',
        ])));

        if ($prepared['delivery_status'] !== 'unsupported') {
            $prepared['delivery_status'] = 'prepared';
        }

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
                    'recipient_type' => $audience === 'coach' ? 'coach' : 'staff',
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
                'warning' => 'Raw email sending is not configured for season archives.',
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

    private function resolveTemplate(string $template, string $audience): string
    {
        $template = $this->optionIn($template, self::TEMPLATES, '');
        if ($template !== '') {
            return $template;
        }

        return match ($audience) {
            'coach', 'staff' => 'staff_review_packet',
            'director' => 'director_packet',
            'parents' => 'parent_safe_season_summary',
            'players' => 'player_development_summary',
            default => 'staff_review_packet',
        };
    }

    private function templateExportOptions(string $template, string $audience): array
    {
        $public = in_array($audience, ['parents', 'players'], true);

        return match ($template) {
            'parent_safe_season_summary' => [
                'include_player_rows' => false,
                'include_benchmark_progress' => true,
                'include_planner_progress' => true,
                'include_communication_summary' => true,
                'include_weekly_timeline' => true,
                'include_next_steps' => true,
            ],
            'player_development_summary' => [
                'include_player_rows' => false,
                'include_benchmark_progress' => true,
                'include_planner_progress' => true,
                'include_communication_summary' => false,
                'include_weekly_timeline' => true,
                'include_next_steps' => true,
            ],
            'director_packet' => [
                'include_player_rows' => ! $public,
                'include_benchmark_progress' => true,
                'include_planner_progress' => true,
                'include_communication_summary' => true,
                'include_weekly_timeline' => true,
                'include_next_steps' => true,
            ],
            default => [
                'include_player_rows' => ! $public,
                'include_benchmark_progress' => true,
                'include_planner_progress' => true,
                'include_communication_summary' => true,
                'include_weekly_timeline' => true,
                'include_next_steps' => true,
            ],
        };
    }

    private function subjectFor(string $teamId, string $template, string $audience, array $options = []): string
    {
        $teamName = Team::query()->whereKey($teamId)->value('name') ?: 'Team';
        $range = $this->cleanText($options['season_range'] ?? null) ?: $this->dateRangeLabel($options);

        return match ($template) {
            'director_packet' => "FMTRX Season Development Summary — {$teamName}",
            'parent_safe_season_summary' => "FMTRX Season Development Update — {$teamName}",
            'player_development_summary' => 'Your FMTRX Season Development Summary',
            'internal_qa_packet' => "FMTRX Internal Season Benchmark QA — {$teamName}",
            default => "FMTRX Season Development Review — {$teamName} — {$range}",
        };
    }

    private function dateRangeLabel(array $options): string
    {
        $start = $this->cleanText($options['season_start_date'] ?? $options['start_date'] ?? null);
        $end = $this->cleanText($options['season_end_date'] ?? $options['end_date'] ?? null);

        if ($start && $end) {
            return $start === $end ? $start : "{$start} to {$end}";
        }

        $weeks = max(1, min(52, (int) ($options['weeks'] ?? 12)));

        return now()->subWeeks($weeks - 1)->startOfWeek()->format('M j').' to '.now()->endOfWeek()->format('M j');
    }

    private function unsafeTemplateAudienceReason(string $template, string $audience): ?string
    {
        if ($template === 'internal_qa_packet' && ! in_array($audience, ['coach', 'staff', 'director'], true)) {
            return 'Internal QA packets are coach/staff/director only.';
        }
        if ($audience === 'parents' && $template !== 'parent_safe_season_summary') {
            return 'Staff, director, player, and internal season packets should not be sent to parents.';
        }
        if ($audience === 'players' && $template !== 'player_development_summary') {
            return 'Staff, director, parent, and internal season packets should not be delivered to players.';
        }
        if (in_array($audience, ['coach', 'staff', 'director'], true)
            && in_array($template, ['parent_safe_season_summary', 'player_development_summary'], true)
        ) {
            return 'Parent or player season templates should not be delivered as staff review packets.';
        }

        return null;
    }

    private function privacyWarnings(string $audience, string $template): array
    {
        return array_values(array_filter([
            $audience === 'parents' ? 'Parent version hides private player details, staff notes, internal QA, and raw benchmark payloads.' : null,
            $audience === 'players' ? 'Player version hides other-player details, staff notes, internal QA, and raw benchmark payloads.' : null,
            $template === 'internal_qa_packet' ? 'Internal QA packets are coach/staff/director only.' : null,
        ]));
    }

    private function unsupportedChannelWarning(string $channel): string
    {
        return match ($channel) {
            'email' => 'Email draft delivery is not configured yet. Copy the season packet manually.',
            'message' => 'In-app message draft delivery is not configured yet. Copy the season packet manually.',
            'announcement' => 'Team announcement draft delivery is not configured yet. Copy the season packet manually.',
            'notification' => 'Full-packet notification delivery is not configured. Copy the season packet manually.',
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

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
