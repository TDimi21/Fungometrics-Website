<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class SpinEngine
{
    /** @return array{input:array<string,mixed>,source:string} */
    public function normalize(array $input): array
    {
        $measured = $input['measured_spin_rpm'] ?? $input['spin_rate_rpm'] ?? null;
        if ($measured !== null) {
            if (!is_numeric($measured) || (float) $measured < 0) {
                throw new \InvalidArgumentException('Measured spin must be a non-negative numeric RPM value.');
            }
            $input['measured_spin_rpm'] = (float) $measured;

            return ['input' => $input, 'source' => 'measured'];
        }

        return ['input' => $input, 'source' => 'estimated'];
    }
}
