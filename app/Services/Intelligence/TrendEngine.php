<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class TrendEngine
{
    private const METRIC_THRESHOLDS = [
        'bullpen_avg_velocity' => 0.5,
        'strike_percentage' => 2.0,
        'cage_avg_ev' => 0.5,
        'exit_velocity_avg' => 0.5,
        'weighted_ball_avg_velocity' => 0.5,
        'long_toss_avg_distance' => 10.0,
        'batting_avg_ev' => 0.5,
        'bullpen_max_velocity' => 0.5,
        'exit_velocity_max' => 0.5,
        'long_toss_max_distance' => 10.0,
        'weighted_ball_5oz_max_velocity' => 0.5,
        'strength_score' => 3.0,
        'mobility_score' => 3.0,
        'recovery_score' => 3.0,
    ];

    public function analyze(array $trendBlocks, array $assembled = []): array
    {
        $normalized = $this->applyCurrentFallbacks($trendBlocks, $assembled);

        if (! isset($normalized['strike_percentage'])) {
            $normalized['strike_percentage'] = $this->staticMetric(
                $assembled['bullpen_summary']['strike_rate'] ?? null,
                $assembled['bullpen_summary']['result_count'] ?? null
            );
        }

        return collect($normalized)
            ->map(fn (array $block, string $metric) => $this->analyzeMetric($metric, $block))
            ->all();
    }

    private function applyCurrentFallbacks(array $trendBlocks, array $assembled): array
    {
        $fallbacks = [
            'batting_avg_ev' => [
                'value' => $assembled['batting_summary']['avg_exit_velocity'] ?? null,
                'count' => $assembled['batting_summary']['result_count'] ?? null,
            ],
            'bullpen_avg_velocity' => [
                'value' => $assembled['bullpen_summary']['avg_pitch_velocity'] ?? null,
                'count' => $assembled['bullpen_summary']['result_count'] ?? null,
            ],
            'cage_avg_ev' => [
                'value' => $assembled['cage_summary']['avg_exit_velocity'] ?? null,
                'count' => $assembled['cage_summary']['result_count'] ?? null,
            ],
            'exit_velocity_avg' => [
                'value' => $assembled['exit_velocity_summary']['avg_exit_velocity'] ?? null,
                'count' => $assembled['exit_velocity_summary']['result_count'] ?? null,
            ],
            'weighted_ball_avg_velocity' => [
                'value' => $this->weightedBallAverage($assembled['weighted_ball_summary']['velocity_by_weight'] ?? []),
                'count' => $assembled['weighted_ball_summary']['total_throws'] ?? null,
            ],
            'long_toss_avg_distance' => [
                'value' => $assembled['long_toss_summary']['avg_distance'] ?? null,
                'count' => $assembled['long_toss_summary']['result_count'] ?? null,
            ],
            'bullpen_max_velocity' => [
                'value' => $assembled['bullpen_summary']['max_pitch_velocity'] ?? null,
                'count' => $assembled['bullpen_summary']['result_count'] ?? null,
            ],
            'exit_velocity_max' => [
                'value' => $this->maxNumber([
                    $assembled['exit_velocity_summary']['max_exit_velocity'] ?? null,
                    $assembled['batting_summary']['max_exit_velocity'] ?? null,
                    $assembled['cage_summary']['max_exit_velocity'] ?? null,
                ]),
                'count' => max(
                    (int) ($assembled['exit_velocity_summary']['result_count'] ?? 0),
                    (int) ($assembled['batting_summary']['result_count'] ?? 0),
                    (int) ($assembled['cage_summary']['result_count'] ?? 0),
                ),
            ],
            'long_toss_max_distance' => [
                'value' => $assembled['long_toss_summary']['max_distance'] ?? null,
                'count' => $assembled['long_toss_summary']['result_count'] ?? null,
            ],
            'weighted_ball_5oz_max_velocity' => [
                'value' => $assembled['weighted_ball_summary']['five_oz_max_velocity'] ?? null,
                'count' => $assembled['weighted_ball_summary']['total_throws'] ?? null,
            ],
            'strength_score' => [
                'value' => $assembled['physical_development']['strength_score'] ?? $assembled['assessment_summary']['strength_overall_score'] ?? null,
                'count' => ($assembled['physical_development']['strength_score'] ?? $assembled['assessment_summary']['strength_overall_score'] ?? null) !== null ? 1 : 0,
                'previous' => $assembled['physical_development']['trend']['strength_score']['previous'] ?? null,
            ],
            'mobility_score' => [
                'value' => $assembled['physical_development']['mobility_score'] ?? $assembled['assessment_summary']['mobility_overall_score'] ?? null,
                'count' => ($assembled['physical_development']['mobility_score'] ?? $assembled['assessment_summary']['mobility_overall_score'] ?? null) !== null ? 1 : 0,
                'previous' => $assembled['physical_development']['trend']['mobility_score']['previous'] ?? null,
            ],
            'recovery_score' => [
                'value' => $assembled['physical_development']['recovery_score'] ?? null,
                'count' => ($assembled['physical_development']['recovery_score'] ?? null) !== null ? 1 : 0,
                'previous' => $assembled['physical_development']['trend']['recovery_score']['previous'] ?? null,
            ],
        ];

        foreach ($fallbacks as $metric => $fallback) {
            if (($trendBlocks[$metric]['current'] ?? null) !== null) {
                continue;
            }

            $trendBlocks[$metric] = array_merge(
                $trendBlocks[$metric] ?? [],
                [
                    'current' => $this->numberOrNull($fallback['value']),
                    'previous' => $this->numberOrNull($fallback['previous'] ?? ($trendBlocks[$metric]['previous'] ?? null)),
                    'current_count' => is_numeric($fallback['count']) ? (int) $fallback['count'] : 0,
                ]
            );
        }

        return $trendBlocks;
    }

    private function weightedBallAverage(array $rows): ?float
    {
        $weightedTotal = 0.0;
        $throws = 0;

        foreach ($rows as $row) {
            if (! is_numeric($row['avg_velocity'] ?? null) || ! is_numeric($row['throws'] ?? null)) {
                continue;
            }

            $weightedTotal += (float) $row['avg_velocity'] * (int) $row['throws'];
            $throws += (int) $row['throws'];
        }

        return $throws > 0 ? round($weightedTotal / $throws, 1) : null;
    }

    private function analyzeMetric(string $metric, array $block): array
    {
        $current = $this->numberOrNull($block['current'] ?? null);
        $previous = $this->numberOrNull($block['previous'] ?? null);
        $currentCount = (int) ($block['current_count'] ?? 0);
        $previousCount = (int) ($block['previous_count'] ?? 0);
        $sampleSize = $currentCount + $previousCount;

        if ($current === null) {
            return [
                'metric' => $metric,
                'current' => null,
                'previous' => $previous,
                'delta' => null,
                'percent_change' => null,
                'sample_size' => $sampleSize,
                'confidence' => 'low',
                'direction' => 'no_data',
                'evidence' => [
                    'reason' => 'No current value available.',
                    'current_count' => $currentCount,
                    'previous_count' => $previousCount,
                ],
            ];
        }

        if ($previous === null) {
            return [
                'metric' => $metric,
                'current' => $current,
                'previous' => null,
                'delta' => null,
                'percent_change' => null,
                'sample_size' => $sampleSize,
                'confidence' => $this->confidence($sampleSize, false),
                'direction' => 'stable',
                'evidence' => [
                    'reason' => 'Current value exists, but previous comparison value is missing.',
                    'current_count' => $currentCount,
                    'previous_count' => $previousCount,
                ],
            ];
        }

        $delta = round($current - $previous, 2);
        $percentChange = $previous != 0.0 ? round(($delta / abs($previous)) * 100, 1) : null;
        $threshold = self::METRIC_THRESHOLDS[$metric] ?? 1.0;

        $direction = match (true) {
            abs($delta) < $threshold => 'stable',
            $delta > 0 => 'improving',
            default => 'declining',
        };

        return [
            'metric' => $metric,
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'percent_change' => $percentChange,
            'sample_size' => $sampleSize,
            'confidence' => $this->confidence($sampleSize, true),
            'direction' => $direction,
            'evidence' => [
                'threshold_for_stable' => $threshold,
                'current_count' => $currentCount,
                'previous_count' => $previousCount,
            ],
        ];
    }

    private function staticMetric(mixed $current, mixed $sampleSize): array
    {
        return [
            'current' => $this->numberOrNull($current),
            'previous' => null,
            'delta' => null,
            'direction' => null,
            'current_count' => is_numeric($sampleSize) ? (int) $sampleSize : 0,
            'previous_count' => 0,
        ];
    }

    private function confidence(int $sampleSize, bool $hasComparison): string
    {
        if ($hasComparison && $sampleSize >= 30) {
            return 'high';
        }

        if ($sampleSize >= 12) {
            return 'medium';
        }

        return 'low';
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function maxNumber(array $values): ?float
    {
        $numbers = array_values(array_filter(
            array_map(fn (mixed $value) => $this->numberOrNull($value), $values),
            fn (?float $value) => $value !== null
        ));

        return count($numbers) ? max($numbers) : null;
    }
}
