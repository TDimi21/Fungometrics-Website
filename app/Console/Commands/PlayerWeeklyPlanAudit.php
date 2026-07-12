<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\PlayerWeeklyPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class PlayerWeeklyPlanAudit extends Command
{
    protected $signature = 'planner:player-weekly-plans
        {playerId : Player user id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Number of days when end date is not supplied}
        {--json : Print raw JSON output}';

    protected $description = 'Audit the player-facing weekly assigned Daily Plan payload.';

    public function handle(PlayerWeeklyPlanService $service): int
    {
        $playerId = (string) $this->argument('playerId');
        $payload = $service->buildForPlayer($playerId, [
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
            'days' => (int) ($this->option('days') ?: 7),
            'include_completed' => true,
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $summary = Arr::get($payload, 'weekly_summary', []);
        $nextAction = Arr::get($payload, 'next_action', []);

        $this->line('FMTRX PLAYER WEEKLY PLANS');
        $this->line('Player ID: '.$playerId);
        $this->line('Week: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null).' · '.$this->value($payload['week_label'] ?? null));
        $this->line('Assigned plans: '.(int) ($summary['assigned_plan_count'] ?? 0));
        $this->line('Completed: '.(int) ($summary['completed_plan_count'] ?? 0));
        $this->line('In progress: '.(int) ($summary['in_progress_plan_count'] ?? 0));
        $this->line('Not started: '.(int) ($summary['not_started_plan_count'] ?? 0));
        $this->line('Benchmark plans: '.(int) ($summary['benchmark_plan_count'] ?? 0));
        $this->line('Pending review: '.(int) ($summary['pending_review_count'] ?? 0));
        $this->line('Updates to acknowledge: '.(int) ($summary['updates_to_acknowledge'] ?? 0));
        $this->line('Weekly completion: '.$this->number($summary['weekly_completion_percentage'] ?? 0).'%');
        $this->newLine();

        $this->line('NEXT ACTION');
        $this->line('-----------');
        $this->line('Title: '.$this->value($nextAction['title'] ?? null));
        $this->line('Message: '.$this->value($nextAction['message'] ?? null));
        $this->line('Action: '.$this->value($nextAction['action_type'] ?? null));
        $this->line('Daily Plan: '.$this->value($nextAction['daily_plan_id'] ?? null));
        $this->newLine();

        $this->line('TODAY PLAN');
        $this->line('----------');
        $today = $payload['today_plan'] ?? null;
        if (is_array($today)) {
            $this->printDay($today);
        } else {
            $this->line('- none');
        }
        $this->newLine();

        $this->line('DAY CARDS');
        $this->line('---------');
        $days = Arr::wrap($payload['days'] ?? []);
        if (empty($days)) {
            $this->line('- none');
        }
        foreach ($days as $day) {
            if (is_array($day)) {
                $this->printDay($day);
            }
        }

        $warnings = Arr::wrap($payload['warnings'] ?? []);
        if (! empty($warnings)) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($warnings as $warning) {
                $this->warn('- '.$warning);
            }
        }

        return self::SUCCESS;
    }

    private function printDay(array $day): void
    {
        $this->line('- '.$this->value($day['day_label'] ?? null).' '.$this->value($day['scheduled_for'] ?? null).' · '.$this->value($day['title'] ?? null));
        $this->line('  Plan: '.$this->value($day['daily_plan_id'] ?? null).' · Status: '.$this->value($day['status'] ?? null).' · Plan status: '.$this->value($day['plan_status'] ?? null));
        $this->line('  Progress: '.(int) ($day['completed_items'] ?? 0).'/'.(int) ($day['total_items'] ?? 0).' · '.$this->number($day['completion_percentage'] ?? 0).'% · Minutes: '.$this->value($day['estimated_total_minutes'] ?? null));
        $this->line('  Benchmark: '.(($day['benchmark_generated'] ?? false) ? 'YES' : 'NO').' · Blocks: '.(int) ($day['benchmark_block_count'] ?? 0).' · Pending review: '.(int) ($day['pending_review_count'] ?? 0));
        if ((bool) Arr::get($day, 'update_status.has_update')) {
            $this->warn('  Update: needs acknowledgement');
        }
        if (! empty($day['next_step'])) {
            $this->line('  Next: '.$day['next_step']);
        }
    }

    private function value($value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }

    private function number($value): string
    {
        return number_format((float) $value, 1);
    }
}
