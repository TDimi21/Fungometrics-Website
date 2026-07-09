<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PopulationPercentileEngine
{
    public const MIN_LOW_CONFIDENCE = 30;
    public const MIN_MEDIUM_CONFIDENCE = 100;
    public const MIN_HIGH_CONFIDENCE = 300;

    public function canUsePopulationBucket(int $count): bool
    {
        return $count >= self::MIN_LOW_CONFIDENCE;
    }

    public function buildBucketKey(array $context): string
    {
        $parts = [
            'age:' . ($context['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN),
            'level:' . ($context['level'] ?? 'unknown'),
            'position:' . $this->normalizeList($context['position'] ?? $context['positions'] ?? 'unknown'),
            'throw:' . ($context['throw_side'] ?? 'unknown'),
            'hit:' . ($context['hit_side'] ?? 'unknown'),
            'body:' . $this->bodyWeightBand($context['body_weight'] ?? null),
        ];

        return implode('|', array_map(fn ($part) => strtolower((string) $part), $parts));
    }

    public function percentileForMetric(string $metricKey, mixed $value, array $populationValues, array $context = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $bucketKey = $this->buildBucketKey($context);
        $clean = array_values(array_filter(
            array_map(fn ($row) => is_numeric($row) ? (float) $row : null, $populationValues),
            fn ($row) => $row !== null
        ));
        $bucketCount = count($clean);
        $raw = is_numeric($value) ? (float) $value : null;

        return [
            'metric_key' => $metricKey,
            'bucket_key' => $bucketKey,
            'bucket_count' => $bucketCount,
            'percentile' => $raw !== null && $this->canUsePopulationBucket($bucketCount)
                ? $this->percentile($metricKey, $raw, $clean)
                : null,
            'confidence' => $this->confidence($bucketCount),
            'source' => 'fmtrx_population',
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

    private function higherIsBetter(string $metricKey): bool
    {
        $definition = BenchmarkDefinitions::metricDefinition($metricKey);

        return (bool) ($definition['higher_is_better'] ?? true);
    }

    private function normalizeList(mixed $value): string
    {
        if (is_array($value)) {
            $value = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $value)));

            return count($value) ? implode(',', $value) : 'unknown';
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : 'unknown';
    }

    private function bodyWeightBand(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return 'unknown';
        }

        $weight = (float) $value;

        return match (true) {
            $weight < 120 => 'under_120',
            $weight < 150 => '120_149',
            $weight < 180 => '150_179',
            $weight < 210 => '180_209',
            default => '210_plus',
        };
    }
}
