<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\SeasonDevelopmentArchiveService;
use Illuminate\Console\Command;

class SeasonDevelopmentArchiveAudit extends Command
{
    protected $signature = 'planner:season-archive
        {teamId : Team id}
        {--start= : Season start date}
        {--end= : Season end date}
        {--weeks=12 : Number of weeks to analyze}
        {--json : Output structured JSON}';

    protected $description = 'Build a read-only FMTRX season development archive for a team.';

    public function handle(SeasonDevelopmentArchiveService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamSeasonArchive($teamId, [
            'season_start_date' => $this->option('start') ?: null,
            'season_end_date' => $this->option('end') ?: null,
            'weeks' => (int) $this->option('weeks'),
            'include_player_rows' => true,
            'include_benchmark_progress' => true,
            'include_report_delivery' => true,
            'include_communication_rhythm' => true,
            'include_weekly_reports' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX SEASON DEVELOPMENT ARCHIVE');
        $this->line('Team ID: '.$teamId);
        $this->line('Season: '.$this->value($payload['season_label'] ?? null));
        $this->line('Status: '.$this->human((string) ($payload['archive_status'] ?? 'unknown')));

        $summary = $payload['executive_summary'] ?? [];
        $this->section('EXECUTIVE SUMMARY');
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));
        $this->line('Summary: '.$this->value($summary['summary_text'] ?? null));
        $this->line('Next best action: '.$this->value($summary['next_best_action'] ?? null));

        $totals = $payload['season_totals'] ?? [];
        $this->section('SEASON TOTALS');
        $this->line('Weeks analyzed: '.$this->value($totals['weeks_analyzed'] ?? 0));
        $this->line('Plans created / published: '.$this->value($totals['daily_plans_created'] ?? 0).' / '.$this->value($totals['daily_plans_published'] ?? 0));
        $this->line('Assigned / completed workouts: '.$this->value($totals['assigned_workouts'] ?? 0).' / '.$this->value($totals['completed_workouts'] ?? 0));
        $this->line('Average completion: '.$this->value($totals['average_completion_percentage'] ?? 0).'%');
        $this->line('Benchmark submitted / approved / trusted: '.$this->value($totals['benchmark_values_submitted'] ?? 0).' / '.$this->value($totals['benchmark_values_approved'] ?? 0).' / '.$this->value($totals['trusted_values_promoted'] ?? 0));
        $this->line('Pending reviews: '.$this->value($totals['pending_reviews_remaining'] ?? 0));
        $this->line('Reports created / shared: '.$this->value($totals['reports_created'] ?? 0).' / '.$this->value($totals['reports_sent_or_copied'] ?? 0));
        $this->line('Communication rhythm score: '.$this->value($totals['communication_rhythm_score'] ?? null));

        $this->section('WEEKLY TIMELINE');
        if (empty($payload['weekly_timeline'])) {
            $this->line('- none');
        }
        foreach (($payload['weekly_timeline'] ?? []) as $row) {
            $this->line('- '.$this->value($row['week_label'] ?? null).' · '.$this->human((string) ($row['status_label'] ?? 'unknown')));
            $this->line('  '.$this->value($row['headline'] ?? null));
            $this->line('  plans: '.$this->value($row['plans_published'] ?? 0).' · completion: '.$this->value($row['team_completion_percentage'] ?? null).'% · approved: '.$this->value($row['benchmark_values_approved'] ?? 0).' · reports shared: '.$this->value($row['reports_shared'] ?? 0));
            if (! empty($row['primary_focus'])) {
                $this->line('  focus: '.$this->value($row['primary_focus']));
            }
        }

        $benchmark = $payload['benchmark_progress'] ?? [];
        $this->section('BENCHMARK PROGRESS');
        $this->line('Current confidence: '.$this->value($benchmark['current_benchmark_confidence'] ?? null));
        $this->line('Trusted values added: '.$this->value($benchmark['trusted_values_added'] ?? 0));
        $this->line('Metrics improved: '.$this->value($benchmark['metrics_improved'] ?? []));
        $this->line('Remaining gaps: '.$this->value(collect($benchmark['remaining_missing_metrics'] ?? [])->pluck('display_name')->take(5)->values()->all()));

        $planner = $payload['planner_progress'] ?? [];
        $this->section('PLANNER PROGRESS');
        $this->line('Plans created / published: '.$this->value($planner['plans_created'] ?? 0).' / '.$this->value($planner['plans_published'] ?? 0));
        $this->line('Completion: '.$this->value($planner['completion_percentage'] ?? 0).'%');
        $this->line('Players completed all: '.$this->value($planner['players_completed_all_count'] ?? 0));
        $this->line('Players needing follow-up: '.$this->value($planner['players_needing_follow_up_count'] ?? 0));

        $communication = $payload['communication_summary'] ?? [];
        $this->section('COMMUNICATION SUMMARY');
        $this->line('Reports created / shared: '.$this->value($communication['reports_created'] ?? 0).' / '.$this->value($communication['reports_shared'] ?? 0));
        $this->line('Parent updates: '.$this->value($communication['parent_updates'] ?? 0));
        $this->line('Staff reports: '.$this->value($communication['staff_reports'] ?? 0));
        $this->line('Rhythm: '.$this->human((string) ($communication['communication_rhythm_label'] ?? 'unknown')).' · '.$this->value($communication['communication_rhythm_score'] ?? null));

        $this->section('PLAYER DEVELOPMENT SUMMARY');
        if (empty($payload['player_development_summary'])) {
            $this->line('- none');
        }
        foreach (array_slice($payload['player_development_summary'] ?? [], 0, 12) as $row) {
            $this->line('- '.$this->value($row['player_name'] ?? null).': '.$this->value($row['completion_percentage'] ?? 0).'% completion · approved '.$this->value($row['benchmark_values_approved'] ?? 0).' · trusted '.$this->value($row['trusted_metrics_added'] ?? []));
            if (! empty($row['next_recommended_action'])) {
                $this->line('  next: '.$this->value($row['next_recommended_action']));
            }
        }

        $this->section('RECOMMENDED NEXT STEPS');
        foreach (($payload['recommended_next_steps'] ?? []) as $step) {
            $this->line('- '.$this->value($step));
        }

        if (! empty($payload['warnings'])) {
            $this->section('WARNINGS');
            foreach ($payload['warnings'] as $warning) {
                $this->line('- '.$this->value($warning));
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

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }
}
