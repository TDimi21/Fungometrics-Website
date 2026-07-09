<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class AgeBenchmarkEngine
{
    public function benchmarkPlayer(array $assembled): array
    {
        $age = $this->numberOrNull($assembled['player_context']['age'] ?? null);
        $ageGroup = BenchmarkDefinitions::ageGroup($age !== null ? (int) $age : null);
        $metrics = $this->extractMetrics($assembled);
        $results = [];

        foreach ($metrics as $category => $categoryMetrics) {
            foreach ($categoryMetrics as $metric => $value) {
                $results[$category][$metric] = $this->benchmarkMetric($category, $metric, $value, $ageGroup, [
                    'age' => $age,
                    'player_id' => $assembled['player_context']['id'] ?? null,
                    'body_weight' => $assembled['physical_development']['body_weight'] ?? $assembled['assessment_summary']['body_weight'] ?? null,
                    'position' => $assembled['player_context']['positions'] ?? [],
                    'level' => $assembled['player_context']['level'] ?? null,
                    'hit_side' => $assembled['player_context']['hit_side'] ?? null,
                    'throw_side' => $assembled['player_context']['throw_side'] ?? null,
                ]);
            }
        }

        return [
            'age_group' => $ageGroup,
            'age' => $age,
            'confidence' => $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN ? 'low' : 'medium',
            'source' => 'manual_age_benchmarks_v1',
            'metrics' => $results,
            'data_gaps' => $this->dataGaps($ageGroup, $metrics),
        ];
    }

    public function benchmarkMetric(string $category, string $metric, mixed $value, string $ageGroup, array $context = []): array
    {
        $definitions = BenchmarkDefinitions::definitions();
        $definition = $definitions[$category][$metric] ?? null;
        $raw = $this->numberOrNull($value);

        $base = [
            'age_group' => $ageGroup,
            'raw_value' => $raw,
            'benchmark_label' => null,
            'percentile_estimate' => null,
            'score_0_100' => null,
            'gap_to_good' => null,
            'gap_to_elite' => null,
            'confidence' => $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN ? 'low' : 'medium',
            'evidence' => [
                'category' => $category,
                'metric' => $metric,
                'context' => $context,
                'source' => 'manual_age_benchmarks_v1',
            ],
        ];

        if ($raw === null || ($raw <= 0 && ! $this->allowsZero($metric))) {
            $base['benchmark_label'] = 'Needs Data';
            $base['evidence']['reason'] = 'Metric value is missing.';

            return $base;
        }

        if (! $definition) {
            $base['benchmark_label'] = 'Needs Benchmark';
            $base['evidence']['reason'] = 'No benchmark definition exists for this metric.';

            return $base;
        }

        $thresholds = $definition['benchmarks'][$ageGroup] ?? null;
        if (! $thresholds || $ageGroup === BenchmarkDefinitions::AGE_UNKNOWN) {
            $base['benchmark_label'] = 'Needs Age';
            $base['evidence']['reason'] = 'Date of birth is missing, so an age-appropriate benchmark cannot be selected.';
            $base['evidence']['unit'] = $definition['unit'] ?? null;
            $base['evidence']['direction'] = $definition['direction'] ?? null;

            return $base;
        }

        $direction = (string) ($definition['direction'] ?? 'higher');
        $percentile = $this->percentileEstimate($raw, $thresholds, $direction);
        $base['benchmark_label'] = $this->label($raw, $thresholds, $direction);
        $base['percentile_estimate'] = $percentile;
        $base['score_0_100'] = $percentile;
        $base['gap_to_good'] = $this->gap($raw, (float) $thresholds['good'], $direction);
        $base['gap_to_elite'] = $this->gap($raw, (float) $thresholds['elite'], $direction);
        $base['evidence']['thresholds'] = $thresholds;
        $base['evidence']['unit'] = $definition['unit'] ?? null;
        $base['evidence']['direction'] = $direction;

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

        $avgEv = $this->firstNumber([
            $exitVelocity['avg_exit_velocity'] ?? null,
            $batting['avg_exit_velocity'] ?? null,
            $cage['avg_exit_velocity'] ?? null,
            $assessment['baseline_exit_velocity'] ?? null,
            $physical['exit_velocity'] ?? null,
        ]);

        $maxEv = $this->maxNumber([
            $exitVelocity['max_exit_velocity'] ?? null,
            $batting['max_exit_velocity'] ?? null,
            $cage['max_exit_velocity'] ?? null,
            $assessment['baseline_exit_velocity'] ?? null,
            $physical['exit_velocity'] ?? null,
        ]);

        return [
            'strength' => [
                'bench_press' => $this->firstNumber([$physical['bench_press'] ?? null, $assessment['bench_press'] ?? null]),
                'squat' => $this->firstNumber([$physical['squat'] ?? null, $assessment['squat'] ?? null]),
                'deadlift' => $this->firstNumber([$physical['deadlift'] ?? null, $assessment['deadlift'] ?? null]),
                'pull_ups' => $physical['pull_ups'] ?? null,
                'pushups' => $physical['pushups'] ?? null,
            ],
            'athletic' => [
                '40_yard_dash' => $physical['40_yard_dash'] ?? null,
                '60_yard_dash' => $physical['60_yard_dash'] ?? null,
                'broad_jump' => $this->firstNumber([$physical['broad_jump'] ?? null, $assessment['broad_jump'] ?? null]),
                'vertical_jump' => $this->firstNumber([$physical['vertical_jump'] ?? null, $assessment['vertical_jump'] ?? null]),
            ],
            'pitching' => [
                'average_fastball_velocity' => $this->firstNumber([$bullpen['avg_pitch_velocity'] ?? null, $assessment['baseline_pitch_velocity'] ?? null, $physical['pitch_velocity'] ?? null]),
                'max_fastball_velocity' => $this->firstNumber([$bullpen['max_pitch_velocity'] ?? null, $assessment['baseline_pitch_velocity'] ?? null, $physical['pitch_velocity'] ?? null]),
                'strike_percentage' => $bullpen['strike_rate'] ?? null,
                'long_toss_max_distance' => $longToss['max_distance'] ?? null,
                'weighted_ball_5oz_velocity' => $weighted['five_oz_max_velocity'] ?? null,
            ],
            'hitting' => [
                'average_exit_velocity' => $avgEv,
                'max_exit_velocity' => $maxEv,
                'hard_hit_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['hardHitPercentage', 'hard_hit_percentage', 'hardContactRate', 'hard_contact_rate']),
                'line_drive_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['lineDrivePercentage', 'line_drive_percentage', 'ldPercentage', 'ld_percentage']),
                'hitter_swing_miss_percentage' => $this->firstNestedNumber($batting['score_breakdown'] ?? [], ['swingMissPercentage', 'swing_miss_percentage', 'missRate', 'miss_rate']),
            ],
            'mobility' => [
                'mobility_score' => $this->firstNumber([$physical['mobility_score'] ?? null, $assessment['mobility_overall_score'] ?? null]),
                'shoulder_mobility_score' => $assessment['shoulder_mobility_score'] ?? null,
                'hip_mobility_score' => $assessment['hip_mobility_score'] ?? null,
                't_spine_mobility_score' => $assessment['t_spine_mobility_score'] ?? null,
            ],
        ];
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

        foreach ($metrics as $category => $categoryMetrics) {
            foreach ($categoryMetrics as $metric => $value) {
                if ($this->numberOrNull($value) === null) {
                    $gaps[] = [
                        'source' => $category,
                        'missing_field' => $metric,
                        'impact' => 'Age-adjusted benchmark unavailable for '.$metric.'.',
                        'recommended_collection_action' => 'Collect '.$metric.' for this player.',
                    ];
                }
            }
        }

        return $gaps;
    }

    private function label(float $value, array $thresholds, string $direction): string
    {
        if ($direction === 'lower') {
            return match (true) {
                $value <= (float) $thresholds['elite'] => 'Elite',
                $value <= (float) $thresholds['good'] => 'Good',
                $value <= (float) $thresholds['average'] => 'Average',
                $value <= (float) $thresholds['below_average'] => 'Below Average',
                default => 'Critical',
            };
        }

        return match (true) {
            $value >= (float) $thresholds['elite'] => 'Elite',
            $value >= (float) $thresholds['good'] => 'Good',
            $value >= (float) $thresholds['average'] => 'Average',
            $value >= (float) $thresholds['below_average'] => 'Below Average',
            default => 'Critical',
        };
    }

    private function percentileEstimate(float $value, array $thresholds, string $direction): int
    {
        $anchors = [
            ['value' => (float) $thresholds['critical'], 'percentile' => 5],
            ['value' => (float) $thresholds['below_average'], 'percentile' => 25],
            ['value' => (float) $thresholds['average'], 'percentile' => 50],
            ['value' => (float) $thresholds['good'], 'percentile' => 75],
            ['value' => (float) $thresholds['elite'], 'percentile' => 95],
        ];

        if ($direction === 'lower') {
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

    private function gap(float $value, float $target, string $direction): float
    {
        $gap = $direction === 'lower' ? $value - $target : $target - $value;

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
            if ($number !== null) {
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
}
