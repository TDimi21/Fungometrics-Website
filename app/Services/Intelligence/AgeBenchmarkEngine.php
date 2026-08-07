<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use Carbon\Carbon;

class AgeBenchmarkEngine
{
    public function __construct(
        private readonly ResearchPercentileEngine $researchPercentileEngine,
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {}

    public function ageGroupFromDate(?string $dob): string
    {
        if (! $dob) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }

        try {
            return BenchmarkDefinitions::ageGroup(Carbon::parse($dob)->age);
        } catch (\Throwable) {
            return BenchmarkDefinitions::AGE_UNKNOWN;
        }
    }

    public function benchmarkMetric(string $metricKey, mixed $value, ?string $dob, array $context = []): array
    {
        $ageGroup = $this->ageGroupFromContext($dob, $context);

        return $this->researchPercentileEngine->percentileForMetric(
            BenchmarkDefinitions::normalizeMetricKey($metricKey),
            $value,
            $dob,
            $context + ['age_group' => $ageGroup]
        );
    }

    public function benchmarkMany(array $metrics, ?string $dob, array $context = []): array
    {
        $results = [];

        foreach ($metrics as $metricKey => $value) {
            $results[BenchmarkDefinitions::normalizeMetricKey((string) $metricKey)] = $this->benchmarkMetric((string) $metricKey, $value, $dob, $context);
        }

        return $results;
    }

    public function benchmarkPlayer(array $assembled): array
    {
        $player = $assembled['player_context'] ?? [];
        $dob = $player['born_date'] ? (string) $player['born_date'] : null;
        $age = $this->numberOrNull($player['age'] ?? null);
        $context = [
            'age' => $age,
            'player_id' => $player['id'] ?? null,
            'team_id' => $player['team_id'] ?? $assembled['team_context']['id'] ?? null,
            'body_weight' => $assembled['physical_development']['body_weight'] ?? $assembled['assessment_summary']['body_weight'] ?? null,
            'height_inches' => $this->heightInches($player['height_ft'] ?? null, $player['height_in'] ?? null),
            'position' => $player['positions'] ?? [],
            'level' => $player['level'] ?? null,
            'hit_side' => $player['hit_side'] ?? null,
            'throw_side' => $player['throw_side'] ?? null,
        ];
        $flatMetrics = $this->extractMetrics($assembled);
        $flatResults = $this->benchmarkMany($flatMetrics, $dob, $context);
        $grouped = [];

        foreach ($flatResults as $metricKey => $result) {
            $category = $this->benchmarkLibrary->metric($metricKey)['category']
                ?? BenchmarkDefinitions::categoryForMetric($metricKey)
                ?? 'unknown';
            $grouped[$category][$metricKey] = $result;
        }

        $ageGroup = $this->ageGroupFromContext($dob, $context);

        return [
            'age_group' => $ageGroup,
            'age' => $age,
            'confidence' => $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN ? 'low' : 'medium',
            'source' => 'benchmark_library',
            'bucket_key' => $this->benchmarkLibrary->bucketKey($context + ['age_group' => $ageGroup]),
            'metrics' => $grouped,
            'flat_metrics' => $flatResults,
            'data_gaps' => $this->dataGaps($ageGroup, $flatMetrics),
        ];
    }

    private function evaluateMetric(string $metricKey, mixed $value, string $ageGroup, array $context): array
    {
        $definition = BenchmarkDefinitions::metricDefinition($metricKey);
        $raw = $this->numberOrNull($value);

        $base = [
            'metric_key' => $metricKey,
            'age_group' => $ageGroup,
            'raw_value' => $raw,
            'unit' => $definition['unit'] ?? null,
            'benchmark_label' => 'unknown',
            'score_0_100' => null,
            'percentile_estimate' => null,
            'gap_to_good' => null,
            'gap_to_elite' => null,
            'confidence' => $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN ? 'low' : 'medium',
            'source' => 'manual_age_benchmark',
            'evidence' => [
                'metric_key' => $metricKey,
                'category' => $definition['category'] ?? null,
                'context' => $context,
            ],
        ];

        if ($raw === null || ($raw <= 0 && ! $this->allowsZero($metricKey))) {
            $base['evidence']['reason'] = 'Metric value is missing.';

            return $base;
        }

        if (! $definition) {
            $base['evidence']['reason'] = 'No benchmark definition exists for this metric.';

            return $base;
        }

        $thresholds = $definition['benchmarks'][$ageGroup] ?? null;
        if (! $thresholds || $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN) {
            $base['evidence']['reason'] = 'Date of birth is missing, so an age-appropriate benchmark cannot be selected.';
            $base['evidence']['higher_is_better'] = $definition['higher_is_better'] ?? null;

            return $base;
        }

        $higherIsBetter = (bool) ($thresholds['higher_is_better'] ?? $definition['higher_is_better'] ?? true);
        $percentile = $this->percentileEstimate($raw, $thresholds, $higherIsBetter);

        $base['benchmark_label'] = $this->label($raw, $thresholds, $higherIsBetter);
        $base['score_0_100'] = $percentile;
        $base['percentile_estimate'] = $percentile;
        $base['gap_to_good'] = $this->gap($raw, (float) $thresholds['good'], $higherIsBetter);
        $base['gap_to_elite'] = $this->gap($raw, (float) $thresholds['elite'], $higherIsBetter);
        $base['evidence']['thresholds'] = $thresholds;
        $base['evidence']['higher_is_better'] = $higherIsBetter;

        return $base;
    }

