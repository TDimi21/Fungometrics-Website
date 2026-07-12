<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachPlannerCommandCenterService;
use Illuminate\Console\Command;

class CoachPlannerCommandCenterActionAudit extends Command
{
    protected $signature = 'planner:coach-command-center-action
        {teamId}
        {actionType}
        {--dailyPlanId=}
        {--taskIds=}
        {--playerIds=}
        {--message=}
        {--dry-run}';

    protected $description = 'Dry-run or execute a Coach Planner Command Center action.';

    public function handle(CoachPlannerCommandCenterService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $actionType = (string) $this->argument('actionType');
        $dailyPlanId = $this->nullableOption('dailyPlanId');
        $taskIds = $this->csvOption('taskIds');
        $playerIds = $this->csvOption('playerIds');
        $message = $this->nullableOption('message');
        $dryRun = (bool) $this->option('dry-run');

        $result = $service->runAction($teamId, $actionType, [
            'daily_plan_id' => $dailyPlanId,
            'task_ids' => $taskIds,
            'player_ids' => $playerIds,
            'message' => $message,
            'dry_run' => $dryRun,
            'days' => 365,
        ], null);

        $this->info('FMTRX COACH COMMAND CENTER ACTION');
        $this->line('Team ID: '.$teamId);
        $this->line('Action: '.$actionType);
        $this->line('Daily Plan ID: '.($dailyPlanId ?: 'none'));
        $this->line('Task IDs: '.(! empty($taskIds) ? implode(', ', $taskIds) : 'none'));
        $this->line('Player IDs: '.(! empty($playerIds) ? implode(', ', $playerIds) : 'none'));
        $this->line('Dry run: '.($dryRun ? 'YES' : 'NO'));
        $this->line('');
        $this->line('Status: '.($result['status'] ?? 'unknown'));
        $this->line('Message: '.($result['message'] ?? 'none'));

        if (! empty($result['warnings'])) {
            $this->line('');
            $this->warn('WARNINGS');
            foreach ($result['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        $this->line('');
        $this->line('RESULT SUMMARY');
        $this->line('--------------');
        $summary = $this->summary($result['result'] ?? []);
        if (empty($summary)) {
            $this->line('- none');
        } else {
            foreach ($summary as $line) {
                $this->line('- '.$line);
            }
        }

        $this->line('');
        $this->line('UPDATED NEXT ACTIONS');
        $this->line('--------------------');
        foreach (($result['updated_command_center']['next_actions'] ?? []) as $action) {
            $this->line('- ['.($action['priority'] ?? 'low').'] '.($action['title'] ?? 'Action').' · '.($action['action_type'] ?? 'none'));
        }
        if (empty($result['updated_command_center']['next_actions'] ?? [])) {
            $this->line('- none');
        }

        return ($result['status'] ?? null) === 'failed' ? self::FAILURE : self::SUCCESS;
    }

    private function nullableOption(string $key): ?string
    {
        $value = trim((string) ($this->option($key) ?? ''));

        return $value !== '' ? $value : null;
    }

    private function csvOption(string $key): array
    {
        $value = $this->nullableOption($key);
        if (! $value) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)))));
    }

    private function summary(array $result): array
    {
        $keys = [
            'pending_count',
            'approved_count',
            'correction_requested_count',
            'promoted_count',
            'failed_count',
            'promotion_count',
            'benchmark_refresh_status',
            'refresh_status',
            'suggested_practice_plan.plan_title',
            'suggested_practice_plan.priority_focus',
            'daily_plan_preview.name',
        ];

        $lines = [];
        foreach ($keys as $key) {
            $value = data_get($result, $key);
            if ($value !== null && $value !== '') {
                $lines[] = str_replace('.', ' ', $key).': '.$value;
            }
        }

        return $lines;
    }
}
