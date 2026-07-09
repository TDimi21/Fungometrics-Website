<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class CompositeBenchmarkEngine
{
    public function __construct(
        private readonly ResearchPercentileEngine $researchPercentileEngine,
        private readonly PopulationPercentileEngine $populationPercentileEngine,
    ) {}

    public function benchmarkMetric(string $metricKey, mixed $value, ?string $dob, array $context = [], array $populationValues = []): array
    {
        $research = $this->researchPercentileEngine->percentileForMetric($metricKey, $value, $dob, $context);
        $population = $this->populationPercentileEngine->percentileForMetric($metricKey, $value, $populationValues, $context + [
            'age_group' => $research['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
        ]);

        $researchPercentile = is_numeric($research['percentile_estimate'] ?? null)
            ? (float) $research['percentile_estimate']
            : null;
        $populationPercentile = is_numeric($population['percentile'] ?? null)
            ? (float) $population['percentile']
            : null;

        if ($populationPercentile === null) {
            return $research + [
                'composite_percentile' => $researchPercentile,
                'research_percentile' => $research,
                'population_percentile' => $population,
                'source' => $research['source'] ?? 'research_benchmark',
            ];
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
            'source' => 'composite_benchmark',
            'composite_percentile' => $score,
            'research_percentile' => $research,
            'population_percentile' => $population,
            'evidence' => [
                'blend' => [
                    'research_weight' => round($researchWeight, 2),
                    'population_weight' => round($populationWeight, 2),
                    'population_bucket_count' => $population['bucket_count'] ?? 0,
                ],
                'research' => $research,
                'population' => $population,
            ],
        ];
    }

    private function populationWeight(string $confidence): float
    {
        return match ($confidence) {
            'high' => 0.75,
            'medium' => 0.55,
            'low' => 0.30,
            default => 0.0,
        };
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
