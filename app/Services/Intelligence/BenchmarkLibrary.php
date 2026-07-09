<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class BenchmarkLibrary
{
    public function all(): array
    {
        return collect($this->definitions())
            ->mapWithKeys(fn (BenchmarkMetricDefinition $definition) => [
                $definition->metricKey => $definition->toArray(),
            ])
            ->all();
    }

    public function metric(string $metricKey): ?array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $definition = $this->definitions()[$metricKey] ?? null;

        return $definition?->toArray();
    }

    public function metricKeys(): array
    {
        return array_keys($this->definitions());
    }

    public function percentileAnchors(string $metricKey, string $ageGroup): ?array
    {
        $metric = $this->metric($metricKey);
        $anchors = $metric['age_percentile_anchors'][$ageGroup] ?? null;

        return is_array($anchors) ? $anchors : null;
    }

    public function legacyDefinition(string $metricKey): ?array
    {
        $metric = $this->metric($metricKey);
        if (! $metric) {
            return null;
        }

        $benchmarks = [];
        foreach ($metric['age_percentile_anchors'] as $ageGroup => $anchors) {
            $benchmarks[$ageGroup] = [
                'critical' => $anchors['p5'],
                'below_average' => $anchors['p25'],
                'average' => $anchors['p50'],
                'good' => $anchors['p75'],
                'elite' => $anchors['p95'],
                'higher_is_better' => $metric['higher_is_better'],
                'unit' => $metric['unit'],
            ];
        }

        return [
            'direction' => $metric['higher_is_better'] ? 'higher' : 'lower',
            'higher_is_better' => $metric['higher_is_better'],
            'unit' => $metric['unit'],
            'benchmarks' => $benchmarks,
            'metadata' => $metric,
        ];
    }

    public function bucketKey(array $context): string
    {
        return implode('|', [
            'age:' . strtolower((string) ($context['age_group'] ?? BenchmarkDefinitions::AGE_UNKNOWN)),
            'level:' . strtolower((string) ($context['level'] ?? 'unknown')),
            'position:' . strtolower($this->normalizeList($context['position'] ?? $context['positions'] ?? 'unknown')),
            'body:' . $this->bodyWeightBand($context['body_weight'] ?? null),
            'height:' . $this->heightBand($context['height_inches'] ?? $context['height'] ?? null),
            'throws:' . strtolower((string) ($context['throws'] ?? $context['throw_side'] ?? 'unknown')),
            'bats:' . strtolower((string) ($context['bats'] ?? $context['hit_side'] ?? 'unknown')),
        ]);
    }

    /**
     * @return array<string, BenchmarkMetricDefinition>
     */
    private function definitions(): array
    {
        return [
            'average_fastball_velocity' => $this->metricDefinition('average_fastball_velocity', 'Average Fastball Velocity', 'pitching', 'mph', true, 0.95, 'medium', [
                [48, 54, 60, 66, 72],
                [58, 64, 70, 76, 82],
                [66, 72, 78, 84, 89],
                [72, 78, 84, 88, 92],
                [76, 82, 87, 91, 95],
            ]),
            'max_fastball_velocity' => $this->metricDefinition('max_fastball_velocity', 'Max Fastball Velocity', 'pitching', 'mph', true, 1.0, 'medium', [
                [50, 56, 62, 68, 74],
                [60, 66, 72, 78, 84],
                [68, 74, 80, 86, 91],
                [74, 80, 86, 90, 94],
                [78, 84, 89, 93, 97],
            ]),
            'strike_percentage' => $this->metricDefinition('strike_percentage', 'Strike Percentage', 'pitching', '%', true, 0.9, 'medium', [
                [40, 48, 55, 62, 70],
                [45, 52, 60, 66, 73],
                [48, 55, 63, 69, 75],
                [50, 58, 65, 71, 78],
                [52, 60, 66, 72, 80],
            ]),
            'long_toss_max_distance' => $this->metricDefinition('long_toss_max_distance', 'Long Toss Max Distance', 'pitching', 'ft', true, 0.75, 'low', [
                [90, 120, 150, 180, 220],
                [130, 170, 210, 250, 290],
                [170, 220, 270, 320, 370],
                [210, 270, 330, 390, 450],
                [240, 300, 360, 420, 500],
            ]),
            'weighted_ball_5oz_velocity' => $this->metricDefinition('weighted_ball_5oz_velocity', 'Weighted Ball 5 oz Velocity', 'pitching', 'mph', true, 0.85, 'low', [
                [50, 56, 62, 68, 74],
                [60, 66, 72, 78, 84],
                [68, 74, 80, 86, 91],
                [74, 80, 86, 90, 94],
                [78, 84, 89, 93, 97],
            ]),
            'average_exit_velocity' => $this->metricDefinition('average_exit_velocity', 'Average Exit Velocity', 'hitting', 'mph', true, 0.9, 'medium', [
                [50, 57, 64, 71, 78],
                [60, 68, 76, 84, 92],
                [68, 76, 84, 92, 100],
                [74, 82, 90, 98, 106],
                [78, 86, 94, 102, 110],
            ]),
            'max_exit_velocity' => $this->metricDefinition('max_exit_velocity', 'Max Exit Velocity', 'hitting', 'mph', true, 0.95, 'medium', [
                [55, 62, 70, 78, 86],
                [65, 73, 82, 91, 99],
                [74, 82, 91, 100, 108],
                [80, 88, 97, 105, 112],
                [84, 92, 100, 108, 116],
            ]),
            'hard_hit_percentage' => $this->metricDefinition('hard_hit_percentage', 'Hard Hit Percentage', 'hitting', '%', true, 0.8, 'low', [
                [10, 18, 28, 38, 50],
                [14, 24, 35, 48, 60],
                [18, 30, 42, 55, 68],
                [22, 34, 46, 60, 72],
                [25, 38, 50, 64, 76],
            ]),
            'line_drive_percentage' => $this->metricDefinition('line_drive_percentage', 'Line Drive Percentage', 'hitting', '%', true, 0.75, 'low', [
                [12, 18, 25, 32, 40],
                [14, 20, 28, 36, 44],
                [16, 23, 31, 39, 48],
                [18, 25, 33, 42, 50],
                [20, 27, 35, 44, 52],
            ]),
            'hitter_swing_miss_percentage' => $this->metricDefinition('hitter_swing_miss_percentage', 'Hitter Swing Miss Percentage', 'hitting', '%', false, 0.85, 'low', [
                [35, 28, 22, 16, 10],
                [30, 24, 18, 13, 8],
                [26, 20, 15, 10, 6],
                [22, 17, 12, 8, 5],
                [20, 15, 10, 7, 4],
            ]),
            'bench_press' => $this->metricDefinition('bench_press', 'Bench Press', 'strength', 'lbs', true, 0.75, 'low', [
                [45, 65, 85, 105, 130],
                [75, 95, 115, 135, 165],
                [95, 125, 155, 185, 225],
                [115, 155, 185, 225, 275],
                [135, 175, 215, 255, 315],
            ]),
            'squat' => $this->metricDefinition('squat', 'Squat', 'strength', 'lbs', true, 0.8, 'low', [
                [75, 105, 135, 165, 205],
                [105, 145, 185, 225, 275],
                [135, 185, 235, 285, 345],
                [165, 225, 285, 345, 415],
                [185, 255, 325, 395, 475],
            ]),
            'deadlift' => $this->metricDefinition('deadlift', 'Deadlift', 'strength', 'lbs', true, 0.8, 'low', [
                [95, 125, 155, 195, 235],
                [125, 165, 215, 265, 315],
                [155, 225, 285, 345, 415],
                [185, 275, 345, 425, 500],
                [225, 315, 405, 495, 585],
            ]),
            'pull_ups' => $this->metricDefinition('pull_ups', 'Pull Ups', 'strength', 'reps', true, 0.65, 'low', [
                [0, 2, 4, 7, 10],
                [1, 3, 6, 10, 14],
                [2, 5, 8, 12, 16],
                [3, 6, 10, 14, 18],
                [4, 7, 11, 15, 20],
            ]),
            'pushups' => $this->metricDefinition('pushups', 'Pushups', 'strength', 'reps', true, 0.55, 'low', [
                [8, 15, 25, 35, 50],
                [12, 22, 35, 50, 65],
                [18, 30, 45, 60, 80],
                [22, 35, 50, 70, 90],
                [25, 40, 58, 78, 100],
            ]),
            'forty_yard_dash' => $this->metricDefinition('forty_yard_dash', '40 Yard Dash', 'athletic', 'sec', false, 0.65, 'low', [
                [6.50, 6.10, 5.80, 5.50, 5.20],
                [6.05, 5.70, 5.35, 5.05, 4.85],
                [5.70, 5.35, 5.05, 4.80, 4.60],
                [5.45, 5.15, 4.90, 4.65, 4.45],
                [5.30, 5.00, 4.75, 4.55, 4.35],
            ]),
            'sixty_yard_dash' => $this->metricDefinition('sixty_yard_dash', '60 Yard Dash', 'athletic', 'sec', false, 0.75, 'low', [
                [9.40, 8.90, 8.50, 8.10, 7.70],
                [8.80, 8.35, 7.95, 7.55, 7.20],
                [8.35, 7.95, 7.55, 7.20, 6.90],
                [8.00, 7.60, 7.25, 6.95, 6.65],
                [7.80, 7.45, 7.10, 6.85, 6.55],
            ]),
            'broad_jump' => $this->metricDefinition('broad_jump', 'Broad Jump', 'athletic', 'in', true, 0.6, 'low', [
                [50, 60, 70, 80, 92],
                [60, 72, 84, 96, 108],
                [68, 80, 92, 104, 116],
                [74, 86, 98, 112, 124],
                [78, 90, 102, 116, 130],
            ]),
            'vertical_jump' => $this->metricDefinition('vertical_jump', 'Vertical Jump', 'athletic', 'in', true, 0.6, 'low', [
                [10, 14, 18, 22, 26],
                [14, 18, 22, 26, 30],
                [16, 20, 24, 28, 32],
                [18, 22, 26, 30, 35],
                [19, 23, 27, 32, 38],
            ]),
            'mobility_score' => $this->mobilityDefinition('mobility_score', 'Mobility Score'),
            'shoulder_mobility_score' => $this->mobilityDefinition('shoulder_mobility_score', 'Shoulder Mobility Score'),
            'hip_mobility_score' => $this->mobilityDefinition('hip_mobility_score', 'Hip Mobility Score'),
            't_spine_mobility_score' => $this->mobilityDefinition('t_spine_mobility_score', 'T-Spine Mobility Score'),
        ];
    }

    private function metricDefinition(string $metricKey, string $displayName, string $category, ?string $unit, bool $higherIsBetter, float $importanceWeight, string $researchConfidence, array $thresholdRows): BenchmarkMetricDefinition
    {
        $ageGroups = [
            BenchmarkDefinitions::AGE_10U_12U,
            BenchmarkDefinitions::AGE_13U_14U,
            BenchmarkDefinitions::AGE_15U_16U,
            BenchmarkDefinitions::AGE_17U_18U,
            BenchmarkDefinitions::AGE_COLLEGE_19_PLUS,
        ];
        $anchors = [];

        foreach ($ageGroups as $index => $ageGroup) {
            $anchors[$ageGroup] = $this->anchorsFromThresholds($thresholdRows[$index], $higherIsBetter);
        }

        return new BenchmarkMetricDefinition(
            metricKey: $metricKey,
            displayName: $displayName,
            category: $category,
            unit: $unit,
            higherIsBetter: $higherIsBetter,
            importanceWeight: $importanceWeight,
            researchConfidence: $researchConfidence,
            populationConfidence: 'insufficient',
            minimumPopulationSample: PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            lastReviewed: '2026-07-09',
            sourceType: 'manual_research_estimate',
            evidenceNotes: [
                'Phase 1 manual benchmark library.',
                'Percentile anchors are research-informed operational estimates and should be replaced or blended with FMTRX population data when bucket sizes are sufficient.',
            ],
            agePercentileAnchors: $anchors,
        );
    }

    private function mobilityDefinition(string $metricKey, string $displayName): BenchmarkMetricDefinition
    {
        return $this->metricDefinition($metricKey, $displayName, 'mobility', 'score', true, 0.7, 'low', [
            [45, 55, 65, 78, 90],
            [45, 55, 65, 78, 90],
            [45, 55, 65, 78, 90],
            [45, 55, 65, 78, 90],
            [45, 55, 65, 78, 90],
        ]);
    }

    private function anchorsFromThresholds(array $thresholds, bool $higherIsBetter): array
    {
        [$critical, $belowAverage, $average, $good, $elite] = array_map('floatval', $thresholds);

        if ($higherIsBetter) {
            return [
                'p1' => round($critical - (($belowAverage - $critical) * 0.5), 1),
                'p5' => $critical,
                'p10' => round(($critical + $belowAverage) / 2, 1),
                'p25' => $belowAverage,
                'p50' => $average,
                'p75' => $good,
                'p90' => round(($good + $elite) / 2, 1),
                'p95' => $elite,
                'p99' => round($elite + (($elite - $good) * 0.5), 1),
            ];
        }

        return [
            'p1' => round($critical + (($critical - $belowAverage) * 0.5), 1),
            'p5' => $critical,
            'p10' => round(($critical + $belowAverage) / 2, 1),
            'p25' => $belowAverage,
            'p50' => $average,
            'p75' => $good,
            'p90' => round(($good + $elite) / 2, 1),
            'p95' => $elite,
            'p99' => round($elite - (($good - $elite) * 0.5), 1),
        ];
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

    private function heightBand(mixed $value): string
    {
        if (! is_numeric($value) || (float) $value <= 0) {
            return 'unknown';
        }

        $height = (float) $value;

        return match (true) {
            $height < 63 => 'under_63',
            $height < 66 => '63_65',
            $height < 69 => '66_68',
            $height < 72 => '69_71',
            $height < 75 => '72_74',
            default => '75_plus',
        };
    }
}
