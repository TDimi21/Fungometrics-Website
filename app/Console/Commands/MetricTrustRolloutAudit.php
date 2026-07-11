<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\MetricTrustRolloutService;
use Illuminate\Console\Command;

class MetricTrustRolloutAudit extends Command
{
    protected $signature = 'intelligence:metric-trust-rollout
        {--profile=initial_safe_rollout : Rollout profile}
        {--preview : Preview recommended policy changes}
        {--apply : Apply recommended policy changes}
        {--metric= : Limit to one metric}
        {--category= : Limit to one benchmark category}
        {--days=365 : Population audit lookback window}
        {--preserve-notes : Keep existing admin notes unchanged}
        {--json : Print JSON payload}';

    protected $description = 'Preview or apply FMTRX metric trust rollout policies.';

    public function handle(MetricTrustRolloutService $rolloutService): int
    {
        $options = $this->optionsPayload();
        $apply = (bool) $this->option('apply');
        $result = $apply
            ? $rolloutService->applyRollout($options)
            : $rolloutService->previewRollout($options);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX METRIC TRUST ROLLOUT');
        $this->line('Profile: '.($result['profile'] ?? '-'));
        $this->line('Days: '.($result['days'] ?? '-'));
        $this->line('Mode: '.($result['mode'] ?? ($apply ? 'apply' : 'preview')));
        $this->line('Metric filter: '.($this->option('metric') ?: '-'));
        $this->line('Category filter: '.($this->option('category') ?: '-'));

        $this->section('SUMMARY');
        $summary = $result['summary'] ?? [];
        $this->kv('Metrics reviewed', $summary['metrics_reviewed'] ?? 0);
        $this->kv('Metrics to update', $summary['metrics_to_update'] ?? 0);
        $this->kv('Metrics unchanged', $summary['metrics_unchanged'] ?? 0);
        $this->kv('Composite enabled', $summary['metrics_set_composite_enabled'] ?? 0);
        $this->kv('Research only', $summary['metrics_set_research_only'] ?? 0);
        $this->kv('Needs review', $summary['metrics_set_needs_review'] ?? 0);
        $this->kv('Disabled', $summary['metrics_disabled'] ?? 0);
        $this->kv('Status counts', $summary['status_counts'] ?? []);

        $this->section('PER-METRIC POLICY');
        $this->printMetricPolicies($result['metrics'] ?? []);

        if (! empty($result['warnings'] ?? [])) {
            $this->section('WARNINGS');
            foreach ($result['warnings'] as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }

    private function optionsPayload(): array
    {
        $metric = $this->optionString('metric');

        return [
            'profile' => $this->option('profile') ?: MetricTrustRolloutService::PROFILE_INITIAL_SAFE,
            'metric_key' => $metric ? BenchmarkDefinitions::normalizeMetricKey($metric) : null,
            'category' => $this->optionString('category'),
            'days' => max(1, min(3650, (int) $this->option('days'))),
            'preserve_notes' => (bool) $this->option('preserve-notes'),
        ];
    }

    private function printMetricPolicies(array $metrics): void
    {
        if (empty($metrics)) {
            $this->line('- none');

            return;
        }

        foreach ($metrics as $metric) {
            $this->line(sprintf(
                '- %s | %s | current %s | recommended %s | bucket %s | readiness %s | update %s',
                $metric['display_name'] ?? $metric['metric_key'] ?? 'Unknown Metric',
                $metric['category'] ?? '-',
                $metric['current_status'] ?? 'none',
                $metric['recommended_status'] ?? '-',
                $metric['bucket_count'] ?? 0,
                $metric['audit_readiness'] ?? '-',
                ($metric['will_update'] ?? false) ? 'YES' : 'NO',
            ));
            $this->line('  qa: '.$this->wrap($metric['qa_flags'] ?? []));
            $this->line('  reason: '.($metric['reason'] ?? '-'));
            if (array_key_exists('applied', $metric)) {
                $this->line('  applied: '.(($metric['applied'] ?? false) ? 'YES' : 'NO'));
            }
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

    private function optionString(string $key): ?string
    {
        $value = $this->option($key);
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
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
}
