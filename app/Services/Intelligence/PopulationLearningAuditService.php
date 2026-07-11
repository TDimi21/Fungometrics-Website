<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PopulationLearningControl;

class PopulationLearningAuditService
{
    public function __construct(
        private readonly BenchmarkLibrary $benchmarkLibrary,
        private readonly PopulationMetricRepository $populationMetricRepository,
        private readonly PopulationLearningControlService $controlService,
    ) {
    }

    public function buildAuditReport(array $options = []): array
    {
        $days = $this->days($options['days'] ?? 365);
        $context = $this->contextFromOptions($options);
        $metricKeys = $this->metricKeys($options);
        $reports = [];

        foreach ($metricKeys as $metricKey) {
            $reports[] = $this->auditMetric($metricKey, $context, $days, $options);
        }

        $readinessSummary = $this->buildReadinessSummary($reports);
        $bucketQualitySummary = $this->buildBucketQualitySummary($reports);
        $guardrailSummary = $this->buildGuardrailSummary($reports);
        $trustedTaskSummary = $this->buildTrustedTaskSummary($reports);

        return [
            'generated_at' => now()->toIso8601String(),
            'days' => $days,
            'context' => $context,
            'metric_count' => count($reports),
            'readiness_summary' => $readinessSummary,
            'bucket_quality_summary' => $bucketQualitySummary,
            'guardrail_summary' => $guardrailSummary,
            'trusted_task_summary' => $trustedTaskSummary,
            'metrics' => $reports,
            'warnings' => $this->warnings($reports),
            'recommended_actions' => $this->recommendedActions($reports),
        ];
    }

