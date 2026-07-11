<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PopulationLearningControl;

class MetricTrustRolloutService
{
    public const PROFILE_INITIAL_SAFE = 'initial_safe_rollout';
    public const PROFILE_EVIDENCE_ONLY = 'evidence_only_rollout';
    public const PROFILE_AGGRESSIVE_TEST = 'aggressive_test_rollout';

    private const EV_METRICS = [
        'average_exit_velocity',
        'max_exit_velocity',
    ];

    private const HITTING_RATE_METRICS = [
        'hard_hit_percentage',
        'line_drive_percentage',
        'hitter_swing_miss_percentage',
    ];

    private const PITCHING_METRICS = [
        'average_fastball_velocity',
        'max_fastball_velocity',
        'strike_percentage',
    ];

    private const THROWING_DEVELOPMENT_METRICS = [
        'long_toss_max_distance',
        'weighted_ball_5oz_velocity',
    ];

    private const STRENGTH_RESEARCH_ONLY_METRICS = [
        'bench_press',
        'squat',
        'deadlift',
    ];

    private const STRENGTH_CONDITIONAL_METRICS = [
        'pull_ups',
        'pushups',
    ];

    private const ATHLETIC_METRICS = [
        'forty_yard_dash',
        'sixty_yard_dash',
        'broad_jump',
        'vertical_jump',
    ];

    private const MOBILITY_METRICS = [
        'mobility_score',
        'shoulder_mobility_score',
        'hip_mobility_score',
        't_spine_mobility_score',
    ];

    public function __construct(
        private readonly BenchmarkLibrary $benchmarkLibrary,
        private readonly PopulationLearningAuditService $auditService,
        private readonly PopulationLearningControlService $controlService,
    ) {
    }

    public function previewRollout(array $options = []): array
    {
        $profile = $this->profile($options['profile'] ?? self::PROFILE_INITIAL_SAFE);
        $days = $this->days($options['days'] ?? 365);
        $report = $this->auditService->buildAuditReport([
            'metric_key' => $options['metric_key'] ?? $options['metric'] ?? null,
            'category' => $options['category'] ?? null,
            'days' => $days,
        ]);

        $policies = [];
        foreach ($report['metrics'] ?? [] as $metricAudit) {
            $policies[] = $this->policyForMetric((string) ($metricAudit['metric_key'] ?? ''), $metricAudit, [
                'profile' => $profile,
                'preserve_notes' => (bool) ($options['preserve_notes'] ?? false),
            ]);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => $profile,
            'mode' => 'preview',
            'days' => $days,
            'metrics' => $policies,
            'summary' => $this->buildRolloutSummary($policies),
            'audit_summary' => [
                'readiness_summary' => $report['readiness_summary'] ?? [],
                'guardrail_summary' => $report['guardrail_summary'] ?? [],
                'bucket_quality_summary' => $report['bucket_quality_summary'] ?? [],
            ],
            'warnings' => $this->warnings($profile, $policies, $report['warnings'] ?? []),
        ];
    }

