<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\PopulationMetricRepository;
use Illuminate\Console\Command;

class PopulationMetricAudit extends Command
{
    protected $signature = 'intelligence:population-metric
        {metricKey : Benchmark metric key to audit}
        {--teamId= : Optional team scope}
        {--days=365 : Population lookback window in days}';

    protected $description = 'Audit FMTRX population values for one benchmark metric.';

    public function handle(PopulationMetricRepository $repository): int
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey((string) $this->argument('metricKey'));
        $days = max(1, (int) $this->option('days'));
        $context = $this->auditContext();
        $values = $repository->valuesForMetric($metricKey, $context, $days);
        $count = count($values);

        $this->info('FMTRX POPULATION METRIC AUDIT');
        $this->line('Metric key: '.$metricKey);
        $this->line('Days: '.$days);
        $this->line('Context: '.$this->formatContext($context));
        $this->line('Total values found: '.$count);
        $this->line('Min: '.$this->formatNumber($count > 0 ? min($values) : null));
        $this->line('Max: '.$this->formatNumber($count > 0 ? max($values) : null));
        $this->line('Average: '.$this->formatNumber($count > 0 ? array_sum($values) / $count : null));
        $this->line('Sample values: '.$this->sampleValues($values));

        if (! in_array($metricKey, $repository->supportedMetricKeys(), true)) {
            $this->warn('Metric is not mapped yet. Available mapped metrics:');
            foreach ($repository->supportedMetricKeys() as $supportedMetricKey) {
                $this->line('- '.$supportedMetricKey);
            }
        }

        return self::SUCCESS;
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