    public function auditMetric(string $metricKey, array $context = [], int $days = 365, array $options = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $days = $this->days($days);
        $context = $this->contextFromOptions($options, $context);
        $definition = $this->benchmarkLibrary->metric($metricKey);
        $mappingExists = in_array($metricKey, $this->populationMetricRepository->supportedMetricKeys(), true);
        $bucketReports = $this->bucketReports($metricKey, $context, $days, $mappingExists);
        $selected = $this->selectedBucketReport($bucketReports);
        $fallback = $selected ?? $this->lastBucketReport($bucketReports);
        $audit = $fallback['audit'] ?? $this->emptyAudit();
        $values = $audit['final_values'] ?? $audit['values'] ?? [];
        $finalCount = count($values);
        $populationConfidence = $this->populationConfidence($finalCount, (string) ($fallback['level'] ?? ''));
        $researchExists = is_array($definition);
        $readiness = $this->readiness($researchExists, $mappingExists, $finalCount, $populationConfidence);
        $trustedExcludedReasons = $this->mergeCounts(
            $audit['trusted_task_excluded_reasons'] ?? [],
            $audit['trusted_task_status_excluded_reasons'] ?? [],
        );
        $qaFlags = $this->qaFlags($audit, $readiness, $fallback, $bucketReports, $definition, $mappingExists);
        $sourceMix = $this->sourceMixPreview($researchExists, $finalCount, $populationConfidence);
        $policy = $this->controlService->resolvePolicyForMetric($metricKey, [
            'population_confidence' => $populationConfidence,
            'selected_bucket_level' => $selected['level'] ?? $fallback['level'] ?? null,
            'selected_bucket_key' => $selected['bucket_key'] ?? $fallback['bucket_key'] ?? null,
            'bucket_count' => $finalCount,
            'final_population_values_count' => $finalCount,
            'raw_values_found' => (int) ($audit['raw_values_found'] ?? 0),
            'guardrail_excluded_count' => (int) ($audit['values_excluded'] ?? $audit['excluded_count'] ?? 0),
            'qa_flags' => $qaFlags,
            'research_available' => $researchExists,
        ]);
        $safeToUse = $this->safeToUse($readiness, $qaFlags)
            && ((bool) ($policy['population_allowed'] ?? false) || (bool) ($policy['composite_allowed'] ?? false));

        return [
            'metric_key' => $metricKey,
            'display_name' => $definition['display_name'] ?? $this->humanMetric($metricKey),
            'category' => $definition['category'] ?? BenchmarkDefinitions::categoryForMetric($metricKey) ?? 'unknown',
            'unit' => $definition['unit'] ?? null,
            'higher_is_better' => (bool) ($definition['higher_is_better'] ?? true),
            'readiness' => $readiness,
            'population_usable' => $finalCount >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            'population_confidence' => $populationConfidence,
            'selected_bucket_level' => $selected['level'] ?? null,
            'selected_bucket_key' => $selected['bucket_key'] ?? null,
            'bucket_count' => $finalCount,
            'attempted_buckets' => $this->attemptedBuckets($bucketReports),
            'raw_values_found' => (int) ($audit['raw_values_found'] ?? 0),
            'guardrail_included_count' => (int) ($audit['raw_values_included'] ?? 0),
            'guardrail_excluded_count' => (int) ($audit['values_excluded'] ?? $audit['excluded_count'] ?? 0),
            'guardrail_excluded_reasons' => $audit['excluded_reason_counts'] ?? $audit['excluded_reasons'] ?? [],
            'trusted_task_values_found' => (int) ($audit['trusted_task_values_found'] ?? 0),
            'trusted_task_values_included' => (int) ($audit['trusted_task_values_included_before_dedupe'] ?? 0),
            'trusted_task_values_excluded' => (int) ($audit['trusted_task_values_excluded'] ?? 0) + (int) ($audit['trusted_task_values_status_excluded'] ?? 0),
            'trusted_task_values_status_excluded' => (int) ($audit['trusted_task_values_status_excluded'] ?? 0),
            'trusted_task_excluded_reasons' => $trustedExcludedReasons,
            'table_values_count' => (int) ($audit['table_values_count'] ?? 0),
            'trusted_task_values_count' => (int) ($audit['trusted_task_values_count'] ?? 0),
            'deduped_count' => (int) ($audit['deduped_count'] ?? 0),
            'final_population_values_count' => $finalCount,
            'min' => $this->min($values),
            'max' => $this->max($values),
            'average' => $this->average($values),
            'sample_values' => array_slice(array_values($values), 0, 12),
            'sample_excluded_values' => array_slice(array_merge(
                $audit['excluded_samples'] ?? [],
                $audit['trusted_task_excluded_samples'] ?? [],
                $audit['trusted_task_status_excluded_samples'] ?? [],
            ), 0, 12),
            'source_mix_preview' => $sourceMix,
            'control_status' => $policy['status'] ?? PopulationLearningControl::STATUS_AUTO,
            'population_allowed' => (bool) ($policy['population_allowed'] ?? false),
            'composite_allowed' => (bool) ($policy['composite_allowed'] ?? false),
            'policy_reason' => $policy['reason'] ?? 'Population learning policy was not available.',
            'admin_notes' => $policy['admin_notes'] ?? null,
            'population_policy' => $policy,
            'safe_to_use' => $safeToUse,
            'qa_flags' => $qaFlags,
            'evidence' => $this->evidence($definition, $mappingExists, $audit, $fallback, $qaFlags, $sourceMix),
            'recommended_actions' => $this->metricRecommendedActions($metricKey, $definition, $audit, $readiness, $qaFlags, $policy),
        ];
    }

    public function buildReadinessSummary(array $metricReports): array
    {
        $counts = array_fill_keys([
            'not_ready',
            'research_only',
            'population_low',
            'population_medium',
            'population_high',
            'composite_ready',
        ], 0);
        $safe = 0;
        $risky = 0;

        foreach ($metricReports as $report) {
            $readiness = (string) ($report['readiness'] ?? 'not_ready');
            $counts[$readiness] = ($counts[$readiness] ?? 0) + 1;
            if (($report['safe_to_use'] ?? false) === true) {
                $safe++;
            } elseif (! in_array($readiness, ['not_ready', 'research_only'], true)) {
                $risky++;
            }
        }

        return $counts + [
            'safe_to_use' => $safe,
            'risky_or_low_confidence' => $risky,
            'total_metrics' => count($metricReports),
        ];
    }

    public function buildBucketQualitySummary(array $metricReports): array
    {
        $levels = [];
        $bucketCounts = [];
        $exactAvailable = 0;
        $globalOnly = 0;
        $insufficient = 0;

        foreach ($metricReports as $report) {
            $level = (string) ($report['selected_bucket_level'] ?? 'none');
            $levels[$level] = ($levels[$level] ?? 0) + 1;
            $bucketCounts[] = (int) ($report['bucket_count'] ?? 0);
            if (in_array('exact_peer_bucket_available', $report['qa_flags'] ?? [], true)) {
                $exactAvailable++;
            }
            if (in_array('global_bucket_only', $report['qa_flags'] ?? [], true)) {
                $globalOnly++;
            }
            if (($report['bucket_count'] ?? 0) < PopulationPercentileEngine::MIN_LOW_CONFIDENCE) {
                $insufficient++;
            }
        }

        return [
            'selected_bucket_levels' => $levels,
            'average_bucket_count' => $this->average($bucketCounts),
            'exact_peer_bucket_available' => $exactAvailable,
            'global_or_broad_bucket_only' => $globalOnly,
            'insufficient_population_sample' => $insufficient,
        ];
    }

