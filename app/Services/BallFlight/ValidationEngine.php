<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class ValidationEngine
{
    public function __construct(private readonly BallFlightEngine $engine)
    {
    }

    /** @param list<array<string,mixed>> $observations @return array<string,mixed> */
    public function validate(array $observations, ?array $calibrationProfile = null, string $spin = 'estimated', int $uncertaintySamples = 25): array
    {
        $errors = [];
        $pairs = [];
        foreach ($observations as $row) {
            $measuredDistance = $row['measured_distance_ft'] ?? $row['measured_carry_ft'] ?? null;
            if (!isset($row['exit_velocity_mph'], $row['launch_angle_deg']) || $measuredDistance === null) {
                continue;
            }
            $input = [
                'exit_velocity_mph' => $row['exit_velocity_mph'],
                'launch_angle_deg' => $row['launch_angle_deg'],
                'spray_angle_deg' => $row['spray_angle_deg'] ?? 0,
            ];
            $measuredSpin = $row['measured_spin_rpm'] ?? $row['spin_rate_rpm'] ?? null;
            if ($spin === 'measured') {
                if ($measuredSpin === null) continue;
                $input['measured_spin_rpm'] = $measuredSpin;
            }
            $prediction = $this->engine->analyze($input, $calibrationProfile, $uncertaintySamples);
            if ($prediction['carry_ft'] === null) {
                continue;
            }
            $error = (float) $prediction['carry_ft'] - (float) $measuredDistance;
            $errors[] = $error;
            $pairs[] = [
                'source' => $row['source'] ?? 'unknown',
                'predicted_carry_ft' => $prediction['carry_ft'],
                'measured_carry_ft' => (float) $measuredDistance,
                'error_ft' => round($error, 1),
            ];
        }

        $count = count($errors);
        if ($count === 0) {
            return ['count' => 0, 'mae_ft' => null, 'rmse_ft' => null, 'bias_ft' => null, 'pairs' => []];
        }

        return [
            'count' => $count,
            'mae_ft' => round(array_sum(array_map('abs', $errors)) / $count, 2),
            'rmse_ft' => round(sqrt(array_sum(array_map(static fn (float $e): float => $e ** 2, $errors)) / $count), 2),
            'bias_ft' => round(array_sum($errors) / $count, 2),
            'pairs' => $pairs,
        ];
    }
}
