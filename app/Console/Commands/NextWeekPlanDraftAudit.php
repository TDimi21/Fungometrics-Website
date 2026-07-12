<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use App\Services\Planner\NextWeekPlanGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class NextWeekPlanDraftAudit extends Command
{
    protected $signature = 'planner:next-week-draft
        {teamId : Team id}
        {--start= : Reviewed week start date YYYY-MM-DD}
        {--end= : Reviewed week end date YYYY-MM-DD}
        {--next-week-start= : Next week start date YYYY-MM-DD}
        {--days=7 : Days to review when start/end are omitted}
        {--plan-days=5 : Number of suggested plan days}
        {--max-minutes=90 : Max minutes per suggested day}
        {--save-day= : Save selected suggested day index as a Daily Planner draft}
        {--json : Output structured JSON}';

    protected $description = 'Generate and optionally save a coach-reviewable next-week Daily Planner draft from the weekly rollup.';

    public function handle(NextWeekPlanGeneratorService $generator, BenchmarkPracticePlanDailyPlannerAdapter $adapter): int
    {
        $teamId = (string) $this->argument('teamId');
        $options = [
            'start_date' => $this->option('start'),
            'end_date' => $this->option('end'),
            'next_week_start_date' => $this->option('next-week-start'),
            'days' => (int) $this->option('days'),
            'plan_days' => (int) $this->option('plan-days'),
            'max_minutes_per_day' => (int) $this->option('max-minutes'),
        ];
        $draft = $generator->generateForTeam($teamId, $options);
        $saveResult = null;

        if ($this->option('save-day') !== null) {
            $dayIndex = (int) $this->option('save-day');
            $day = collect(Arr::wrap($draft['suggested_plan_days'] ?? []))->firstWhere('day_index', $dayIndex);
            if (! is_array($day)) {
                $saveResult = [
                    'ok' => false,
                    'message' => 'Suggested day '.$dayIndex.' was not found.',
                ];
            } else {
                $saveResult = $adapter->saveGeneratedDayToDailyPlanner($teamId, $day, [
                    'scheduled_for' => $day['scheduled_for'] ?? null,
                    'status' => 'draft',
                    'assigned_player_ids' => [],
                ]);
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'draft' => $draft,
                'save_result' => $saveResult,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->printDraft($draft);
        if ($saveResult !== null) {
            $this->newLine();
            $this->line('SAVED DAILY PLAN');
            $this->line('----------------');
            $this->line('OK: '.$this->value($saveResult['ok'] ?? false));
            $this->line('Daily Plan ID: '.$this->value($saveResult['saved_daily_plan_id'] ?? null));
            $this->line('Status: '.$this->value($saveResult['status'] ?? null));
            $this->line('Published: '.$this->value($saveResult['published'] ?? false));
            $this->line('Assigned players: '.$this->value($saveResult['assigned_player_count'] ?? 0));
            foreach (Arr::wrap($saveResult['warnings'] ?? []) as $warning) {
                $this->line('- '.$this->value($warning));
            }
        }

        return self::SUCCESS;
    }

    private function printDraft(array $draft): void
    {
        $week = Arr::wrap($draft['week_reviewed'] ?? []);

        $this->info('FMTRX NEXT WEEK PLAN DRAFT');
        $this->line('Team ID: '.$this->value($draft['team_id'] ?? null));
        $this->line('Status: '.$this->value($draft['generation_status'] ?? null));
        $this->line('Reviewed: '.$this->value($week['start_date'] ?? null).' to '.$this->value($week['end_date'] ?? null));
        $this->line('Next week starts: '.$this->value($draft['next_week_start_date'] ?? null));
        $this->line('Completion: '.$this->value($week['team_completion_percentage'] ?? 0).'%');
        $this->line('Submitted / approved / pending / trusted: '.$this->value($week['benchmark_values_submitted'] ?? 0).' / '.$this->value($week['benchmark_values_approved'] ?? 0).' / '.$this->value($week['pending_review_count'] ?? 0).' / '.$this->value($week['trusted_values_added'] ?? 0));

        $this->newLine();
        $this->line('TOP PRIORITIES');
        $this->line('--------------');
        foreach (Arr::wrap($draft['priority_focuses'] ?? []) as $priority) {
            $this->line('- #'.$this->value($priority['rank'] ?? null).' '.$this->value($priority['title'] ?? null).' ('.$this->value($priority['priority'] ?? null).'): '.$this->value($priority['why'] ?? null));
        }
        if (empty($draft['priority_focuses'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('SUGGESTED DAYS');
        $this->line('--------------');
        foreach (Arr::wrap($draft['suggested_plan_days'] ?? []) as $day) {
            $this->line($this->value($day['day_index'] ?? null).'. '.$this->value($day['day_label'] ?? null).' - '.$this->value($day['title'] ?? null).' ('.$this->value($day['estimated_total_minutes'] ?? 0).' min)');
            foreach (Arr::wrap($day['blocks'] ?? []) as $block) {
                $this->line('   - '.$this->value($block['title'] ?? null).' · '.$this->value($block['duration_minutes'] ?? 0).' min · '.$this->value($block['category'] ?? null));
            }
        }
        if (empty($draft['suggested_plan_days'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('BENCHMARK TARGETS');
        $this->line('-----------------');
        foreach (Arr::wrap($draft['benchmark_collection_targets'] ?? []) as $target) {
            $this->line('- '.$this->value($target['title'] ?? null).': '.$this->value($target['metrics'] ?? []));
        }
        if (empty($draft['benchmark_collection_targets'])) {
            $this->line('- none');
        }

        if (! empty($draft['warnings'])) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach (Arr::wrap($draft['warnings']) as $warning) {
                $this->line('- '.$this->value($warning));
            }
        }
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
