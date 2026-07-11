<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkPracticePlanDailyPlannerAdapter;
use Illuminate\Console\Command;

class PracticePlanReconciliationAudit extends Command
{
    protected $signature = 'intelligence:practice-plan-reconcile
        {teamId}
        {--days=365 : Intelligence lookback window in days}
        {--preview : Print the mapped Daily Planner preview}
        {--save-to-daily-planner : Save the mapped plan into the existing daily_plans table}
        {--publish : Publish the saved daily plan so assigned players can see it}
        {--assign-all : Assign the saved daily plan to every player on the team}
        {--max-minutes=90 : Maximum minutes for the suggested practice plan}';

    protected $description = 'Audit and optionally map FMTRX coach action practice plans into the existing Daily Plan / Workout system.';

    public function handle(BenchmarkPracticePlanDailyPlannerAdapter $adapter): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $maxMinutes = max(30, min(180, (int) $this->option('max-minutes')));

        $preview = $adapter->previewMapping($teamId, $days);

        $this->info('FMTRX PRACTICE PLAN RECONCILIATION');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->line('Max minutes: '.$maxMinutes);
        $this->kv('Source of truth', $preview['source_of_truth'] ?? '-');
        $this->kv('Recommendation layer', $preview['recommendation_layer'] ?? '-');
        $this->kv('Execution layer', $preview['execution_layer'] ?? '-');
        $this->kv('Duplicate planner exists', $preview['duplicate_planner_exists'] ?? false);

        $this->section('EXISTING PLANNER TABLES');
        $this->printKeyValues($preview['existing_planner_tables_found'] ?? []);

        $this->section('PHASE 2Z TABLES');
        $this->printKeyValues($preview['phase_2z_tables_found'] ?? []);

        $this->section('RECONCILIATION ANSWERS');
        $this->printKeyValues($preview['answers'] ?? []);

        $this->section('SUGGESTED PRACTICE PLAN');
        $this->printKeyValues($preview['suggested_practice_plan'] ?? []);

        $this->section('2Z BLOCKS TO DAILY PLAN BUCKETS');
        $this->printMappingRows($preview['mapping'] ?? []);

        if ($this->option('preview') || ! $this->option('save-to-daily-planner')) {
            $this->section('DAILY PLAN PREVIEW');
            $this->printDailyPlanPreview($preview['daily_plan_preview'] ?? []);
        }

        $this->section('WARNINGS');
        $this->printList($preview['warnings'] ?? []);

        $this->section('SKIPPED FIELDS');
        $this->printList($preview['skipped_fields'] ?? []);

        if (! $this->option('save-to-daily-planner')) {
            $this->newLine();
            $this->line('Dry run only. Add --save-to-daily-planner to create a Daily Plan draft.');

            return self::SUCCESS;
        }

        $save = $adapter->saveToExistingDailyPlanner($teamId, null, [
            'days' => $days,
            'max_minutes' => $maxMinutes,
            'status' => $this->option('publish') ? 'published' : 'draft',
            'assign_all' => (bool) $this->option('assign-all'),
        ]);

        $this->section('SAVE RESULT');
        $this->kv('OK', $save['ok'] ?? false);
        $this->kv('Saved daily plan ID', $save['saved_daily_plan_id'] ?? '-');
        $this->kv('Status', $save['status'] ?? '-');
        $this->kv('Published', $save['published'] ?? false);
        $this->kv('Assigned players', $save['assigned_player_count'] ?? 0);
        $this->kv('Source', $save['source'] ?? '-');

        $this->section('SAVE WARNINGS');
        $this->printList($save['warnings'] ?? []);

        if (! ($save['published'] ?? false)) {
            $this->line('Note: players only see plans after they are published and assigned.');
        }

        return self::SUCCESS;
    }

    private function printDailyPlanPreview(array $plan): void
    {
        if (empty($plan)) {
            $this->line('- no Daily Plan preview available');

            return;
        }

        $this->kv('Name', $plan['name'] ?? '-');
        $this->kv('Date', $plan['date'] ?? '-');
        $this->kv('Status', $plan['status'] ?? '-');
        $this->kv('Primary goal', $plan['primary_goal'] ?? '-');
        $this->kv('Estimated minutes', ($plan['estimated_minutes'] ?? 0).' min');
        $this->kv('Workload', $plan['workload_level'] ?? '-');
        $this->kv('Assignment count from blocks', count($plan['assigned_player_ids'] ?? []));

        $this->newLine();
        $this->line('Buckets:');
        foreach (($plan['buckets'] ?? []) as $bucket) {
            if (! is_array($bucket)) {
                continue;
            }

            $this->line(sprintf(
                '- %s (%s): %s item(s)%s',
                $bucket['title'] ?? $bucket['type'] ?? 'Bucket',
                $bucket['type'] ?? '-',
                count($bucket['items'] ?? []),
                ! empty($bucket['note']) ? ' + note' : '',
            ));

            foreach (array_slice($bucket['items'] ?? [], 0, 3) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $this->line(sprintf(
                    '  - %s | %s | %s',
                    $item['name'] ?? 'Item',
                    isset($item['durationSec']) ? round(((int) $item['durationSec']) / 60).' min' : '-',
                    $item['workloadType'] ?? '-',
                ));
            }
        }
    }

    private function printMappingRows(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $this->line(sprintf(
                '- %s -> %s | category: %s | metrics: %s | players: %s',
                $row['block'] ?? 'Practice Block',
                $row['bucket_title'] ?? $row['daily_plan_bucket'] ?? '-',
                $row['category'] ?? '-',
                $this->list($row['metrics'] ?? []),
                $row['player_count'] ?? 0,
            ));
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function printKeyValues(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $key => $value) {
            $this->kv($this->human((string) $key), $value);
        }
    }

    private function printList(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function list(array $values): string
    {
        $list = collect($values)
            ->map(fn ($value) => is_array($value) ? ($value['display_name'] ?? $value['metric_key'] ?? null) : $value)
            ->filter()
            ->implode(', ');

        return $list !== '' ? $list : '-';
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
