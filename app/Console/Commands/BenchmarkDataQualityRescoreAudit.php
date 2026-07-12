<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BenchmarkCollectionTask;
use App\Services\Intelligence\BenchmarkDataQualityRescoreService;
use Illuminate\Console\Command;

class BenchmarkDataQualityRescoreAudit extends Command
{
    protected $signature = 'intelligence:benchmark-data-quality-rescore
        {teamId}
        {--playerId= : Optional player id to rescore with the team}
        {--taskId= : Optional benchmark collection task id to inspect}
        {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Audit benchmark data quality re-score after trusted benchmark task promotion.';

    public function handle(BenchmarkDataQualityRescoreService $rescoreService): int
    {
        @ini_set('memory_limit', '512M');

        $teamId = (string) $this->argument('teamId');
        $playerId = $this->option('playerId') ? (string) $this->option('playerId') : null;
        $taskId = $this->option('taskId') ? (string) $this->option('taskId') : null;
        $days = max(7, min(365, (int) $this->option('days')));
        $options = ['days' => $days];

        if ($taskId) {
            $task = BenchmarkCollectionTask::query()->find($taskId);
            if (! $task || (string) $task->team_id !== $teamId) {
                $this->error('Benchmark task was not found for this team.');

                return self::FAILURE;
            }

            $playerId = $playerId ?: ((string) $task->assigned_to_player_id ?: null);
            $promotionResult = is_array($task->promotion_result ?? null) ? $task->promotion_result : [];
            $rescoreResult = is_array($promotionResult['rescore'] ?? null) ? $promotionResult['rescore'] : [];

            if (! empty($rescoreResult['before'] ?? null)) {
                $options['before'] = $rescoreResult['before'];
            }
            if (! empty($promotionResult)) {
                $options['promotion'] = $promotionResult;
                $options['trusted_payload'] = $promotionResult['trusted_payload'] ?? [];
            }
        }

        $result = $rescoreService->rescoreAfterPromotion($teamId, $playerId, $options);

        $this->info('FMTRX BENCHMARK DATA QUALITY RESCORE');
        $this->line('Team ID: '.$teamId);
        $this->line('Player ID: '.($playerId ?: '-'));
        $this->line('Task ID: '.($taskId ?: '-'));
        $this->line('Days: '.$days);
        $this->kv('Rescore status', $result['rescore_status'] ?? '-');

        $summary = is_array($result['improvement_summary'] ?? null) ? $result['improvement_summary'] : [];
        $this->section('BEFORE / AFTER SUMMARY');
        $this->kv('Benchmark metric count', $this->beforeAfter($summary, 'benchmark_metric_count_before', 'benchmark_metric_count_after'));
        $this->kv('Players with benchmark data', $this->beforeAfter($summary, 'players_with_benchmark_data_before', 'players_with_benchmark_data_after'));
        $this->kv('Completion %', $this->beforeAfter($summary, 'completion_percentage_before', 'completion_percentage_after'));
        $this->kv('Benchmark confidence', $this->beforeAfter($summary, 'benchmark_confidence_before', 'benchmark_confidence_after'));
        $this->kv('Source mix before', $summary['source_mix_before'] ?? []);
        $this->kv('Source mix after', $summary['source_mix_after'] ?? []);
        $this->kv('Collection priority', $this->beforeAfter($summary, 'collection_priority_before', 'collection_priority_after'));
        $this->kv('Decision focus', $this->beforeAfter($summary, 'decision_focus_before', 'decision_focus_after'));

        $this->section('CHANGES');
        $this->printRows($result['changes'] ?? [], fn (array $row): string => $row['message'] ?? json_encode($row, JSON_UNESCAPED_SLASHES) ?: '-');

        $this->section('REMAINING GAPS');
        $this->printRows(array_slice($result['remaining_gaps'] ?? [], 0, 10), fn (array $row): string => sprintf(
            '%s | %s | missing %s | players: %s',
            $row['display_name'] ?? $row['metric_key'] ?? 'Benchmark Data',
            $row['category'] ?? '-',
            $row['missing_count'] ?? '-',
            $this->playerNames($row['players_missing'] ?? []),
        ));

        $this->section('NEXT ACTIONS');
        $this->printRows($result['next_recommended_actions'] ?? [], fn (array $row): string => sprintf(
            '%s | %s | %s min | %s',
            $row['title'] ?? 'Benchmark Collection',
            $row['priority'] ?? '-',
            $row['duration_minutes'] ?? '-',
            $row['why'] ?? '-',
        ));

        $rerank = is_array($result['action_rerank'] ?? null) ? $result['action_rerank'] : [];
        $this->section('COACH ACTION RERANK');
        $this->kv('Rerank status', $rerank['rerank_status'] ?? '-');
        $this->kv('Primary focus', $this->beforeAfter($rerank, 'primary_focus_before', 'primary_focus_after'));
        $this->kv('Data collection priority', $this->beforeAfter($rerank, 'data_collection_priority_before', 'data_collection_priority_after'));
        $this->kv('Practice plan title', $rerank['updated_practice_plan']['plan_title'] ?? '-');
        $this->kv('Coach summary', $rerank['coach_summary'] ?? '-');

        $this->printRows(array_slice($rerank['top_actions_after'] ?? [], 0, 5), fn (array $row): string => sprintf(
            '#%s %s | %s | %s',
            $row['rank'] ?? '-',
            $row['title'] ?? 'Coach Action',
            $row['priority'] ?? '-',
            $row['reason_for_rank'] ?? '-',
        ));

        $this->section('WARNINGS');
        $this->printList($result['warnings'] ?? []);

        $this->section('EVIDENCE');
        $this->kv('Evidence', $result['evidence'] ?? []);

        $exitCode = ($result['rescore_status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
        unset($result);
        gc_collect_cycles();

        return $exitCode;
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

    private function printList(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
    }

    private function beforeAfter(array $summary, string $beforeKey, string $afterKey): string
    {
        return $this->wrap($summary[$beforeKey] ?? null).' -> '.$this->wrap($summary[$afterKey] ?? null);
    }

    private function playerNames(array $players): string
    {
        $names = collect($players)
            ->map(fn ($player): ?string => is_array($player) ? ($player['player_name'] ?? $player['name'] ?? $player['player_id'] ?? null) : null)
            ->filter()
            ->take(6)
            ->implode(', ');

        return $names !== '' ? $names : '-';
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        if (is_float($value)) {
            return number_format($value, 1);
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        return (string) $value;
    }
}
