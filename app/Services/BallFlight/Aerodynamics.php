<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class Aerodynamics
{
    private const PROFILES = ['standardized', 'flat_seam_pro', 'raised_seam', 'high_school', 'youth'];

    /** @return array{input:array<string,mixed>,profile:string,assumptions:list<string>} */
    public function normalize(array $input): array
    {
        $profile = (string) ($input['ball_profile'] ?? 'standardized');
        $assumptions = [];
        if (!in_array($profile, self::PROFILES, true)) {
            $assumptions[] = "Unknown ball profile '{$profile}'; standardized baseball aerodynamics were used.";
            $profile = 'standardized';
        }
        $input['ball_profile'] = $profile;

        return ['input' => $input, 'profile' => $profile, 'assumptions' => $assumptions];
    }
}
