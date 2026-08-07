<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class BenchmarkDefinitions
{
    public const AGE_10U_12U = '10U_12U';
    public const AGE_13U_14U = '13U_14U';
    public const AGE_15U_16U = '15U_16U';
    public const AGE_17U_18U = '17U_18U';
    public const AGE_COLLEGE_19_PLUS = 'COLLEGE_19_PLUS';
    public const AGE_UNKNOWN = 'UNKNOWN';

    public const AGE_GROUPS = [
        self::AGE_10U_12U,
        self::AGE_13U_14U,
        self::AGE_15U_16U,
        self::AGE_17U_18U,
        self::AGE_COLLEGE_19_PLUS,
        self::AGE_UNKNOWN,
    ];

    public static function ageGroup(?int $age): string
    {
        return match (true) {
            null === $age => self::AGE_UNKNOWN,
            $age <= 12 => self::AGE_10U_12U,
            $age <= 14 => self::AGE_13U_14U,
            $age <= 16 => self::AGE_15U_16U,
            $age <= 18 => self::AGE_17U_18U,
            default => self::AGE_COLLEGE_19_PLUS,
        };
    }

    /**
     * Resolve an age benchmark group from levels that identify a specific
     * competition stage. Ambiguous levels (for example travel or club) must
     * not be assigned an age group because those teams span multiple bands.
     */
    public static function ageGroupFromLevel(mixed $level): string
    {
        $level = mb_strtolower(trim((string) $level));
        $level = str_replace(['-', ' '], '_', $level);

        return match ($level) {
            'mid', 'ms', 'middle', 'middle_school', 'junior_high', 'jr_high' => self::AGE_13U_14U,
            'high', 'hs', 'highschool', 'high_school', 'varsity' => self::AGE_17U_18U,
            'juco', 'd1', 'd2', 'd3', 'ncaa', 'naia', 'college',
            'pro', 'professional', 'milb' => self::AGE_COLLEGE_19_PLUS,
            default => self::AGE_UNKNOWN,
        };
    }

    public static function metricDefinition(string $metricKey): ?array
    {
        $metricKey = self::normalizeMetricKey($metricKey);

        foreach (self::definitions() as $category => $metrics) {
            if (isset($metrics[$metricKey])) {
                return $metrics[$metricKey] + [
                    'category' => $category,
                    'metric_key' => $metricKey,
                ];
            }
        }

        return null;
    }

    public static function categoryForMetric(string $metricKey): ?string
    {
        return self::metricDefinition($metricKey)['category'] ?? null;
    }

    public static function normalizeMetricKey(string $metricKey): string
    {
        return match ($metricKey) {
            '40_yard_dash', 'forty_yard_sec', 'yd_40_dash' => 'forty_yard_dash',
            '60_yard_dash', 'sixty_yard_sec', 'yd_60_dash' => 'sixty_yard_dash',
            'push_ups' => 'pushups',
            'dead_lift', 'conventional_deadlift' => 'deadlift',
            'trap_bar', 'trapbar_deadlift', 'trap_bar_deadlift_lbs' => 'trap_bar_deadlift',
            'front_squat_lbs' => 'front_squat',
            'back_squat_lbs' => 'back_squat',
            'power_clean_lbs' => 'power_clean',
            'grip_left', 'hand_strength_left' => 'grip_strength_left',
            'grip_right', 'hand_strength_right' => 'grip_strength_right',
            'plank_hold_sec' => 'plank_hold',
            'sprint_10yd_sec', 'ten_yard_sec', '10_yard_dash' => 'sprint_10yd',
            'avg_fastball', 'avg_fastball_velocity', 'avg_pitch_velocity', 'bullpen_avg_velocity' => 'average_fastball_velocity',
            'max_fastball', 'max_pitch_velocity', 'bullpen_max_velocity' => 'max_fastball_velocity',
            'strike_pct' => 'strike_percentage',
            'avg_ev', 'avg_exit_velocity', 'exit_velocity_avg', 'batting_avg_ev', 'cage_avg_ev' => 'average_exit_velocity',
            'max_ev', 'exit_velocity_max' => 'max_exit_velocity',
            'forty' => 'forty_yard_dash',
            'sixty' => 'sixty_yard_dash',
            default => $metricKey,
        };
    }

    public static function definitions(): array
    {
        return [
            'strength' => [
                'bench_press' => self::metric('higher', 'lbs', [
                    self::AGE_10U_12U => [45, 65, 85, 105, 130],
                    self::AGE_13U_14U => [75, 95, 115, 135, 165],
                    self::AGE_15U_16U => [95, 125, 155, 185, 225],
                    self::AGE_17U_18U => [115, 155, 185, 225, 275],
                    self::AGE_COLLEGE_19_PLUS => [135, 175, 215, 255, 315],
                ]),
                'squat' => self::metric('higher', 'lbs', [
                    self::AGE_10U_12U => [75, 105, 135, 165, 205],
                    self::AGE_13U_14U => [105, 145, 185, 225, 275],
                    self::AGE_15U_16U => [135, 185, 235, 285, 345],
                    self::AGE_17U_18U => [165, 225, 285, 345, 415],
                    self::AGE_COLLEGE_19_PLUS => [185, 255, 325, 395, 475],
                ]),
                'front_squat' => self::metric('higher', 'lbs', [
                    [45, 65, 85, 105, 130],
                    [75, 95, 115, 135, 165],
                    [95, 125, 155, 185, 225],
                    [115, 155, 185, 225, 275],
                    [135, 175, 215, 255, 315],
                ]),
                'back_squat' => self::metric('higher', 'lbs', [
                    [75, 105, 135, 165, 205],
                    [105, 145, 185, 225, 275],
                    [135, 185, 235, 285, 345],
                    [165, 225, 285, 345, 415],
                    [185, 255, 325, 395, 475],
                ]),
                'deadlift' => self::metric('higher', 'lbs', [
                    self::AGE_10U_12U => [95, 125, 155, 195, 235],
                    self::AGE_13U_14U => [125, 165, 215, 265, 315],
                    self::AGE_15U_16U => [155, 225, 285, 345, 415],
                    self::AGE_17U_18U => [185, 275, 345, 425, 500],
                    self::AGE_COLLEGE_19_PLUS => [225, 315, 405, 495, 585],
                ]),
                'pull_ups' => self::metric('higher', 'reps', [
                    self::AGE_10U_12U => [0, 2, 4, 7, 10],
                    self::AGE_13U_14U => [1, 3, 6, 10, 14],
                    self::AGE_15U_16U => [2, 5, 8, 12, 16],
                    self::AGE_17U_18U => [3, 6, 10, 14, 18],
                    self::AGE_COLLEGE_19_PLUS => [4, 7, 11, 15, 20],
                ]),
                'pushups' => self::metric('higher', 'reps', [
                    self::AGE_10U_12U => [8, 15, 25, 35, 50],
                    self::AGE_13U_14U => [12, 22, 35, 50, 65],
                    self::AGE_15U_16U => [18, 30, 45, 60, 80],
                    self::AGE_17U_18U => [22, 35, 50, 70, 90],
                    self::AGE_COLLEGE_19_PLUS => [25, 40, 58, 78, 100],
                ]),
            ],
            'athletic' => [
                'forty_yard_dash' => self::metric('lower', 'sec', [
                    self::AGE_10U_12U => [6.50, 6.10, 5.80, 5.50, 5.20],
                    self::AGE_13U_14U => [6.05, 5.70, 5.35, 5.05, 4.85],
                    self::AGE_15U_16U => [5.70, 5.35, 5.05, 4.80, 4.60],
                    self::AGE_17U_18U => [5.45, 5.15, 4.90, 4.65, 4.45],
                    self::AGE_COLLEGE_19_PLUS => [5.30, 5.00, 4.75, 4.55, 4.35],
                ]),
                'sixty_yard_dash' => self::metric('lower', 'sec', [
                    self::AGE_10U_12U => [9.40, 8.90, 8.50, 8.10, 7.70],
                    self::AGE_13U_14U => [8.80, 8.35, 7.95, 7.55, 7.20],
                    self::AGE_15U_16U => [8.35, 7.95, 7.55, 7.20, 6.90],
                    self::AGE_17U_18U => [8.00, 7.60, 7.25, 6.95, 6.65],
                    self::AGE_COLLEGE_19_PLUS => [7.80, 7.45, 7.10, 6.85, 6.55],
                ]),
                'broad_jump' => self::metric('higher', 'in', [
                    self::AGE_10U_12U => [50, 60, 70, 80, 92],
                    self::AGE_13U_14U => [60, 72, 84, 96, 108],
                    self::AGE_15U_16U => [68, 80, 92, 104, 116],
                    self::AGE_17U_18U => [74, 86, 98, 112, 124],
                    self::AGE_COLLEGE_19_PLUS => [78, 90, 102, 116, 130],
                ]),
                'vertical_jump' => self::metric('higher', 'in', [
                    self::AGE_10U_12U => [10, 14, 18, 22, 26],
                    self::AGE_13U_14U => [14, 18, 22, 26, 30],
                    self::AGE_15U_16U => [16, 20, 24, 28, 32],
                    self::AGE_17U_18U => [18, 22, 26, 30, 35],
                    self::AGE_COLLEGE_19_PLUS => [19, 23, 27, 32, 38],
                ]),
            ],
            'pitching' => [
                'average_fastball_velocity' => self::metric('higher', 'mph', [
                    self::AGE_10U_12U => [48, 54, 60, 66, 72],
                    self::AGE_13U_14U => [58, 64, 70, 76, 82],
                    self::AGE_15U_16U => [66, 72, 78, 84, 89],
                    self::AGE_17U_18U => [72, 78, 84, 88, 92],
                    self::AGE_COLLEGE_19_PLUS => [76, 82, 87, 91, 95],
                ]),
                'max_fastball_velocity' => self::metric('higher', 'mph', [
                    self::AGE_10U_12U => [50, 56, 62, 68, 74],
                    self::AGE_13U_14U => [60, 66, 72, 78, 84],
                    self::AGE_15U_16U => [68, 74, 80, 86, 91],
                    self::AGE_17U_18U => [74, 80, 86, 90, 94],
                    self::AGE_COLLEGE_19_PLUS => [78, 84, 89, 93, 97],
                ]),
                'strike_percentage' => self::metric('higher', '%', [
                    self::AGE_10U_12U => [40, 48, 55, 62, 70],
                    self::AGE_13U_14U => [45, 52, 60, 66, 73],
                    self::AGE_15U_16U => [48, 55, 63, 69, 75],
                    self::AGE_17U_18U => [50, 58, 65, 71, 78],
                    self::AGE_COLLEGE_19_PLUS => [52, 60, 66, 72, 80],
                ]),
                'long_toss_max_distance' => self::metric('higher', 'ft', [
                    self::AGE_10U_12U => [90, 120, 150, 180, 220],
                    self::AGE_13U_14U => [130, 170, 210, 250, 290],
                    self::AGE_15U_16U => [170, 220, 270, 320, 370],
                    self::AGE_17U_18U => [210, 270, 330, 390, 450],
                    self::AGE_COLLEGE_19_PLUS => [240, 300, 360, 420, 500],
                ]),
                'weighted_ball_5oz_velocity' => self::metric('higher', 'mph', [
                    self::AGE_10U_12U => [50, 56, 62, 68, 74],
                    self::AGE_13U_14U => [60, 66, 72, 78, 84],
                    self::AGE_15U_16U => [68, 74, 80, 86, 91],
                    self::AGE_17U_18U => [74, 80, 86, 90, 94],
                    self::AGE_COLLEGE_19_PLUS => [78, 84, 89, 93, 97],
                ]),
            ],
            'hitting' => [
                'average_exit_velocity' => self::metric('higher', 'mph', [
                    self::AGE_10U_12U => [50, 57, 64, 71, 78],
                    self::AGE_13U_14U => [60, 68, 76, 84, 92],
                    self::AGE_15U_16U => [68, 76, 84, 92, 100],
                    self::AGE_17U_18U => [74, 82, 90, 98, 106],
                    self::AGE_COLLEGE_19_PLUS => [78, 86, 94, 102, 110],
                ]),
                'max_exit_velocity' => self::metric('higher', 'mph', [
                    self::AGE_10U_12U => [55, 62, 70, 78, 86],
                    self::AGE_13U_14U => [65, 73, 82, 91, 99],
                    self::AGE_15U_16U => [74, 82, 91, 100, 108],
                    self::AGE_17U_18U => [80, 88, 97, 105, 112],
                    self::AGE_COLLEGE_19_PLUS => [84, 92, 100, 108, 116],
                ]),
                'hard_hit_percentage' => self::metric('higher', '%', [
                    self::AGE_10U_12U => [10, 18, 28, 38, 50],
                    self::AGE_13U_14U => [14, 24, 35, 48, 60],
                    self::AGE_15U_16U => [18, 30, 42, 55, 68],
                    self::AGE_17U_18U => [22, 34, 46, 60, 72],
                    self::AGE_COLLEGE_19_PLUS => [25, 38, 50, 64, 76],
                ]),
                'line_drive_percentage' => self::metric('higher', '%', [
                    self::AGE_10U_12U => [12, 18, 25, 32, 40],
                    self::AGE_13U_14U => [14, 20, 28, 36, 44],
                    self::AGE_15U_16U => [16, 23, 31, 39, 48],
                    self::AGE_17U_18U => [18, 25, 33, 42, 50],
                    self::AGE_COLLEGE_19_PLUS => [20, 27, 35, 44, 52],
                ]),
                'hitter_swing_miss_percentage' => self::metric('lower', '%', [
                    self::AGE_10U_12U => [35, 28, 22, 16, 10],
                    self::AGE_13U_14U => [30, 24, 18, 13, 8],
                    self::AGE_15U_16U => [26, 20, 15, 10, 6],
                    self::AGE_17U_18U => [22, 17, 12, 8, 5],
                    self::AGE_COLLEGE_19_PLUS => [20, 15, 10, 7, 4],
                ]),
            ],
            'mobility' => [
                'mobility_score' => self::mobilityMetric(),
                'shoulder_mobility_score' => self::mobilityMetric(),
                'hip_mobility_score' => self::mobilityMetric(),
                't_spine_mobility_score' => self::mobilityMetric(),
            ],
        ];
    }

    private static function mobilityMetric(): array
    {
        return self::metric('higher', 'score', [
            self::AGE_10U_12U => [45, 55, 65, 78, 90],
            self::AGE_13U_14U => [45, 55, 65, 78, 90],
            self::AGE_15U_16U => [45, 55, 65, 78, 90],
            self::AGE_17U_18U => [45, 55, 65, 78, 90],
            self::AGE_COLLEGE_19_PLUS => [45, 55, 65, 78, 90],
        ]);
    }

    private static function metric(string $direction, string $unit, array $values): array
    {
        $benchmarks = [];

        foreach ($values as $ageGroup => $row) {
            $benchmarks[$ageGroup] = [
                'critical' => $row[0],
                'below_average' => $row[1],
                'average' => $row[2],
                'good' => $row[3],
                'elite' => $row[4],
                'higher_is_better' => 'higher' === $direction,
                'unit' => $unit,
            ];
        }

        return [
            'direction' => $direction,
            'higher_is_better' => 'higher' === $direction,
            'unit' => $unit,
            'benchmarks' => $benchmarks,
        ];
    }
}
