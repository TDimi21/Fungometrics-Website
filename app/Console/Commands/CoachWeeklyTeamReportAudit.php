<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachWeeklyTeamReportService;
use Illuminate\Console\Command;

class CoachWeeklyTeamReportAudit extends Command
{
    protected $signature = 'planner:coach-weekly-report
        {teamId : Team id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Days to include when start/end are omitted}
        {--json : Output structured JSON}';

    protected $description = 'Audit the FMTRX coach weekly team report for a team.';

    public function handle(CoachWeeklyTeamReportService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $report = $service->buildTeamReport($teamId, [
            'start_date' => $this->option('start'),
            'end_date' => $this->option('end'),
            'days' => (int) $this->option('days'),
            'include_player_rows' => true,
            'include_benchmark_details' => true,
            'include_next_week_priorities' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->printReport($report);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function printReport(array $report): void
    {
        $summary = $report['executive_summary'] ?? [];
        $team = $report['team_completion'] ?? [];
        $benchmark = $report['benchmark_submission_summary'] ?? [];
        $review = $report['review_summary'] ?? [];
        $trusted = $report['trusted_data_summary'] ?? [];
        $missed = $report['missed_work_summary'] ?? [];

        $this->info('FMTRX COACH WEEKLY TEAM REPORT');
        $this->line('Team ID: '.$this->value($report['team_id'] ?? null));
        $this->line('Week: '.$this->value($report['week_label'] ?? null));
        $this->line('Status: '.$this->value($report['report_status'] ?? null));
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));
        $this->line('Summary: '.$this->value($summary['summary_text'] ?? null));
        $this->line('Next best action: '.$this->value($summary['next_best_action'] ?? null));

        $this->newLine();
        $this->line('TEAM COMPLETION');
        $this->line('---------------');
        $this->line('Assigned players: '.$this->value($team['assigned_player_count'] ?? 0));
        $this->line('Plans assigned / published: '.$this->value($team['plans_assigned'] ?? 0).' / '.$this->value($team['plans_published'] ?? 0));
        $this->line('Assignments complete / in progress / not started: '.$this->value($team['completed_assignments'] ?? 0).' / '.$this->value($team['in_progress_assignments'] ?? 0).' / '.$this->value($team['not_started_assignments'] ?? 0));
        $this->line('Team completion: '.$this->value($team['team_completion_percentage'] ?? 0).'%');
        $this->line('Average player completion: '.$this->value($team['average_player_completion_percentage'] ?? 0).'%');

        $this->newLine();
        $this->line('PLAYER ROWS');
        $this->line('-----------');
        foreach (($report['player_rows'] ?? []) as $row) {
            $this->line('- '.$this->value($row['player_name'] ?? null)
                .' | '.$this->value($row['completion_percentage'] ?? 0).'%'
                .' | plans '.$this->value($row['plans_completed'] ?? 0).'/'.$this->value($row['plans_assigned'] ?? 0)
                .' | submitted '.$this->value($row['benchmark_values_submitted'] ?? 0)
                .' | pending '.$this->value($row['pending_review_count'] ?? 0)
                .' | approved '.$this->value($row['approved_count'] ?? 0)
                .' | status '.$this->value($row['status_label'] ?? null));
        }
        if (empty($report['player_rows'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('BENCHMARK SUBMISSIONS');
        $this->line('---------------------');
        $this->line('Submitted values: '.$this->value($benchmark['submitted_metric_count'] ?? 0));
        $this->line('Approved values: '.$this->value($benchmark['approved_metric_count'] ?? 0));
        $this->line('Pending review: '.$this->value($benchmark['pending_review_count'] ?? 0));
        $this->line('Rejected: '.$this->value($benchmark['rejected_count'] ?? 0));
        $this->line('Correction requested: '.$this->value($benchmark['correction_requested_count'] ?? 0));
        $this->line('Trusted values promoted: '.$this->value($benchmark['trusted_values_promoted'] ?? 0));
        $this->line('Top collected metrics: '.$this->listLabels($benchmark['top_collected_metrics'] ?? [], 'display_name'));
        $this->line('Top remaining gaps: '.$this->listLabels($benchmark['top_remaining_missing_metrics'] ?? [], 'display_name'));

        $this->newLine();
        $this->line('REVIEW + TRUSTED DATA');
        $this->line('---------------------');
        $this->line('Pending reviews: '.$this->value($review['pending_review_count'] ?? 0));
        $this->line('Oldest pending: '.$this->value($review['oldest_pending_at'] ?? null));
        $this->line('Corrections requested: '.$this->value($review['correction_requested_count'] ?? 0));
        $this->line('Trusted values added: '.$this->value($trusted['trusted_values_added'] ?? 0));
        $this->line('Players improved: '.$this->value($trusted['players_improved'] ?? 0));
        $this->line('Team confidence after: '.$this->value($trusted['team_confidence_after'] ?? null));

        $this->newLine();
        $this->line('MISSED WORK');
        $this->line('-----------');
        $this->line('Players with missed work: '.$this->value($missed['players_with_missed_work'] ?? 0));
        $this->line('Missed plans: '.$this->value($missed['missed_plan_count'] ?? 0));
        $this->line('Missed items: '.$this->value($missed['missed_items_count'] ?? 0));

        $this->newLine();
        $this->line('COACH FOLLOW-UPS');
        $this->line('----------------');
        foreach (($report['coach_follow_ups'] ?? []) as $followUp) {
            $this->line('- '.$this->value($followUp['title'] ?? null).' ('.$this->value($followUp['priority'] ?? null).'): '.$this->value($followUp['recommended_action'] ?? null));
        }
        if (empty($report['coach_follow_ups'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('NEXT WEEK PRIORITIES');
        $this->line('--------------------');
        foreach (($report['next_week_priorities'] ?? []) as $priority) {
            $this->line('#'.$this->value($priority['rank'] ?? null).' '.$this->value($priority['title'] ?? null).' ('.$this->value($priority['priority'] ?? null).'): '.$this->value($priority['why'] ?? null));
            if (! empty($priority['suggested_block'])) {
                $this->line('  Block: '.$this->value($priority['suggested_block']).' · '.$this->value($priority['estimated_minutes'] ?? null).' min');
            }
        }
        if (empty($report['next_week_priorities'])) {
            $this->line('- none');
        }

        if (! empty($report['warnings'])) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($report['warnings'] as $warning) {
                $this->line('- '.$this->value($warning));
            }
        }
    }

    private function listLabels(mixed $rows, string $key): string
    {
        $labels = collect(is_array($rows) ? $rows : [])
            ->map(fn ($row): string => is_array($row) ? (string) ($row[$key] ?? $row['metric_key'] ?? '') : '')
            ->filter()
            ->values()
            ->all();

        return empty($labels) ? '-' : implode(', ', $labels);
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
