<?php

declare(strict_types=1);

namespace App\Services\Rapsodo;

final class RapsodoTiltAverager
{
    /** @param array<int, mixed> $values */
    public function average(array $values): ?string
    {
        $angles = [];
        foreach ($values as $value) {
            $minutes = $this->minutes($value);
            if (null !== $minutes) {
                $angles[] = 2 * M_PI * ($minutes / 720);
            }
        }
        if ([] === $angles) {
            return null;
        }

        $sine = array_sum(array_map('sin', $angles)) / count($angles);
        $cosine = array_sum(array_map('cos', $angles)) / count($angles);
        if (abs($sine) < 1.0E-12 && abs($cosine) < 1.0E-12) {
            return null;
        }

        $angle = atan2($sine, $cosine);
        if ($angle < 0) {
            $angle += 2 * M_PI;
        }
        $minutes = (int) round(($angle / (2 * M_PI)) * 720) % 720;
        $hour = intdiv($minutes, 60);

        return sprintf('%d:%02d', 0 === $hour ? 12 : $hour, $minutes % 60);
    }

    private function minutes(mixed $value): ?int
    {
        $text = trim((string) $value);
        if (1 !== preg_match('/^(00|0?[1-9]|1[0-2])(?:h|:)(?::)?([0-5]\d)m?$/i', $text, $matches)) {
            return null;
        }

        return (((int) $matches[1]) % 12) * 60 + (int) $matches[2];
    }
}
