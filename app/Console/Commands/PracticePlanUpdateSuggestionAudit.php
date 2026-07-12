<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyPlan;
use App\Services\Intelligence\PracticePlanUpdateSuggestionService;
use Illuminate\Console\Command;

class PracticePlanUpdateSuggestionAudit extends Command
{
    protected $signature = 'intelligence:practice-plan-update-suggestions
        {teamId}
        {--dailyPlanId= : Saved Daily Plan ID to compare}
        {--days=365 : Intelligence lookback window}
        {--apply= : Comma-separated suggestion IDs to apply}
        {--republish : Allow applying updates to a published plan}
        {--json : Print raw JSON payload}';

    protected $description = 'Preview or apply coach-reviewed Daily Plan update suggestions from FMTRX intelligence.';

    public function handle(PracticePlanUpdateSuggestionService $service): int
    {
        ini_set('memory_limit', '512M');

        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $dailyPlanId = trim((string) ($this->option('dailyPlanId') ?? ''));
        $apply = trim((string) ($this->option('apply') ?? ''));

        if ($dailyPlanId === '') {
            $dailyPlanId = (string) (DailyPlan::query()
                ->where('team_id', $teamId)
                ->whereIn('status', ['draft', 'published'])
                ->orderByRaw("case when status = 'draft' then 0 when status = 'published' then 1 else 2 end")
                ->orderByDesc('updated_at')
                ->value('id') ?? '');
        }

        $result = $dailyPlanId !== ''
            ? $service->suggestUpdatesForDailyPlan($dailyPlanId, ['days' => $days])
            : $service->suggestUpdatesForTeam($teamId, $days);

        $applyResult = null;
        if ($apply !== '' && $dailyPlanId !== '') {
            $suggestionIds = array_values(array_filter(array_map('trim', explode(',', $apply))));
            $applyResult = $service->applyApprovedSuggestions($dailyPlanId, $suggestionIds, null, [
                'days' => $days,
                'republish' => (bool) $this->option('republish'),
            ]);
            $result = $applyResult['post_apply_preview'] ?? $result;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'preview' => $result,
                'apply_result' => $applyResult,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('FMTRX PRACTICE PLAN UPDATE SUGGESTIONS');
        $this->line('Team ID: '.$teamId);
        $this->line('Daily Plan ID: '.($result['daily_plan_id'] ?? $dailyPlanId ?: '-'));
        $this->line('Days: '.$days);
        $this->line('Suggestion status: '.($result['suggestion_status'] ?? '-'));
        $this->line('Summary: '.($result['summary'] ?? '-'));

        $focus = $result['focus_change'] ?? [];
        $this->newLine();
        $this->line('FOCUS CHANGE');
        $this->line('------------');
        $this->line('Changed: '.(($focus['changed'] ?? false) ? 'YES' : 'NO'));
        $this->line('Current: '.($focus['current_focus'] ?? '-'));
        $this->line('Latest: '.($focus['latest_focus'] ?? '-'));
        $this->line('Reason: '.($focus['reason'] ?? '-'));

        $suggestions = $result['suggestions'] ?? [];
        $this->newLine();
        $this->line('SUGGESTIONS');
        $this->line('-----------');
        if (empty($suggestions)) {
            $this->line('- none');
        }

        foreach ($suggestions as $suggestion) {
            $this->line(sprintf(
                '- [%s] %s | %s | %s min',
                $suggestion['priority'] ?? 'low',
                $suggestion['suggestion_id'] ?? '-',
                $suggestion['title'] ?? '-',
                $suggestion['estimated_minutes_delta'] ?? 0,
            ));
            $this->line('  Type: '.($suggestion['type'] ?? '-'));
            $this->line('  Why: '.($suggestion['why'] ?? '-'));
            $this->line('  Republish: '.(($suggestion['requires_republish'] ?? false) ? 'YES' : 'NO'));
        }

        $warnings = $result['warnings'] ?? [];
        if (! empty($warnings)) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($warnings as $warning) {
                $this->line('- '.$warning);
            }
        }

        if ($applyResult) {
            $this->newLine();
            $this->line('APPLY RESULT');
            $this->line('------------');
            $this->line('Status: '.($applyResult['apply_status'] ?? '-'));
            $this->line('Applied: '.count($applyResult['applied_suggestions'] ?? []));
            $this->line('Skipped: '.count($applyResult['skipped_suggestions'] ?? []));
            foreach (($applyResult['warnings'] ?? []) as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }
}
