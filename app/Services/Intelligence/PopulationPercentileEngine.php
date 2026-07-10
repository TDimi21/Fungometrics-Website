<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PopulationPercentileEngine
{
    public const MIN_LOW_CONFIDENCE = 30;
    public const MIN_MEDIUM_CONFIDENCE = 100;
    public const MIN_HIGH_CONFIDENCE = 300;

    public function __construct(
        private readonly BenchmarkLibrary $benchmarkLibrary,
        private readonly PopulationMetricRepository $populationMetricRepository,
        private readonly PopulationValueGuardrail $guardrail,
    ) {}

    public function canUsePopulationBucket(int $count): bool
    {
        return $count >= self::MIN_LOW_CONFIDENCE;
    }

    public function buildBucketKey(array $context): string
    {
        return $this->benchmarkLibrary->bucketKey($context);
    }

    public function percentileForMetric(string $metricKey, mixed $value, array $populationValues, array $context = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $bucketKey = $this->buildBucketKey($context);
        $clean = array_values(array_filter(
            array_map(function ($row) use ($metricKey) {
                $validation = $this->guardrail->validate($metricKey, $row);

                return ($validation['included'] ?? false) === true ? (float) $validation['value'] : null;
            }, $populationValues),
            fn ($row) => $row !== null
        ));
        $bucketCount = count($clean);
        $rawValidation = $this->guardrail->validate($metricKey, $value);
        $raw = ($rawValidation['included'] ?? false) === true ? (float) $rawValidation['value'] : null;

        $usable = $raw !== null && $this->canUsePopulationBucket($bucketCount);

        return [
            'metric_key' => $metricKey,
            'bucket_key' => $bucketKey,
            'bucket_count' => $bucketCount,
            'percentile' => $usable
                ? $this->percentile($metricKey, $raw, $clean)
                : null,
            'confidence' => $this->confidence($bucketCount),
            'source' => 'fmtrx_population',
            'usable' => $usable,
            'evidence' => $this->evidence($bucketCount, $raw, $rawValidation['reason'] ?? null),
        ];
    }

    public function percentileFromRepository(
        string $metricKey,
        mixed $value,
        array $context = [],
        int $days = 365
    ): array {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $days = max(1, $days);
        $populationValues = $this->populationMetricRepository->valuesForMetric($metricKey, $context, $days);
        $result = $this->percentileForMetric($metricKey, $value, $populationValues, $context);

        return $result + [
            'days' => $days,
        ];
    }

    private function percentile(string $metricKey, float $value, array $populationValues): int
    {
        $less = 0;
        $equal = 0;

        foreach ($populationValues as $populationValue) {
            if ($populationValue < $value) {
                $less++;
            } elseif ($populationValue === $value) {
                $equal++;
            }
        }

        $percentile = (($less + (0.5 * $equal)) / max(1, count($populationValues))) * 100;

        if (! $this->higherIsBetter($metricKey)) {
            $percentile = 100 - $percentile;
        }

        return (int) round(max(0, min(100, $percentile)));
    }

    private function confidence(int $count): string
    {
        return match (true) {
            $count >= self::MIN_HIGH_CONFIDENCE => 'high',
            $count >= self::MIN_MEDIUM_CONFIDENCE => 'medium',
            $count >= self::MIN_LOW_CONFIDENCE => 'low',
            default => 'insufficient',
        };
    }

    private function evidence(int $count, ?float $raw, ?string $rawReason = null): array
    {
        if ($raw === null) {
            return [$rawReason
                ? 'Player value is not valid for FMTRX population comparison: '.$rawReason.'.'
                : 'No player value was provided for FMTRX population comparison.'
            ];
        }

        if (! $this->canUsePopulationBucket($count)) {
            return ['FMTRX population sample is not large enough yet. Minimum is 30.'];
        }

        return ['FMTRX population bucket has '.$count.' rows.'];
    }

    private function higherIsBetter(string $metricKey): bool
    {
        $definition = $this->benchmarkLibrary->metric($metricKey)
            ?? BenchmarkDefinitions::metricDefinition($metricKey);

        return (bool) ($definition['higher_is_better'] ?? true);
    }
}