    public function buildGuardrailSummary(array $metricReports): array
    {
        $raw = 0;
        $included = 0;
        $excluded = 0;
        $reasons = [];

        foreach ($metricReports as $report) {
            $raw += (int) ($report['raw_values_found'] ?? 0);
            $included += (int) ($report['guardrail_included_count'] ?? 0);
            $excluded += (int) ($report['guardrail_excluded_count'] ?? 0);
            foreach (($report['guardrail_excluded_reasons'] ?? []) as $reason => $count) {
                $reasons[(string) $reason] = ($reasons[(string) $reason] ?? 0) + (int) $count;
            }
        }

        arsort($reasons);

        return [
            'raw_values_found' => $raw,
            'included_after_guardrails' => $included,
            'excluded_by_guardrails' => $excluded,
            'exclusion_rate' => $raw > 0 ? round(($excluded / $raw) * 100, 1) : null,
            'excluded_reasons' => $reasons,
            'top_excluded_reason' => array_key_first($reasons),
        ];
    }

    public function buildTrustedTaskSummary(array $metricReports): array
    {
        $found = 0;
        $included = 0;
        $final = 0;
        $excluded = 0;
        $statusExcluded = 0;
        $deduped = 0;
        $reasons = [];

        foreach ($metricReports as $report) {
            $found += (int) ($report['trusted_task_values_found'] ?? 0);
            $included += (int) ($report['trusted_task_values_included'] ?? 0);
            $final += (int) ($report['trusted_task_values_count'] ?? 0);
            $excluded += (int) ($report['trusted_task_values_excluded'] ?? 0);
            $statusExcluded += (int) ($report['trusted_task_values_status_excluded'] ?? 0);
            $deduped += (int) ($report['deduped_count'] ?? 0);
            foreach (($report['trusted_task_excluded_reasons'] ?? []) as $reason => $count) {
                $reasons[(string) $reason] = ($reasons[(string) $reason] ?? 0) + (int) $count;
            }
        }

        arsort($reasons);

        return [
            'trusted_task_values_found' => $found,
            'trusted_task_values_included_after_guardrails' => $included,
            'trusted_task_values_in_final_population' => $final,
            'trusted_task_values_excluded' => $excluded,
            'trusted_task_values_status_excluded' => $statusExcluded,
            'trusted_task_values_deduped' => $deduped,
            'trusted_task_excluded_reasons' => $reasons,
        ];
    }

    private function metricKeys(array $options): array
    {
        $metric = $options['metric_key'] ?? $options['metric'] ?? null;
        if (is_string($metric) && trim($metric) !== '') {
            return [BenchmarkDefinitions::normalizeMetricKey(trim($metric))];
        }

        $category = $options['category'] ?? null;

        return collect($this->benchmarkLibrary->metricKeys())
            ->map(fn (string $key) => BenchmarkDefinitions::normalizeMetricKey($key))
            ->unique()
            ->filter(function (string $key) use ($category): bool {
                if (! is_string($category) || trim($category) === '') {
                    return true;
                }

                $metric = $this->benchmarkLibrary->metric($key);

                return strtolower((string) ($metric['category'] ?? '')) === strtolower(trim($category));
            })
            ->values()
            ->all();
    }

    private function bucketReports(string $metricKey, array $context, int $days, bool $mappingExists): array
    {
        if (! $mappingExists) {
            return [];
        }

        $reports = [];
        foreach ($this->benchmarkLibrary->populationBucketCandidates($context) as $candidate) {
            $level = (string) ($candidate['level'] ?? BenchmarkLibrary::BUCKET_EXACT_PEER);
            $bucketContext = ($candidate['context'] ?? []) + [
                '_bucket_level' => $level,
                'include_trusted_tasks' => $context['include_trusted_tasks'] ?? true,
            ];
            $audit = $this->populationMetricRepository->auditForMetric($metricKey, $bucketContext, $days);
            $count = (int) ($audit['final_population_values_count'] ?? $audit['final_values_count'] ?? count($audit['final_values'] ?? []));
            $reports[] = [
                'level' => $level,
                'bucket_key' => (string) ($candidate['bucket_key'] ?? $this->benchmarkLibrary->bucketKeyForLevel($context, $level)),
                'count' => $count,
                'usable' => $count >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
                'table_values_count' => (int) ($audit['table_values_count'] ?? 0),
                'trusted_task_values_count' => (int) ($audit['trusted_task_values_count'] ?? 0),
                'deduped_count' => (int) ($audit['deduped_count'] ?? 0),
                'final_population_values_count' => $count,
                'audit' => $audit,
            ];
        }

        return $reports;
    }

