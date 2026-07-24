<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class EnvironmentEngine
{
    /** @return array{input:array<string,mixed>,assumptions:list<string>} */
    public function normalize(array $input): array
    {
        $mode = (string) ($input['mode'] ?? 'standardized');
        if (!in_array($mode, ['standardized', 'facility'], true)) {
            throw new \InvalidArgumentException("Ball-flight mode must be 'standardized' or 'facility'.");
        }

        $input['mode'] = $mode;

        return ['input' => $input, 'assumptions' => []];
    }
}
