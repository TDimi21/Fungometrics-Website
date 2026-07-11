<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\PopulationMetricRepository;
use App\Services\Intelligence\PopulationPercentileEngine;
use Illuminate\Console\Command;

class PopulationMetricAudit extends Command
{
    protected $signature = 'intelligence:population-metric
        {metricKey : Benchmark metric key to audit}
        {--teamId= : Optional team scope}
        {--age-group= : Optional age group bucket}
        {--level= : Optional team/player level bucket}
        {--position= : Optional position or role bucket}
        {--bodyweight-band= : Optional bodyweight bucket}
        {--height-band= : Optional height bucket}
        {--throws= : Optional throwing side bucket}
        {--bats= : Optional hitting side bucket}
        {--value= : Optional player value to compare against the FMTRX population bucket}
        {--include-trusted-tasks : Include approved/promoted benchmark task payloads in population samples}
        {--exclude-trusted-tasks : Exclude approved/promoted benchmark task payloads from population samples}
        {--days=365 : Population lookback window in days}';

    protected $description = 'Audit FMTRX population values for one benchmark metric.';

    public function handle(PopulationMetricRepository $repository, PopulationPercentileEngine $populationPercentileEngine): int
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $this->argument('metricKey'));
        $days = max(1, (int) $this->option('days'));
        $context = $this->auditContext();
        $audit = $repository->auditForMetric($metricKey, $context, $days);
        $values = $audit['final_values'] ?? $audit['values'] ?? [];
        $count = count($values);

        $this->info('FMTRX POPULATION METRIC AUDIT');
        $this->line('Metric key: '.$metricKey);
        $this->line('Days: '.$days);
        $this->line('Context: '.$this->formatContext($context));
        $this->line('Trusted task payloads: '.(($audit['trusted_tasks_included'] ?? false) ? 'included' : 'excluded'));

        $this->newLine();
        $this->info('RAW SAMPLE BEFORE GUARDRAILS');
        $this->line('Raw values found: '.($audit['raw_values_found'] ?? 0));
        $this->line('Sample raw values: '.$this->sampleDebugRows($audit['raw_sample_before_guardrails'] ?? [], 'raw_value'));

        $this->newLine();
        $this->info('GUARDRAIL-FILTERED SAMPLE');
        $this->line('Raw values included after guardrails: '.($audit['raw_values_included'] ?? 0));
        $this->line('Values excluded by guardrails: '.($audit['values_excluded'] ?? 0));
        $this->line('Excluded reasons: '.$this->formatReasonCounts($audit['excluded_reason_counts'] ?? []));
        $this->line('Player-level values after guardrails: '.($audit['guardrail_filtered_count'] ?? 0));
        $this->line('Sample player-level values: '.$this->sampleDebugRows($audit['guardrail_filtered_sample'] ?? [], 'value'));
        $this->line('Note: valid raw rows are grouped by player before percentile calculation.');
        $this->line('Sample excluded values: '.$this->sampleExcludedValues($audit['excluded_samples'] ?? []));

        $this->newLine();
        $this->info('TRUSTED TASK PAYLOAD SAMPLE');
        if (! ($audit['trusted_tasks_included'] ?? false)) {
            $this->line('Trusted task payload sampling is disabled for this audit.');
        } else {
            $this->line('Trusted task values found: '.($audit['trusted_task_values_found'] ?? 0));
            $this->line('Trusted task values included after guardrails: '.($audit['trusted_task_values_included_before_dedupe'] ?? 0));
            $this->line('Trusted task values excluded by guardrails: '.($audit['trusted_task_values_excluded'] ?? 0));
            $this->line('Trusted excluded reasons: '.$this->formatReasonCounts($audit['trusted_task_excluded_reasons'] ?? []));
            $this->line('Trusted task values deduped behind existing table values: '.($audit['trusted_task_deduped_count'] ?? $audit['deduped_count'] ?? 0));
            $this->line('Trusted task values in final sample: '.($audit['trusted_task_values_count'] ?? 0));
            $this->line('Sample trusted task values: '.$this->sampleDebugRows($audit['trusted_task_sample'] ?? [], 'value'));
            $this->line('Sample trusted excluded values: '.$this->sampleExcludedValues($audit['trusted_task_excluded_samples'] ?? []));
            $this->line('Note: only completed, approved, promoted benchmark task payloads are eligible. Pending, draft, rejected, and unapproved tasks are excluded.');
        }

        $this->newLine();
        $this->info('BUCKET / CONTEXT FILTERED SAMPLE');
        $this->line('Bucket key: '.($audit['bucket_key'] ?? '-'));
        $this->line('Context filters applied: '.(($audit['bucket_filter_applied'] ?? false) ? 'yes' : 'no'));
        $this->line('Values before context filter: '.($audit['bucket_context_before_count'] ?? 0));
        $this->line('Values removed by context filters: '.($audit['bucket_context_removed_count'] ?? 0));
        $this->line('Values after context filter: '.($audit['bucket_context_after_count'] ?? 0));
        if (($audit['bucket_context_removed_count'] ?? 0) > 0) {
            $this->line('Sample removed values: '.$this->sampleDebugRows($audit['bucket_context_removed_samples'] ?? [], 'value'));
        }

        $this->newLine();
        $this->info('FINAL PERCENTILE SAMPLE');
        $this->line('Final values used for percentile: '.$count);
        $this->line('Min: '.$this->formatNumber($count > 0 ? min($values) : null));
        $this->line('Max: '.$this->formatNumber($count > 0 ? max($values) : null));
        $this->line('Average: '.$this->formatNumber($count > 0 ? array_sum($values) / $count : null));
        $this->line('Sample final values: '.$this->sampleValues($values));

        if ($this->hasComparisonValue()) {
            $this->newLine();
            $this->info('POPULATION PERCENTILE');
            $percentile = $populationPercentileEngine->percentileFromRepository(
                $metricKey,
                (float) $this->option('value'),
                $context,
                $days,
            );

            $this->line('Value: '.$this->formatNumber($this->option('value')));
            $this->line('Selected bucket level: '.($percentile['selected_bucket_level'] ?? 'none'));
            $this->line('Selected bucket key: '.($percentile['selected_bucket_key'] ?? '-'));
            $this->line('Selected bucket count: '.($percentile['bucket_count'] ?? 0));
            $this->line('Table values in selected bucket: '.($percentile['table_values_count'] ?? 0));
            $this->line('Trusted task values in selected bucket: '.($percentile['trusted_task_values_count'] ?? 0));
            $this->line('Values deduped before percentile: '.($percentile['deduped_count'] ?? 0));
            $this->line('Final population values: '.($percentile['final_population_values_count'] ?? $percentile['bucket_count'] ?? 0));
            $this->line('Population percentile: '.$this->formatNumber($percentile['percentile'] ?? null));
            $this->line('Usable: '.(($percentile['usable'] ?? false) ? 'yes' : 'no'));
            $this->line('Confidence: '.($percentile['confidence'] ?? 'insufficient'));
            $this->line('Attempted buckets: '.$this->formatAttemptedBuckets($percentile['attempted_buckets'] ?? []));
            $this->line('Source: '.($percentile['source'] ?? 'fmtrx_population'));
            $this->line('Evidence: '.$this->formatEvidence($percentile['evidence'] ?? []));
        }

        if (! in_array($metricKey, $repository->supportedMetricKeys(), true)) {
            $this->warn('Metric is not mapped yet. Available mapped metrics:');
            foreach ($repository->supportedMetricKeys() as $supportedMetricKey) {
                $this->line('- '.$supportedMetricKey);
            }
        }

        return self::SUCCESS;
    }

    private function hasComparisonValue(): bool
    {
        $value = $this->option('value');

        return $value !== null && $value !== '' && is_numeric($value);
    }

    private function auditContext(): array
    {
        $context = [];
        $teamId = $this->option('teamId');

        if (is_string($teamId) && trim($teamId) !== '') {
            $context['team_id'] = trim($teamId);
        }

        foreach ([
            'age-group' => 'age_group',
            'level' => 'level',
            'position' => 'position',
            'bodyweight-band' => 'bodyweight_band',
            'height-band' => 'height_band',
            'throws' => 'throws',
            'bats' => 'bats',
        ] as $option => $contextKey) {
            $value = $this->option($option);
            if (is_string($value) && trim($value) !== '') {
                $context[$contextKey] = trim($value);
            }
        }

        $context['include_trusted_tasks'] = ! (bool) $this->option('exclude-trusted-tasks');
        if ((bool) $this->option('include-trusted-tasks')) {
            $context['include_trusted_tasks'] = true;
        }

        return $context;
    }

    private function formatContext(array $context): string
    {
        if (empty($context)) {
            return '{}';
        }

        return json_encode($context, JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function sampleValues(array $values): string
    {
        if (empty($values)) {
            return '-';
        }

        return implode(', ', array_map(
            fn ($value) => $this->formatNumber($value),
            array_slice($values, 0, 12),
        ));
    }

    private function formatReasonCounts(array $counts): string
    {
        if (empty($counts)) {
            return '-';
        }

        return implode(', ', array_map(
            fn ($reason, $count) => $reason.': '.$count,
            array_keys($counts),
            array_values($counts),
        ));
    }

    private function sampleExcludedValues(array $samples): string
    {
        if (empty($samples)) {
            return '-';
        }

        return implode(' | ', array_map(function (array $sample) {
            $value = $sample['raw_value'] ?? null;
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
            }

            return sprintf(
                '%s.%s=%s (%s)',
                $sample['table'] ?? '-',
                $sample['column'] ?? '-',
                $value === null || $value === '' ? '-' : (string) $value,
                $sample['reason'] ?? 'invalid_value',
            );
        }, array_slice($samples, 0, 6)));
    }

    private function sampleDebugRows(array $rows, string $valueKey): string
    {
        if (empty($rows)) {
            return '-';
        }

        return implode(' | ', array_map(function (array $row) use ($valueKey) {
            $value = $row[$valueKey] ?? $row['value'] ?? $row['raw_value'] ?? null;
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
            }

            $source = isset($row['table'])
                ? ($row['table'].'.'.($row['column'] ?? 'value'))
                : ('user '.($row['user_id'] ?? '-'));
            $reason = isset($row['reason']) ? ' ('.$row['reason'].')' : '';

            return sprintf(
                '%s=%s%s',
                $source,
                $this->formatNumberOrText($value),
                $reason,
            );
        }, array_slice($rows, 0, 8)));
    }

    private function formatEvidence(array $evidence): string
    {
        if (empty($evidence)) {
            return '-';
        }

        return implode(' | ', array_map(
            fn ($item) => is_scalar($item) ? (string) $item : (json_encode($item, JSON_UNESCAPED_SLASHES) ?: ''),
            $evidence,
        ));
    }

    private function formatAttemptedBuckets(array $attemptedBuckets): string
    {
        if (empty($attemptedBuckets)) {
            return '-';
        }

        return implode(' | ', array_map(function (array $attempt) {
            $trusted = isset($attempt['trusted_task_values_count'])
                ? ', trusted '.$attempt['trusted_task_values_count']
                : '';

            return sprintf(
                '%s=%s (%s%s%s)',
                $attempt['level'] ?? 'unknown',
                $attempt['bucket_key'] ?? '-',
                (int) ($attempt['count'] ?? 0),
                $trusted,
                ($attempt['usable'] ?? false) ? ', usable' : ''
            );
        }, $attemptedBuckets));
    }

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        $value = (float) $value;
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function formatNumberOrText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_numeric($value)) {
            return $this->formatNumber($value);
        }

        return (string) $value;
    }
}