    private function selectedBucketReport(array $bucketReports): ?array
    {
        foreach ($bucketReports as $report) {
            if (($report['usable'] ?? false) === true) {
                return $report;
            }
        }

        return null;
    }

    private function lastBucketReport(array $bucketReports): ?array
    {
        return empty($bucketReports) ? null : $bucketReports[array_key_last($bucketReports)];
    }

    private function attemptedBuckets(array $bucketReports): array
    {
        return array_map(fn (array $report): array => [
            'level' => $report['level'] ?? 'unknown',
            'bucket_key' => $report['bucket_key'] ?? null,
            'count' => (int) ($report['count'] ?? 0),
            'usable' => (bool) ($report['usable'] ?? false),
            'table_values_count' => (int) ($report['table_values_count'] ?? 0),
            'trusted_task_values_count' => (int) ($report['trusted_task_values_count'] ?? 0),
            'deduped_count' => (int) ($report['deduped_count'] ?? 0),
        ], $bucketReports);
    }

    private function readiness(bool $researchExists, bool $mappingExists, int $finalCount, string $populationConfidence): string
    {
        if (! $mappingExists || ! $researchExists) {
            return 'not_ready';
        }

        if ($finalCount < PopulationPercentileEngine::MIN_LOW_CONFIDENCE) {
            return 'research_only';
        }

        if ($researchExists) {
            return 'composite_ready';
        }

        return match ($populationConfidence) {
            'high' => 'population_high',
            'medium' => 'population_medium',
            'low' => 'population_low',
            default => 'not_ready',
        };
    }

    private function qaFlags(array $audit, string $readiness, ?array $bucket, array $bucketReports, ?array $definition, bool $mappingExists): array
    {
        $flags = [];
        $raw = (int) ($audit['raw_values_found'] ?? 0);
        $excluded = (int) ($audit['values_excluded'] ?? $audit['excluded_count'] ?? 0);
        $finalCount = (int) ($audit['final_population_values_count'] ?? count($audit['final_values'] ?? []));
        $reasons = $audit['excluded_reason_counts'] ?? $audit['excluded_reasons'] ?? [];

        if (! $mappingExists) {
            $flags[] = 'missing_metric_mapping';
        }
        if (! is_array($definition)) {
            $flags[] = 'missing_research_benchmark';
        }
        if ($finalCount < PopulationPercentileEngine::MIN_LOW_CONFIDENCE) {
            $flags[] = 'low_sample_size';
        }
        if ($finalCount === 0) {
            $flags[] = 'no_population_values';
        }
        if ($raw > 0 && ($excluded / max(1, $raw)) > 0.40) {
            $flags[] = 'high_exclusion_rate';
        }
        if ($this->topReason($reasons) === 'zero_placeholder') {
            $flags[] = 'many_zero_placeholders';
        }
        if (($reasons['above_valid_range'] ?? 0) > 0 || ($reasons['below_valid_range'] ?? 0) > 0) {
            $flags[] = 'suspicious_outliers_removed';
        }
        if ((int) ($audit['trusted_task_values_count'] ?? 0) > 0) {
            $flags[] = 'trusted_task_payloads_present';
        }
        if ((int) ($audit['trusted_task_values_excluded'] ?? 0) > 0 || (int) ($audit['trusted_task_values_status_excluded'] ?? 0) > 0) {
            $flags[] = 'trusted_task_payloads_excluded';
        }
        if ($this->isGlobalOrBroadBucket($bucket)) {
            $flags[] = 'global_bucket_only';
        }
        if ($this->exactPeerBucketAvailable($bucketReports)) {
            $flags[] = 'exact_peer_bucket_available';
        }
        if ($readiness === 'research_only') {
            $flags[] = 'research_fallback_active';
        }
        if ($readiness === 'composite_ready') {
            $flags[] = 'population_blend_active';
        }

        return array_values(array_unique($flags));
    }

