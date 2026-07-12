<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\CoachActionReRankingService;
use Illuminate\Console\Command;

class CoachActionReRankingAudit extends Command
{
    protected $signature = 'intelligence:coach-action-rerank
        {teamId}
        {--days=365 : Intelligence lookback window in days}
        {--after-refresh : Build rerank response shape instead of current ranking only}
        {--json : Print raw JSON payload}';

    protected $description = 'Audit FMTRX coach action re-ranking after benchmark data quality changes.';

    public function handle(CoachActionReRankingService $reRankingService): int
    {
        @ini_set('memory_limit', '512M');

        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $payload = $this->option('after-refresh')
            ? $reRankingService->rerankAfterBenchmarkRefresh($teamId, [], [], ['days' => $days])
            : $reRankingService->buildCurrentActionRanking($teamId, $days);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX COACH ACTION RERANK AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->kv('Rerank status', $payload['rerank_status'] ?? $payload['status'] ?? 'current');
        $this->kv('Primary focus before', $payload['primary_focus_before'] ?? '-');
        $this->kv('Primary focus after', $payload['primary_focus_after'] ?? $payload['primary_focus'] ?? '-');
        $this->kv('Data collection priority before', $payload['data_collection_priority_before'] ?? '-');
        $this->kv('Data collection priority after', $payload['data_collection_priority_after'] ?? $payload['data_collection_priority'] ?? '-');

        $practicePlan = is_array($payload['updated_practice_plan'] ?? null) ? $payload['updated_practice_plan'] : [];
        $this->kv('Practice plan title', $practicePlan['plan_title'] ?? '-');
        $this->kv('Coach summary', $payload['coach_summary'] ?? '-');

        $this->section('TOP ACTIONS');
        $actions = $payload['top_actions_after'] ?? $payload['top_actions'] ?? [];
        $this->printRows(array_slice($actions, 0, 5), fn (array $action): string => sprintf(
            '#%s %s | %s | %s | %s',
            $action['rank'] ?? '-',
            $action['title'] ?? 'Coach Action',
            $action['priority'] ?? '-',
            $action['category'] ?? '-',
            $action['reason_for_rank'] ?? '-',
        ));

        $this->section('ACTION CHANGES');
        $this->printRows($payload['action_changes'] ?? [], fn (array $change): string => $change['message'] ?? json_encode($change, JSON_UNESCAPED_SLASHES) ?: '-');

        $this->section('WARNINGS');
        $this->printList($payload['warnings'] ?? []);

        return ($payload['rerank_status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
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
