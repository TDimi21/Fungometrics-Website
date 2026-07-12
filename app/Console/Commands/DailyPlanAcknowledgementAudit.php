<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanPlayerUpdateService;
use Illuminate\Console\Command;

class DailyPlanAcknowledgementAudit extends Command
{
    protected $signature = 'planner:daily-plan-acknowledgements
        {dailyPlanId}
        {--playerId= : Show or acknowledge one player}
        {--acknowledge : Mark the selected player as acknowledging the latest revision}
        {--revision= : Specific revision id or revision number}
        {--json : Print raw JSON output}';

    protected $description = 'Audit player acknowledgement status for a republished Daily Plan.';

    public function handle(DailyPlanPlayerUpdateService $service): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $playerId = $this->option('playerId') ? (string) $this->option('playerId') : null;
        $revision = $this->option('revision') ? (string) $this->option('revision') : null;

        if ((bool) $this->option('acknowledge') && ! $playerId) {
            $this->error('--acknowledge requires --playerId.');

            return self::FAILURE;
        }

        if ($playerId && (bool) $this->option('acknowledge')) {
            $result = $service->acknowledgeUpdate($dailyPlanId, $playerId, $revision, [
                'source' => 'planner:daily-plan-acknowledgements',
            ]);
        } elseif ($playerId) {
            $result = $service->buildPlayerAcknowledgementStatus($dailyPlanId, $playerId);
        } else {
            $result = $service->buildTeamAcknowledgementStatus($dailyPlanId);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX DAILY PLAN ACKNOWLEDGEMENTS');
        $this->line('Daily Plan ID: '.$dailyPlanId);

        if ($playerId) {
            $this->printPlayer($result);

            return self::SUCCESS;
        }

        $this->line('Team ID: '.($result['team_id'] ?? '-'));
        $this->line('Latest revision: '.($result['latest_revision_number'] ?? '-').' | '.($result['latest_revision_id'] ?? '-'));
        $this->line('Assigned players: '.($result['assigned_player_count'] ?? 0));
        $this->line('Acknowledged: '.($result['acknowledged_count'] ?? 0));
        $this->line('Not acknowledged: '.($result['not_acknowledged_count'] ?? 0));
        $this->line('Acknowledgement %: '.($result['acknowledgement_percentage'] ?? 0).'%');

        $this->newLine();
        $this->line('PLAYERS ACKNOWLEDGED');
        $this->line('--------------------');
        $this->printPlayers($result['players_acknowledged'] ?? []);

        $this->newLine();
        $this->line('PLAYERS PENDING');
        $this->line('---------------');
        $this->printPlayers($result['players_not_acknowledged'] ?? []);

        $this->newLine();
        $this->line('WARNINGS');
        $this->line('--------');
        $warnings = $result['warnings'] ?? [];
        if (empty($warnings)) {
            $this->line('- none');
        }
        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        return self::SUCCESS;
    }

    private function printPlayer(array $result): void
    {
        $this->line('Player ID: '.($result['player_id'] ?? '-'));
        $this->line('Player: '.($result['player_name'] ?? '-'));
        $this->line('Assigned: '.(($result['assigned'] ?? false) ? 'YES' : 'NO'));
        $this->line('Has update: '.(($result['has_update'] ?? false) ? 'YES' : 'NO'));
        $this->line('Acknowledged: '.(($result['acknowledged'] ?? false) ? 'YES' : 'NO'));
        $this->line('Acknowledged at: '.($result['acknowledged_at'] ?? '-'));
        $this->line('Latest seen at: '.($result['latest_revision_seen_at'] ?? '-'));
        $this->line('Latest revision #: '.($result['latest_revision_number'] ?? '-'));

        $warnings = $result['warnings'] ?? [];
        if (! empty($warnings)) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($warnings as $warning) {
                $this->line('- '.$warning);
            }
        }
    }

    private function printPlayers(array $players): void
    {
        if (empty($players)) {
            $this->line('- none');

            return;
        }

        foreach ($players as $player) {
            $this->line(sprintf(
                '- %s | %s | seen %s | acknowledged %s',
                $player['player_name'] ?? $player['player_id'] ?? '-',
                $player['player_id'] ?? '-',
                $player['latest_revision_seen_at'] ?? '-',
                $player['acknowledged_at'] ?? '-',
            ));
        }
    }
}
