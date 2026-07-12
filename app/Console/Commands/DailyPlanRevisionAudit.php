<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanRevisionService;
use Illuminate\Console\Command;

class DailyPlanRevisionAudit extends Command
{
    protected $signature = 'planner:daily-plan-revisions
        {dailyPlanId}
        {--compare= : Compare two revision numbers, e.g. 1,2}
        {--json : Print raw JSON output}';

    protected $description = 'List or compare Daily Plan revision history.';

    public function handle(DailyPlanRevisionService $revisionService): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $compare = trim((string) ($this->option('compare') ?? ''));

        if ($compare !== '') {
            [$from, $to] = array_pad(array_map('intval', explode(',', $compare)), 2, 0);
            $payload = $revisionService->compareRevisions($dailyPlanId, $from, $to);
        } else {
            $payload = $revisionService->listRevisions($dailyPlanId);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX DAILY PLAN REVISION AUDIT');
        $this->line('Daily Plan ID: '.$dailyPlanId);

        if ($compare !== '') {
            $this->line('Compare: '.$compare);
            $this->line('Status: '.($payload['compare_status'] ?? '-'));
            $this->printDiff($payload['diff_summary'] ?? []);
            $this->printWarnings($payload['warnings'] ?? []);

            return ($payload['compare_status'] ?? null) === 'not_found' ? self::FAILURE : self::SUCCESS;
        }

        $this->line('Revision count: '.($payload['revision_count'] ?? 0));
        $this->newLine();
        $this->line('REVISIONS');
        $this->line('---------');

        $revisions = $payload['revisions'] ?? [];
        if (empty($revisions)) {
            $this->line('- none');

            return self::SUCCESS;
        }

        foreach ($revisions as $revision) {
            $this->line(sprintf(
                '#%s %s | %s | %s',
                $revision['revision_number'] ?? '-',
                $revision['source'] ?? '-',
                $revision['change_type'] ?? '-',
                $revision['created_at'] ?? '-',
            ));
            $this->line('  Reason: '.($revision['reason'] ?? '-'));
            $this->printDiff($revision['diff_summary'] ?? [], '  ');
        }

        return self::SUCCESS;
    }

    private function printDiff(array $diff, string $prefix = ''): void
    {
        if (empty($diff)) {
            $this->line($prefix.'Diff: -');

            return;
        }

        $this->line($prefix.'Title changed: '.(($diff['title_changed'] ?? false) ? 'YES' : 'NO'));
        $this->line($prefix.'Status changed: '.(($diff['status_changed'] ?? false) ? 'YES' : 'NO'));
        $this->line($prefix.'Blocks added: '.count($diff['blocks_added'] ?? []));
        $this->line($prefix.'Blocks removed: '.count($diff['blocks_removed'] ?? []));
        $this->line($prefix.'Blocks updated: '.count($diff['blocks_updated'] ?? []));
        $this->line($prefix.'Duration delta: '.($diff['duration_delta'] ?? '-'));
        $this->line($prefix.'Metrics added: '.$this->list($diff['metrics_added'] ?? []));
        $this->line($prefix.'Metrics removed: '.$this->list($diff['metrics_removed'] ?? []));
        $this->printWarnings($diff['warnings'] ?? [], $prefix);
    }

    private function printWarnings(array $warnings, string $prefix = ''): void
    {
        if (empty($warnings)) {
            return;
        }

        $this->line($prefix.'Warnings:');
        foreach ($warnings as $warning) {
            $this->line($prefix.'- '.$warning);
        }
    }

    private function list(array $values): string
    {
        return empty($values) ? '-' : implode(', ', array_map('strval', $values));
    }
}
