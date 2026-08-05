<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PlayerFitness;

final class StrengthBenchmarkService
{
    private const LIFTS = ['front_squat', 'back_squat', 'bench_press', 'deadlift', 'trap_bar_deadlift', 'power_clean'];
    private const MAXIMUM_LIFTS = ['front_squat', 'back_squat', 'bench_press', 'deadlift', 'trap_bar_deadlift'];
    private const LOWER_BODY_LIFTS = ['front_squat', 'back_squat', 'deadlift', 'trap_bar_deadlift'];
    private const SCORE_WEIGHTS = [
        'maximum_strength' => 0.45,
        'explosive_strength' => 0.30,
        'strength_endurance' => 0.15,
        'strength_balance' => 0.10,
    ];

    public function __construct(
        private readonly StrengthBenchmarkRegistry $registry,
        private readonly StrengthOneRepMaxCalculator $oneRepMax,
        private readonly CompositeBenchmarkEngine $composite,
    ) {
    }

    /**
     * Pure with respect to application data: this method performs benchmark reads
     * but never writes or mutates the supplied assessment.
     *
     * @param PlayerFitness|array<string, mixed> $assessment
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function benchmark(PlayerFitness|array $assessment, ?array $previous = null, array $context = []): array
    {
        $facts = $assessment instanceof PlayerFitness ? $assessment->toArray() : $assessment;
        $metadata = is_array($facts['strength_test_metadata'] ?? null) ? $facts['strength_test_metadata'] : [];
        $bodyWeight = $this->positive($facts['body_weight'] ?? $facts['body_weight_lbs'] ?? null);
        $age = is_numeric($context['age'] ?? null) ? (int) $context['age'] : null;
        $ageGroup = isset($context['age_group'])
            ? (string) $context['age_group']
            : BenchmarkDefinitions::ageGroup($age);
        $dob = isset($context['dob']) && $context['dob'] ? (string) $context['dob'] : null;
        $strengthBand = $this->registry->strengthBodyweightBand($bodyWeight, $age);
        $benchmarkContext = $context + [
            'age' => $age,
            'age_group' => $ageGroup,
            'body_weight' => $bodyWeight,
            'bodyweight' => $bodyWeight,
            'bodyweight_band' => $strengthBand,
        ];

        $raw = $this->rawMetrics($facts);
        $metrics = [];
        foreach ($this->registry->all() as $key => $definition) {
            if (in_array($key, ['grip_strength_average', 'grip_strength_best', 'grip_asymmetry_percentage'], true)) {
                continue;
            }

            $value = $raw[$key] ?? null;
            $test = in_array($key, self::LIFTS, true)
                ? $this->liftTest($key, $value, $bodyWeight, $metadata, $facts)
                : $this->simpleTest($key, $value, $bodyWeight, $metadata, $facts);
            $benchmarkValue = in_array($key, self::LIFTS, true)
                ? ($test['estimated_1rm'] ?? $test['actual_load'] ?? null)
                : $value;
            $metrics[$key] = $this->metricResult(
                $key,
                $definition,
                $test,
                $benchmarkValue,
                $dob,
                $benchmarkContext,
                $previous,
            );
        }

        $this->addDerivedGripMetrics($metrics, $raw, $facts, $bodyWeight, $ageGroup, $strengthBand);
        $subscores = $this->subscores($metrics);
        $overall = $this->overallScore($metrics, $subscores, $bodyWeight);

        return [
            'version' => StrengthBenchmarkRegistry::VERSION,
            'status' => $overall['status'],
            'score' => $overall['score'],
            'classification' => null !== $overall['score'] ? $this->classification((float) $overall['score']) : 'Benchmark Needs Data',
            'missing_requirements' => $overall['missing_requirements'],
            'subscores' => $subscores,
            'metrics' => array_values($metrics),
            'metric_map' => $metrics,
            'context' => [
                'age_group' => $ageGroup,
                'body_weight_at_test' => $bodyWeight,
                'bodyweight_band' => $strengthBand,
                'level' => $context['level'] ?? 'unknown',
                'test_date' => $facts['fitness_date'] ?? null,
            ],
            'source_policy' => [
                'fmtrx_exact_peer', 'fmtrx_athletic_peer', 'fmtrx_age_role', 'fmtrx_age_only',
                'approved_research', 'approved_community', 'provisional_v1', 'benchmark_needs_data',
            ],
            'data_quality' => array_values(array_unique(array_reduce(
                $metrics,
                fn (array $flags, array $metric): array => array_merge($flags, $metric['data_quality'] ?? []),
                [],
            ))),
        ];
    }

    /** @return array<string, ?float> */
    private function rawMetrics(array $facts): array
    {
        $left = $this->positive($facts['grip_strength_left'] ?? null);
        $right = $this->positive($facts['grip_strength_right'] ?? null);

        return [
            'body_weight' => $this->positive($facts['body_weight'] ?? $facts['body_weight_lbs'] ?? null),
            'front_squat' => $this->positive($facts['front_squat'] ?? $facts['front_squat_lbs'] ?? null),
            'back_squat' => $this->positive($facts['back_squat'] ?? $facts['back_squat_lbs'] ?? null),
            'bench_press' => $this->positive($facts['bench_press'] ?? $facts['bench_press_lbs'] ?? null),
            'deadlift' => $this->positive($facts['dead_lift'] ?? $facts['deadlift'] ?? $facts['dead_lift_lbs'] ?? null),
            'trap_bar_deadlift' => $this->positive($facts['trap_bar_deadlift'] ?? null),
            'power_clean' => $this->positive($facts['power_clean'] ?? $facts['power_clean_lbs'] ?? null),
            'pull_ups' => $this->nonNegative($facts['pull_ups'] ?? null),
            'pushups' => $this->nonNegative($facts['push_ups'] ?? $facts['pushups'] ?? null),
            'plank_hold' => $this->positive($facts['plank_hold'] ?? null),
            'grip_strength_left' => $left,
            'grip_strength_right' => $right,
            'vertical_jump' => $this->positive($facts['vertical_jump'] ?? null),
            'broad_jump' => $this->positive($facts['broad_jump'] ?? null),
            'med_ball_rotational_throw' => $this->positive($facts['med_ball_rotational_throw'] ?? null),
            'sprint_10yd' => $this->positive($facts['sprint_10yd'] ?? null),
            'forty_yard_dash' => $this->positive($facts['yd_40_dash'] ?? $facts['forty_yard_dash'] ?? null),
            'sixty_yard_dash' => $this->positive($facts['yd_60_dash'] ?? $facts['sixty_yard_dash'] ?? null),
        ];
    }

