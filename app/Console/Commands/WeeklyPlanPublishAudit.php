<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyPlanPublishService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class WeeklyPlanPublishAudit extends Command
{
    protected $signature = 'planner:weekly-plan-publish
        {teamId : Team id}
        {--dailyPlanId= : Weekly Daily Plan draft id to publish}
        {--publish : Publish selected/all eligible weekly drafts}
        {--assign-all : Assign published plans to all team players}
        {--playerIds= : Comma-separated player user ids to assign}
        {--republish : Allow already-published weekly plans to be republished}
        {--json : Output structured JSON}';

    protected $description = 'List, publish, and assign weekly generated Daily Planner drafts.';

    public function handle(WeeklyPlanPublishService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $dailyPlanId = trim((string) ($this->option('dailyPlanId') ?? ''));
        $publish = (bool) $this->option('publish');
        $options = [
            'assign_all' => (bool) $this->option('assign-all'),
            'player_ids' => $this->playerIds((string) ($this->option('playerIds') ?? '')),
            'republish' => (bool) $this->option('republish'),
            'notify_players' => false,
            'published_by_user_id' => null,
        ];
        $list = $service->listWeeklyDrafts($teamId);
        $result = null;

        if ($publish && $dailyPlanId !== '') {
            $result = ($options['assign_all'] || ! empty($options['player_ids']))
                ? $service->publishAndAssign($dailyPlanId, $options['player_ids'], $options)
                : $service->publishDraftDay($dailyPlanId, $options);
        } elseif ($publish) {
            $result = $service->publishWeeklyDrafts($teamId, [], $options);
            $list = $service->listWeeklyDrafts($teamId);
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'weekly_drafts' => $list,
                'publish_result' => $result,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('FMTRX WEEKLY PLAN PUBLISH AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Draft plans found: '.(int) ($list['draft_count'] ?? 0));
        $this->line('Published weekly plans found: '.(int) ($list['published_count'] ?? 0));
        $this->line('Total weekly plans found: '.(int) ($list['plan_count'] ?? 0));
        $this->newLine();

        $this->line('WEEKLY DRAFT PLANS');
        $this->line('------------------');
        foreach (Arr::wrap($list['plans'] ?? []) as $plan) {
            if (! is_array($plan)) {
                continue;
            }
            $this->line('- '.$this->value($plan['daily_plan_id'] ?? null).' · '.$this->value($plan['title'] ?? null));
            $this->line('  Date: '.$this->value($plan['scheduled_for'] ?? null).' · Status: '.$this->value($plan['status'] ?? null).' · Assigned: '.(int) ($plan['assigned_player_count'] ?? 0));
            $this->line('  Focus: '.$this->value($plan['primary_focus'] ?? null).' · Blocks: '.(int) ($plan['block_count'] ?? 0));
            foreach (Arr::wrap($plan['warnings'] ?? []) as $warning) {
                $this->warn('  Warning: '.$warning);
            }
        }
        if (empty($list['plans'])) {
            $this->line('- none');
        }

        if ($result !== null) {
            $this->newLine();
            $this->line('PUBLISH RESULT');
            $this->line('--------------');
            $this->line('Status: '.$this->value($result['status'] ?? null));
            $this->line('Published count: '.(int) ($result['published_count'] ?? 0));
            $this->line('Assigned count: '.(int) ($result['assigned_count'] ?? 0));
            $this->line('Skipped count: '.(int) ($result['skipped_count'] ?? 0));
            $this->line('Player visibility: '.Arr::get($result, 'evidence.player_visibility_contract', 'published daily plan plus assignment'));

            foreach (Arr::wrap($result['published_plans'] ?? []) as $plan) {
                if (! is_array($plan)) {
                    continue;
                }
                $this->info('- '.$this->value($plan['daily_plan_id'] ?? null).' · '.$this->value($plan['status_before'] ?? null).' -> '.$this->value($plan['status_after'] ?? null).' · assigned '.$this->value($plan['assigned_player_count'] ?? 0));
            }
            foreach (Arr::wrap($result['skipped_plans'] ?? []) as $plan) {
                if (! is_array($plan)) {
                    continue;
                }
                $this->warn('- skipped '.$this->value($plan['daily_plan_id'] ?? null).': '.$this->value($plan['reason'] ?? null));
            }
            foreach (Arr::wrap($result['warnings'] ?? []) as $warning) {
                $this->warn('Warning: '.$warning);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function playerIds(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $piece): string => trim($piece))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function value($value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
