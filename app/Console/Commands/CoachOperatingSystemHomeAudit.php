<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachOperatingSystemHomeService;
use Illuminate\Console\Command;

class CoachOperatingSystemHomeAudit extends Command
{
    protected $signature = 'planner:operating-home
        {teamId : Team id}
        {--date= : Optional date in YYYY-MM-DD format}
        {--days=30 : Lookback days}
        {--weeks=8 : Lookback weeks}
        {--json : Output structured JSON}';

    protected $description = 'Build the FMTRX coach operating system home payload.';

    public function handle(CoachOperatingSystemHomeService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildHome($teamId, [
            'date' => $this->nullableOption('date'),
            'days' => (int) $this->option('days'),
            'weeks' => (int) $this->option('weeks'),
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX OPERATING SYSTEM HOME');
        $this->line('Team ID: '.$teamId);
        $this->line('Date: '.$this->value($payload['date'] ?? null));
        $this->line('Home status: '.$this->value($payload['home_status'] ?? null));

        $this->section('OPERATING SUMMARY');
        $summary = $payload['operating_summary'] ?? [];
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));
        $this->line('Status label: '.$this->value($summary['status_label'] ?? null));
        $this->line('Primary focus: '.$this->value($summary['primary_focus'] ?? null));
        $this->line('Next best action: '.$this->value($summary['next_best_action'] ?? null));
        $this->line('Summary: '.$this->value($summary['summary_text'] ?? null));

        $this->section('TODAY PLAN');
        $plan = $payload['today_plan'] ?? [];
        $this->line('Title: '.$this->value($plan['title'] ?? null));
        $this->line('Status: '.$this->value($plan['status'] ?? null));
        $this->line('Scheduled: '.$this->value($plan['scheduled_for'] ?? null));
        $this->line('Assigned: '.$this->value($plan['assigned_count'] ?? 0));
        $this->line('Completed: '.$this->value($plan['completed_count'] ?? 0));
        $this->line('Completion %: '.$this->value($plan['completion_percentage'] ?? null));
        $this->line('Pending review: '.$this->value($plan['pending_review_count'] ?? 0));

        $this->section('HEALTH SNAPSHOT');
        $health = $payload['health_snapshot'] ?? [];
        $this->line('Score: '.$this->value($health['overall_score_0_100'] ?? null));
        $this->line('Label: '.$this->value($health['label'] ?? null));
        $this->line('Trend: '.$this->value($health['trend_direction'] ?? null));
        $this->line('Primary risk: '.$this->value($health['primary_risk'] ?? null));

        $this->section('ALERTS SNAPSHOT');
        $alerts = $payload['alerts_snapshot'] ?? [];
        $this->line('Active: '.$this->value($alerts['active_alert_count'] ?? 0));
        $this->line('Critical: '.$this->value($alerts['critical_count'] ?? 0));
        $this->line('High: '.$this->value($alerts['high_count'] ?? 0));
        $this->line('Highest: '.$this->value($alerts['highest_priority_alert']['title'] ?? null));

        $this->section('BENCHMARK SNAPSHOT');
        $benchmark = $payload['benchmark_snapshot'] ?? [];
        $this->line('Confidence: '.$this->value($benchmark['benchmark_confidence'] ?? null));
        $this->line('Players with data: '.$this->value($benchmark['players_with_benchmark_data'] ?? null));
        $this->line('Players without data: '.$this->value($benchmark['players_without_benchmark_data'] ?? null));
        $this->line('Weakest category: '.$this->value($benchmark['weakest_category'] ?? null));
        $this->line('Weakest metric: '.$this->value($benchmark['weakest_metric'] ?? null));
        $this->line('Data priority: '.$this->value($benchmark['data_collection_priority'] ?? null));

        $this->section('REVIEW SNAPSHOT');
        $review = $payload['review_snapshot'] ?? [];
        $this->line('Pending review: '.$this->value($review['pending_review_count'] ?? 0));
        $this->line('Approved unpromoted: '.$this->value($review['approved_unpromoted_count'] ?? 0));
        $this->line('Corrections: '.$this->value($review['correction_requested_count'] ?? 0));
        $this->line('Message: '.$this->value($review['message'] ?? null));

        $this->section('COMMUNICATION SNAPSHOT');
        $communication = $payload['communication_snapshot'] ?? [];
        $this->line('Weekly report due: '.((bool) ($communication['weekly_report_due'] ?? false) ? 'YES' : 'NO'));
        $this->line('Last report: '.$this->value($communication['last_weekly_report_at'] ?? null));
        $this->line('Rhythm: '.$this->value($communication['communication_rhythm_label'] ?? null));
        $this->line('Message: '.$this->value($communication['message'] ?? null));

        $this->section('PRIMARY NEXT ACTION');
        $primary = $payload['primary_next_action'] ?? [];
        $this->line('Title: '.$this->value($primary['title'] ?? null));
        $this->line('Priority: '.$this->value($primary['priority'] ?? null));
        $this->line('Why: '.$this->value($primary['why'] ?? null));
        $this->line('Action: '.$this->value($primary['action'] ?? null));
        $this->line('Button: '.$this->value($primary['button_label'] ?? null));

        $this->section('PLAYER ATTENTION');
        foreach (array_slice($payload['player_attention'] ?? [], 0, 8) as $row) {
            $this->line('- '.$this->value($row['player_name'] ?? null).' · '.$this->value($row['priority'] ?? null).' · '.$this->value($row['reason'] ?? null));
        }
        if (empty($payload['player_attention'] ?? [])) {
            $this->line('- none');
        }

        if (! empty($payload['warnings'] ?? [])) {
            $this->section('WARNINGS');
            foreach ($payload['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function nullableOption(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
