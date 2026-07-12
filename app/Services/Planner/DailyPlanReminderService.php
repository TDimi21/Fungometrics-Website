<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\DailyPlan;
use Illuminate\Support\Arr;

class DailyPlanReminderService
{
    public function __construct(
        private readonly DailyPlanPlayerUpdateService $playerUpdateService,
    ) {
    }

    public function buildReminderPreview(string $dailyPlanId, array $options = []): array
    {
        $status = $this->playerUpdateService->buildTeamAcknowledgementStatus($dailyPlanId);
        $players = $this->pendingPlayers($status);
        $warnings = $this->warnings($status, count($players));

        return [
            'daily_plan_id' => $dailyPlanId,
            'team_id' => $status['team_id'] ?? $this->teamId($dailyPlanId),
            'latest_revision_id' => $status['latest_revision_id'] ?? null,
            'latest_revision_number' => $status['latest_revision_number'] ?? null,
            'assigned_player_count' => (int) ($status['assigned_player_count'] ?? 0),
            'acknowledged_count' => (int) ($status['acknowledged_count'] ?? 0),
            'unacknowledged_count' => count($players),
            'players_to_remind' => $players,
            'reminder_channel' => $this->reminderChannel(count($players), $status),
            'reminder_preview' => $this->previewPayload(count($players), $options),
            'send_result' => [],
            'warnings' => $warnings,
        ];
    }

    public function sendReminderToUnacknowledged(string $dailyPlanId, ?string $sentByUserId = null, array $options = []): array
    {
        $preview = $this->buildReminderPreview($dailyPlanId, $options);

        return $this->sendReminderToPlayers(
            $dailyPlanId,
            array_column($preview['players_to_remind'], 'player_id'),
            $sentByUserId,
            $options
        );
    }

    public function sendReminderToPlayers(string $dailyPlanId, array $playerIds, ?string $sentByUserId = null, array $options = []): array
    {
        $preview = $this->buildReminderPreview($dailyPlanId, $options);
        $requestedIds = $this->stringList($playerIds);
        $pendingById = collect($preview['players_to_remind'])
            ->keyBy(fn (array $player): string => (string) ($player['player_id'] ?? ''));

        $players = $requestedIds === []
            ? $preview['players_to_remind']
            : collect($requestedIds)
                ->map(fn (string $playerId) => $pendingById->get($playerId))
                ->filter()
                ->values()
                ->all();

        $skipped = $requestedIds === []
            ? []
            : collect($requestedIds)
                ->reject(fn (string $playerId): bool => $pendingById->has($playerId))
                ->map(fn (string $playerId): array => [
                    'player_id' => $playerId,
                    'reason' => 'Player is not assigned or is not pending acknowledgement.',
                ])
                ->values()
                ->all();

        $sendResult = [
            'send_supported' => false,
            'sent' => false,
            'sent_count' => 0,
            'requested_player_count' => count($requestedIds),
            'selected_player_count' => count($players),
            'manual_copy_required' => count($players) > 0,
            'sent_by_user_id' => $sentByUserId,
            'players_reminded' => [],
            'players_selected' => $players,
            'players_skipped' => $skipped,
            'message' => count($players) > 0
                ? 'Reminder sending is not available yet. Copy this message instead.'
                : 'No players need an update reminder.',
            'warnings' => count($players) > 0
                ? ['No reusable Daily Plan notification/message delivery system was found. Manual copy fallback is active.']
                : [],
        ];

        return [
            ...$preview,
            'players_to_remind' => $players,
            'unacknowledged_count' => count($players),
            'reminder_channel' => count($players) > 0 ? 'manual_copy' : 'none',
            'reminder_preview' => $this->previewPayload(count($players), $options),
            'send_result' => $sendResult,
            'warnings' => array_values(array_unique([
                ...Arr::wrap($preview['warnings'] ?? []),
                ...Arr::wrap($sendResult['warnings'] ?? []),
            ])),
        ];
    }

    public function buildReminderStatus(string $dailyPlanId): array
    {
        return $this->buildReminderPreview($dailyPlanId);
    }

    private function pendingPlayers(array $status): array
    {
        return collect(Arr::wrap($status['players_not_acknowledged'] ?? []))
            ->map(function (array $player): array {
                return [
                    'player_id' => (string) ($player['player_id'] ?? ''),
                    'player_name' => (string) ($player['player_name'] ?? 'Player'),
                    'acknowledged' => false,
                    'last_seen_at' => $player['latest_revision_seen_at'] ?? null,
                    'assigned' => (bool) ($player['assigned'] ?? true),
                ];
            })
            ->filter(fn (array $player): bool => $player['player_id'] !== '')
            ->values()
            ->all();
    }

    private function previewPayload(int $playerCount, array $options = []): array
    {
        $customMessage = $this->cleanText($options['message'] ?? null);
        $message = $customMessage ?: 'Your coach updated today\'s plan. Open FMTRX, review the changes, and tap Got it so your coach knows you saw it.';
        $shortMessage = $this->cleanText($options['short_message'] ?? null)
            ?: 'Your FMTRX daily plan was updated. Please review and acknowledge it.';
        $coachCopy = $this->cleanText($options['coach_copy'] ?? null)
            ?: ($customMessage ?: 'Reminder: today\'s FMTRX plan was updated. Please open the app, review the changes, and tap Got it.');

        return [
            'title' => 'Updated Daily Plan',
            'message' => $message,
            'short_message' => $shortMessage,
            'coach_copy' => $coachCopy,
            'player_count' => $playerCount,
        ];
    }

    private function reminderChannel(int $pendingCount, array $status): string
    {
        if ($pendingCount < 1) {
            return 'none';
        }

        if (empty($status['latest_revision_id'])) {
            return 'none';
        }

        return 'manual_copy';
    }

    private function warnings(array $status, int $pendingCount): array
    {
        $warnings = Arr::wrap($status['warnings'] ?? []);

        if ($pendingCount > 0) {
            $warnings[] = 'No reusable Daily Plan notification/message delivery system was found. Manual copy fallback is active.';
        }

        return array_values(array_unique(array_filter(array_map('strval', $warnings))));
    }

    private function teamId(string $dailyPlanId): ?string
    {
        $teamId = DailyPlan::query()
            ->where('id', $dailyPlanId)
            ->value('team_id');

        return $teamId ? (string) $teamId : null;
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
