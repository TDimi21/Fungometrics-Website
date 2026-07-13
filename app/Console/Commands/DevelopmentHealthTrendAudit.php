<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DevelopmentHealthTrendService;
use Illuminate\Console\Command;

class DevelopmentHealthTrendAudit extends Command
{
    protected $signature = 'planner:development-health-trend
        {teamId : Team id}
        {--weeks=8 : Number of weeks to analyze}
        {--start= : Start date}
        {--end= : End date}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX development health trendline for a team.';

    public function handle(DevelopmentHealthTrendService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $payload = $service->buildTeamTrendline($teamId, [
            'weeks' => (int) $this->option('weeks'),
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
            'include_components' => true,
            'include_recommendations' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $overall = $payload['overall_trend'] ?? [];
        $this->info('FMTRX DEVELOPMENT HEALTH TREND');
        $this->line('Team ID: '.$teamId);
        $this->line('Window: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null));
        $this->line('Current Score: '.$this->value($overall['current_score'] ?? null).' — '.$this->value($overall['trend_label'] ?? null));
        $this->line('Previous Score: '.$this->value($overall['previous_score'] ?? null));
        $this->line('Trend: '.$this->value($overall['trend_label'] ?? null).' '.$this->signed($overall['score_delta_vs_previous'] ?? null));
        $this->line($this->value($overall['summary'] ?? null));

        $this->section('PERIOD SCORES');
        foreach (($payload['period_scores'] ?? []) as $period) {
            $this->line('- '.$this->value($period['period_label'] ?? null).': '.$this->value($period['overall_score_0_100'] ?? null).' · '.$this->human((string) ($period['overall_label'] ?? 'no_data')));
            $this->line('  strength: '.$this->value($period['top_strength'] ?? null).' · risk: '.$this->value($period['top_risk'] ?? null));
            $this->line('  next: '.$this->value($period['next_best_action'] ?? null));
        }

        $this->section('COMPONENT TRENDS');
        foreach (($payload['component_trends'] ?? []) as $component => $trend) {
            $this->line('- '.$this->value($trend['display_name'] ?? $this->human((string) $component)).': '.$this->value($trend['current_score'] ?? null).', '.$this->human((string) ($trend['trend_direction'] ?? 'no_data')).' '.$this->signed($trend['delta'] ?? null));
        }

        $this->section('BIGGEST IMPROVEMENTS');
        $this->listMoves($payload['biggest_improvements'] ?? []);

        $this->section('BIGGEST DECLINES');
        $this->listMoves($payload['biggest_declines'] ?? []);

        $this->section('RECOMMENDATIONS');
        if (empty($payload['trend_recommendations'])) {
            $this->line('- none');
        }
        foreach (($payload['trend_recommendations'] ?? []) as $recommendation) {
            $this->line('- ['.$this->human((string) ($recommendation['priority'] ?? 'medium')).'] '.$this->value($recommendation['title'] ?? null));
            $this->line('  why: '.$this->value($recommendation['why'] ?? null));
            $this->line('  action: '.$this->value($recommendation['action'] ?? null));
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

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function listMoves(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->value($row['message'] ?? null));
        }
    }

    private function signed(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '';
        }

        $number = round((float) $value, 1);

        return ($number > 0 ? '+' : '').(string) $number;
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