    private function metricRecommendedActions(string $metricKey, ?array $definition, array $audit, string $readiness, array $qaFlags, array $policy): array
    {
        $display = $definition['display_name'] ?? $this->humanMetric($metricKey);
        $actions = [];

        if (in_array('missing_metric_mapping', $qaFlags, true)) {
            $actions[] = 'Map '.$display.' to an existing FMTRX table or trusted task payload before enabling population learning.';
        }
        if (in_array('low_sample_size', $qaFlags, true)) {
            $actions[] = 'Collect more '.$display.' values before enabling FMTRX population percentile.';
        }
        if (in_array('many_zero_placeholders', $qaFlags, true)) {
            $actions[] = 'Review zero placeholders for '.$display.' collection.';
        }
        if (in_array('suspicious_outliers_removed', $qaFlags, true)) {
            $actions[] = 'Review outlier entries removed by guardrails for '.$display.'.';
        }
        if (in_array('global_bucket_only', $qaFlags, true)) {
            $actions[] = 'Improve player roster context to unlock exact peer buckets for '.$display.'.';
        }
        if (in_array('trusted_task_payloads_present', $qaFlags, true)) {
            $actions[] = 'Approved benchmark task payloads are contributing to '.$display.' population learning.';
        }
        if (in_array('trusted_task_payloads_excluded', $qaFlags, true)) {
            $actions[] = 'Review trusted task payload values rejected by guardrails for '.$display.'.';
        }
        if ($readiness === 'composite_ready') {
            if (($policy['composite_allowed'] ?? false) === true) {
                $actions[] = 'Population learning is active for '.$display.' with '.$this->populationConfidence((int) ($audit['final_population_values_count'] ?? 0)).' confidence.';
            } elseif (($policy['status'] ?? null) === PopulationLearningControl::STATUS_RESEARCH_ONLY) {
                $actions[] = $display.' has enough population data, but admin control is keeping it research-only.';
            } elseif (($policy['status'] ?? null) === PopulationLearningControl::STATUS_NEEDS_REVIEW) {
                $actions[] = $display.' has enough population data, but it is marked needs review before use.';
            } else {
                $actions[] = $display.' has enough population data, but control policy blocks blending: '.($policy['reason'] ?? 'policy unavailable');
            }
        }
        if (($policy['status'] ?? null) === PopulationLearningControl::STATUS_DISABLED) {
            $actions[] = $display.' is disabled for benchmark scoring by admin control.';
        }

        return array_values(array_unique($actions));
    }

    private function recommendedActions(array $reports): array
    {
        return collect($reports)
            ->flatMap(fn (array $report) => $report['recommended_actions'] ?? [])
            ->filter()
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    private function warnings(array $reports): array
    {
        $warnings = [];
        if (empty($reports)) {
            $warnings[] = 'No benchmark metrics were found for the selected audit options.';
        }
        if (collect($reports)->every(fn (array $report) => ($report['readiness'] ?? null) === 'research_only')) {
            $warnings[] = 'All audited metrics are currently research-only because FMTRX population samples are below the minimum threshold.';
        }
        if (collect($reports)->contains(fn (array $report) => in_array('missing_metric_mapping', $report['qa_flags'] ?? [], true))) {
            $warnings[] = 'Some benchmark metrics do not have population repository mappings yet.';
        }

        return $warnings;
    }

    private function evidence(?array $definition, bool $mappingExists, array $audit, ?array $bucket, array $qaFlags, array $sourceMix): array
    {
        $evidence = [
            'research_benchmark_exists' => is_array($definition),
            'population_mapping_exists' => $mappingExists,
            'selected_bucket_level' => $bucket['level'] ?? null,
            'selected_bucket_count' => $audit['final_population_values_count'] ?? count($audit['final_values'] ?? []),
            'source_mix_preview' => $sourceMix,
        ];

        if (in_array('research_fallback_active', $qaFlags, true)) {
            $evidence['fallback'] = 'Research benchmark remains active because final guarded FMTRX population sample is below 30.';
        }
        if (in_array('population_blend_active', $qaFlags, true)) {
            $evidence['blend'] = 'Composite benchmark can blend research and guarded FMTRX population data.';
        }

        return $evidence;
    }

    private function sourceMixPreview(bool $researchExists, int $finalCount, string $populationConfidence): array
    {
        $populationUsable = $finalCount >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE;
        $populationWeight = $populationUsable ? $this->populationWeight($populationConfidence) : 0.0;
        $researchWeight = $researchExists ? max(0.0, 1.0 - $populationWeight) : 0.0;

        return [
            'research_weight' => round($researchWeight, 2),
            'population_weight' => round($populationWeight, 2),
            'population_bucket_count' => $finalCount,
            'population_confidence' => $populationConfidence,
        ];
    }

    private function safeToUse(string $readiness, array $qaFlags): bool
    {
        return $readiness === 'composite_ready'
            && ! in_array('high_exclusion_rate', $qaFlags, true)
            && ! in_array('suspicious_outliers_removed', $qaFlags, true);
    }

    private function contextFromOptions(array $options, array $base = []): array
    {
        $context = $base;
        foreach ([
            'team_id',
            'teamId',
            'age_group',
            'level',
            'position',
            'bodyweight_band',
            'height_band',
            'throws',
            'bats',
        ] as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null && $options[$key] !== '') {
                $context[$key] = $options[$key];
            }
        }

        if (array_key_exists('include_trusted_tasks', $options)) {
            $context['include_trusted_tasks'] = (bool) $options['include_trusted_tasks'];
        } elseif (! array_key_exists('include_trusted_tasks', $context)) {
            $context['include_trusted_tasks'] = true;
        }

        return $context;
    }

