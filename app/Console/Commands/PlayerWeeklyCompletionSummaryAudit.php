<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\PlayerWeeklyCompletionSummaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class PlayerWeeklyCompletionSummaryAudit extends Command
{
    protected $signature = 'planner:player-weekly-summary
        {playerId : Player user id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Number of days when end date is not supplied}
        {--json : Print raw JSON output}';

    protected $description = 'Audit the player-facing weekly completion summary payload.';

    public function handle(PlayerWeeklyCompletionSummaryService $service): int
    {
        $playerId = (string) $this->argument('playerId');
        $payload = $service->buildForPlayer($playerId, [
            'start_date' => $this->option('start') ?: null,
            'end_date' => $this->option('end') ?: null,
            'days' => (int) ($this->option('days') ?: 7),
            'include_completed' => true,
            'include_benchmark_reviews' => true,
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $completion = Arr::get($payload, 'weekly_completion', []);
        $benchmark = Arr::get($payload, 'benchmark_summary', []);
        $next = Arr::get($payload, 'next_step', []);

        $this->line('FMTRX PLAYER WEEKLY COMPLETION SUMMARY');
        $this->line('Player ID: '.$playerId);
        $this->line('Week: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null).' · '.$this->value($payload['week_label'] ?? null));
        $this->line('Status: '.$this->value($payload['summary_status'] ?? null));
        $this->line('Message: '.$this->value($payload['player_message'] ?? null));
        $this->newLine();

        $this->line('WEEKLY COMPLETION');
        $this->line('-----------------');
        $this->line('Assigned plans: '.(int) ($completion['assigned_plan_count'] ?? 0));
        $this->line('Completed plans: '.(int) ($completion['completed_plan_count'] ?? 0));
        $this->line('In progress plans: '.(int) ($completion['in_progress_plan_count'] ?? 0));
        $this->line('Not started plans: '.(int) ($completion['not_started_plan_count'] ?? 0));
        $this->line('Items: '.(int) ($completion['completed_items'] ?? 0).'/'.(int) ($completion['total_items'] ?? 0));
        $this->line('Completion: '.$this->number($completion['completion_percentage'] ?? 0).'%');
        $this->line('Benchmark items completed: '.(int) ($completion['benchmark_items_completed'] ?? 0));
        $this->line('Benchmark values submitted: '.(int) ($completion['benchmark_values_submitted'] ?? 0));
        $this->newLine();

        $this->line('BENCHMARK REVIEW');
        $this->line('----------------');
        $this->line('Submitted values: '.(int) ($benchmark['submitted_metric_count'] ?? 0));
        $this->line('Pending review: '.(int) ($benchmark['pending_review_count'] ?? 0));
        $this->line('Approved: '.(int) ($benchmark['approved_count'] ?? 0));
        $this->line('Rejected: '.(int) ($benchmark['rejected_count'] ?? 0));
        $this->line('Corrections requested: '.(int) ($benchmark['correction_requested_count'] ?? 0));
        $this->newLine();

        $this->line('NEXT STEP');
        $this->line('---------');
        $this->line('Title: '.$this->value($next['title'] ?? null));
        $this->line('Message: '.$this->value($next['message'] ?? null));
        $this->line('Action: '.$this->value($next['action_type'] ?? null));
        $this->line('Daily Plan: '.$this->value($next['daily_plan_id'] ?? null));
        $this->newLine();

        $this->line('PLAN ROWS');
        $this->line('---------');
        $rows = Arr::wrap($payload['plan_rows'] ?? []);
        if (empty($rows)) {
            $this->line('- none');
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $this->line('- '.$this->value($row['day_label'] ?? null).' '.$this->value($row['scheduled_for'] ?? null).' · '.$this->value($row['title'] ?? null));
            $this->line('  Status: '.$this->value($row['status'] ?? null).' · Progress: '.(int) ($row['completed_items'] ?? 0).'/'.(int) ($row['total_items'] ?? 0).' · '.$this->number($row['completion_percentage'] ?? 0).'%');
            $this->line('  Submitted: '.(int) ($row['submitted_metric_count'] ?? 0).' · Pending: '.(int) ($row['pending_review_count'] ?? 0).' · Approved: '.(int) ($row['approved_count'] ?? 0).' · Corrections: '.(int) ($row['correction_requested_count'] ?? 0));
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

    private function value($value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }

    private function number($value): string
    {
        return number_format((float) $value, 1);
    }
}
