<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanCompletionSummaryService;
use Illuminate\Console\Command;

class DailyPlanCompletionSummaryAudit extends Command
{
    protected $signature = 'planner:completion-summary
        {dailyPlanId : Daily plan id}
        {--playerId= : Optional player/user id for player summary}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX Daily Plan completion summaries for a player or whole plan.';

    public function handle(DailyPlanCompletionSummaryService $service): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $playerId = $this->option('playerId') ? (string) $this->option('playerId') : null;

        $summary = $playerId
            ? $service->buildPlayerSummary($dailyPlanId, $playerId)
            : $service->buildCoachSummary($dailyPlanId);

        if ($this->option('json')) {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($playerId) {
            $this->printPlayerSummary($summary);
        } else {
            $this->printCoachSummary($summary);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function printPlayerSummary(array $summary): void
    {
        $this->info('FMTRX PLAYER WORKOUT COMPLETION SUMMARY');
        $this->line('Daily Plan ID: '.$this->value($summary['daily_plan_id'] ?? null));
        $this->line('Player ID: '.$this->value($summary['player_id'] ?? null));
        $this->line('Plan title: '.$this->value($summary['plan_title'] ?? null));
        $this->line('Status: '.$this->value($summary['summary_status'] ?? null));
        $this->line('Completion: '.$this->value($summary['completed_items'] ?? 0).'/'.$this->value($summary['total_items'] ?? 0).' ('.$this->value($summary['completion_percentage'] ?? 0).'%)');
        $this->line('Benchmark items completed: '.$this->value($summary['benchmark_items_completed'] ?? 0));
        $this->line('Submitted values: '.count($summary['metric_values_submitted'] ?? []));
        $this->line('Pending review: '.count($summary['pending_review'] ?? []));
        $this->line('Approved: '.count($summary['approved_results'] ?? []));
        $this->line('Corrections requested: '.count($summary['corrections_requested'] ?? []));
        $this->line('Message: '.$this->value($summary['message'] ?? null));
        $this->line('Next step: '.$this->value($summary['next_step'] ?? null));

        $this->newLine();
        $this->line('SUBMITTED VALUES');
        $this->line('----------------');
        foreach (($summary['metric_values_submitted'] ?? []) as $row) {
            $this->line('- '.$this->value($row['label'] ?? null).': '.$this->value($row['value'] ?? null).' '.$this->value($row['unit'] ?? null));
        }
        if (empty($summary['metric_values_submitted'])) {
            $this->line('- none');
        }
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function printCoachSummary(array $summary): void
    {
        $this->info('FMTRX DAILY PLAN COMPLETION SUMMARY');
        $this->line('Daily Plan ID: '.$this->value($summary['daily_plan_id'] ?? null));
        $this->line('Team ID: '.$this->value($summary['team_id'] ?? null));
        $this->line('Plan title: '.$this->value($summary['plan_title'] ?? null));
        $this->line('Team completion: '.$this->value($summary['team_completion_percentage'] ?? 0).'%');
        $this->line('Completed players: '.$this->value($summary['completed_player_count'] ?? 0));
        $this->line('In progress: '.$this->value($summary['in_progress_player_count'] ?? 0));
        $this->line('Not started: '.$this->value($summary['not_started_player_count'] ?? 0));
        $this->line('Benchmark submissions: '.$this->value($summary['benchmark_submissions_count'] ?? 0));
        $this->line('Pending reviews: '.$this->value($summary['pending_review_count'] ?? 0));
        $this->line('Approved values: '.$this->value($summary['approved_count'] ?? 0));
        $this->line('Correction requests: '.$this->value($summary['correction_requested_count'] ?? 0));

        $this->newLine();
        $this->line('PLAYER SUMMARIES');
        $this->line('----------------');
        foreach (($summary['player_summaries'] ?? []) as $row) {
            $this->line(sprintf(
                '- %s: %s%% complete, %s values, %s pending, %s approved, %s corrections. Next: %s',
                $this->value($row['player_name'] ?? null),
                $this->value($row['completion_percentage'] ?? 0),
                $this->value($row['benchmark_values_submitted'] ?? 0),
                $this->value($row['pending_review_count'] ?? 0),
                $this->value($row['approved_count'] ?? 0),
                $this->value($row['correction_requested_count'] ?? 0),
                $this->value($row['next_needed_action'] ?? null),
            ));
        }
        if (empty($summary['player_summaries'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('NEXT ACTIONS');
        $this->line('------------');
        foreach (($summary['coach_next_actions'] ?? []) as $action) {
            $this->line('- '.$this->value($action));
        }
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