    public function applyRollout(array $options = []): array
    {
        $preview = $this->previewRollout($options);
        $applied = [];

        foreach ($preview['metrics'] as $policy) {
            if (($policy['will_update'] ?? false) !== true) {
                $applied[] = $policy + [
                    'applied' => false,
                    'applied_control' => null,
                ];

                continue;
            }

            $control = $this->controlService->applyMetricPolicy(
                (string) $policy['metric_key'],
                $policy + [
                    'preserve_notes' => (bool) ($options['preserve_notes'] ?? false),
                ],
                null,
            );
            $applied[] = $policy + [
                'applied' => true,
                'applied_control' => $control,
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'profile' => $preview['profile'],
            'mode' => 'apply',
            'days' => $preview['days'],
            'metrics' => $applied,
            'summary' => $this->buildRolloutSummary($applied),
            'audit_summary' => $preview['audit_summary'],
            'warnings' => $preview['warnings'],
        ];
    }

    public function policyForMetric(string $metricKey, array $audit = [], array $options = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $profile = $this->profile($options['profile'] ?? self::PROFILE_INITIAL_SAFE);
        $definition = $this->benchmarkLibrary->metric($metricKey);
        $current = $this->controlService->getControlForMetric($metricKey);
        $bucketCount = (int) ($audit['final_population_values_count'] ?? $audit['bucket_count'] ?? 0);
        $qaFlags = array_values(array_unique(array_map('strval', $audit['qa_flags'] ?? [])));
        $safeComposite = $this->safeToEnableComposite($audit, $definition, $bucketCount, $qaFlags);
        $policy = $this->basePolicy($metricKey, $definition, $audit, $current, $profile, $bucketCount, $qaFlags);

        if ($this->shouldDisable($definition, $bucketCount, $qaFlags)) {
            $policy = $this->disabledPolicy($policy, 'Metric is disabled because it has no usable benchmark definition, mapping, or population values.');
        } elseif ($profile === self::PROFILE_EVIDENCE_ONLY) {
            $policy = $this->evidenceOnlyPolicy($policy, $qaFlags);
        } elseif ($profile === self::PROFILE_AGGRESSIVE_TEST) {
            $policy = $this->aggressivePolicy($policy, $safeComposite, $qaFlags);
        } else {
            $policy = $this->initialSafePolicy($policy, $safeComposite, $qaFlags);
        }

        $policy['will_update'] = $this->willUpdate($current, $policy);

        return $policy;
    }

    public function buildRolloutSummary(array $results): array
    {
        $statusCounts = [];
        $updates = 0;
        $unchanged = 0;

        foreach ($results as $result) {
            $status = (string) ($result['recommended_status'] ?? PopulationLearningControl::STATUS_AUTO);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            if (($result['will_update'] ?? false) === true) {
                $updates++;
            } else {
                $unchanged++;
            }
        }

        return [
            'metrics_reviewed' => count($results),
            'metrics_to_update' => $updates,
            'metrics_unchanged' => $unchanged,
            'metrics_set_composite_enabled' => $statusCounts[PopulationLearningControl::STATUS_COMPOSITE_ENABLED] ?? 0,
            'metrics_set_research_only' => $statusCounts[PopulationLearningControl::STATUS_RESEARCH_ONLY] ?? 0,
            'metrics_set_needs_review' => $statusCounts[PopulationLearningControl::STATUS_NEEDS_REVIEW] ?? 0,
            'metrics_disabled' => $statusCounts[PopulationLearningControl::STATUS_DISABLED] ?? 0,
            'status_counts' => $statusCounts,
        ];
    }

    private function initialSafePolicy(array $policy, bool $safeComposite, array $qaFlags): array
    {
        $metricKey = (string) $policy['metric_key'];

        if (in_array($metricKey, self::EV_METRICS, true)) {
            return $safeComposite
                ? $this->compositePolicy($policy, 'Enabled for low-confidence composite. Research remains majority weight until population confidence improves.')
                : $this->researchOnlyPolicy($policy, 'EV metric stays research-only until the guarded population sample reaches 30 and passes quality checks.');
        }

        if (in_array($metricKey, self::HITTING_RATE_METRICS, true)) {
            return $safeComposite
                ? $this->compositePolicy($policy, 'Hitting quality metric has enough clean data for cautious composite blending.')
                : $this->researchOnlyPolicy($policy, 'Hitting quality metric remains research-only until sample and context improve.');
        }

        if (in_array($metricKey, self::PITCHING_METRICS, true)) {
            if ($safeComposite && ! in_array('global_bucket_only', $qaFlags, true)) {
                return $this->compositePolicy($policy, 'Pitching metric has enough clean peer-context data for cautious composite blending.');
            }

            return in_array('global_bucket_only', $qaFlags, true) || in_array('high_exclusion_rate', $qaFlags, true)
                ? $this->needsReviewPolicy($policy, 'Pitching metric needs review because sample context or guardrail quality is weak.')
                : $this->researchOnlyPolicy($policy, 'Pitching metric remains research-only until population sample quality improves.');
        }

        if (in_array($metricKey, self::THROWING_DEVELOPMENT_METRICS, true)) {
            return $safeComposite && ! in_array('global_bucket_only', $qaFlags, true)
                ? $this->compositePolicy($policy, 'Throwing development metric has enough clean contextual data for composite blending.')
                : $this->needsReviewPolicy($policy, 'Throwing development metric needs review until clean throwing-specific data exists.');
        }

        if (in_array($metricKey, self::STRENGTH_RESEARCH_ONLY_METRICS, true)) {
            return $this->researchOnlyPolicy($policy, 'Strength sample is not large enough or clean enough for population blending.');
        }

        if (in_array($metricKey, self::STRENGTH_CONDITIONAL_METRICS, true)) {
            return $safeComposite
                ? $this->compositePolicy($policy, 'Relative strength metric has enough clean data for cautious composite blending.')
                : $this->researchOnlyPolicy($policy, 'Relative strength metric remains research-only until sample improves.');
        }

        if (in_array($metricKey, self::ATHLETIC_METRICS, true)) {
            return in_array('high_exclusion_rate', $qaFlags, true) || in_array('missing_metric_mapping', $qaFlags, true)
                ? $this->needsReviewPolicy($policy, 'Athletic metric needs review because guardrails or mapping quality is weak.')
                : $this->researchOnlyPolicy($policy, 'Athletic metric remains research-only during the initial trust rollout.');
        }

        if (in_array($metricKey, self::MOBILITY_METRICS, true)) {
            return $this->needsReviewPolicy($policy, 'Mobility metric needs review before population blending is trusted.');
        }

        return $safeComposite
            ? $this->compositePolicy($policy, 'Metric has enough guarded data for cautious composite blending.')
            : $this->researchOnlyPolicy($policy, 'Metric remains research-only until population trust improves.');
    }

    private function evidenceOnlyPolicy(array $policy, array $qaFlags): array
    {
        if ($this->shouldNeedsReview($qaFlags)) {
            return $this->needsReviewPolicy($policy, 'Population data can appear as evidence, but this metric needs review before scoring use.');
        }

        return $this->researchOnlyPolicy($policy, 'Population data can appear as evidence only. Final score remains research-only.');
    }

    private function aggressivePolicy(array $policy, bool $safeComposite, array $qaFlags): array
    {
        if ($safeComposite || ((int) $policy['bucket_count'] >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE && ! $this->shouldNeedsReview($qaFlags))) {
            return $this->compositePolicy($policy, 'Aggressive test rollout allows composite when sample is at least 30. Dev/test only.');
        }

        return $this->initialSafePolicy($policy, $safeComposite, $qaFlags);
    }

    private function basePolicy(string $metricKey, ?array $definition, array $audit, array $current, string $profile, int $bucketCount, array $qaFlags): array
    {
        return [
            'metric_key' => $metricKey,
            'display_name' => $definition['display_name'] ?? $this->humanMetric($metricKey),
            'category' => $definition['category'] ?? BenchmarkDefinitions::categoryForMetric($metricKey) ?? 'unknown',
            'recommended_status' => PopulationLearningControl::STATUS_RESEARCH_ONLY,
            'population_enabled' => false,
            'composite_enabled' => false,
            'research_enabled' => true,
            'minimum_sample_size' => PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            'minimum_confidence' => null,
            'allow_global_bucket' => true,
            'allow_exact_peer_bucket' => true,
            'allow_age_bucket' => true,
            'reason' => 'Metric remains on research fallback during initial rollout.',
            'audit_readiness' => (string) ($audit['readiness'] ?? 'not_ready'),
            'bucket_count' => $bucketCount,
            'qa_flags' => $qaFlags,
            'admin_notes' => 'Research fallback remains active for every metric.',
            'will_update' => false,
            'current_status' => $current['exists'] ?? false ? (string) ($current['status'] ?? PopulationLearningControl::STATUS_AUTO) : null,
            'current_control_exists' => (bool) ($current['exists'] ?? false),
            'rollout_profile' => $profile,
            'last_audit_summary' => $this->auditSummary($audit),
        ];
    }

    private function compositePolicy(array $policy, string $reason): array
    {
        return array_merge($policy, [
            'recommended_status' => PopulationLearningControl::STATUS_COMPOSITE_ENABLED,
            'population_enabled' => true,
            'composite_enabled' => true,
            'research_enabled' => true,
            'minimum_sample_size' => PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            'minimum_confidence' => 'low',
            'allow_global_bucket' => true,
            'reason' => $reason,
            'admin_notes' => $reason,
        ]);
    }

    private function researchOnlyPolicy(array $policy, string $reason): array
    {
        return array_merge($policy, [
            'recommended_status' => PopulationLearningControl::STATUS_RESEARCH_ONLY,
            'population_enabled' => false,
            'composite_enabled' => false,
            'research_enabled' => true,
            'minimum_confidence' => null,
            'reason' => $reason,
            'admin_notes' => $reason,
        ]);
    }

    private function needsReviewPolicy(array $policy, string $reason): array
    {
        return array_merge($policy, [
            'recommended_status' => PopulationLearningControl::STATUS_NEEDS_REVIEW,
            'population_enabled' => false,
            'composite_enabled' => false,
            'research_enabled' => true,
            'minimum_confidence' => null,
            'reason' => $reason,
            'admin_notes' => $reason,
        ]);
    }

    private function disabledPolicy(array $policy, string $reason): array
    {
        return array_merge($policy, [
            'recommended_status' => PopulationLearningControl::STATUS_DISABLED,
            'population_enabled' => false,
            'composite_enabled' => false,
            'research_enabled' => false,
            'minimum_confidence' => null,
            'reason' => $reason,
            'admin_notes' => $reason,
        ]);
    }

    private function safeToEnableComposite(array $audit, ?array $definition, int $bucketCount, array $qaFlags): bool
    {
        return is_array($definition)
            && $bucketCount >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE
            && ! $this->shouldNeedsReview($qaFlags)
            && ! in_array('missing_research_benchmark', $qaFlags, true)
            && ! in_array('missing_metric_mapping', $qaFlags, true)
            && ! in_array('no_population_values', $qaFlags, true)
            && ($audit['population_usable'] ?? false) === true;
    }

    private function shouldNeedsReview(array $qaFlags): bool
    {
        return in_array('high_exclusion_rate', $qaFlags, true)
            || in_array('suspicious_outliers_removed', $qaFlags, true)
            || in_array('trusted_task_payloads_excluded', $qaFlags, true);
    }

    private function shouldDisable(?array $definition, int $bucketCount, array $qaFlags): bool
    {
        if (in_array('missing_metric_mapping', $qaFlags, true)) {
            return true;
        }

        if (! is_array($definition) && ($bucketCount === 0 || in_array('no_population_values', $qaFlags, true))) {
            return true;
        }

        return false;
    }

    private function willUpdate(array $current, array $policy): bool
    {
        if (($current['exists'] ?? false) !== true) {
            return true;
        }

        $checks = [
            'status' => 'recommended_status',
            'population_enabled' => 'population_enabled',
            'research_enabled' => 'research_enabled',
            'composite_enabled' => 'composite_enabled',
            'minimum_sample_size' => 'minimum_sample_size',
            'minimum_confidence' => 'minimum_confidence',
            'allow_global_bucket' => 'allow_global_bucket',
            'allow_exact_peer_bucket' => 'allow_exact_peer_bucket',
            'allow_age_bucket' => 'allow_age_bucket',
        ];

        foreach ($checks as $currentKey => $policyKey) {
            $currentValue = $current[$currentKey] ?? null;
            $policyValue = $policy[$policyKey] ?? null;
            if ($currentValue != $policyValue) {
                return true;
            }
        }

        return false;
    }

    private function auditSummary(array $audit): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'readiness' => $audit['readiness'] ?? null,
            'population_confidence' => $audit['population_confidence'] ?? null,
            'bucket_count' => $audit['bucket_count'] ?? null,
            'raw_values_found' => $audit['raw_values_found'] ?? null,
            'guardrail_excluded_count' => $audit['guardrail_excluded_count'] ?? null,
            'qa_flags' => $audit['qa_flags'] ?? [],
        ];
    }

    private function warnings(string $profile, array $policies, array $auditWarnings): array
    {
        $warnings = $auditWarnings;
        if ($profile === self::PROFILE_AGGRESSIVE_TEST) {
            $warnings[] = 'Aggressive test rollout is for development/testing only.';
        }
        if (collect($policies)->contains(fn (array $policy) => ($policy['recommended_status'] ?? null) === PopulationLearningControl::STATUS_DISABLED)) {
            $warnings[] = 'One or more metrics are recommended disabled because benchmark mapping or source data is unusable.';
        }

        return array_values(array_unique(array_filter($warnings)));
    }

    private function profile(mixed $profile): string
    {
        $profile = is_string($profile) ? trim($profile) : self::PROFILE_INITIAL_SAFE;

        return in_array($profile, [
            self::PROFILE_INITIAL_SAFE,
            self::PROFILE_EVIDENCE_ONLY,
            self::PROFILE_AGGRESSIVE_TEST,
        ], true) ? $profile : self::PROFILE_INITIAL_SAFE;
    }

    private function days(mixed $days): int
    {
        return max(1, min(3650, (int) $days));
    }

    private function humanMetric(string $metricKey): string
    {
        return ucwords(str_replace('_', ' ', $metricKey));
    }
}