    private function liftTest(string $key, ?float $load, ?float $bodyWeight, array $metadata, array $facts): array
    {
        $metricMeta = is_array($metadata['metrics'][$key] ?? null) ? $metadata['metrics'][$key] : [];
        $repetitions = $metricMeta['repetitions'] ?? $facts[$key.'_repetitions'] ?? null;
        $estimate = $this->oneRepMax->estimate($load, $repetitions);
        $effective = $estimate['estimated_1rm'] ?? $load;

        return [
            'date' => $facts['fitness_date'] ?? null,
            'method' => $metricMeta['method'] ?? ($estimate['tested_1rm'] ? 'tested_1rm' : (null !== $estimate['estimated_1rm'] ? 'estimated_1rm' : 'tested_load')),
            'actual_load' => $estimate['actual_load'],
            'repetitions' => $estimate['repetitions'],
            'estimated_1rm' => $estimate['estimated_1rm'],
            'formula' => $estimate['formula'],
            'formula_version' => $estimate['formula_version'],
            'body_weight_at_test' => $bodyWeight,
            'relative_strength' => null !== $effective && null !== $bodyWeight ? round($effective / $bodyWeight, 3) : null,
            'normalization_method' => 'body_weight_at_test',
        ];
    }

    private function simpleTest(string $key, ?float $value, ?float $bodyWeight, array $metadata, array $facts): array
    {
        $protocols = is_array($metadata['protocols'] ?? null) ? $metadata['protocols'] : [];
        $protocol = match ($key) {
            'pushups' => $protocols['push_ups'] ?? null,
            'pull_ups' => $protocols['pull_ups'] ?? null,
            'plank_hold' => $protocols['plank_hold'] ?? null,
            'med_ball_rotational_throw' => $protocols['med_ball'] ?? null,
            'grip_strength_left', 'grip_strength_right' => $protocols['grip'] ?? null,
            default => $protocols[$key] ?? null,
        };

        return [
            'date' => $facts['fitness_date'] ?? null,
            'method' => $protocol ? 'protocol_test' : 'recorded_value',
            'actual_value' => $value,
            'body_weight_at_test' => $bodyWeight,
            'protocol' => $protocol,
            'device' => str_starts_with($key, 'grip_') ? ($protocols['grip_device'] ?? null) : null,
            'ball_weight_lbs' => 'med_ball_rotational_throw' === $key ? ($protocols['med_ball_weight_lbs'] ?? null) : null,
            'normalization_method' => 'none',
        ];
    }

