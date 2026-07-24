<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

use App\Services\Cage\CageDistanceService;

/**
 * Stable physics boundary for every FMTRX consumer.
 *
 * The current RK4 implementation remains in CageDistanceService during the
 * compatibility phase. Moving the integrator behind this boundary lets Cage,
 * Live AB, reports, and research validation share one contract immediately.
 */
final class PhysicsEngine
{
    public function __construct(private readonly CageDistanceService $cagePhysics)
    {
    }

    /** @return array<string,mixed> */
    public function simulate(array $input, ?int $uncertaintySamples = null): array
    {
        return $this->cagePhysics->estimate($input, $uncertaintySamples);
    }
}