    private function extractMetrics(array $assembled): array
    {
        $physical = $assembled['physical_development'] ?? [];
        $assessment = $assembled['assessment_summary'] ?? [];
        $batting = $assembled['batting_summary'] ?? [];
        $cage = $assembled['cage_summary'] ?? [];
        $exitVelocity = $assembled['exit_velocity_summary'] ?? [];
        $bullpen = $assembled['bullpen_summary'] ?? [];
        $longToss = $assembled['long_toss_summary'] ?? [];
        $weighted = $assembled['weighted_ball_summary'] ?? [];

        return [
            'average_fastball_velocity' => $this->firstNumber([$bullpen['avg_pitch_velocity'] ?? null, $assessment['baseline_pitch_velocity'] ?? null, $physical['pitch_velocity'] ?? null]),
            'max_fastball_velocity' => $this->firstNumber([$bullpen['max_pitch_velocity'] ?? null, $assessment['baseline_pitch_velocity'] ?? null, $physical['pitch_velocity'] ?? null]),
            'strike_percentage' => $bullpen['strike_rate'] ?? null,
            'long_toss_max_distance' => $longToss['max_distance'] ?? null,
            'weighted_ball_5oz_velocity' => $weighted['five_oz_max_velocity'] ?? null,
            'average_exit_velocity' => $this->firstNumber([
                $exitVelocity['avg_exit_velocity'] ?? null,
                $batting['avg_exit_velocity'] ?? null,
                $cage['avg_exit_velocity'] ?? null,
                $assessment['baseline_exit_velocity'] ?? null,
                $physical['exit_velocity'] ?? null,
            ]),
            'max_exit_velocity' => $this->maxNumber([
                $exitVelocity['max_exit_velocity'] ?? null,
                $batting['max_exit_velocity'] ?? null,
                $cage['max_exit_velocity'] ?? null,
                $assessment['baseline_exit_velocity'] ?? null,
                $physical['exit_velocity'] ?? null,
            ]),
            'hard_hit_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['hardHitPercentage', 'hard_hit_percentage', 'hardContactRate', 'hard_contact_rate']),
            'line_drive_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['lineDrivePercentage', 'line_drive_percentage', 'ldPercentage', 'ld_percentage']),
            'hitter_swing_miss_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['swingMissPercentage', 'swing_miss_percentage', 'missRate', 'miss_rate']),
            'bench_press' => $this->firstNumber([$physical['bench_press'] ?? null, $assessment['bench_press'] ?? null]),
            'squat' => $this->firstNumber([$physical['squat'] ?? null, $assessment['squat'] ?? null]),
            'deadlift' => $this->firstNumber([$physical['deadlift'] ?? null, $assessment['deadlift'] ?? null]),
            'pull_ups' => $physical['pull_ups'] ?? null,
            'pushups' => $physical['pushups'] ?? null,
            'forty_yard_dash' => $physical['40_yard_dash'] ?? null,
            'sixty_yard_dash' => $physical['60_yard_dash'] ?? null,
            'broad_jump' => $this->firstNumber([$physical['broad_jump'] ?? null, $assessment['broad_jump'] ?? null]),
            'vertical_jump' => $this->firstNumber([$physical['vertical_jump'] ?? null, $assessment['vertical_jump'] ?? null]),
            'mobility_score' => $this->firstNumber([$physical['mobility_score'] ?? null, $assessment['mobility_overall_score'] ?? null]),
            'shoulder_mobility_score' => $assessment['shoulder_mobility_score'] ?? null,
            'hip_mobility_score' => $assessment['hip_mobility_score'] ?? null,
            't_spine_mobility_score' => $assessment['t_spine_mobility_score'] ?? null,
        ];
    }

    private function ageGroupFromContext(?string $dob, array $context): string
    {
        $ageGroup = $this->ageGroupFromDate($dob);
        if ($ageGroup !== BenchmarkDefinitions::AGE_UNKNOWN) {
            return $ageGroup;
        }

        $age = $this->numberOrNull($context['age'] ?? null);

        return BenchmarkDefinitions::ageGroup($age !== null ? (int) $age : null);
    }

