<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanPlayerUpdateService;
use Illuminate\Console\Command;

class DailyPlanPlayerUpdateAudit extends Command
{
    protected $signature = 'planner:daily-plan-player-update
        {dailyPlanId}
        {playerId}
        {--mark-seen : Mark the latest revision as seen for this player}
        {--revision= : Specific revision id or revision number to mark seen}
        {--json : Print raw JSON output}';

    protected $description = 'Audit the player-facing update banner payload for an assigned Daily Plan.';

    public function handle(DailyPlanPlayerUpdateService $service): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $playerId = (string) $this->argument('playerId');
        $revisionId = $this->option('revision') ? (string) $this->option('revision') : null;

        $status = (bool) $this->option('mark-seen')
            ? $service->markUpdateSeen($dailyPlanId, $playerId, $revisionId)
            : $service->buildPlayerPlanUpdateStatus($dailyPlanId, $playerId);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX DAILY PLAN PLAYER UPDATE');
        $this->line('Daily Plan ID: '.$dailyPlanId);
        $this->line('Player ID: '.$playerId);
        $this->line('Has update: '.(($status['has_update'] ?? false) ? 'YES' : 'NO'));
        $this->line('Seen: '.(($status['seen'] ?? false) ? 'YES' : 'NO'));
        $this->line('Latest revision: '.($status['latest_revision_number'] ?? '-').' | '.($status['latest_revision_id'] ?? '-'));
        $this->line('Updated at: '.($status['updated_at'] ?? '-'));
        $this->line('Title: '.($status['update_title'] ?? '-'));
        $this->line('Message: '.($status['update_message'] ?? '-'));
        $this->line('Progress preserved: '.(($status['progress_preserved'] ?? false) ? 'YES' : 'NO'));
        $this->line('Requires attention: '.(($status['requires_attention'] ?? false) ? 'YES' : 'NO'));

        $this->newLine();
        $this->line('CHANGE SUMMARY');
        $this->line('--------------');
        $summary = $status['change_summary'] ?? [];
        if (empty($summary)) {
            $this->line('- none');
        } else {
            foreach ($summary as $key => $value) {
                $this->line('- '.$key.': '.$this->stringValue($value));
            }
        }

        $this->printBlocks('ADDED BLOCKS', $status['added_blocks'] ?? []);
        $this->printBlocks('UPDATED BLOCKS', $status['updated_blocks'] ?? []);
        $this->printBlocks('REMOVED OR MOVED BLOCKS', $status['removed_or_moved_blocks'] ?? []);

        $this->newLine();
        $this->line('WARNINGS');
        $this->line('--------');
        $warnings = $status['warnings'] ?? [];
        if (empty($warnings)) {
            $this->line('- none');
        }
        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        return self::SUCCESS;
    }

    private function printBlocks(string $title, array $blocks): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
        if (empty($blocks)) {
            $this->line('- none');

            return;
        }

        foreach ($blocks as $block) {
            $parts = array_filter([
                $block['title'] ?? 'Practice Block',
                $block['bucket'] ?? null,
                isset($block['duration_minutes']) && $block['duration_minutes'] !== null ? $block['duration_minutes'].' min' : null,
            ]);
            $this->line('- '.implode(' | ', $parts));
            if (! empty($block['message'])) {
                $this->line('  '.$block['message']);
            }
        }
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '-' : implode(', ', array_map('strval', $value));
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        return $value === null ? '-' : (string) $value;
    }
}
