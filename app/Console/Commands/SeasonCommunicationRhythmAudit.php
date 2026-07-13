<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\SeasonCommunicationRhythmService;
use Illuminate\Console\Command;

class SeasonCommunicationRhythmAudit extends Command
{
    protected $signature = 'planner:season-communication-rhythm
        {teamId : Team id}
        {--months=6 : Number of months to analyze}
        {--start= : Start date}
        {--end= : End date}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX season archive communication rhythm for a team.';

    public function handle(SeasonCommunicationRhythmService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamRhythm($teamId, [
            'months' => (int) $this->option('months'),
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX SEASON COMMUNICATION RHYTHM');
        $this->line('Team ID: '.$teamId);
        $this->line('Window: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null));
        $this->line('Months analyzed: '.$this->value($payload['months_analyzed'] ?? 0));

        $score = $payload['rhythm_score'] ?? [];
        $streaks = $payload['streaks'] ?? [];
        $this->section('RHYTHM SCORE');
        $this->line('Score: '.$this->value($score['score_0_100'] ?? null));
        $this->line('Label: '.$this->human((string) ($score['label'] ?? 'unknown')));
        $this->line('Season packet activity: '.$this->value($score['periods_with_any_packet'] ?? 0).' periods ('.$this->value($score['communication_percentage'] ?? 0).'%)');
        $this->line('Staff packets: '.$this->value($score['periods_with_staff_packet'] ?? 0).' periods ('.$this->value($score['staff_packet_percentage'] ?? 0).'%)');
        $this->line('Parent summaries: '.$this->value($score['periods_with_parent_summary'] ?? 0).' periods ('.$this->value($score['parent_summary_percentage'] ?? 0).'%)');
        $this->line('Player summaries: '.$this->value($score['periods_with_player_summary'] ?? 0).' periods ('.$this->value($score['player_summary_percentage'] ?? 0).'%)');
        $this->line('Current packet streak: '.$this->value($streaks['current_any_packet_streak'] ?? 0));
        $this->line('Current parent summary streak: '.$this->value($streaks['current_parent_summary_streak'] ?? 0));

        $this->section('PERIOD ROWS');
        foreach (($payload['season_rows'] ?? []) as $row) {
            $this->line('- '.$this->value($row['period_label'] ?? null).' · '.$this->human((string) ($row['status_label'] ?? 'unknown')));
            $this->line('  staff: '.$this->yesNo($row['has_staff_review_packet'] ?? false).' · parents: '.$this->yesNo($row['has_parent_safe_summary'] ?? false).' · players: '.$this->yesNo($row['has_player_development_summary'] ?? false).' · internal QA: '.$this->yesNo($row['has_internal_qa_packet'] ?? false));
            $this->line('  sent: '.$this->value($row['sent_count'] ?? 0).' · copy-only: '.$this->value($row['copy_only_count'] ?? 0).' · drafts: '.$this->value($row['draft_created_count'] ?? 0).' · blocked: '.$this->value($row['blocked_count'] ?? 0).' · unsupported: '.$this->value($row['unsupported_count'] ?? 0).' · failed: '.$this->value($row['failed_count'] ?? 0));
            if (! empty($row['recommended_action'])) {
                $this->line('  action: '.$this->value($row['recommended_action']));
            }
        }

        $this->section('AUDIENCE SUMMARY');
        foreach (($payload['audience_summary'] ?? []) as $audience => $row) {
            $this->line('- '.$this->human((string) $audience).': '.$this->value($row['periods_reached'] ?? 0).' periods · '.$this->human((string) ($row['status'] ?? 'unknown')).' · last '.$this->value($row['last_reached_at'] ?? null));
        }

        $this->section('TEMPLATE SUMMARY');
        if (empty($payload['template_summary'])) {
            $this->line('- none');
        }
        foreach (($payload['template_summary'] ?? []) as $row) {
            $this->line('- '.$this->value($row['display_name'] ?? $row['template_key'] ?? null).': '.$this->value($row['periods_used'] ?? 0).' periods · '.$this->value($row['total_uses'] ?? 0).' uses · '.$this->value($row['sent_count'] ?? 0).' sent');
        }

        $health = $payload['delivery_health_summary'] ?? [];
        $this->section('DELIVERY HEALTH');
        $this->line('Total records: '.$this->value($health['total_records'] ?? 0));
        $this->line('Sent: '.$this->value($health['sent_count'] ?? 0).' · Drafts: '.$this->value($health['draft_created_count'] ?? 0));
        $this->line('Copy-only: '.$this->value($health['copy_only_count'] ?? 0).' ('.$this->value($health['copy_only_rate'] ?? 0).'%)');
        $this->line('Blocked: '.$this->value($health['blocked_count'] ?? 0).' · Unsupported: '.$this->value($health['unsupported_count'] ?? 0).' · Failed: '.$this->value($health['failed_count'] ?? 0));
        $this->line('Privacy blocks: '.$this->value($health['privacy_block_count'] ?? 0).' · Missing contacts: '.$this->value($health['missing_contact_warning_count'] ?? 0).' · Unsafe recipients: '.$this->value($health['unsafe_recipient_count'] ?? 0));

        $this->section('MISSED PERIODS');
        if (empty($payload['missed_periods'])) {
            $this->line('- none');
        }
        foreach (($payload['missed_periods'] ?? []) as $row) {
            $this->line('- '.$this->value($row['period_label'] ?? null).': '.$this->value($row['missed_audiences'] ?? []));
            $this->line('  action: '.$this->value($row['recommended_action'] ?? null));
        }

        $this->section('RECOMMENDED ACTIONS');
        if (empty($payload['recommended_actions'])) {
            $this->line('- none');
        }
        foreach (($payload['recommended_actions'] ?? []) as $action) {
            $this->line('- ['.$this->human((string) ($action['priority'] ?? 'medium')).'] '.$this->value($action['title'] ?? null));
            $this->line('  why: '.$this->value($action['why'] ?? null));
            $this->line('  action: '.$this->value($action['action'] ?? null));
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function yesNo(mixed $value): string
    {
        return (bool) $value ? 'yes' : 'no';
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
