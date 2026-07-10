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
        {--value= : Optional player value to compare against the FMTRX population bucket}
        {--days=365 : Population lookback window in days}';

    protected $description = 'Audit FMTRX population values for one benchmark metric.';

    public function handle(PopulationMetricRepository $repository, PopulationPercentileEngine $populationPercentileEngine): int
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $this->argument('metricKey'));
        $days = max(1, (int) $this->option('days'));
        $context = $this->auditContext();
        $audit = $repository->auditForMetric($metricKey, $context, $days);
        $values = $audit['values'] ?? [];
        $count = count($values);

        $this->info('FMTRX POPULATION METRIC AUDIT');
        $this->line('Metric key: '.$metricKey);
        $this->line('Days: '.$days);
        $this->line('Context: '.$this->formatContext($context));
        $this->line('Raw values found: '.($audit['raw_values_found'] ?? 0));
        $this->line('Values included: '.($audit['raw_values_included'] ?? $count));
        $this->line('Values excluded: '.($audit['values_excluded'] ?? 0));
        $this->line('Population values used: '.($audit['values_included'] ?? $count));
        $this->line('Aggregation: valid raw rows are grouped by player before percentile calculation.');
        $this->line('Excluded reasons: '.$this->formatReasonCounts($audit['excluded_reason_counts'] ?? []));
        $this->line('Min after filtering: '.$this->formatNumber($count > 0 ? min($values) : null));
        $this->line('Max after filtering: '.$this->formatNumber($count > 0 ? max($values) : null));
        $this->line('Average after filtering: '.$this->formatNumber($count > 0 ? array_sum($values) / $count : null));
        $this->line('Sample included values: '.$this->sampleValues($values));
        $this->line('Sample excluded values: '.$this->sampleExcludedValues($audit['excluded_samples'] ?? []));

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
            $this->line('Population percentile: '.$this->formatNumber($percentile['percentile'] ?? null));
            $this->line('Usable: '.(($percentile['usable'] ?? false) ? 'yes' : 'no'));
            $this->line('Confidence: '.($percentile['confidence'] ?? 'insufficient'));
            $this->line('Bucket count: '.($percentile['bucket_count'] ?? 0));
            $this->line('Bucket key: '.($percentile['bucket_key'] ?? '-'));
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

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        $value = (float) $value;
        $formatted = number_format($value, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
