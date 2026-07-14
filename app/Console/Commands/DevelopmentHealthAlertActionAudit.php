<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DevelopmentHealthAlertActionService;
use Illuminate\Console\Command;

class DevelopmentHealthAlertActionAudit extends Command
{
    protected $signature = 'planner:development-health-alert-actions
        {teamId : Team id}
        {--action= : Optional action type to dry-run or execute}
        {--alertId= : Optional alert id}
        {--confirm : Confirm a supported dangerous action}
        {--dry-run : Show what would happen without executing}
        {--json : Output structured JSON}';

    protected $description = 'List or safely execute Development Health Alert coach actions.';

    public function handle(DevelopmentHealthAlertActionService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $action = $this->nullableOption('action');

        if (! $action) {
            $payload = $service->buildActionsForTeam($teamId, [
                'days' => 30,
                'weeks' => 8,
                'severity_threshold' => 'medium',
            ]);

            if ($this->option('json')) {
                $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $this->info('FMTRX DEVELOPMENT HEALTH ALERT ACTIONS');
            $this->line('Team ID: '.$teamId);
            $this->line('Alert status: '.$this->value($payload['alert_status'] ?? null));
            $this->line('Action count: '.$this->value($payload['action_count'] ?? 0));
            $this->section('ALERT ACTIONS');
            foreach (($payload['alerts'] ?? []) as $alert) {
                $this->line('- '.$this->value($alert['alert_id'] ?? null).' · '.$this->value($alert['alert_type'] ?? null));
                foreach (($alert['actions'] ?? []) as $row) {
                    $enabled = ($row['enabled'] ?? false) ? 'enabled' : 'disabled';
                    $confirm = ($row['requires_confirmation'] ?? false) ? ' · confirm required' : '';
                    $this->line('  - ['.$this->value($row['priority'] ?? null).'] '.$this->value($row['button_label'] ?? $row['title'] ?? null).' · '.$this->value($row['action_type'] ?? null).' · '.$enabled.$confirm);
                    if (! ($row['enabled'] ?? false) && ! empty($row['disabled_reason'])) {
                        $this->line('    disabled: '.$row['disabled_reason']);
                    }
                }
            }
            if (empty($payload['alerts'] ?? [])) {
                $this->line('- none');
            }

            return self::SUCCESS;
        }

        $result = $service->executeAlertAction($teamId, $action, [
            'alert_id' => $this->nullableOption('alertId'),
            'confirm' => (bool) $this->option('confirm'),
            'dry_run' => (bool) $this->option('dry-run'),
            'days' => 30,
            'weeks' => 8,
            'severity_threshold' => 'medium',
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        $this->info('FMTRX DEVELOPMENT HEALTH ALERT ACTION');
        $this->line('Team ID: '.$teamId);
        $this->line('Action: '.$action);
        $this->line('Dry run: '.($this->option('dry-run') ? 'YES' : 'NO'));
        $this->line('Confirm: '.($this->option('confirm') ? 'YES' : 'NO'));
        $this->line('Status: '.$this->value($result['status'] ?? null));
        $this->line('Message: '.$this->value($result['message'] ?? null));

        $this->section('RESULT');
        foreach ($this->summary($result['result'] ?? []) as $line) {
            $this->line('- '.$line);
        }
        if (empty($this->summary($result['result'] ?? []))) {
            $this->line('- none');
        }

        $this->section('WARNINGS');
        foreach (($result['warnings'] ?? []) as $warning) {
            $this->line('- '.$warning);
        }
        if (empty($result['warnings'] ?? [])) {
            $this->line('- none');
        }

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
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

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }

    /**
     * @return array<int, string>
     */
    private function summary(array $result): array
    {
        $keys = [
            'would_execute',
            'target_section',
            'target_route',
            'pending_count',
            'promoted_count',
            'daily_plan_preview.name',
            'availability.disabled_reason',
        ];

        $lines = [];
        foreach ($keys as $key) {
            $value = data_get($result, $key);
            if ($value !== null && $value !== '') {
                $lines[] = str_replace('.', ' ', $key).': '.$this->value($value);
            }
        }

        return $lines;
    }
}