    private function metricResult(string $key, array $definition, array $test, ?float $value, ?string $dob, array $context, ?array $previous): array
    {
        $quality = [];
        if (null === $value) {
            $quality[] = 'benchmark_unavailable';
        }
        if (($context['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN) === BenchmarkDefinitions::AGE_UNKNOWN) {
            $quality[] = 'missing_age';
        }
        if (($definition['normalization_method'] ?? null) === 'body_weight_at_test' && ($test['body_weight_at_test'] ?? null) === null) {
            $quality[] = 'missing_body_weight';
        }
        if (in_array($key, ['pull_ups', 'pushups', 'plank_hold', 'grip_strength_left', 'grip_strength_right', 'med_ball_rotational_throw'], true)
            && empty($test['protocol'])) {
            $quality[] = 'protocol_unknown';
        }

        $populationValues = is_array($context['population_values'][$key] ?? null) ? $context['population_values'][$key] : [];
        $result = null !== $value
            ? $this->composite->benchmarkMetric((string) ($definition['population_metric_key'] ?? $key), $value, $dob, $context, $populationValues)
            : [];
        $percentile = is_numeric($result['score_0_100'] ?? null) ? (int) round((float) $result['score_0_100']) : null;
        if (null === $percentile && null !== $value) {
            $provisional = $this->provisionalBenchmark($key, $value, $test, $context);
            if (null !== $provisional) {
                $result = $provisional;
                $percentile = (int) $provisional['score_0_100'];
                $quality[] = 'provisional_reference';
            }
        }
        $population = is_array($result['population_percentile'] ?? null) ? $result['population_percentile'] : [];
        $source = (string) ($result['source'] ?? 'benchmark_needs_data');
        if (null === $percentile) {
            $quality[] = 'insufficient_population';
            $quality[] = 'benchmark_unavailable';
        }
        $sourceType = match ($source) {
            'fmtrx_population' => 'fmtrx_population',
            'composite', 'composite_benchmark' => 'composite',
            'research_benchmark' => 'approved_reference',
            'provisional_v1' => 'provisional_v1',
            default => 'benchmark_needs_data',
        };

        return [
            'metric_key' => $key,
            'label' => $definition['label'],
            'category' => $definition['category'],
            'unit' => $definition['canonical_unit'],
            'available' => null !== $value,
            'test' => $test,
            'benchmark' => [
                'percentile' => $percentile,
                'classification' => 'body_weight' === $key && null !== $value
                    ? 'Descriptive'
                    : (null !== $percentile ? $this->classification($percentile) : 'Benchmark Needs Data'),
                'source_type' => $sourceType,
                'source_name' => $this->sourceName($source, $population),
                'source_version' => StrengthBenchmarkRegistry::VERSION,
                'bucket_level' => $population['selected_bucket_level'] ?? null,
                'sample_size' => (int) ($population['bucket_count'] ?? 0),
                'confidence' => null !== $percentile ? (string) ($result['confidence'] ?? 'low') : 'insufficient',
                'age_group' => $context['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN,
                'bodyweight_band' => $context['bodyweight_band'] ?? 'unknown',
                'level' => $context['level'] ?? 'unknown',
                'test_method' => $test['method'] ?? null,
            ],
            'goal' => $this->goal($value, $percentile, $result, (string) ($definition['direction'] ?? 'higher')),
            'trend' => $this->trend($key, $value, $previous),
            'evidence' => $result['evidence'] ?? [],
            'data_quality' => array_values(array_unique($quality)),
        ];
    }

    private function addDerivedGripMetrics(array &$metrics, array $raw, array $facts, ?float $bodyWeight, string $ageGroup, string $band): void
    {
        $left = $raw['grip_strength_left'];
        $right = $raw['grip_strength_right'];
        $derived = [
            'grip_strength_average' => null !== $left && null !== $right ? round(($left + $right) / 2, 1) : ($left ?? $right),
            'grip_strength_best' => null !== $left || null !== $right ? max(array_filter([$left, $right], fn ($v) => null !== $v)) : null,
            'grip_asymmetry_percentage' => null !== $left && null !== $right && max($left, $right) > 0
                ? round((abs($left - $right) / max($left, $right)) * 100, 1)
                : null,
        ];

        foreach ($derived as $key => $value) {
            $definition = $this->registry->get($key) ?? [];
            $metrics[$key] = [
                'metric_key' => $key,
                'label' => $definition['label'] ?? $key,
                'category' => 'grip_strength',
                'unit' => $definition['canonical_unit'] ?? ('grip_asymmetry_percentage' === $key ? '%' : 'lbs'),
                'available' => null !== $value,
                'test' => [
                    'date' => $facts['fitness_date'] ?? null,
                    'method' => 'derived',
                    'actual_value' => $value,
                    'body_weight_at_test' => $bodyWeight,
                    'normalization_method' => $definition['normalization_method'] ?? 'none',
                ],
                'benchmark' => [
                    'percentile' => null,
                    'classification' => 'grip_asymmetry_percentage' === $key && null !== $value ? 'Descriptive' : 'Benchmark Needs Data',
                    'source_type' => 'derived_measurement',
                    'source_name' => 'FMTRX bilateral grip calculation',
                    'source_version' => StrengthBenchmarkRegistry::VERSION,
                    'bucket_level' => null,
                    'sample_size' => 0,
                    'confidence' => null !== $value ? 'measured' : 'insufficient',
                    'age_group' => $ageGroup,
                    'bodyweight_band' => $band,
                    'level' => 'unknown',
                    'test_method' => 'derived',
                ],
                'goal' => null,
                'trend' => $this->trend($key, $value, null),
                'evidence' => ['Derived from separately preserved left and right measurements.'],
                'data_quality' => null === $value ? ['benchmark_unavailable'] : [],
            ];
        }
    }

    private function subscores(array $metrics): array
    {
        $average = function (array $keys) use ($metrics): ?float {
            $values = [];
            foreach ($keys as $key) {
                $value = $metrics[$key]['benchmark']['percentile'] ?? null;
                if (is_numeric($value)) {
                    $values[] = (float) $value;
                }
            }
            return $values ? round(array_sum($values) / count($values), 1) : null;
        };

        $balanceParts = [];
        $asymmetry = $metrics['grip_asymmetry_percentage']['test']['actual_value'] ?? null;
        if (is_numeric($asymmetry)) {
            $balanceParts[] = max(0.0, 100.0 - ((float) $asymmetry * 2));
        }
        $push = $metrics['pushups']['benchmark']['percentile'] ?? null;
        $pull = $metrics['pull_ups']['benchmark']['percentile'] ?? null;
        if (is_numeric($push) && is_numeric($pull)) {
            $balanceParts[] = max(0.0, 100.0 - abs((float) $push - (float) $pull));
        }

        return [
            'maximum_strength' => $average(self::MAXIMUM_LIFTS),
            'explosive_strength' => $average(['power_clean', 'vertical_jump', 'broad_jump', 'med_ball_rotational_throw']),
            'strength_endurance' => $average(['pull_ups', 'pushups', 'plank_hold']),
            'strength_balance' => $balanceParts ? round(array_sum($balanceParts) / count($balanceParts), 1) : null,
        ];
    }

    private function overallScore(array $metrics, array $subscores, ?float $bodyWeight): array
    {
        $missing = [];
        $present = fn (string $key): bool => ($metrics[$key]['available'] ?? false) === true;
        if (null === $bodyWeight) {
            $missing[] = 'valid_body_weight_at_test';
        }
        if (count(array_filter(self::MAXIMUM_LIFTS, $present)) < 2) {
            $missing[] = 'two_maximum_strength_measurements';
        }
        if (count(array_filter(self::LOWER_BODY_LIFTS, $present)) < 1) {
            $missing[] = 'one_lower_body_measurement';
        }
        if (count(array_filter(['power_clean', 'vertical_jump', 'broad_jump', 'med_ball_rotational_throw', 'pull_ups', 'pushups', 'plank_hold'], $present)) < 1) {
            $missing[] = 'one_explosive_or_endurance_measurement';
        }
        if ( ! is_numeric($subscores['maximum_strength'] ?? null)) {
            $missing[] = 'maximum_strength_benchmark_coverage';
        }
        if ( ! is_numeric($subscores['explosive_strength'] ?? null) && ! is_numeric($subscores['strength_endurance'] ?? null)) {
            $missing[] = 'explosive_or_endurance_benchmark_coverage';
        }

        $weighted = 0.0;
        $weight = 0.0;
        foreach (self::SCORE_WEIGHTS as $key => $metricWeight) {
            if (is_numeric($subscores[$key] ?? null)) {
                $weighted += (float) $subscores[$key] * $metricWeight;
                $weight += $metricWeight;
            }
        }
        if ($weight <= 0) {
            $missing[] = 'benchmark_coverage';
        }

        return [
            'score' => empty($missing) && $weight > 0 ? round($weighted / $weight, 1) : null,
            'status' => empty($missing) && $weight > 0 ? 'available' : 'needs_data',
            'missing_requirements' => array_values(array_unique($missing)),
        ];
    }

    private function goal(?float $value, ?int $percentile, array $result, string $direction): ?array
    {
        if (null === $value || null === $percentile || $percentile >= 90) {
            return null;
        }
        $targetPercentile = match (true) {
            $percentile < 25 => 25,
            $percentile < 50 => 50,
            $percentile < 75 => 75,
            default => 90,
        };
        $anchors = $result['research_percentile']['evidence']['age_percentile_anchors']
            ?? $result['evidence']['age_percentile_anchors']
            ?? null;
        $targetValue = is_array($anchors) && is_numeric($anchors['p'.$targetPercentile] ?? null)
            ? (float) $anchors['p'.$targetPercentile]
            : null;

        return [
            'policy' => 'next_percentile_tier',
            'target_percentile' => $targetPercentile,
            'target_value' => $targetValue,
            'gap' => null !== $targetValue
                ? round('lower' === $direction ? max(0, $value - $targetValue) : max(0, $targetValue - $value), 2)
                : null,
        ];
    }

    private function trend(string $key, ?float $value, ?array $previous): array
    {
        $priorRaw = $previous ? ($this->rawMetrics($previous)[$key] ?? null) : null;
        $change = null !== $value && null !== $priorRaw ? round($value - $priorRaw, 2) : null;

        return [
            'absolute_change' => $change,
            'relative_change' => null !== $change && 0.0 !== $priorRaw ? round($change / abs($priorRaw), 3) : null,
            'direction' => null === $change ? 'unknown' : ($change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')),
        ];
    }

    private function classification(float $percentile): string
    {
        return match (true) {
            $percentile >= 90 => 'Elite',
            $percentile >= 75 => 'Above Average',
            $percentile >= 50 => 'Average',
            $percentile >= 25 => 'Below Average',
            default => 'Needs Development',
        };
    }

    private function sourceName(string $source, array $population): string
    {
        if ('fmtrx_population' === $source) {
            return 'FMTRX '.str_replace('_', ' ', (string) ($population['selected_bucket_level'] ?? 'population'));
        }
        if (in_array($source, ['composite', 'composite_benchmark'], true)) {
            return 'FMTRX population + governed reference';
        }
        if ('research_benchmark' === $source) {
            return 'FMTRX Benchmark Library';
        }
        if ('provisional_v1' === $source) {
            return 'FMTRX approved provisional reference';
        }
        return 'Benchmark Needs Data';
    }

    private function provisionalBenchmark(string $key, float $value, array $test, array $context): ?array
    {
        $age = is_numeric($context['age'] ?? null) ? (int) $context['age'] : null;
        if (null === $age) {
            return null;
        }
        $ageGroup = match (true) {
            $age <= 14 => '12-14',
            $age <= 18 => '15-18',
            default => '19-22',
        };
        $metricKey = match ($key) {
            'front_squat' => 'front_squat_ratio',
            'back_squat' => 'back_squat_ratio',
            'bench_press' => 'bench_ratio',
            'deadlift' => 'deadlift_ratio',
            'power_clean' => 'power_clean_ratio',
            'pull_ups' => 'pull_ups_reps',
            'pushups' => 'push_ups_reps',
            'grip_strength_left', 'grip_strength_right' => 'hand_strength_lbs',
            'vertical_jump' => 'vertical_jump_inches',
            'broad_jump' => 'broad_jump_inches',
            'med_ball_rotational_throw' => 'med_ball_rotational_throw_ft',
            'sprint_10yd' => 'ten_yard_sec',
            'forty_yard_dash' => 'forty_yard_sec',
            'sixty_yard_dash' => 'sixty_yard_sec',
            default => null,
        };
        if (null === $metricKey) {
            return null;
        }
        $role = in_array($context['role'] ?? null, ['hitter', 'pitcher'], true) ? (string) $context['role'] : 'hitter';
        $thresholds = config("fmtrx_benchmarks.age_groups.{$ageGroup}.{$role}.metrics.{$metricKey}")
            ?? config("fmtrx_benchmarks.age_groups.{$ageGroup}.hitter.metrics.{$metricKey}");
        if ( ! is_array($thresholds) || count($thresholds) < 2) {
            return null;
        }
        $benchmarkValue = str_ends_with($metricKey, '_ratio') ? ($test['relative_strength'] ?? null) : $value;
        if ( ! is_numeric($benchmarkValue)) {
            return null;
        }
        $lowerIsBetter = in_array($metricKey, config('fmtrx_benchmarks.lower_is_better_metrics', []), true);
        $percentile = $this->interpolatePercentile((float) $benchmarkValue, $thresholds, ! $lowerIsBetter);

        return [
            'score_0_100' => $percentile,
            'percentile_estimate' => $percentile,
            'confidence' => 'low',
            'source' => 'provisional_v1',
            'evidence' => [
                'reference' => 'config/fmtrx_benchmarks.php',
                'reference_version' => StrengthBenchmarkRegistry::VERSION,
                'age_group' => $ageGroup,
                'role' => $role,
                'metric_key' => $metricKey,
                'thresholds' => $thresholds,
                'warning' => 'Operational provisional reference; not a baseball-population research norm.',
            ],
            'population_percentile' => ['bucket_count' => 0, 'confidence' => 'insufficient'],
        ];
    }

    private function interpolatePercentile(float $value, array $thresholds, bool $higherIsBetter): int
    {
        $points = [];
        foreach ($thresholds as $percentile => $threshold) {
            if (is_numeric($percentile) && is_numeric($threshold)) {
                $points[] = ['percentile' => (int) $percentile, 'value' => (float) $threshold];
            }
        }
        if ( ! $higherIsBetter) {
            $value *= -1;
            foreach ($points as &$point) {
                $point['value'] *= -1;
            }
            unset($point);
        }
        usort($points, fn (array $left, array $right): int => $left['value'] <=> $right['value']);
        if ($value <= $points[0]['value']) {
            return $points[0]['percentile'];
        }
        $last = $points[count($points) - 1];
        if ($value >= $last['value']) {
            return $last['percentile'];
        }
        for ($index = 1; $index < count($points); $index++) {
            if ($value <= $points[$index]['value']) {
                $left = $points[$index - 1];
                $right = $points[$index];
                $progress = ($value - $left['value']) / max(0.0001, $right['value'] - $left['value']);
                return (int) round($left['percentile'] + ($progress * ($right['percentile'] - $left['percentile'])));
            }
        }

        return 50;
    }

    private function positive(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private function nonNegative(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }
}
