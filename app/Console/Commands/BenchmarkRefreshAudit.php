<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkRefreshService;
use Illuminate\Console\Command;

class BenchmarkRefreshAudit extends Command
{
    protected $signature = 'intelligence:benchmark-refresh
        {teamId}
        {--playerId= : Refresh a single player plus team context}
        {--taskId= : Refresh after a completed benchmark task}
        {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Refresh or audit benchmark intelligence after collection task completion.';

    public function handle(BenchmarkRefreshService $refreshService): int
    {
        $teamId = (string) $this->argument('teamId');
        $playerId = $this->option('playerId') ? (string) $this->option('playerId') : null;
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;
        $days = max(7, min(365, (int) $this->option('days')));

        $this->info('FMTRX BENCHMARK REFRESH AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        if ($playerId) {
            $this->line('Player ID: '.$playerId);
        }
        if ($taskId) {
            $this->line('Task ID: '.$taskId);
        }

        if ($taskId) {
            $result = $refreshService->refreshAfterTaskCompletion($taskId, ['days' => $days]);
        } elseif ($playerId) {
            $playerResult = $refreshService->refreshPlayerBenchmarks($teamId, $playerId, $days);
            $teamResult = $refreshService->refreshTeamBenchmarks($teamId, $days);
            $result = [
                ...$teamResult,
                'player_id' => $playerId,
                'player_benchmark_profile' => $playerResult['player_benchmark_profile'] ?? [],
                'player_refresh_status' => $playerResult['refresh_status'] ?? null,
                'warnings' => [
                    ...($playerResult['warnings'] ?? []),
                    ...($teamResult['warnings'] ?? []),
                ],
            ];
        } else {
            $result = $refreshService->refreshTeamBenchmarks($teamId, $days);
        }

        $this->newLine();
        $this->kv('Refresh status', $result['refresh_status'] ?? '-');
        $this->kv('Refreshed at', $result['refreshed_at'] ?? '-');
        $this->kv('Benchmark confidence', $result['team_benchmark_profile']['benchmark_confidence'] ?? $result['data_quality_report']['benchmark_confidence'] ?? '-');
        $this->kv('Metric count', $result['team_benchmark_profile']['metric_count'] ?? $result['data_quality_report']['metric_count'] ?? '-');
        $this->kv('Decision focus', $result['decision_brief']['primary_focus']['title'] ?? '-');
        $this->kv('Collection priority', $result['collection_plan']['priority_level'] ?? '-');

        $this->section('DATA QUALITY SUMMARY');
        foreach (($result['data_quality_report'] ?? []) as $key => $value) {
            $this->kv($key, $value);
        }

        $this->section('CHANGED SIGNALS');
        $this->printRows($result['changed_signals'] ?? [], fn (array $row): string => sprintf(
            '%s | %s | %s -> %s',
            $row['type'] ?? 'signal',
            $row['message'] ?? '-',
            $this->wrap($row['before'] ?? null),
            $this->wrap($row['after'] ?? null),
        ));

        $this->section('WARNINGS');
        $this->printRows($result['warnings'] ?? [], fn ($warning): string => (string) $warning);

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
            $this->line('- '.$formatter($row));
        }
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        return (string) $value;
    }
}
