<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DevelopmentProgramHealthService;
use Illuminate\Console\Command;

class DevelopmentProgramHealthAudit extends Command
{
    protected $signature = 'planner:development-program-health
        {teamId : Team id}
        {--days=30 : Number of days to analyze}
        {--start= : Start date}
        {--end= : End date}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX development program health for a team.';

    public function handle(DevelopmentProgramHealthService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamHealthScore($teamId, [
            'days' => (int) $this->option('days'),
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX DEVELOPMENT PROGRAM HEALTH');
        $this->line('Team ID: '.$teamId);
        $this->line('Window: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null));
        $this->line('Overall Score: '.$this->value($payload['overall_score_0_100'] ?? null));
        $this->line('Overall Label: '.$this->human((string) ($payload['overall_label'] ?? 'no_data')));

        $summary = $payload['summary'] ?? [];
        $this->section('SUMMARY');
        $this->line('Headline: '.$this->value($summary['headline'] ?? null));
        $this->line('Primary strength: '.$this->value($summary['primary_strength'] ?? null));
        $this->line('Primary risk: '.$this->value($summary['primary_risk'] ?? null));
        $this->line('Next best action: '.$this->value($summary['next_best_action'] ?? null));
        $this->line($this->value($summary['summary_text'] ?? null));

        $this->section('COMPONENTS');
        foreach (($payload['score_components'] ?? []) as $key => $component) {
            $this->line('- '.$this->human((string) $key).': '.$this->value($component['score_0_100'] ?? null).' · '.$this->human((string) ($component['label'] ?? 'no_data')));
            $this->line('  '.$this->value($component['headline'] ?? null));
            $this->line('  why: '.$this->value($component['why_it_matters'] ?? null));
            $this->line('  evidence: '.$this->value($component['evidence'] ?? []));
        }

        $this->section('STRENGTHS');
        $this->listRows($payload['strengths'] ?? [], 'title', 'why');

        $this->section('RISKS');
        $this->listRows($payload['risks'] ?? [], 'title', 'risk');

        $this->section('HIGHEST LEVERAGE ACTIONS');
        $this->listRows($payload['highest_leverage_actions'] ?? [], 'title', 'action');

        $this->section('TREND SIGNALS');
        $this->listRows($payload['trend_signals'] ?? [], 'label', 'message');

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

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function listRows(array $rows, string $titleKey, string $detailKey): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->value($row[$titleKey] ?? null));
            if (! empty($row[$detailKey])) {
                $this->line('  '.$this->value($row[$detailKey]));
            }
        }
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
