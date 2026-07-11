<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PopulationLearningControl;
use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\PopulationLearningAuditService;
use App\Services\Intelligence\PopulationLearningControlService;
use Illuminate\Console\Command;

class PopulationLearningControlAudit extends Command
{
    protected $signature = 'intelligence:population-learning-controls
        {--metric= : Optional benchmark metric key}
        {--category= : Optional benchmark category}
        {--days=365 : Audit lookback window in days}
        {--sync-from-audit : Create/update controls from the current population learning audit}
        {--set-status= : Set status: auto, research_only, population_enabled, composite_enabled, disabled, needs_review}
        {--enable-population : Enable population samples for this metric control}
        {--disable-population : Disable population samples for this metric control}
        {--research-only : Lock this metric to research benchmark scoring}
        {--disable-metric : Disable this metric from benchmark scoring}
        {--min-sample= : Set minimum population sample size}
        {--notes= : Admin review notes}
        {--json : Print JSON payload}';

    protected $description = 'Inspect and manage FMTRX population learning admin controls.';

    public function handle(
        PopulationLearningControlService $controlService,
        PopulationLearningAuditService $auditService,
    ): int {
        $metric = $this->metricOption();
        $category = $this->optionString('category');
        $days = max(1, min(3650, (int) $this->option('days')));

        if ((bool) $this->option('sync-from-audit')) {
            $result = $controlService->syncControlsFromAudit([
                'metric_key' => $metric,
                'category' => $category,
                'days' => $days,
            ]);

            return $this->printSyncResult($result);
        }

        if ($this->hasUpdateOptions()) {
            if ($metric === null) {
                $this->error('Use --metric= when updating a population learning control.');

                return self::FAILURE;
            }

            $control = $controlService->updateControl($metric, $this->updatePayload(), null);
            $this->info('Population learning control updated.');
            $this->newLine();
            $this->printControl($control, $this->metricAudit($auditService, $metric, $days));

            return self::SUCCESS;
        }

        if ($metric !== null) {
            $control = $controlService->getControlForMetric($metric);
            $this->printControl($control, $this->metricAudit($auditService, $metric, $days));

            return self::SUCCESS;
        }

        $summary = $controlService->buildControlSummary([
            'category' => $category,
        ]);

        if ((bool) $this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX POPULATION LEARNING CONTROLS');
        $this->line('Generated: '.($summary['generated_at'] ?? '-'));
        $this->line('Metric count: '.($summary['metric_count'] ?? 0));
        $this->line('Status counts: '.$this->wrap($summary['status_counts'] ?? []));
        $this->newLine();

        foreach (($summary['controls'] ?? []) as $control) {
            $this->line(sprintf(
                '- %s | %s | %s | population %s | composite %s | min %s | notes %s',
                $control['metric_key'] ?? '-',
                $control['category'] ?? '-',
                $control['status'] ?? PopulationLearningControl::STATUS_AUTO,
                ($control['population_enabled'] ?? false) ? 'enabled' : 'off',
                ($control['composite_enabled'] ?? false) ? 'enabled' : 'off',
                $control['minimum_sample_size'] ?? 30,
                $control['admin_notes'] ?? '-',
            ));
        }

        return self::SUCCESS;
    }

    private function printSyncResult(array $result): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return self::SUCCESS;
        }

        $this->info('FMTRX POPULATION LEARNING CONTROLS SYNC');
        $this->line('Generated: '.($result['generated_at'] ?? '-'));
        $this->line('Controls updated: '.($result['metric_count'] ?? 0));
        $this->line('Readiness summary: '.$this->wrap($result['audit_summary']['readiness_summary'] ?? []));
        $this->line('Guardrail summary: '.$this->wrap($result['audit_summary']['guardrail_summary'] ?? []));
        $this->newLine();

        foreach (($result['controls'] ?? []) as $control) {
            $this->line(sprintf(
                '- %s | status %s | population %s | composite %s | min %s',
                $control['metric_key'] ?? '-',
                $control['status'] ?? PopulationLearningControl::STATUS_AUTO,
                ($control['population_enabled'] ?? false) ? 'enabled' : 'off',
                ($control['composite_enabled'] ?? false) ? 'enabled' : 'off',
                $control['minimum_sample_size'] ?? 30,
            ));
        }

