<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PopulationValueGuardrail
{
    private const RANGES = [
        'average_exit_velocity' => [20, 115],
        'max_exit_velocity' => [30, 125],
        'hard_hit_percentage' => [0, 100],
        'line_drive_percentage' => [0, 100],
        'hitter_swing_miss_percentage' => [0, 100],
        'average_fastball_velocity' => [30, 110],
        'max_fastball_velocity' => [35, 115],
        'strike_percentage' => [0, 100],
        'long_toss_max_distance' => [30, 450],
        'weighted_ball_5oz_velocity' => [30, 115],
        'bench_press' => [25, 450],
        'squat' => [25, 700],
        'deadlift' => [25, 750],
        'pull_ups' => [0, 40],
        'pushups' => [0, 150],
        'forty_yard_dash' => [4.0, 10.0],
        'sixty_yard_dash' => [5.5, 12.0],
        'broad_jump' => [24, 140],
        'vertical_jump' => [5, 45],
        'mobility_score' => [0, 100],
        'shoulder_mobility_score' => [0, 100],
        'hip_mobility_score' => [0, 100],
        't_spine_mobility_score' => [0, 100],
    ];

    private const ZERO_CAN_BE_REAL = [
        'strike_percentage',
        'hard_hit_percentage',
        'line_drive_percentage',
        'hitter_swing_miss_percentage',
        'pull_ups',
        'pushups',
        'mobility_score',
        'shoulder_mobility_score',
        'hip_mobility_score',
        't_spine_mobility_score',
    ];

    public function validate(string $metricKey, mixed $value): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);

        if ($value === null) {
            return $this->invalid($metricKey, $value, 'null_value');
        }

        if (is_string($value) && trim($value) === '') {
            return $this->invalid($metricKey, $value, 'empty_value');
        }

        $number = $this->numberOrNull($value);
        if ($number === null) {
            return $this->invalid($metricKey, $value, 'non_numeric');
        }

        if ($number == 0.0 && ! $this->zeroCanBeReal($metricKey)) {
            return $this->invalid($metricKey, $value, 'zero_placeholder', $number);
        }

        $range = $this->rangeForMetric($metricKey);
        if ($range === null) {
            return $this->invalid($metricKey, $value, 'unknown_metric', $number);
        }

        [$min, $max] = $range;
        if ($number < $min) {
            return $this->invalid($metricKey, $value, 'below_valid_range', $number);
        }

        if ($number > $max) {
            return $this->invalid($metricKey, $value, 'above_valid_range', $number);
        }

        return [
            'included' => true,
            'metric_key' => $metricKey,
            'value' => $number,
            'raw_value' => $value,
            'reason' => null,
            'range' => ['min' => $min, 'max' => $max],
        ];
    }

    public function rangeForMetric(string $metricKey): ?array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);

        return self::RANGES[$metricKey] ?? null;
    }

    public function ranges(): array
    {
        return self::RANGES;
    }

    public function zeroCanBeReal(string $metricKey): bool
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);

        return in_array($metricKey, self::ZERO_CAN_BE_REAL, true);
    }

    private function invalid(string $metricKey, mixed $rawValue, string $reason, ?float $value = null): array
    {
        return [
            'included' => false,
            'metric_key' => $metricKey,
            'value' => $value,
            'raw_value' => $rawValue,
            'reason' => $reason,
            'range' => $this->rangeForMetric($metricKey),
        ];
    }

    private function numberOrNull(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, ':')) {
            return $this->timeStringToSeconds($value);
        }

        return null;
    }

    private function timeStringToSeconds(string $value): ?float
    {
        $parts = array_map('trim', explode(':', $value));
        if (empty($parts)) {
            return null;
        }

        $seconds = 0.0;
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                return null;
            }
            $seconds = ($seconds * 60) + (float) $part;
        }

        return round($seconds, 2);
    }
}
