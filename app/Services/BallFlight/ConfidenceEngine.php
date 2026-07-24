<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class ConfidenceEngine
{
    /** @return array{percent:int,label:string,factors:array<string,bool>} */
    public function score(array $input, array $physics, string $calibrationStatus): array
    {
        $factors = [
            'measured_spin' => ($physics['inputs_used']['spin_source'] ?? null) === 'measured',
            'measured_environment' => ($input['mode'] ?? 'standardized') === 'facility'
                && isset($input['temperature_f'], $input['elevation_ft']),
            'precise_launch_angle' => !isset($input['launch_angle_min_deg'], $input['launch_angle_max_deg']),
            'precise_spray_angle' => !isset($input['spray_angle_min_deg'], $input['spray_angle_max_deg']),
            'research_calibrated' => $calibrationStatus === 'calibrated',
        ];

        $percent = 55;
        $percent += $factors['measured_spin'] ? 18 : 0;
        $percent += $factors['measured_environment'] ? 7 : 0;
        $percent += $factors['precise_launch_angle'] ? 5 : 0;
        $percent += $factors['precise_spray_angle'] ? 5 : 0;
        $percent += $factors['research_calibrated'] ? 10 : 0;
        $percent = max(1, min(99, $percent));

        return [
            'percent' => $percent,
            'label' => $percent >= 90 ? 'high' : ($percent >= 70 ? 'medium' : 'low'),
            'factors' => $factors,
        ];
    }
}
