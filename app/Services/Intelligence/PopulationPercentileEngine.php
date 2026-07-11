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
        $bucketLevel = (string) ($context['_bucket_level'] ?? BenchmarkLibrary::BUCKET_EXACT_PEER);
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
        $percentile = $usable ? $this->percentile($metricKey, $raw, $clean) : null;
        $attemptedBuckets = [[
            'level' => $bucketLevel,
            'bucket_key' => $bucketKey,
            'count' => $bucketCount,
            'usable' => $usable,
            'table_values_count' => $bucketCount,
            'trusted_task_values_count' => 0,
            'deduped_count' => 0,
            'final_population_values_count' => $bucketCount,
        ]];

        return [
            'metric_key' => $metricKey,
            'selected_bucket_key' => $usable ? $bucketKey : null,
            'selected_bucket_level' => $usable ? $bucketLevel : 'none',
            'bucket_key' => $bucketKey,
            'bucket_count' => $bucketCount,
            'percentile' => $percentile,
            'confidence' => $this->confidence($bucketCount, $bucketLevel),
            'source' => 'fmtrx_population',
            'usable' => $usable,
            'table_values_count' => $bucketCount,
            'trusted_task_values_count' => 0,
            'deduped_count' => 0,
            'final_population_values_count' => $bucketCount,
            'attempted_buckets' => $attemptedBuckets,
            'evidence' => $this->evidence($bucketCount, $raw, $rawValidation['reason'] ?? null, $usable ? $bucketLevel : null),
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
        $rawValidation = $this->guardrail->validate($metricKey, $value);
        $raw = ($rawValidation['included'] ?? false) === true ? (float) $rawValidation['value'] : null;
        $attemptedBuckets = [];
        $selected = null;

        foreach ($this->benchmarkLibrary->populationBucketCandidates($context) as $candidate) {
            $bucketContext = ($candidate['context'] ?? []) + [
                '_bucket_level' => $candidate['level'] ?? BenchmarkLibrary::BUCKET_EXACT_PEER,
            ];
            $audit = $this->populationMetricRepository->auditForMetric($metricKey, $bucketContext, $days);
            $values = array_values(array_filter(
                $audit['final_values'] ?? $audit['values'] ?? [],
                fn ($row) => is_numeric($row)
            ));
            $count = count($values);
            $usable = $raw !== null && $this->canUsePopulationBucket($count);
            $attempt = [
                'level' => (string) ($candidate['level'] ?? 'unknown'),
                'bucket_key' => (string) ($candidate['bucket_key'] ?? $this->benchmarkLibrary->bucketKeyForLevel($context, (string) ($candidate['level'] ?? BenchmarkLibrary::BUCKET_EXACT_PEER))),
                'count' => $count,
                'usable' => $usable,
                'table_values_count' => (int) ($audit['table_values_count'] ?? 0),
                'trusted_task_values_count' => (int) ($audit['trusted_task_values_count'] ?? 0),
                'deduped_count' => (int) ($audit['deduped_count'] ?? 0),
                'final_population_values_count' => (int) ($audit['final_population_values_count'] ?? $count),
                'trusted_task_excluded_reasons' => $audit['trusted_task_excluded_reasons'] ?? [],
                'trusted_tasks_included' => (bool) ($audit['trusted_tasks_included'] ?? false),
            ];
            $attemptedBuckets[] = $attempt;

            if ($usable && $selected === null) {
                $selected = $attempt + [
                    'values' => array_map('floatval', $values),
                ];
            }
        }

        if ($selected === null) {
            $lastAttempt = ! empty($attemptedBuckets) ? $attemptedBuckets[array_key_last($attemptedBuckets)] : null;

            return [
                'metric_key' => $metricKey,
                'selected_bucket_key' => null,
                'selected_bucket_level' => 'none',
                'bucket_key' => null,
                'bucket_count' => (int) ($lastAttempt['count'] ?? 0),
                'percentile' => null,
                'confidence' => 'insufficient',
                'source' => 'fmtrx_population',
                'usable' => false,
                'table_values_count' => (int) ($lastAttempt['table_values_count'] ?? 0),
                'trusted_task_values_count' => (int) ($lastAttempt['trusted_task_values_count'] ?? 0),
                'deduped_count' => (int) ($lastAttempt['deduped_count'] ?? 0),
                'final_population_values_count' => (int) ($lastAttempt['final_population_values_count'] ?? $lastAttempt['count'] ?? 0),
                'trusted_task_excluded_reasons' => $lastAttempt['trusted_task_excluded_reasons'] ?? [],
                'attempted_buckets' => $attemptedBuckets,
                'evidence' => $this->evidence((int) ($lastAttempt['count'] ?? 0), $raw, $rawValidation['reason'] ?? null, null, $attemptedBuckets),
                'days' => $days,
            ];
        }

        $result = [
            'metric_key' => $metricKey,
            'selected_bucket_key' => $selected['bucket_key'],
            'selected_bucket_level' => $selected['level'],
            'bucket_key' => $selected['bucket_key'],
            'bucket_count' => (int) $selected['count'],
            'percentile' => $this->percentile($metricKey, (float) $raw, $selected['values']),
            'confidence' => $this->confidence((int) $selected['count'], (string) $selected['level']),
            'source' => 'fmtrx_population',
            'usable' => true,
            'table_values_count' => (int) ($selected['table_values_count'] ?? 0),
            'trusted_task_values_count' => (int) ($selected['trusted_task_values_count'] ?? 0),
            'deduped_count' => (int) ($selected['deduped_count'] ?? 0),
            'final_population_values_count' => (int) ($selected['final_population_values_count'] ?? $selected['count']),
            'trusted_task_excluded_reasons' => $selected['trusted_task_excluded_reasons'] ?? [],
            'attempted_buckets' => $attemptedBuckets,
            'evidence' => $this->evidence((int) $selected['count'], $raw, null, (string) $selected['level'], $attemptedBuckets),
        ];

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

    private function confidence(int $count, string $bucketLevel = ''): string
    {
        if ($bucketLevel === BenchmarkLibrary::BUCKET_GLOBAL_CLEAN) {
            return $count >= self::MIN_HIGH_CONFIDENCE ? 'high' : ($count >= self::MIN_LOW_CONFIDENCE ? 'low' : 'insufficient');
        }

        return match (true) {
            $count >= self::MIN_HIGH_CONFIDENCE => 'high',
            $count >= self::MIN_MEDIUM_CONFIDENCE => 'medium',
            $count >= self::MIN_LOW_CONFIDENCE => 'low',
            default => 'insufficient',
        };
    }

    private function evidence(int $count, ?float $raw, ?string $rawReason = null, ?string $selectedBucketLevel = null, array $attemptedBuckets = []): array
    {
        if ($raw === null) {
            return [$rawReason
                ? 'Player value is not valid for FMTRX population comparison: '.$rawReason.'.'
                : 'No player value was provided for FMTRX population comparison.'
            ];
        }

        if (! $this->canUsePopulationBucket($count)) {
            $message = 'No FMTRX population bucket reached 30 guarded values. Research benchmark remains active.';

            if (! empty($attemptedBuckets)) {
                $message .= ' Attempted buckets: '.$this->attemptSummary($attemptedBuckets).'.';
            }

            return array_merge([$message], $this->trustedTaskEvidence($attemptedBuckets));
        }

        $label = $selectedBucketLevel ?: 'selected';
        $messages = ['FMTRX population '.$label.' bucket selected with '.$count.' guarded values.'];

        return array_merge($messages, $this->trustedTaskEvidence($attemptedBuckets));
    }

    private function higherIsBetter(string $metricKey): bool
    {
        $definition = $this->benchmarkLibrary->metric($metricKey)
            ?? BenchmarkDefinitions::metricDefinition($metricKey);

        return (bool) ($definition['higher_is_better'] ?? true);
    }

    private function attemptSummary(array $attemptedBuckets): string
    {
        return implode(', ', array_map(
            fn (array $attempt) => ($attempt['level'] ?? 'unknown').': '.(int) ($attempt['count'] ?? 0),
            $attemptedBuckets
        ));
    }

    private function trustedTaskEvidence(array $attemptedBuckets): array
    {
        $trustedCount = 0;
        $dedupedCount = 0;

        foreach ($attemptedBuckets as $attempt) {
            $trustedCount = max($trustedCount, (int) ($attempt['trusted_task_values_count'] ?? 0));
            $dedupedCount = max($dedupedCount, (int) ($attempt['deduped_count'] ?? 0));
        }

        $messages = [];
        if ($trustedCount > 0) {
            $messages[] = 'FMTRX population bucket includes '.$trustedCount.' approved benchmark task payload value'.($trustedCount === 1 ? '' : 's').'.';
        }

        if ($dedupedCount > 0) {
            $messages[] = $dedupedCount.' trusted benchmark task payload value'.($dedupedCount === 1 ? ' was' : 's were').' deduped behind existing table values.';
        }

        if ($trustedCount > 0 || $dedupedCount > 0) {
            $messages[] = 'Draft, pending review, rejected, and unapproved task payloads are excluded from population learning.';
        }

        return $messages;
    }
}
