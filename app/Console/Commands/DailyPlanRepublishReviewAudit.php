<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\DailyPlanRepublishReviewService;
use Illuminate\Console\Command;

class DailyPlanRepublishReviewAudit extends Command
{
    protected $signature = 'planner:daily-plan-republish-review
        {dailyPlanId}
        {--preview : Print an edited-plan preview}
        {--apply= : Comma-separated change IDs to apply}
        {--republish : Republish the plan after apply or republish without edits}
        {--json : Print raw JSON output}';

    protected $description = 'Build, preview, apply, or republish a coach-reviewed Daily Plan update package.';

    public function handle(DailyPlanRepublishReviewService $service): int
    {
        $dailyPlanId = (string) $this->argument('dailyPlanId');
        $apply = trim((string) ($this->option('apply') ?? ''));
        $republish = (bool) $this->option('republish');
        $preview = (bool) $this->option('preview');

        $package = $service->buildReviewPackage($dailyPlanId);
        $changes = $package['editable_changes'] ?? [];
        $selectedIds = $apply !== '' ? array_values(array_filter(array_map('trim', explode(',', $apply)))) : [];
        $selectedEdits = empty($selectedIds)
            ? $changes
            : array_values(array_filter($changes, fn (array $change): bool => in_array((string) ($change['change_id'] ?? ''), $selectedIds, true)));

        $result = $package;
        if ($preview) {
            $result = $service->previewEditedPlan($dailyPlanId, $selectedEdits);
        }

        if ($apply !== '') {
            $result = $service->applyCoachApprovedEdits($dailyPlanId, $selectedEdits, null, [
                'republish' => $republish,
                'coach_note' => 'Applied from planner:daily-plan-republish-review audit command.',
            ]);
        } elseif ($republish) {
            $result = $service->republishPlan($dailyPlanId, null, [
                'approved_edits' => $selectedEdits,
                'coach_note' => 'Republished from planner:daily-plan-republish-review audit command.',
            ]);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX DAILY PLAN REPUBLISH REVIEW');
        $this->line('Daily Plan ID: '.$dailyPlanId);
        $this->line('Review status: '.($result['review_status'] ?? $result['republish_status'] ?? '-'));
        $this->line('Current focus: '.($result['current_plan']['primary_goal'] ?? '-'));
        $this->line('Suggested focus: '.($result['suggested_plan']['priority_focus'] ?? '-'));
        $this->line('Minutes: '.($result['estimated_minutes_before'] ?? '-').' -> '.($result['estimated_minutes_after'] ?? '-'));
        $this->line('Requires republish: '.(($result['requires_republish'] ?? false) ? 'YES' : 'NO'));
        $this->line('Can apply: '.(($result['can_apply'] ?? false) ? 'YES' : 'NO'));
        $this->line('Can republish: '.(($result['can_republish'] ?? false) ? 'YES' : 'NO'));

        $this->newLine();
        $this->line('EDITABLE CHANGES');
        $this->line('----------------');
        $this->printChanges($result['editable_changes'] ?? $package['editable_changes'] ?? []);

        $this->newLine();
        $this->line('LOCKED BLOCKS');
        $this->line('-------------');
        $locked = $result['locked_blocks'] ?? $package['locked_blocks'] ?? [];
        if (empty($locked)) {
            $this->line('- none');
        }
        foreach ($locked as $block) {
            $this->line('- '.($block['title'] ?? '-').' | '.($block['reason'] ?? '-'));
        }

        $this->newLine();
        $this->line('WARNINGS');
        $this->line('--------');
        $warnings = $result['warnings'] ?? [];
        if (empty($warnings)) {
            $this->line('- none');
        }
        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        if (! empty($result['revision'] ?? [])) {
            $this->newLine();
            $this->line('REVISION RESULT');
            $this->line('---------------');
            $this->line('Status: '.($result['revision']['revision_status'] ?? '-'));
            $this->line('Revision #: '.($result['revision']['revision_number'] ?? '-'));
        }

        return ($result['review_status'] ?? null) === 'failed' || ($result['ok'] ?? true) === false
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function printChanges(array $changes): void
    {
        if (empty($changes)) {
            $this->line('- none');

            return;
        }

        foreach ($changes as $change) {
            $this->line(sprintf(
                '- [%s] %s | %s | %s',
                $change['priority'] ?? 'low',
                $change['change_id'] ?? '-',
                $change['type'] ?? '-',
                $change['title'] ?? '-',
            ));
            $this->line('  Blocked: '.(! empty($change['blocked_reason']) ? $change['blocked_reason'] : 'NO'));
        }
    }
}
