<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class BallFlightEngine
{
    public const ENGINE_VERSION = 'bfi_v1.0';

    public function __construct(
        private readonly PhysicsEngine $physics,
        private readonly EnvironmentEngine $environment,
        private readonly SpinEngine $spin,
        private readonly Aerodynamics $aerodynamics,
        private readonly CalibrationEngine $calibration,
        private readonly ConfidenceEngine $confidence,
    ) {
    }

    /** @return array<string,mixed> */
    public function analyze(array $input, ?array $calibrationProfile = null, ?int $uncertaintySamples = null): array
    {
        $environment = $this->environment->normalize($input);
        $spin = $this->spin->normalize($environment['input']);
        $aerodynamics = $this->aerodynamics->normalize($spin['input']);
        $physics = $this->physics->simulate($aerodynamics['input'], $uncertaintySamples);
        $calibration = $this->calibration->apply($physics['estimated_carry_ft'], $calibrationProfile);

        return [
            'carry_ft' => $calibration['carry_ft'],
            'carry_low_ft' => $physics['carry_low_ft'],
            'carry_high_ft' => $physics['carry_high_ft'],
            'hang_time_seconds' => $physics['hang_time_seconds'],
            'maximum_height_ft' => $physics['maximum_height_ft'],
            'landing' => [
                'x_ft' => $physics['landing_x_ft'],
                'y_ft' => $physics['landing_y_ft'],
            ],
            'batted_ball_type' => $physics['batted_ball_type'],
            'confidence' => $this->confidence->score($aerodynamics['input'], $physics, $calibration['status']),
            'calibration' => $calibration,
            'physics' => $physics,
            'assumptions' => array_values(array_unique(array_merge(
                $environment['assumptions'],
                $aerodynamics['assumptions'],
                $physics['assumptions'] ?? [],
            ))),
            'engine_version' => self::ENGINE_VERSION,
            'physics_model_version' => $physics['model_version'] ?? null,
        ];
    }
}
