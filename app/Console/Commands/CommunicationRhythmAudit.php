<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CommunicationRhythmService;
use Illuminate\Console\Command;

class CommunicationRhythmAudit extends Command
{
    protected $signature = 'planner:communication-rhythm
        {teamId : Team id}
        {--weeks=8 : Number of weeks to analyze}
        {--start= : Start date}
        {--end= : End date}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly report communication rhythm for a team.';

    public function handle(CommunicationRhythmService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamRhythm($teamId, [
            'weeks' => (int) $this->option('weeks'),
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX COMMUNICATION RHYTHM');
        $this->line('Team ID: '.$teamId);
        $this->line('Window: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null));
        $this->line('Weeks analyzed: '.$this->value($payload['weeks_analyzed'] ?? 0));

        $score = $payload['rhythm_score'] ?? [];
        $streaks = $payload['streaks'] ?? [];
        $this->section('RHYTHM SCORE');
        $this->line('Score: '.$this->value($score['score_0_100'] ?? null));
        $this->line('Label: '.$this->human((string) ($score['label'] ?? 'unknown')));
        $this->line('Weekly activity: '.$this->value($score['weeks_with_any_report'] ?? 0).' weeks ('.$this->value($score['consistency_percentage'] ?? 0).'%)');
        $this->line('Parent updates: '.$this->value($score['weeks_with_parent_update'] ?? 0).' weeks ('.$this->value($score['parent_update_percentage'] ?? 0).'%)');
        $this->line('Staff reports: '.$this->value($score['weeks_with_staff_report'] ?? 0).' weeks ('.$this->value($score['staff_report_percentage'] ?? 0).'%)');
        $this->line('Player summaries: '.$this->value($score['weeks_with_player_summary'] ?? 0).' weeks ('.$this->value($score['player_summary_percentage'] ?? 0).'%)');
        $this->line('Current report streak: '.$this->value($streaks['current_any_report_streak'] ?? 0));

        $this->section('WEEKLY ROWS');
        foreach (($payload['weekly_rows'] ?? []) as $row) {
            $this->line('- '.$this->value($row['week_label'] ?? null).' · '.$this->human((string) ($row['status_label'] ?? 'unknown')));
            $this->line('  parents: '.$this->yesNo($row['has_parent_update'] ?? false).' · staff: '.$this->yesNo($row['has_staff_report'] ?? false).' · players: '.$this->yesNo($row['has_player_summary'] ?? false));
            $this->line('  sent: '.$this->value($row['sent_count'] ?? 0).' · copy-only: '.$this->value($row['copy_only_count'] ?? 0).' · blocked: '.$this->value($row['blocked_count'] ?? 0).' · failed: '.$this->value($row['failed_count'] ?? 0));
            if (! empty($row['recommended_action'])) {
                $this->line('  action: '.$this->value($row['recommended_action']));
            }
        }

        $this->section('AUDIENCE SUMMARY');
        foreach (($payload['audience_summary'] ?? []) as $audience => $row) {
            $this->line('- '.$this->human((string) $audience).': '.$this->value($row['weeks_reached'] ?? 0).' weeks · '.$this->human((string) ($row['status'] ?? 'unknown')).' · last '.$this->value($row['last_reached_at'] ?? null));
        }

        $health = $payload['delivery_health_summary'] ?? [];
        $this->section('DELIVERY HEALTH');
        $this->line('Total records: '.$this->value($health['total_records'] ?? 0));
        $this->line('Sent: '.$this->value($health['sent_count'] ?? 0));
        $this->line('Copy-only: '.$this->value($health['copy_only_count'] ?? 0).' ('.$this->value($health['copy_only_rate'] ?? 0).'%)');
        $this->line('Blocked: '.$this->value($health['blocked_count'] ?? 0).' · Unsupported: '.$this->value($health['unsupported_count'] ?? 0).' · Failed: '.$this->value($health['failed_count'] ?? 0));

        $this->section('MISSED WEEKS');
        if (empty($payload['missed_weeks'])) {
            $this->line('- none');
        }
        foreach (($payload['missed_weeks'] ?? []) as $row) {
            $this->line('- '.$this->value($row['week_label'] ?? null).': '.$this->value($row['missed_audiences'] ?? []));
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
