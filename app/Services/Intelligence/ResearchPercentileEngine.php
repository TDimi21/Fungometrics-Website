<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Carbon\Carbon;

class ResearchPercentileEngine
{
    public function __construct(
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {}

    public function percentileForMetric(string $metricKey, mixed $value, ?string $dob, array $context = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $metric = $this->benchmarkLibrary->metric($metricKey);
        $ageGroup = $this->ageGroup($dob, $context);
        $raw = is_numeric($value) ? (float) $value : null;

        $base = [
            'metric_key' => $metricKey,
            'age_group' => $ageGroup,
            'raw_value' => $raw,
            'unit' => $metric['unit'] ?? null,
            'percentile_estimate' => null,
            'score_0_100' => null,
            'label' => 'unknown',
            'benchmark_label' => 'unknown',
            'gap_to_good' => null,
            'gap_to_elite' => null,
            'confidence' => 'low',
            'source' => 'research_benchmark',
            'evidence' => [
                'metric_key' => $metricKey,
                'age_group' => $ageGroup,
                'context' => $context + ['dob' => $dob],
            ],
        ];

        if (! $metric) {
            $base['evidence']['reason'] = 'No benchmark metric definition exists.';

            return $base;
        }

        $base['unit'] = $metric['unit'];
        $base['confidence'] = $this->researchConfidence($metric['research_confidence'] ?? 'low', $ageGroup);
        $base['evidence']['metric_definition'] = [
            'display_name' => $metric['display_name'],
            'category' => $metric['category'],
            'higher_is_better' => $metric['higher_is_better'],
            'importance_weight' => $metric['importance_weight'],
            'research_confidence' => $metric['research_confidence'],
            'source_type' => $metric['source_type'],
            'evidence_notes' => $metric['evidence_notes'],
        ];

        if ($raw === null) {
            $base['label'] = 'Needs Data';
            $base['benchmark_label'] = 'Needs Data';
            $base['evidence']['reason'] = 'Metric value is missing.';

            return $base;
        }

        if ($ageGroup === BenchmarkDefinitions::AGE_UNKNOWN) {
            $base['label'] = 'Needs Age';
            $base['benchmark_label'] = 'Needs Age';
            $base['evidence']['reason'] = 'Date of birth or age is missing, so an age-specific research benchmark cannot be selected.';

            return $base;
        }

        $anchors = $this->benchmarkLibrary->percentileAnchors($metricKey, $ageGroup);
        if (! $anchors) {
            $base['label'] = 'Needs Benchmark';
            $base['benchmark_label'] = 'Needs Benchmark';
            $base['evidence']['reason'] = 'No percentile anchors exist for this metric and age group.';

            return $base;
        }

        $higherIsBetter = (bool) $metric['higher_is_better'];
        $percentile = $this->estimatePercentile($raw, $anchors, $higherIsBetter);
        $label = $this->labelFromPercentile($percentile);

        $base['percentile_estimate'] = $percentile;
        $base['score_0_100'] = $percentile;
        $base['label'] = $label;
        $base['benchmark_label'] = $label;
        $base['gap_to_good'] = $this->gap($raw, (float) $anchors['p75'], $higherIsBetter);
        $base['gap_to_elite'] = $this->gap($raw, (float) $anchors['p95'], $higherIsBetter);
        $base['evidence']['age_percentile_anchors'] = $anchors;
        $base['evidence']['higher_is_better'] = $higherIsBetter;

        return $base;
    }

    private function ageGroup(?string $dob, array $context): string
    {
        if ($dob) {
            try {
                return BenchmarkDefinitions::ageGroup(Carbon::parse($dob)->age);
            } catch (\Throwable) {
                // Fall back to explicit context below.
            }
        }

        if (isset($context['age_group']) && in_array($context['age_group'], BenchmarkDefinitions::AGE_GROUPS, true)) {
            return (string) $context['age_group'];
        }

        if (is_numeric($context['age'] ?? null)) {
            return BenchmarkDefinitions::ageGroup((int) $context['age']);
        }

        return BenchmarkDefinitions::AGE_UNKNOWN;
    }

    private function estimatePercentile(float $value, array $anchors, bool $higherIsBetter): int
    {
        $points = [];

        foreach ($anchors as $percentile => $anchorValue) {
            $points[] = [
                'value' => (float) $anchorValue,
                'percentile' => (int) ltrim((string) $percentile, 'p'),
            ];
        }

        if (! $higherIsBetter) {
            foreach ($points as &$point) {
                $point['value'] *= -1;
            }
            unset($point);
            $value *= -1;
        }

        usort($points, fn (array $left, array $right) => $left['value'] <=> $right['value']);

        if ($value <= $points[0]['value']) {
            return $points[0]['percentile'];
        }

        $last = $points[count($points) - 1];
        if ($value >= $last['value']) {
            return $last['percentile'];
        }

        for ($index = 1; $index < count($points); $index++) {
            $left = $points[$index - 1];
            $right = $points[$index];

            if ($value <= $right['value']) {
                $span = max(0.0001, $right['value'] - $left['value']);
                $progress = ($value - $left['value']) / $span;
                $percentile = $left['percentile'] + ($progress * ($right['percentile'] - $left['percentile']));

                return (int) round(max(1, min(99, $percentile)));
            }
        }

        return 50;
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

    private function gap(float $value, float $target, bool $higherIsBetter): float
    {
        $gap = $higherIsBetter ? $target - $value : $value - $target;

        return round(max(0.0, $gap), 1);
    }

    private function researchConfidence(string $confidence, string $ageGroup): string
    {
        if ($ageGroup === BenchmarkDefinitions::AGE_UNKNOWN) {
            return 'low';
        }

        return in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : 'low';
    }
}
