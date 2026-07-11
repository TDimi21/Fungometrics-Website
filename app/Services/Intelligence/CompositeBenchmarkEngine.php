<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PopulationLearningControl;

class CompositeBenchmarkEngine
{
    public function __construct(
        private readonly ResearchPercentileEngine $researchPercentileEngine,
        private readonly PopulationPercentileEngine $populationPercentileEngine,
        private readonly PopulationLearningControlService $populationLearningControlService,
    ) {}

    public function benchmarkMetric(string $metricKey, mixed $value, ?string $dob, array $context = [], array $populationValues = []): array
    {
        $research = $this->researchPercentileEngine->percentileForMetric($metricKey, $value, $dob, $context);
        $populationContext = $context + [
            'age_group' => $research['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
        ];
        $population = ! empty($populationValues)
            ? $this->populationPercentileEngine->percentileForMetric($metricKey, $value, $populationValues, $populationContext)
            : $this->populationPercentileEngine->percentileFromRepository(
                $metricKey,
                $value,
                $populationContext,
                (int) ($context['population_days'] ?? $context['days'] ?? 365),
            );

        $researchPercentile = is_numeric($research['percentile_estimate'] ?? null)
            ? (float) $research['percentile_estimate']
            : null;
        $populationUsable = ($population['usable'] ?? false) === true
            && ($population['bucket_count'] ?? 0) >= PopulationPercentileEngine::MIN_LOW_CONFIDENCE
            && is_numeric($population['percentile'] ?? null);
        $populationPercentile = $populationUsable
            ? (float) $population['percentile']
            : null;
        $policy = $this->populationLearningControlService->resolvePolicyForMetric($metricKey, [
            'population_confidence' => $population['confidence'] ?? 'insufficient',
            'selected_bucket_level' => $population['selected_bucket_level'] ?? null,
            'selected_bucket_key' => $population['selected_bucket_key'] ?? null,
            'bucket_count' => (int) ($population['bucket_count'] ?? 0),
            'final_population_values_count' => (int) ($population['final_population_values_count'] ?? $population['bucket_count'] ?? 0),
            'research_available' => $researchPercentile !== null,
        ]);

        if (($policy['status'] ?? null) === PopulationLearningControl::STATUS_DISABLED) {
            return $this->disabledResult($metricKey, $value, $research, $population, $policy);
        }

        if ($populationPercentile !== null && $this->isPopulationOnlyPolicy($policy)) {
            $score = (int) round(max(0, min(100, $populationPercentile)));
            $label = $this->labelFromPercentile($score);

            return [
                'metric_key' => BenchmarkDefinitions::normalizeMetricKey($metricKey),
                'age_group' => $research['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
                'raw_value' => $research['raw_value'] ?? (is_numeric($value) ? (float) $value : null),
                'unit' => $research['unit'] ?? null,
                'percentile_estimate' => $score,
                'score_0_100' => $score,
                'label' => $label,
                'benchmark_label' => $label,
                'gap_to_good' => $research['gap_to_good'] ?? null,
                'gap_to_elite' => $research['gap_to_elite'] ?? null,
                'confidence' => (string) ($population['confidence'] ?? 'low'),
                'source' => 'fmtrx_population',
                'composite_percentile' => $score,
                'research_percentile' => $research,
                'population_percentile' => $population,
                'source_mix' => $this->sourceMix(0.0, 1.0, $population),
                'population_policy' => $policy,
                'evidence' => [
                    'population_policy' => $policy,
                    'research' => $research,
                    'population' => $population,
                ],
            ];
        }

        if ($populationPercentile === null || ($policy['composite_allowed'] ?? false) !== true) {
            return array_merge($research, [
                'composite_percentile' => $researchPercentile,
                'research_percentile' => $research,
                'population_percentile' => $population,
                'source_mix' => $this->sourceMix(1.0, 0.0, $population),
                'population_policy' => $policy,
                'source' => $research['source'] ?? 'research_benchmark',
                'evidence' => array_merge(
                    $research['evidence'] ?? [],
                    [
                        'population' => $population['evidence'] ?? ['FMTRX population benchmark is not usable yet.'],
                        'population_policy' => $policy,
                    ],
                ),
            ]);
        }

        $populationWeight = $this->populationWeight((string) ($population['confidence'] ?? 'insufficient'));
        $researchWeight = $researchPercentile === null ? 0.0 : 1.0 - $populationWeight;

        $composite = $researchPercentile === null
            ? $populationPercentile
            : (($populationPercentile * $populationWeight) + ($researchPercentile * $researchWeight));
        $score = (int) round(max(0, min(100, $composite)));
        $label = $this->labelFromPercentile($score);

        return [
            'metric_key' => BenchmarkDefinitions::normalizeMetricKey($metricKey),
            'age_group' => $research['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
            'raw_value' => $research['raw_value'] ?? (is_numeric($value) ? (float) $value : null),
            'unit' => $research['unit'] ?? null,
            'percentile_estimate' => $score,
            'score_0_100' => $score,
            'label' => $label,
            'benchmark_label' => $label,
            'gap_to_good' => $research['gap_to_good'] ?? null,
            'gap_to_elite' => $research['gap_to_elite'] ?? null,
            'confidence' => $this->confidence((string) ($research['confidence'] ?? 'low'), (string) ($population['confidence'] ?? 'insufficient')),
            'source' => 'composite',
            'composite_percentile' => $score,
            'research_percentile' => $research,
            'population_percentile' => $population,
            'source_mix' => $this->sourceMix($researchWeight, $populationWeight, $population),
            'population_policy' => $policy,
            'evidence' => [
                'blend' => $this->sourceMix($researchWeight, $populationWeight, $population),
                'population_policy' => $policy,
                'research' => $research,
                'population' => $population,
            ],
        ];
    }

    private function disabledResult(string $metricKey, mixed $value, array $research, array $population, array $policy): array
    {
        return [
            'metric_key' => BenchmarkDefinitions::normalizeMetricKey($metricKey),
            'age_group' => $research['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
            'raw_value' => $research['raw_value'] ?? (is_numeric($value) ? (float) $value : null),
            'unit' => $research['unit'] ?? null,
            'percentile_estimate' => null,
            'score_0_100' => null,
            'label' => 'disabled',
            'benchmark_label' => 'disabled',
            'gap_to_good' => null,
            'gap_to_elite' => null,
            'confidence' => 'low',
            'source' => 'disabled',
            'composite_percentile' => null,
            'research_percentile' => $research,
            'population_percentile' => $population,
            'source_mix' => $this->sourceMix(0.0, 0.0, $population),
            'population_policy' => $policy,
            'evidence' => [
                'reason' => $policy['reason'] ?? 'Metric is disabled by admin control.',
                'population_policy' => $policy,
                'research' => $research,
                'population' => $population,
            ],
        ];
    }

    private function isPopulationOnlyPolicy(array $policy): bool
    {
        return ($policy['status'] ?? null) === PopulationLearningControl::STATUS_POPULATION_ENABLED
            && ($policy['population_allowed'] ?? false) === true
            && ($policy['composite_allowed'] ?? false) !== true;
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

    private function sourceMix(float $researchWeight, float $populationWeight, array $population): array
    {
        return [
            'research_weight' => round($researchWeight, 2),
            'population_weight' => round($populationWeight, 2),
            'population_bucket_count' => (int) ($population['bucket_count'] ?? 0),
            'population_confidence' => $population['confidence'] ?? 'insufficient',
            'population_usable' => ($population['usable'] ?? false) === true,
            'selected_bucket_key' => $population['selected_bucket_key'] ?? $population['bucket_key'] ?? null,
            'selected_bucket_level' => $population['selected_bucket_level'] ?? null,
            'attempted_bucket_count' => is_array($population['attempted_buckets'] ?? null) ? count($population['attempted_buckets']) : 0,
            'table_values_count' => (int) ($population['table_values_count'] ?? 0),
            'trusted_task_values_count' => (int) ($population['trusted_task_values_count'] ?? 0),
            'deduped_count' => (int) ($population['deduped_count'] ?? 0),
            'final_population_values_count' => (int) ($population['final_population_values_count'] ?? $population['bucket_count'] ?? 0),
        ];
    }

    private function confidence(string $research, string $population): string
    {
        if ($population === 'high' || ($population === 'medium' && $research === 'high')) {
            return 'high';
        }

        if (in_array($population, ['medium', 'low'], true) || $research === 'medium') {
            return 'medium';
        }

        return 'low';
    }

    private function labelFromPercentile(int $percentile): string
    {
        return match (true) {
            $percentile >= 95 => 'elite',
            $percentile >= 75 => 'good',
            $percentile >= 50 => 'average',
            $percentile >= 25 => 'below_average',
            default => 'critical',
        };
    }
}
