<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class ProjectionEngine
{
    private const GAIN_CAPS_90_DAYS = [
        'bullpen_avg_velocity' => 5.0,
        'weighted_ball_avg_velocity' => 5.0,
        'cage_avg_ev' => 8.0,
        'exit_velocity_avg' => 8.0,
        'batting_avg_ev' => 8.0,
        'strike_percentage' => 12.0,
        'long_toss_avg_distance' => 60.0,
        'bullpen_max_velocity' => 5.0,
        'exit_velocity_max' => 8.0,
        'long_toss_max_distance' => 60.0,
        'weighted_ball_5oz_max_velocity' => 5.0,
        'strength_score' => 10.0,
        'mobility_score' => 10.0,
        'recovery_score' => 10.0,
    ];

    public function project(array $trends, array $assembled = [], array $ageBenchmarks = []): array
    {
        return collect($this->ensureMetricSet($trends, $assembled))
            ->map(fn (array $trend, string $metric) => $this->projectMetric($metric, $trend, $ageBenchmarks))
            ->all();
    }

    private function ensureMetricSet(array $trends, array $assembled): array
    {
        $currentOnly = [
            'bullpen_avg_velocity' => $assembled['bullpen_summary']['avg_pitch_velocity'] ?? null,
            'bullpen_max_velocity' => $assembled['bullpen_summary']['max_pitch_velocity'] ?? null,
            'strike_percentage' => $assembled['bullpen_summary']['strike_rate'] ?? null,
            'exit_velocity_avg' => $assembled['exit_velocity_summary']['avg_exit_velocity'] ?? null,
            'exit_velocity_max' => $this->maxNumber([
                $assembled['exit_velocity_summary']['max_exit_velocity'] ?? null,
                $assembled['batting_summary']['max_exit_velocity'] ?? null,
                $assembled['cage_summary']['max_exit_velocity'] ?? null,
            ]),
            'long_toss_avg_distance' => $assembled['long_toss_summary']['avg_distance'] ?? null,
            'long_toss_max_distance' => $assembled['long_toss_summary']['max_distance'] ?? null,
            'weighted_ball_avg_velocity' => $trends['weighted_ball_avg_velocity']['current'] ?? null,
            'weighted_ball_5oz_max_velocity' => $assembled['weighted_ball_summary']['five_oz_max_velocity'] ?? null,
            'strength_score' => $assembled['physical_development']['strength_score'] ?? $assembled['assessment_summary']['strength_overall_score'] ?? null,
            'mobility_score' => $assembled['physical_development']['mobility_score'] ?? $assembled['assessment_summary']['mobility_overall_score'] ?? null,
            'recovery_score' => $assembled['physical_development']['recovery_score'] ?? null,
        ];

        foreach ($currentOnly as $metric => $current) {
            if (isset($trends[$metric])) {
                continue;
            }

            $trends[$metric] = [
                'metric' => $metric,
                'current' => $this->numberOrNull($current),
                'previous' => null,
                'delta' => null,
                'percent_change' => null,
                'sample_size' => $this->numberOrNull($current) !== null ? 1 : 0,
                'confidence' => 'low',
                'direction' => $this->numberOrNull($current) !== null ? 'stable' : 'no_data',
                'evidence' => [
                    'reason' => 'Current value assembled without comparison history.',
                ],
            ];
        }

        return $trends;
    }

    private function projectMetric(string $metric, array $trend, array $ageBenchmarks = []): array
    {
        $current = $this->numberOrNull($trend['current'] ?? null);
        $delta = $this->numberOrNull($trend['delta'] ?? null);
        $confidence = $trend['confidence'] ?? 'low';
        $benchmark = $this->benchmarkForProjection($metric, $ageBenchmarks);

        if ($current === null || ($trend['direction'] ?? null) === 'no_data') {
            return [
                'metric' => $metric,
                'current' => $current,
                'projected_30_day' => null,
                'projected_60_day' => null,
                'projected_90_day' => null,
                'confidence' => 'low',
                'evidence' => [
                    'reason' => 'Projection requires current and previous comparison data.',
                    'trend' => $trend,
                    'age_benchmark' => $benchmark,
                ],
            ];
        }

        if ($delta === null) {
            return [
                'metric' => $metric,
                'current' => $current,
                'projected_30_day' => $current,
                'projected_60_day' => $current,
                'projected_90_day' => $current,
                'confidence' => 'low',
                'evidence' => [
                    'reason' => 'Current value exists, but no previous comparison exists. Projection holds current value until trend history is available.',
                    'current' => $current,
                    'trend_direction' => $trend['direction'] ?? null,
                    'sample_size' => $trend['sample_size'] ?? null,
                    'age_benchmark' => $benchmark,
                ],
            ];
        }

        $cap90 = self::GAIN_CAPS_90_DAYS[$metric] ?? 10.0;
        $gain90 = max(-$cap90, min($cap90, $delta * 3));

        return [
            'metric' => $metric,
            'current' => $current,
            'projected_30_day' => round($current + ($gain90 / 3), 1),
            'projected_60_day' => round($current + (($gain90 / 3) * 2), 1),
            'projected_90_day' => round($current + $gain90, 1),
            'confidence' => $confidence,
            'evidence' => [
                'current' => $current,
                '30_day_delta_used' => round($gain90 / 3, 2),
                '90_day_gain_cap' => $cap90,
                'trend_delta' => $delta,
                'trend_direction' => $trend['direction'] ?? null,
                'sample_size' => $trend['sample_size'] ?? null,
                'age_benchmark' => $benchmark,
            ],
        ];
    }

    private function benchmarkForProjection(string $metric, array $ageBenchmarks): ?array
    {
        $map = [
            'bullpen_avg_velocity' => ['pitching', 'average_fastball_velocity'],
            'bullpen_max_velocity' => ['pitching', 'max_fastball_velocity'],
            'strike_percentage' => ['pitching', 'strike_percentage'],
            'long_toss_max_distance' => ['pitching', 'long_toss_max_distance'],
            'weighted_ball_5oz_max_velocity' => ['pitching', 'weighted_ball_5oz_velocity'],
            'exit_velocity_avg' => ['hitting', 'average_exit_velocity'],
            'batting_avg_ev' => ['hitting', 'average_exit_velocity'],
            'cage_avg_ev' => ['hitting', 'average_exit_velocity'],
            'exit_velocity_max' => ['hitting', 'max_exit_velocity'],
            'mobility_score' => ['mobility', 'mobility_score'],
        ];

        [$category, $metricKey] = $map[$metric] ?? [null, null];
        if (! $category || ! $metricKey) {
            return null;
        }

        $benchmark = $ageBenchmarks['metrics'][$category][$metricKey] ?? null;

        return is_array($benchmark) ? $benchmark : null;
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
