<?php

declare(strict_types=1);

namespace App\Services\Blast;

final class BlastBatSpeedRankingService
{
    /** @return array<string, mixed>|null */
    public function rank(float $batSpeed, ?string $storedLevel, ?int $age): ?array
    {
        $level = $this->resolveLevel($storedLevel, $age);
        $range = $level ? config("blast_benchmarks.levels.{$level}.metrics.bat_speed") : null;
        if ( ! is_array($range)) {
            return null;
        }

        $min = (float) $range['min'];
        $max = (float) $range['max'];
        $span = max(0.01, $max - $min);
        $rangePosition = (int) round(max(0, min(100, (($batSpeed - $min) / $span) * 100)));
        $status = match (true) {
            $batSpeed < $min => ['below_suggested_range', 'Below Suggested Range'],
            $batSpeed > $max => ['above_suggested_range', 'Above Suggested Range'],
            default => ['in_suggested_range', 'In Suggested Range'],
        };

        return [
            'metric_key' => 'bat_speed',
            'raw_value' => round($batSpeed, 1),
            'percentile' => $rangePosition,
            'score_0_100' => $rangePosition,
            'label' => $status[1],
            'status' => $status[0],
            'unit' => 'mph',
            'source' => (string) config('blast_benchmarks.source', 'Blast Motion Suggested Ranges'),
            'confidence' => 'range-derived',
            'benchmark_level' => $level,
            'benchmark_level_label' => config("blast_benchmarks.levels.{$level}.label"),
            'range_min' => $min,
            'range_max' => $max,
            'range_label' => $min.'–'.$max.' mph',
            'goal' => $batSpeed < $min ? $min : null,
            'gap' => $batSpeed < $min ? round($min - $batSpeed, 1) : null,
            'evidence' => [
                'method' => 'position_within_suggested_range',
                'formula' => 'clamp((value - range_min) / (range_max - range_min) * 100)',
                'population_percentile' => false,
                'benchmark_version' => config('blast_benchmarks.version'),
            ],
        ];
    }

    public function resolveLevel(?string $storedLevel, ?int $age): ?string
    {
        $key = mb_strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $storedLevel), '_'));
        $mapped = match ($key) {
            'pro', 'professional' => 'pro',
            'milb', 'minor_league', 'minor_league_baseball' => 'milb',
            'college', 'juco', 'd1', 'd2', 'd3', 'naia' => 'college',
            'high', 'hs', 'high_school', 'varsity', 'high_school_varsity' => 'high_school_varsity',
            'jv', 'high_school_jv' => 'high_school_jv',
            'mid', 'ms', 'middle', 'middle_school' => 'middle_school',
            'youth' => 'youth',
            default => null,
        };

        if ($mapped) {
            return $mapped;
        }

        return match (true) {
            null === $age => null,
            $age <= 12 => 'youth',
            $age <= 14 => 'middle_school',
            $age <= 18 => 'high_school_varsity',
            $age <= 22 => 'college',
            default => 'pro',
        };
    }
}
