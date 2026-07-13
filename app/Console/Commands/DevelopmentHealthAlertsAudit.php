<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DevelopmentHealthAlertService;
use Illuminate\Console\Command;

class DevelopmentHealthAlertsAudit extends Command
{
    protected $signature = 'planner:development-health-alerts
        {teamId : Team id}
        {--days=30 : Number of days to analyze}
        {--weeks=8 : Number of weeks to analyze for trendline context}
        {--severity=medium : Minimum severity to print}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX development health alerts for a team.';

    public function handle(DevelopmentHealthAlertService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamAlerts($teamId, [
            'days' => (int) $this->option('days'),
            'weeks' => (int) $this->option('weeks'),
            'severity_threshold' => (string) $this->option('severity'),
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $summary = $payload['summary'] ?? [];
        $counts = $payload['alert_counts'] ?? [];
        $highest = $payload['highest_priority_alert'] ?? [];

        $this->info('FMTRX DEVELOPMENT HEALTH ALERTS');
        $this->line('Team ID: '.$teamId);
        $this->line('Status: '.$this->value($payload['alert_status'] ?? null));
        $this->line('Active alerts: '.$this->value($summary['active_alert_count'] ?? 0));
        $this->line('Critical: '.$this->value($counts['critical'] ?? 0));
        $this->line('High: '.$this->value($counts['high'] ?? 0));
        $this->line('Medium: '.$this->value($counts['medium'] ?? 0));
        $this->line('Low: '.$this->value($counts['low'] ?? 0));

        $this->section('HIGHEST PRIORITY');
        if (empty($highest)) {
            $this->line('- none');
        } else {
            $this->line($this->value($highest['title'] ?? null));
            $this->line('Severity: '.$this->human((string) ($highest['severity'] ?? 'medium')));
            $this->line('Message: '.$this->value($highest['message'] ?? null));
            $this->line('Action: '.$this->value($highest['recommended_action'] ?? null));
        }

        $this->section('ALERTS');
        if (empty($payload['alerts'])) {
            $this->line('- none');
        }
        foreach (($payload['alerts'] ?? []) as $alert) {
            $this->line('- ['.$this->human((string) ($alert['severity'] ?? 'medium')).'] '.$this->value($alert['title'] ?? null));
            $this->line('  type: '.$this->value($alert['type'] ?? null).' · component: '.$this->value($alert['component'] ?? null));
            $this->line('  message: '.$this->value($alert['message'] ?? null));
            $this->line('  action: '.$this->value($alert['recommended_action'] ?? null));
        }

        $this->section('RECOMMENDED ACTIONS');
        if (empty($payload['recommended_actions'])) {
            $this->line('- none');
        }
        foreach (($payload['recommended_actions'] ?? []) as $action) {
            $this->line('- ['.$this->human((string) ($action['priority'] ?? 'medium')).'] '.$this->value($action['title'] ?? null));
            $this->line('  action: '.$this->value($action['action'] ?? null));
            $this->line('  type: '.$this->value($action['action_type'] ?? null));
        }

        $this->section('WARNINGS');
        if (empty($payload['warnings'])) {
            $this->line('- none');
        }
        foreach (($payload['warnings'] ?? []) as $warning) {
            $this->line('- '.$this->value($warning));
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
