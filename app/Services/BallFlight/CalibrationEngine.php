<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class CalibrationEngine
{
    /**
     * Applies only an explicitly fitted calibration profile. No coefficient is
     * inferred from undocumented data and an absent profile is a no-op.
     *
     * @return array{carry_ft:?float,status:string,profile:?string,offset_ft:float}
     */
    public function apply(?float $physicsCarryFt, ?array $profile = null): array
    {
        if ($physicsCarryFt === null || $profile === null) {
            return [
                'carry_ft' => $physicsCarryFt,
                'status' => 'uncalibrated',
                'profile' => null,
                'offset_ft' => 0.0,
            ];
        }

        $offset = is_numeric($profile['carry_offset_ft'] ?? null)
            ? (float) $profile['carry_offset_ft']
            : 0.0;

        return [
            'carry_ft' => round(max(0.0, $physicsCarryFt + $offset), 1),
            'status' => 'calibrated',
            'profile' => (string) ($profile['id'] ?? 'explicit'),
            'offset_ft' => $offset,
        ];
    }

    /** @param list<array{predicted_carry_ft:float,measured_carry_ft:float}> $observations */
    public function fitCarryOffset(array $observations, string $profileId): array
    {
        if ($observations === []) {
            throw new \InvalidArgumentException('Calibration requires at least one paired carry observation.');
        }

        $residuals = array_map(
            static fn (array $row): float => $row['measured_carry_ft'] - $row['predicted_carry_ft'],
            $observations,
        );

        return [
            'id' => $profileId,
            'carry_offset_ft' => array_sum($residuals) / count($residuals),
            'observation_count' => count($residuals),
            'fitted_at' => now()->toIso8601String(),
        ];
    }
}