    private function dataGaps(string $ageGroup, array $metrics): array
    {
        $gaps = [];

        if ($ageGroup === BenchmarkDefinitions::AGE_UNKNOWN) {
            $gaps[] = [
                'source' => 'player',
                'missing_field' => 'born_date',
                'impact' => 'Age-adjusted benchmark confidence is low without date of birth.',
                'recommended_collection_action' => 'Add date of birth to the player profile.',
            ];
        }

        foreach ($metrics as $metricKey => $value) {
            if ($this->numberOrNull($value) === null) {
                $gaps[] = [
                    'source' => BenchmarkDefinitions::categoryForMetric((string) $metricKey) ?? 'benchmark',
                    'missing_field' => BenchmarkDefinitions::normalizeMetricKey((string) $metricKey),
                    'impact' => 'Age-adjusted benchmark unavailable for '.BenchmarkDefinitions::normalizeMetricKey((string) $metricKey).'.',
                    'recommended_collection_action' => 'Collect '.BenchmarkDefinitions::normalizeMetricKey((string) $metricKey).' for this player.',
                ];
            }
        }

        return $gaps;
    }

    private function label(float $value, array $thresholds, bool $higherIsBetter): string
    {
        if (! $higherIsBetter) {
            return match (true) {
                $value <= (float) $thresholds['elite'] => 'elite',
                $value <= (float) $thresholds['good'] => 'good',
                $value <= (float) $thresholds['average'] => 'average',
                $value <= (float) $thresholds['below_average'] => 'below_average',
                default => 'critical',
            };
        }

        return match (true) {
            $value >= (float) $thresholds['elite'] => 'elite',
            $value >= (float) $thresholds['good'] => 'good',
            $value >= (float) $thresholds['average'] => 'average',
            $value >= (float) $thresholds['below_average'] => 'below_average',
            default => 'critical',
        };
    }

    private function percentileEstimate(float $value, array $thresholds, bool $higherIsBetter): int
    {
        $anchors = [
            ['value' => (float) $thresholds['critical'], 'percentile' => 5],
            ['value' => (float) $thresholds['below_average'], 'percentile' => 25],
            ['value' => (float) $thresholds['average'], 'percentile' => 50],
            ['value' => (float) $thresholds['good'], 'percentile' => 75],
            ['value' => (float) $thresholds['elite'], 'percentile' => 95],
        ];

        if (! $higherIsBetter) {
            foreach ($anchors as &$anchor) {
                $anchor['value'] *= -1;
            }
            unset($anchor);
            $value *= -1;
        }

        usort($anchors, fn (array $a, array $b) => $a['value'] <=> $b['value']);

        if ($value <= $anchors[0]['value']) {
            return 5;
        }

        $last = $anchors[count($anchors) - 1];
        if ($value >= $last['value']) {
            return 95;
        }

        for ($i = 1; $i < count($anchors); $i++) {
            $left = $anchors[$i - 1];
            $right = $anchors[$i];

            if ($value <= $right['value']) {
                $span = max(0.0001, $right['value'] - $left['value']);
                $progress = ($value - $left['value']) / $span;

                return (int) round($left['percentile'] + ($progress * ($right['percentile'] - $left['percentile'])));
            }
        }

        return 50;
    }

    private function gap(float $value, float $target, bool $higherIsBetter): float
    {
        $gap = $higherIsBetter ? $target - $value : $value - $target;

        return round(max(0.0, $gap), 1);
    }

    private function allowsZero(string $metric): bool
    {
        return in_array($metric, [
            'pull_ups',
            'pushups',
            'hard_hit_percentage',
            'line_drive_percentage',
            'hitter_swing_miss_percentage',
        ], true);
    }

    private function firstNumber(array $values): ?float
    {
        foreach ($values as $value) {
            $number = $this->numberOrNull($value);
            if ($number !== null && ($number > 0 || $number === 0.0)) {
                return $number;
            }
        }

        return null;
    }

    private function maxNumber(array $values): ?float
    {
        $numbers = array_values(array_filter(array_map(fn ($value) => $this->numberOrNull($value), $values), fn ($value) => $value !== null));

        return count($numbers) ? max($numbers) : null;
    }

    private function firstNestedNumber(array $data, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $number = $this->numberOrNull($data[$key]);
                if ($number !== null) {
                    return $number;
                }
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $number = $this->firstNestedNumber($value, $keys);
                if ($number !== null) {
                    return $number;
                }
            }
        }

        return null;
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function heightInches(mixed $feet, mixed $inches): ?float
    {
        $feet = $this->numberOrNull($feet);
        $inches = $this->numberOrNull($inches);

        if ($feet === null && $inches === null) {
            return null;
        }

        return (($feet ?? 0.0) * 12) + ($inches ?? 0.0);
    }
}
