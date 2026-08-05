<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

final class StrengthOneRepMaxCalculator
{
    public const FORMULA = 'epley';
    public const VERSION = '1.0.0';
    public const MIN_REPETITIONS = 1;
    public const MAX_REPETITIONS = 10;

    /** @return array<string, mixed> */
    public function estimate(mixed $load, mixed $repetitions): array
    {
        $actual = is_numeric($load) && (float) $load > 0 ? (float) $load : null;
        $reps = is_numeric($repetitions) ? (int) $repetitions : null;
        $supported = null !== $actual && null !== $reps && $reps >= self::MIN_REPETITIONS && $reps <= self::MAX_REPETITIONS;

        return [
            'actual_load' => $actual,
            'repetitions' => $reps,
            'estimated_1rm' => $supported ? round(1 === $reps ? $actual : $actual * (1 + ($reps / 30)), 1) : null,
            'tested_1rm' => $supported && 1 === $reps,
            'formula' => $supported && $reps > 1 ? self::FORMULA : null,
            'formula_version' => $supported && $reps > 1 ? self::VERSION : null,
            'supported' => $supported,
            'quality_flag' => match (true) {
                null === $actual => 'missing_load',
                null === $reps => 'missing_repetitions',
                $reps < self::MIN_REPETITIONS || $reps > self::MAX_REPETITIONS => 'rep_range_unsupported',
                default => null,
            },
        ];
    }
}