    private function days(mixed $value): int
    {
        return max(1, min(3650, (int) $value));
    }

    private function populationConfidence(int $count, string $bucketLevel = ''): string
    {
        if ($count >= PopulationPercentileEngine::MIN_HIGH_CONFIDENCE) {
            return 'high';
        }
        if ($count >= PopulationPercentileEngine::MIN_MEDIUM_CONFIDENCE) {
            return 'medium';
        }
        if ($count >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE) {
            return 'low';
        }

        return 'insufficient';
    }

    private function populationWeight(string $confidence): float
    {
        return match ($confidence) {
            'high' => 0.70,
            'medium' => 0.50,
            'low' => 0.30,
            default => 0.0,
        };
    }

    private function isGlobalOrBroadBucket(?array $bucket): bool
    {
        if ($bucket === null) {
            return true;
        }

        $level = (string) ($bucket['level'] ?? '');
        $key = (string) ($bucket['bucket_key'] ?? '');

        return $level === BenchmarkLibrary::BUCKET_GLOBAL_CLEAN
            || ($level === BenchmarkLibrary::BUCKET_EXACT_PEER && str_contains($key, 'unknown'));
    }

    private function exactPeerBucketAvailable(array $bucketReports): bool
    {
        foreach ($bucketReports as $report) {
            if (($report['level'] ?? null) === BenchmarkLibrary::BUCKET_EXACT_PEER
                && (int) ($report['count'] ?? 0) >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE
                && ! str_contains((string) ($report['bucket_key'] ?? ''), 'unknown')) {
                return true;
            }
        }

        return false;
    }

    private function topReason(array $reasons): ?string
    {
        if (empty($reasons)) {
            return null;
        }

        arsort($reasons);

        return array_key_first($reasons);
    }

    private function mergeCounts(array ...$sets): array
    {
        $merged = [];

        foreach ($sets as $set) {
            foreach ($set as $key => $count) {
                $merged[(string) $key] = ($merged[(string) $key] ?? 0) + (int) $count;
            }
        }

        arsort($merged);

        return $merged;
    }

    private function emptyAudit(): array
    {
        return [
            'final_values' => [],
            'values' => [],
            'excluded_reason_counts' => [],
            'trusted_task_excluded_reasons' => [],
        ];
    }

    private function min(array $values): ?float
    {
        $values = $this->numericValues($values);

        return empty($values) ? null : min($values);
    }

    private function max(array $values): ?float
    {
        $values = $this->numericValues($values);

        return empty($values) ? null : max($values);
    }

    private function average(array $values): ?float
    {
        $values = $this->numericValues($values);

        return empty($values) ? null : round(array_sum($values) / count($values), 1);
    }

    private function numericValues(array $values): array
    {
        return array_values(array_filter(
            array_map(fn ($value) => is_numeric($value) ? (float) $value : null, $values),
            fn ($value) => $value !== null
        ));
    }

    private function humanMetric(string $metricKey): string
    {
        return ucwords(str_replace('_', ' ', $metricKey));
    }
}
