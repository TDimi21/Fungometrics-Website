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
    ];

    public function project(array $trends): array
    {
        return collect($trends)
            ->map(fn (array $trend, string $metric) => $this->projectMetric($metric, $trend))
            ->all();
    }

    private function projectMetric(string $metric, array $trend): array
    {
        $current = $this->numberOrNull($trend['current'] ?? null);
        $delta = $this->numberOrNull($trend['delta'] ?? null);
        $confidence = $trend['confidence'] ?? 'low';

        if ($current === null || $delta === null || ($trend['direction'] ?? null) === 'no_data') {
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
            ],
        ];
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
