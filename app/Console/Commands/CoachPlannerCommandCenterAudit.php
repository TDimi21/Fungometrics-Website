<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachPlannerCommandCenterService;
use Illuminate\Console\Command;

class CoachPlannerCommandCenterAudit extends Command
{
    protected $signature = 'planner {teamId} {--dailyPlanId=} {--json}';

    protected $description = 'Audit the FMTRX Coach Planner Command Center payload for a team or Daily Plan.';

    public function handle(CoachPlannerCommandCenterService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $dailyPlanId = trim((string) ($this->option('dailyPlanId') ?? ''));

        $payload = $dailyPlanId !== ''
            ? $service->buildForDailyPlan($dailyPlanId)
            : $service->buildForTeam($teamId);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX COACH PLANNER COMMAND CENTER');
        $this->line('Team ID: '.$teamId);
        $this->line('Daily Plan ID: '.($payload['daily_plan_id'] ?? 'none'));
        $this->line('');

        $header = $payload['operating_header'] ?? [];
        $primary = $payload['primary_next_action'] ?? [];
        $this->line('OPERATING HEADER');
        $this->line('----------------');
        $this->line('Today\'s plan: '.($header['display_title'] ?? 'No active Daily Plan'));
        $this->line('Status: '.($header['status_label'] ?? 'Unknown'));
        $this->line('Scheduled: '.($header['scheduled_for'] ?? 'none'));
        $this->line('Published state: '.($header['published_state'] ?? 'none'));
        $this->line('Assigned: '.((int) ($header['assigned_count'] ?? 0)));
        $this->line('Acknowledged: '.((int) ($header['acknowledged_count'] ?? 0)).' / '.((int) ($header['assigned_count'] ?? 0)));
        $this->line('Completed: '.((int) ($header['completed_count'] ?? 0)).' / '.((int) ($header['assigned_count'] ?? 0)));
        $this->line('Pending review: '.((int) ($header['pending_review_count'] ?? 0)));
        $this->line('Revision: '.($header['latest_revision_number'] ?? 'none'));
        if (! empty($header['revision_note'])) {
            $this->line('Revision note: '.$header['revision_note']);
        }
        if (! empty($header['empty_state'])) {
            $this->line('Empty state: '.$header['empty_state']);
        }
        $this->line('');

        $this->line('PRIMARY NEXT ACTION');
        $this->line('-------------------');
        $this->line('Title: '.($primary['title'] ?? 'none'));
        $this->line('Why: '.($primary['why'] ?? 'none'));
        $this->line('Button: '.($primary['button_label'] ?? 'guidance only'));
        $this->line('Type: '.($primary['action_type'] ?? 'none'));
        $this->line('Enabled: '.((bool) ($primary['enabled'] ?? false) ? 'YES' : 'NO'));
        $this->line('');

        $plan = $payload['plan_status'] ?? [];
        $this->line('PLAN STATUS');
        $this->line('-----------');
        $this->line('Title: '.($plan['title'] ?? 'none'));
        $this->line('Status: '.($plan['status'] ?? 'unknown'));
        $this->line('Scheduled: '.($plan['scheduled_for'] ?? 'none'));
        $this->line('Published: '.($plan['published_at'] ?? 'none'));
        $this->line('Latest revision: '.($plan['latest_revision_number'] ?? 'none'));
        $this->line('Unpublished suggestions: '.((bool) ($plan['has_unpublished_suggestions'] ?? false) ? 'YES' : 'NO'));
        $this->line('Blocks: '.((int) ($plan['block_count'] ?? 0)));
        $this->line('Benchmark generated: '.((bool) ($plan['benchmark_generated'] ?? false) ? 'YES' : 'NO'));
        $this->line('');

        $summary = $payload['player_status_summary'] ?? [];
        $this->line('PLAYER SUMMARY');
        $this->line('--------------');
        $this->line('Assigned: '.((int) ($summary['assigned_count'] ?? 0)));
        $this->line('Acknowledged: '.((int) ($summary['acknowledged_count'] ?? 0)).' / '.((int) ($summary['assigned_count'] ?? 0)).' ('.($summary['acknowledgement_percentage'] ?? 0).'%)');
        $this->line('Started: '.((int) ($summary['started_count'] ?? 0)));
        $this->line('Completed: '.((int) ($summary['completed_count'] ?? 0)).' / '.((int) ($summary['assigned_count'] ?? 0)).' ('.($summary['completion_percentage'] ?? 0).'%)');
        $this->line('Pending review: '.((int) ($summary['pending_review_count'] ?? 0)));
        $this->line('Corrections requested: '.((int) ($summary['correction_requested_count'] ?? 0)));
        $this->line('');

        $benchmark = $payload['benchmark_workflow_summary'] ?? [];
        $this->line('BENCHMARK WORKFLOW');
        $this->line('------------------');
        $this->line('Benchmark items: '.((int) ($benchmark['benchmark_items_completed'] ?? 0)).' / '.((int) ($benchmark['benchmark_items_total'] ?? 0)));
        $this->line('Submitted metrics: '.((int) ($benchmark['submitted_metric_count'] ?? 0)));
        $this->line('Pending review: '.((int) ($benchmark['pending_review_count'] ?? 0)));
        $this->line('Approved: '.((int) ($benchmark['approved_count'] ?? 0)));
        $this->line('Promoted: '.((int) ($benchmark['promoted_count'] ?? 0)));
        $this->line('Refresh status: '.($benchmark['refresh_status'] ?? 'none'));
        $this->line('Rescore status: '.($benchmark['rescore_status'] ?? 'none'));
        $this->line('');

        $review = $payload['review_queue_summary'] ?? [];
        $this->line('REVIEW QUEUE');
        $this->line('------------');
        $this->line('Pending review count: '.((int) ($review['pending_review_count'] ?? 0)));
        $this->line('Oldest pending: '.($review['oldest_pending_at'] ?? 'none'));
        foreach (($review['tasks_pending_review'] ?? []) as $task) {
            $this->line('- '.($task['player_name'] ?? 'Player').' · '.($task['title'] ?? 'Benchmark Task').' · '.($task['submitted_at'] ?? 'no date'));
        }
        if (empty($review['tasks_pending_review'])) {
            $this->line('- none');
        }
        $this->line('');

        $trusted = $payload['trusted_data_summary'] ?? [];
        $this->line('TRUSTED DATA');
        $this->line('------------');
        $this->line('Trusted values added: '.((int) ($trusted['trusted_values_added'] ?? 0)));
        $this->line('Players improved: '.((int) ($trusted['players_improved'] ?? 0)));
        $metricsImproved = implode(', ', $trusted['metrics_improved'] ?? []);
        $this->line('Metrics improved: '.($metricsImproved !== '' ? $metricsImproved : 'none'));
        $this->line('Last promotion: '.($trusted['last_promotion_at'] ?? 'none'));
        $this->line('Last refresh: '.($trusted['last_refresh_at'] ?? 'none'));
        $this->line('');

        $this->line('PLAYER ROWS');
        $this->line('-----------');
        foreach (($payload['player_rows'] ?? []) as $row) {
            $this->line(sprintf(
                '- %s · ack %s · started %s · complete %s%% · review %d · next: %s',
                $row['player_name'] ?? 'Player',
                (bool) ($row['acknowledged'] ?? false) ? 'YES' : 'NO',
                (bool) ($row['started'] ?? false) ? 'YES' : 'NO',
                $row['completion_percentage'] ?? 0,
                (int) ($row['pending_review_count'] ?? 0),
                $row['next_needed_action'] ?? 'none',
            ));
        }
        if (empty($payload['player_rows'])) {
            $this->line('- none');
        }
        $this->line('');

        $this->line('REMAINING BENCHMARK GAPS');
        $this->line('------------------------');
        foreach (($payload['remaining_benchmark_gaps'] ?? []) as $gap) {
            $this->line('- '.($gap['display_name'] ?? 'Gap').' · '.($gap['missing_count'] ?? 0).'/'.($gap['eligible_count'] ?? 0).' · '.($gap['priority'] ?? 'low'));
        }
        if (empty($payload['remaining_benchmark_gaps'])) {
            $this->line('- none');
        }
        $this->line('');

        $this->line('NEXT ACTIONS');
        $this->line('------------');
        foreach (($payload['next_actions'] ?? []) as $action) {
            $this->line('- ['.($action['priority'] ?? 'low').'] '.($action['title'] ?? 'Action').' — '.($action['why'] ?? ''));
            $this->line('  Do: '.($action['action'] ?? ''));
            $this->line('  Type: '.($action['action_type'] ?? 'none'));
        }
        if (empty($payload['next_actions'])) {
            $this->line('- none');
        }

        if (! empty($payload['warnings'])) {
            $this->line('');
            $this->warn('WARNINGS');
            foreach ($payload['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }
}
