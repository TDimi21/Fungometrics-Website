<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use App\Services\Planner\NextWeekPlanGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class NextWeekCalendarDraftAudit extends Command
{
    protected $signature = 'planner:next-week-calendar-draft
        {teamId : Team id}
        {--next-week-start= : Next week start date YYYY-MM-DD}
        {--days=7 : Days to review for the weekly rollup}
        {--plan-days=5 : Number of calendar days to generate}
        {--max-minutes=90 : Target max minutes per day}
        {--save-days= : Comma-separated generated day indexes to save as Daily Planner drafts}
        {--overwrite-existing : Update an existing plan for the date only when no player progress exists}
        {--json : Output structured JSON}';

    protected $description = 'Generate and optionally save a next-week Daily Planner calendar draft from the weekly rollup.';

    public function handle(
        NextWeekPlanGeneratorService $generator,
        BenchmarkPracticePlanDailyPlannerAdapter $adapter,
    ): int {
        $teamId = (string) $this->argument('teamId');
        $options = [
            'next_week_start_date' => $this->option('next-week-start') ?: null,
            'days' => max(1, min(365, (int) $this->option('days'))),
            'plan_days' => max(1, min(7, (int) $this->option('plan-days'))),
            'max_minutes_per_day' => max(30, min(180, (int) $this->option('max-minutes'))),
        ];
        $draft = $generator->buildCalendarDraft($teamId, $options);
        $saveResult = null;
        $saveIndexes = $this->saveIndexes((string) ($this->option('save-days') ?? ''));

        if (! empty($saveIndexes)) {
            $calendarDays = collect(Arr::wrap($draft['calendar_days'] ?? []))->keyBy('day_index');
            $missingIndexes = [];
            $selected = collect($saveIndexes)
                ->map(function (int $index) use ($calendarDays, &$missingIndexes): ?array {
                    $day = $calendarDays->get($index);
                    if (! is_array($day)) {
                        $missingIndexes[] = [
                            'day_index' => $index,
                            'reason' => 'No generated calendar day exists for this index.',
                        ];
                        return null;
                    }

                    return [
                        ...$day,
                        'overwrite_existing' => (bool) $this->option('overwrite-existing'),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $saveResult = $adapter->saveGeneratedDaysToDailyPlanner($teamId, $selected, [
                'overwrite_existing' => (bool) $this->option('overwrite-existing'),
            ]);
            if (! empty($missingIndexes)) {
                $saveResult['skipped_days'] = [
                    ...Arr::wrap($saveResult['skipped_days'] ?? []),
                    ...$missingIndexes,
                ];
                $saveResult['skipped_count'] = count($saveResult['skipped_days']);
            }
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'calendar_draft' => $draft,
                'save_result' => $saveResult,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('FMTRX NEXT WEEK CALENDAR DRAFT');
        $this->line('Team ID: '.$teamId);
        $this->line('Week: '.($draft['week_start_date'] ?? '—').' to '.($draft['week_end_date'] ?? '—'));
        $this->line('Source: '.($draft['source'] ?? '—'));
        $summary = Arr::wrap($draft['weekly_workload_summary'] ?? []);
        $this->line('Total planned minutes: '.(int) ($summary['total_planned_minutes'] ?? 0));
        $this->line('Average minutes/day: '.($summary['average_minutes_per_day'] ?? 0));
        $this->line('High workload days: '.(int) ($summary['high_workload_days'] ?? 0));
        $this->line('Recovery/support days: '.(int) ($summary['recovery_support_days'] ?? 0));
        $this->newLine();

        $this->line('DAY CARDS');
        $this->line('---------');
        foreach (Arr::wrap($draft['calendar_days'] ?? []) as $day) {
            if (! is_array($day)) {
                continue;
            }
            $this->line('#'.($day['day_index'] ?? '?').' '.$this->field($day, 'day_label').' '.$this->field($day, 'scheduled_for'));
            $this->line('  Title: '.$this->field($day, 'title'));
            $this->line('  Focus: '.$this->field($day, 'primary_focus'));
            $this->line('  Minutes: '.(int) ($day['estimated_total_minutes'] ?? 0).' · Workload: '.$this->field($day, 'workload_label'));
            $this->line('  Save: '.Arr::get($day, 'save_status.message', 'not saved'));
            foreach (Arr::wrap($day['blocks'] ?? []) as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $this->line('    - '.($block['title'] ?? 'Block').' · '.(int) ($block['duration_minutes'] ?? 0).' min');
            }
            foreach (Arr::wrap($day['warnings'] ?? []) as $warning) {
                $this->warn('    Warning: '.$warning);
            }
        }

        if (! empty($draft['warnings'])) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach (Arr::wrap($draft['warnings'] ?? []) as $warning) {
                $this->warn('- '.$warning);
            }
        }

        if ($saveResult !== null) {
            $this->newLine();
            $this->line('SAVE RESULT');
            $this->line('-----------');
            $this->line('Saved: '.(int) ($saveResult['saved_count'] ?? 0));
            $this->line('Skipped: '.(int) ($saveResult['skipped_count'] ?? 0));
            foreach (Arr::wrap($saveResult['saved_daily_plans'] ?? []) as $saved) {
                if (is_array($saved)) {
                    $this->info('- Day '.($saved['day_index'] ?? '?').' saved as '.$saved['saved_daily_plan_id']);
                }
            }
            foreach (Arr::wrap($saveResult['skipped_days'] ?? []) as $skipped) {
                if (is_array($skipped)) {
                    $this->warn('- Day '.($skipped['day_index'] ?? '?').' skipped: '.($skipped['reason'] ?? 'Unknown reason'));
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function saveIndexes(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $piece): int => (int) trim($piece))
            ->filter(fn (int $index): bool => $index >= 1 && $index <= 7)
            ->unique()
            ->values()
            ->all();
    }

    private function field(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return $value === null || $value === '' ? '—' : (string) $value;
    }
}
