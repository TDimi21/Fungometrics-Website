<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyPlannerRollupService;
use Illuminate\Console\Command;

class WeeklyPlannerRollupAudit extends Command
{
    protected $signature = 'planner:weekly-rollup
        {teamId : Team id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Days to include when start/end are omitted}
        {--playerId= : Optional player/user id for player rollup}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly Daily Planner rollups for a team or player.';

    public function handle(WeeklyPlannerRollupService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $playerId = $this->option('playerId') ? (string) $this->option('playerId') : null;
        $options = [
            'start_date' => $this->option('start'),
            'end_date' => $this->option('end'),
            'days' => (int) $this->option('days'),
            'include_players' => true,
            'include_benchmark_intelligence' => true,
        ];

        $rollup = $playerId
            ? $service->buildPlayerWeeklyRollup($teamId, $playerId, $options)
            : $service->buildTeamWeeklyRollup($teamId, $options);

        if ($this->option('json')) {
            $this->line((string) json_encode($rollup, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $playerId ? $this->printPlayerRollup($rollup) : $this->printTeamRollup($rollup);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $rollup
     */
    private function printTeamRollup(array $rollup): void
    {
        $plan = $rollup['plan_execution_summary'] ?? [];
        $players = $rollup['player_completion_summary'] ?? [];
        $benchmark = $rollup['benchmark_collection_summary'] ?? [];
        $review = $rollup['review_summary'] ?? [];
        $trusted = $rollup['trusted_data_summary'] ?? [];

        $this->info('FMTRX WEEKLY PLANNER ROLLUP');
        $this->line('Team ID: '.$this->value($rollup['team_id'] ?? null));
        $this->line('Week: '.$this->value($rollup['week_label'] ?? null));
        $this->line('Status: '.$this->value($rollup['summary_status'] ?? null));
        $this->line('Plans published: '.$this->value($plan['plans_published'] ?? 0).' / created: '.$this->value($plan['plans_created'] ?? 0));
        $this->line('Average completion: '.$this->value($plan['average_completion_percentage'] ?? 0).'%');
        $this->line('Completed assignments: '.$this->value($plan['total_completed_assignments'] ?? 0).' / '.$this->value($plan['total_assigned_players'] ?? 0));
        $this->line('Players completed / partial / not started: '.$this->value($players['players_completed_all'] ?? 0).' / '.$this->value($players['players_partially_completed'] ?? 0).' / '.$this->value($players['players_not_started'] ?? 0));
        $this->line('Benchmark values submitted: '.$this->value($benchmark['metric_values_submitted'] ?? 0));
        $this->line('Approved values: '.$this->value($benchmark['metric_values_approved'] ?? 0));
        $this->line('Pending reviews: '.$this->value($review['pending_review_count'] ?? 0));
        $this->line('Trusted values promoted: '.$this->value($trusted['trusted_values_added'] ?? 0));
        $this->line('Coach summary: '.$this->value($rollup['coach_summary'] ?? null));

        $this->newLine();
        $this->line('PLAYERS NEEDING FOLLOW-UP');
        $this->line('-------------------------');
        foreach (($players['players_needing_follow_up'] ?? []) as $player) {
            $this->line('- '.$this->value($player['player_name'] ?? null).': '.$this->value($player['reason'] ?? null));
        }
        if (empty($players['players_needing_follow_up'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('NEXT WEEK RECOMMENDATIONS');
        $this->line('-------------------------');
        foreach (($rollup['next_week_recommendations'] ?? []) as $recommendation) {
            $this->line('- '.$this->value($recommendation['title'] ?? null).' ('.$this->value($recommendation['priority'] ?? null).'): '.$this->value($recommendation['why'] ?? null));
        }
        if (empty($rollup['next_week_recommendations'])) {
            $this->line('- none');
        }

        if (! empty($rollup['warnings'])) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($rollup['warnings'] as $warning) {
                $this->line('- '.$this->value($warning));
            }
        }
    }

    /**
     * @param array<string, mixed> $rollup
     */
    private function printPlayerRollup(array $rollup): void
    {
        $row = $rollup['player_rollup'] ?? [];

        $this->info('FMTRX PLAYER WEEKLY PLANNER ROLLUP');
        $this->line('Team ID: '.$this->value($rollup['team_id'] ?? null));
        $this->line('Player ID: '.$this->value($rollup['player_id'] ?? null));
        $this->line('Week: '.$this->value($rollup['week_label'] ?? null));
        $this->line('Plans assigned: '.$this->value($row['plans_assigned'] ?? 0));
        $this->line('Plans completed: '.$this->value($row['plans_completed'] ?? 0));
        $this->line('Completion: '.$this->value($row['completion_percentage'] ?? 0).'%');
        $this->line('Benchmark values submitted: '.$this->value($row['benchmark_values_submitted'] ?? 0));
        $this->line('Benchmark values approved: '.$this->value($row['benchmark_values_approved'] ?? 0));
        $this->line('Pending reviews: '.$this->value($row['pending_review_count'] ?? 0));
        $this->line('Corrections requested: '.$this->value($row['correction_requested_count'] ?? 0));
        $this->line('Next action: '.$this->value($row['next_recommended_action'] ?? null));
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
