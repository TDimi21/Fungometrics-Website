<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\PopulationLearningAuditService;
use Illuminate\Console\Command;

class PopulationLearningAudit extends Command
{
    protected $signature = 'intelligence:population-learning-audit
        {--metric= : Optional benchmark metric key}
        {--category= : Optional benchmark category}
        {--teamId= : Optional team scope}
        {--days=365 : Population lookback window in days}
        {--age-group= : Optional age group bucket}
        {--level= : Optional team/player level bucket}
        {--position= : Optional position or role bucket}
        {--bodyweight-band= : Optional bodyweight bucket}
        {--height-band= : Optional height bucket}
        {--throws= : Optional throwing side bucket}
        {--bats= : Optional hitting side bucket}
        {--exclude-trusted-tasks : Exclude approved/promoted benchmark task payloads from population samples}
        {--json : Print the full structured report as JSON}';

    protected $description = 'Audit FMTRX population learning readiness, bucket quality, guardrails, and trusted task contribution.';

    public function handle(PopulationLearningAuditService $auditService): int
    {
        $report = $auditService->buildAuditReport($this->optionsPayload());

        if ((bool) $this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX POPULATION LEARNING AUDIT');
        $this->line('Generated: '.($report['generated_at'] ?? '-'));
        $this->line('Days: '.($report['days'] ?? '-'));
        $this->line('Context: '.$this->wrap($report['context'] ?? []));
        $this->line('Metric count: '.($report['metric_count'] ?? 0));

        $this->section('READINESS');
        $this->kv('Composite ready', $report['readiness_summary']['composite_ready'] ?? 0);
        $this->kv('Research only', $report['readiness_summary']['research_only'] ?? 0);
        $this->kv('Not ready', $report['readiness_summary']['not_ready'] ?? 0);
        $this->kv('Population low', $report['readiness_summary']['population_low'] ?? 0);
        $this->kv('Population medium', $report['readiness_summary']['population_medium'] ?? 0);
        $this->kv('Population high', $report['readiness_summary']['population_high'] ?? 0);
        $this->kv('Safe to use', $report['readiness_summary']['safe_to_use'] ?? 0);

        $this->section('BUCKET QUALITY');
        $this->kv('Selected bucket levels', $report['bucket_quality_summary']['selected_bucket_levels'] ?? []);
        $this->kv('Average bucket count', $this->fmt($report['bucket_quality_summary']['average_bucket_count'] ?? null));
        $this->kv('Exact peer available', $report['bucket_quality_summary']['exact_peer_bucket_available'] ?? 0);
        $this->kv('Global/broad only', $report['bucket_quality_summary']['global_or_broad_bucket_only'] ?? 0);
        $this->kv('Insufficient sample', $report['bucket_quality_summary']['insufficient_population_sample'] ?? 0);

        $this->section('GUARDRAILS');
        $this->kv('Raw values found', $report['guardrail_summary']['raw_values_found'] ?? 0);
        $this->kv('Included after guardrails', $report['guardrail_summary']['included_after_guardrails'] ?? 0);
        $this->kv('Excluded by guardrails', $report['guardrail_summary']['excluded_by_guardrails'] ?? 0);
        $this->kv('Exclusion rate', $this->pct($report['guardrail_summary']['exclusion_rate'] ?? null));
        $this->kv('Top excluded reason', $report['guardrail_summary']['top_excluded_reason'] ?? '-');
        $this->kv('Excluded reasons', $report['guardrail_summary']['excluded_reasons'] ?? []);

        $this->section('TRUSTED TASK PAYLOADS');
        $this->kv('Values found', $report['trusted_task_summary']['trusted_task_values_found'] ?? 0);
        $this->kv('Included after guardrails', $report['trusted_task_summary']['trusted_task_values_included_after_guardrails'] ?? 0);
        $this->kv('Final population values', $report['trusted_task_summary']['trusted_task_values_in_final_population'] ?? 0);
        $this->kv('Excluded', $report['trusted_task_summary']['trusted_task_values_excluded'] ?? 0);
        $this->kv('Status-excluded', $report['trusted_task_summary']['trusted_task_values_status_excluded'] ?? 0);
        $this->kv('Deduped behind table values', $report['trusted_task_summary']['trusted_task_values_deduped'] ?? 0);
        $this->kv('Excluded reasons', $report['trusted_task_summary']['trusted_task_excluded_reasons'] ?? []);

        $this->section('PER-METRIC QA');
        $this->printMetricRows($report['metrics'] ?? []);

        $this->section('RECOMMENDED ACTIONS');
        $this->printRows($report['recommended_actions'] ?? [], fn ($row) => (string) $row);

        if (! empty($report['warnings'] ?? [])) {
            $this->section('WARNINGS');
            $this->printRows($report['warnings'], fn ($row) => (string) $row);
        }

        return self::SUCCESS;
    }

    private function optionsPayload(): array
    {
        return [
            'metric_key' => $this->option('metric'),
            'category' => $this->option('category'),
            'team_id' => $this->option('teamId'),
            'days' => $this->option('days'),
            'age_group' => $this->option('age-group'),
            'level' => $this->option('level'),
            'position' => $this->option('position'),
            'bodyweight_band' => $this->option('bodyweight-band'),
            'height_band' => $this->option('height-band'),
            'throws' => $this->option('throws'),
            'bats' => $this->option('bats'),
            'include_trusted_tasks' => ! (bool) $this->option('exclude-trusted-tasks'),
        ];
    }

    private function printMetricRows(array $metrics): void
    {
        if (empty($metrics)) {
            $this->line('- none');

            return;
        }

        foreach ($metrics as $metric) {
            $this->line(sprintf(
                '- %s | readiness %s | confidence %s | bucket %s (%s) | raw %s | included %s | final %s | excluded %s | trusted %s | flags %s',
                $metric['display_name'] ?? $metric['metric_key'] ?? 'Unknown Metric',
                $metric['readiness'] ?? 'not_ready',
                $metric['population_confidence'] ?? 'insufficient',
                $metric['selected_bucket_level'] ?? 'none',
                $metric['bucket_count'] ?? 0,
                $metric['raw_values_found'] ?? 0,
                $metric['guardrail_included_count'] ?? 0,
                $metric['final_population_values_count'] ?? 0,
                $metric['guardrail_excluded_count'] ?? 0,
                $metric['trusted_task_values_count'] ?? 0,
                empty($metric['qa_flags'] ?? []) ? '-' : implode(', ', $metric['qa_flags']),
            ));

            if (! empty($metric['recommended_actions'] ?? [])) {
                foreach (array_slice($metric['recommended_actions'], 0, 2) as $action) {
                    $this->line('  action: '.$action);
                }
            }
        }
    }

    private function printRows(array $rows, callable $formatter): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$formatter($row));
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return empty($value) ? '-' : (json_encode($value, JSON_UNESCAPED_SLASHES) ?: '-');
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function fmt(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return (string) round((float) $value, 1);
    }

    private function pct(mixed $value): string
    {
        return is_numeric($value) ? $this->fmt($value).'%' : '-';
    }
}