        return self::SUCCESS;
    }

    private function printControl(array $control, ?array $audit): void
    {
        $policy = $audit['population_policy'] ?? null;
        if (! is_array($policy)) {
            $policy = [];
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'control' => $control,
                'latest_audit' => $audit,
                'policy' => $policy,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

            return;
        }

        $this->info('FMTRX POPULATION LEARNING CONTROL');
        $this->line('Metric: '.($control['metric_key'] ?? '-'));
        $this->line('Category: '.($control['category'] ?? '-'));
        $this->line('Status: '.($control['status'] ?? PopulationLearningControl::STATUS_AUTO));
        $this->line('Exists in DB: '.(($control['exists'] ?? false) ? 'YES' : 'NO'));
        $this->line('Population enabled: '.(($control['population_enabled'] ?? false) ? 'YES' : 'NO'));
        $this->line('Research enabled: '.(($control['research_enabled'] ?? true) ? 'YES' : 'NO'));
        $this->line('Composite enabled: '.(($control['composite_enabled'] ?? true) ? 'YES' : 'NO'));
        $this->line('Minimum sample size: '.($control['minimum_sample_size'] ?? 30));
        $this->line('Minimum confidence: '.($control['minimum_confidence'] ?? '-'));
        $this->line('Allow global bucket: '.(($control['allow_global_bucket'] ?? true) ? 'YES' : 'NO'));
        $this->line('Allow exact peer bucket: '.(($control['allow_exact_peer_bucket'] ?? true) ? 'YES' : 'NO'));
        $this->line('Allow age bucket: '.(($control['allow_age_bucket'] ?? true) ? 'YES' : 'NO'));
        $this->line('Max exclusion rate: '.($control['max_exclusion_rate'] ?? '-'));
        $this->line('Admin notes: '.($control['admin_notes'] ?? '-'));
        $this->line('Last reviewed: '.($control['last_reviewed_at'] ?? '-'));

        $this->newLine();
        $this->info('LATEST AUDIT / POLICY');
        if ($audit === null) {
            $this->line('- no audit report available');

            return;
        }

        $this->line('Readiness: '.($audit['readiness'] ?? '-'));
        $this->line('Population confidence: '.($audit['population_confidence'] ?? '-'));
        $this->line('Bucket: '.($audit['selected_bucket_level'] ?? 'none').' ('.($audit['bucket_count'] ?? 0).')');
        $this->line('Raw values found: '.($audit['raw_values_found'] ?? 0));
        $this->line('Included after guardrails: '.($audit['guardrail_included_count'] ?? 0));
        $this->line('Final population values: '.($audit['final_population_values_count'] ?? 0));
        $this->line('Excluded by guardrails: '.($audit['guardrail_excluded_count'] ?? 0));
        $this->line('QA flags: '.$this->wrap($audit['qa_flags'] ?? []));
        $this->line('Population allowed: '.(($policy['population_allowed'] ?? false) ? 'YES' : 'NO'));
        $this->line('Composite allowed: '.(($policy['composite_allowed'] ?? false) ? 'YES' : 'NO'));
        $this->line('Policy reason: '.($policy['reason'] ?? $audit['policy_reason'] ?? '-'));

        if (! empty($audit['recommended_actions'] ?? [])) {
            $this->newLine();
            $this->info('RECOMMENDED ACTIONS');
            foreach ($audit['recommended_actions'] as $action) {
                $this->line('- '.$action);
            }
        }
    }

    private function metricAudit(PopulationLearningAuditService $auditService, string $metric, int $days): ?array
    {
        $report = $auditService->buildAuditReport([
            'metric_key' => $metric,
            'days' => $days,
        ]);

        return $report['metrics'][0] ?? null;
    }

    private function updatePayload(): array
    {
        $payload = [];
        $status = $this->optionString('set-status');

        if ($status !== null) {
            $payload['status'] = $status;
        }
        if ((bool) $this->option('research-only')) {
            $payload['status'] = PopulationLearningControl::STATUS_RESEARCH_ONLY;
            $payload['population_enabled'] = false;
            $payload['composite_enabled'] = false;
            $payload['research_enabled'] = true;
        }
        if ((bool) $this->option('disable-metric')) {
            $payload['status'] = PopulationLearningControl::STATUS_DISABLED;
            $payload['population_enabled'] = false;
            $payload['composite_enabled'] = false;
            $payload['research_enabled'] = false;
        }
        if ((bool) $this->option('enable-population')) {
            $payload['population_enabled'] = true;
        }
        if ((bool) $this->option('disable-population')) {
            $payload['population_enabled'] = false;
            $payload['composite_enabled'] = false;
        }
        if (($payload['status'] ?? null) === PopulationLearningControl::STATUS_COMPOSITE_ENABLED) {
            $payload['composite_enabled'] = true;
        }
        if (($payload['status'] ?? null) === PopulationLearningControl::STATUS_POPULATION_ENABLED) {
            $payload['population_enabled'] = true;
        }

        $minSample = $this->optionString('min-sample');
        if ($minSample !== null) {
            $payload['minimum_sample_size'] = $minSample;
        }

        $notes = $this->optionString('notes');
        if ($notes !== null) {
            $payload['admin_notes'] = $notes;
        }

        return $payload;
    }

    private function hasUpdateOptions(): bool
    {
        return $this->optionString('set-status') !== null
            || (bool) $this->option('enable-population')
            || (bool) $this->option('disable-population')
            || (bool) $this->option('research-only')
            || (bool) $this->option('disable-metric')
            || $this->optionString('min-sample') !== null
            || $this->optionString('notes') !== null;
    }

    private function metricOption(): ?string
    {
        $metric = $this->optionString('metric');

        return $metric !== null ? BenchmarkDefinitions::normalizeMetricKey($metric) : null;
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
