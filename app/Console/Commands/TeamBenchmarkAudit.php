<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\TeamBenchmarkProfileService;
use Illuminate\Console\Command;

class TeamBenchmarkAudit extends Command
{
    protected $signature = 'intelligence:team-benchmarks {teamId} {--days=365 : Benchmark lookback window in days}';

    protected $description = 'Print team-level benchmark intelligence for FMTRX.';

    public function handle(TeamBenchmarkProfileService $teamBenchmarkProfileService): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $profile = $teamBenchmarkProfileService->build($teamId, $days);

        $this->info('FMTRX TEAM BENCHMARK AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->kv('Players', $profile['player_count'] ?? 0);
        $this->kv('Benchmark metrics', $profile['metric_count'] ?? 0);
        $this->kv('Benchmark confidence', $profile['benchmark_confidence'] ?? 'low');
        $this->kv('Source mix', $profile['source_mix'] ?? []);

        $this->section('TEAM CATEGORY SCORES');
        $this->printRows($profile['category_scores'] ?? [], fn (array $row) => sprintf(
            '%s | score %s | players %s | metrics %s | %s | confidence %s',
            $row['category'] ?? 'unknown',
            $this->fmt($row['score_0_100'] ?? null),
            $row['player_count'] ?? 0,
            $row['metric_count'] ?? 0,
            $row['label'] ?? 'unknown',
            $row['confidence'] ?? 'low',
        ));

        $this->section('WEAKEST CATEGORIES');
        $this->printRows($profile['weakest_categories'] ?? [], fn (array $row) => sprintf(
            '%s | score %s | %s',
            $row['category'] ?? 'unknown',
            $this->fmt($row['score_0_100'] ?? null),
            $row['label'] ?? 'unknown',
        ));

        $this->section('WEAKEST METRICS');
        $this->printRows($profile['weakest_metrics'] ?? [], fn (array $row) => sprintf(
            '%s | %s | score %s | players %s | %s',
            $row['display_name'] ?? $row['metric_key'] ?? 'unknown',
            $row['category'] ?? 'unknown',
            $this->fmt($row['score_0_100'] ?? null),
            $row['player_count'] ?? 0,
            $row['label'] ?? 'unknown',
        ));

        $this->section('PLAYERS NEEDING ATTENTION');
        $this->printRows($profile['players_needing_attention'] ?? [], fn (array $row) => sprintf(
            '%s | score %s | metrics %s | %s',
            $row['name'] ?? $row['player_id'] ?? 'Unknown Player',
            $this->fmt($row['average_score'] ?? null),
            $row['metric_count'] ?? 0,
            $row['reason'] ?? 'Needs review',
        ));

        $this->section('MISSING METRICS');
        $this->printRows(array_slice($profile['missing_metrics'] ?? [], 0, 12), fn (array $row) => sprintf(
            '%s | %s | missing %s of %s',
            $row['display_name'] ?? $row['metric_key'] ?? 'unknown',
            $row['category'] ?? 'unknown',
            $row['missing_count'] ?? 0,
            $row['player_count'] ?? 0,
        ));

        $this->section('TEAM GAPS');
        $this->printRows($profile['team_gaps'] ?? [], fn (array $row) => sprintf(
            '%s | affected %s | %s',
            $row['title'] ?? $row['id'] ?? 'Benchmark gap',
            $row['affected_count'] ?? 0,
            $row['why'] ?? 'No explanation available.',
        ));

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function printRows(array $rows, callable $formatter): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$formatter((array) $row));
        }
    }

    private function fmt(mixed $value): string
    {
        return is_numeric($value) ? (string) round((float) $value, 1) : '-';
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
