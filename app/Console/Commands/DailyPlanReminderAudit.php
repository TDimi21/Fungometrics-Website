<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanReminderService;
use Illuminate\Console\Command;

class DailyPlanReminderAudit extends Command
{
    protected $signature = 'planner:daily-plan-reminders
        {dailyPlanId}
        {--send : Prepare reminder send result. Existing delivery support falls back to manual copy when unavailable.}
        {--players= : Comma-separated player IDs}
        {--message= : Custom reminder message}
        {--json : Print raw JSON output}';

    protected $description = 'Audit Daily Plan reminder follow-ups for players who have not acknowledged an update.';

    public function handle(DailyPlanReminderService $reminderService): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $playerIds = $this->playerIds((string) ($this->option('players') ?? ''));
        $options = [
            'message' => $this->option('message') ? (string) $this->option('message') : null,
        ];

        if ((bool) $this->option('send')) {
            $payload = $playerIds === []
                ? $reminderService->sendReminderToUnacknowledged($dailyPlanId, null, $options)
                : $reminderService->sendReminderToPlayers($dailyPlanId, $playerIds, null, $options);
        } else {
            $payload = $reminderService->buildReminderPreview($dailyPlanId, $options);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX DAILY PLAN REMINDERS');
        $this->line('Daily Plan ID: '.$dailyPlanId);
        $this->line('Team ID: '.($payload['team_id'] ?? '-'));
        $this->line('Latest revision: '.($payload['latest_revision_number'] ?? '-').' | '.($payload['latest_revision_id'] ?? '-'));
        $this->line('Assigned players: '.($payload['assigned_player_count'] ?? 0));
        $this->line('Acknowledged: '.($payload['acknowledged_count'] ?? 0));
        $this->line('Unacknowledged: '.($payload['unacknowledged_count'] ?? 0));
        $this->line('Reminder channel: '.($payload['reminder_channel'] ?? '-'));

        $this->newLine();
        $this->line('REMINDER PREVIEW');
        $this->line('----------------');
        $preview = $payload['reminder_preview'] ?? [];
        $this->line('Title: '.($preview['title'] ?? '-'));
        $this->line('Message: '.($preview['message'] ?? '-'));
        $this->line('Short: '.($preview['short_message'] ?? '-'));
        $this->line('Coach copy: '.($preview['coach_copy'] ?? '-'));

        $this->newLine();
        $this->line('PLAYERS TO REMIND');
        $this->line('-----------------');
        $this->printPlayers($payload['players_to_remind'] ?? []);

        if (! empty($payload['send_result'])) {
            $this->newLine();
            $this->line('SEND RESULT');
            $this->line('-----------');
            $result = $payload['send_result'];
            $this->line('Send supported: '.(($result['send_supported'] ?? false) ? 'YES' : 'NO'));
            $this->line('Sent: '.(($result['sent'] ?? false) ? 'YES' : 'NO'));
            $this->line('Sent count: '.($result['sent_count'] ?? 0));
            $this->line('Manual copy required: '.(($result['manual_copy_required'] ?? false) ? 'YES' : 'NO'));
            $this->line('Message: '.($result['message'] ?? '-'));

            if (! empty($result['players_skipped'])) {
                $this->line('Skipped:');
                foreach ($result['players_skipped'] as $row) {
                    $this->line('- '.($row['player_id'] ?? '-').' | '.($row['reason'] ?? '-'));
                }
            }
        }

        $warnings = $payload['warnings'] ?? [];
        if (! empty($warnings)) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($warnings as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }

    private function playerIds(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $item): string => trim($item),
            explode(',', $value)
        ))));
    }

    private function printPlayers(array $players): void
    {
        if (empty($players)) {
            $this->line('- none');

            return;
        }

        foreach ($players as $player) {
            $this->line(sprintf(
                '- %s | %s | seen %s',
                $player['player_name'] ?? 'Player',
                $player['player_id'] ?? '-',
                $player['last_seen_at'] ?? '-',
            ));
        }
    }
}
