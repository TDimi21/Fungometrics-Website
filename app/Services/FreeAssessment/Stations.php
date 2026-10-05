<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

final class Stations
{
    public static function all(): array
    {
        return [
            'pushups' => ['name' => 'Push-Ups', 'count' => 1, 'unit' => 'reps', 'metric' => 'pushups', 'field' => 'push_ups', 'min' => 0, 'max' => 1000],
            'pull_ups' => ['name' => 'Pull-Ups', 'count' => 1, 'unit' => 'reps', 'metric' => 'pull_ups', 'field' => 'pull_ups', 'min' => 0, 'max' => 300],
            'broad_jump' => ['name' => 'Broad Jump', 'count' => 3, 'unit' => 'in', 'metric' => 'broad_jump', 'field' => 'broad_jump', 'min' => 1, 'max' => 180],
            'sprint_10yd' => ['name' => '10-Yard Dash', 'count' => 3, 'unit' => 'sec', 'metric' => 'sprint_10yd', 'field' => 'sprint_10yd', 'lower' => true, 'min' => 0.5, 'max' => 30],
            'shuttle_5_10_5' => ['name' => '5-10-5 Shuttle', 'count' => 3, 'unit' => 'sec', 'metric' => 'shuttle_5_10_5', 'field' => 'shuttle_5_10_5', 'lower' => true, 'min' => 1, 'max' => 60],
            'grip_strength' => ['name' => 'Grip Strength', 'count' => 6, 'unit' => 'lbs', 'metric' => 'grip_strength_left', 'min' => 0.1, 'max' => 500],
            'pull_strength' => ['name' => 'Pull Strength', 'count' => 3, 'unit' => 'lbs', 'metric' => 'pull_strength', 'field' => 'pull_strength', 'min' => 0.1, 'max' => 2000],
            'exit_velocity' => ['name' => 'Exit Velocity', 'count' => 10, 'unit' => 'mph', 'metric' => 'max_exit_velocity', 'field' => 'exit_velo', 'min' => 1, 'max' => 150],
            'pitching_velocity' => ['name' => 'Pitching Velocity', 'count' => 10, 'unit' => 'mph', 'metric' => 'max_fastball_velocity', 'field' => 'pitch_velo', 'min' => 1, 'max' => 130],
        ];
    }
    public static function summarize(string $station, array $values): array
    {
        $definition = self::all()[$station];
        $aggregate = static fn ($v) => ['best' => ($definition['lower'] ?? false) ? min($v) : max($v), 'average' => round(array_sum($v) / count($v), 3)];
        if ('grip_strength' === $station) {
            $left = $aggregate(array_slice($values, 0, 3));
            $right = $aggregate(array_slice($values, 3, 3));
            return ['left' => $left, 'right' => $right, 'best' => max($left['best'], $right['best']), 'difference_percent' => round(abs($left['best'] - $right['best']) / max($left['best'], $right['best']) * 100, 1)];
        }
        return $aggregate($values);
    }
}
