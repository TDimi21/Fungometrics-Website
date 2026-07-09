<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class LimiterEngine
{
    public function detect(array $assembled, array $trends): array
    {
        $limiters = [];

        $this->longTossTransfer($limiters, $assembled, $trends);
        $this->weightedBallTransfer($limiters, $assembled, $trends);
        $this->weightedBallSpectrum($limiters, $assembled);
        $this->command($limiters, $assembled, $trends);
        $this->barrelControl($limiters, $assembled);
        $this->mobilityRestriction($limiters, $assembled);
        $this->recoveryWorkloadRisk($limiters, $assembled, $trends);

        return $this->uniqueById($limiters);
    }

    private function longTossTransfer(array &$limiters, array $assembled, array $trends): void
    {
        $lt = $trends['long_toss_avg_distance'] ?? [];
        $velo = $trends['bullpen_avg_velocity'] ?? [];

        if (($lt['direction'] ?? null) === 'improving' && in_array($velo['direction'] ?? null, ['stable', 'no_data'], true)) {
            $limiters[] = $this->limiter(
                'long-toss-to-mound-transfer',
                'throwing',
                'medium',
                'Long Toss to Mound Transfer',
                'Long toss distance is improving, but bullpen velocity is not improving at the same rate.',
                [
                    'long_toss_trend' => $lt,
                    'bullpen_velocity_trend' => $velo,
                    'long_toss_summary' => $assembled['long_toss_summary'] ?? [],
                    'bullpen_summary' => $assembled['bullpen_summary'] ?? [],
                ],
                'medium'
            );
        }
    }

    private function weightedBallTransfer(array &$limiters, array $assembled, array $trends): void
    {
        $fiveOzMax = $this->numberOrNull($assembled['weighted_ball_summary']['five_oz_max_velocity'] ?? null);
        $bullpenMax = $this->numberOrNull($assembled['bullpen_summary']['max_pitch_velocity'] ?? null);
        $baselinePitchVelo = $this->numberOrNull($assembled['assessment_summary']['baseline_pitch_velocity'] ?? null)
            ?? $this->numberOrNull($assembled['physical_development']['pitch_velocity'] ?? null);
        $bullpenTrend = $trends['bullpen_avg_velocity'] ?? [];
        $weightedTrend = $trends['weighted_ball_5oz_max_velocity'] ?? $trends['weighted_ball_avg_velocity'] ?? [];

        if ($fiveOzMax !== null && $bullpenMax !== null && $fiveOzMax - $bullpenMax >= 3) {
            $limiters[] = $this->limiter(
                'weighted-ball-to-mound-transfer',
                'throwing',
                'high',
                'Weighted Ball to Mound Transfer',
                'The player is showing more 5 oz velocity in weighted-ball work than mound velocity.',
                [
                    'weighted_ball_5oz_max_velocity' => $fiveOzMax,
                    'bullpen_max_pitch_velocity' => $bullpenMax,
                    'gap_mph' => round($fiveOzMax - $bullpenMax, 1),
                    'bullpen_velocity_trend' => $bullpenTrend,
                    'weighted_ball_trend' => $weightedTrend,
                ],
                'medium'
            );
        }

        if ($fiveOzMax !== null && $baselinePitchVelo !== null && $fiveOzMax <= $baselinePitchVelo) {
            $limiters[] = $this->limiter(
                'five-oz-transfer-flat',
                'throwing',
                'medium',
                '5 oz Velocity Transfer',
                'Current 5 oz weighted-ball velocity is not above the assessment pitch-velocity baseline.',
                [
                    'assessment_baseline_pitch_velocity' => $baselinePitchVelo,
                    'weighted_ball_5oz_max_velocity' => $fiveOzMax,
                    'transfer_delta_mph' => round($fiveOzMax - $baselinePitchVelo, 1),
                ],
                'medium'
            );
        }
    }

    private function weightedBallSpectrum(array &$limiters, array $assembled): void
    {
        $speedReserve = $this->numberOrNull($assembled['weighted_ball_summary']['speed_reserve_3_to_5'] ?? null);
        $strengthReserve = $this->numberOrNull($assembled['weighted_ball_summary']['strength_reserve_5_to_7'] ?? null);
        $velocityRatio = $this->numberOrNull($assembled['weighted_ball_summary']['velocity_ratio_5_to_3'] ?? null);
        $forceDropOff = $this->numberOrNull($assembled['weighted_ball_summary']['force_drop_off_per_oz'] ?? null);
        $profile = $assembled['weighted_ball_summary']['profile_label'] ?? null;

        if ($speedReserve !== null && $speedReserve >= 7 && ($strengthReserve === null || $strengthReserve >= 6)) {
            $limiters[] = $this->limiter(
                'underload-speed-transfer',
                'throwing',
                'medium',
                'Underload Speed Transfer',
                'The lighter ball is much faster than the 5 oz ball, suggesting arm speed is not fully transferring to the game ball.',
                [
                    'speed_reserve_3_to_5' => $speedReserve,
                    'strength_reserve_5_to_7' => $strengthReserve,
                    'velocity_ratio_5_to_3' => $velocityRatio,
                    'weighted_ball_profile' => $profile,
                ],
                'medium'
            );
        }

        if ($strengthReserve !== null && $strengthReserve >= 7) {
            $limiters[] = $this->limiter(
                'overload-strength',
                'throwing',
                'medium',
                'Overload Strength',
                'Velocity drops sharply from 5 oz to 7 oz, which points to overload strength or sequencing limitations.',
                [
                    'strength_reserve_5_to_7' => $strengthReserve,
                    'force_drop_off_per_oz' => $forceDropOff,
                    'weighted_ball_profile' => $profile,
                ],
                'medium'
            );
        }

        if ($velocityRatio !== null && $velocityRatio < 0.92) {
            $limiters[] = $this->limiter(
                'velocity-spectrum-efficiency',
                'throwing',
                'medium',
                'Velocity Spectrum Efficiency',
                'The 5 oz to 3 oz velocity ratio is below target, showing a large drop from underload speed to game-ball velocity.',
                [
                    'velocity_ratio_5_to_3' => $velocityRatio,
                    'speed_reserve_3_to_5' => $speedReserve,
                    'weighted_ball_profile' => $profile,
                ],
                'medium'
            );
        }
    }

    private function command(array &$limiters, array $assembled, array $trends): void
    {
        $strikeRate = $this->numberOrNull($assembled['bullpen_summary']['strike_rate'] ?? null);
        $maxVelo = $this->numberOrNull($assembled['bullpen_summary']['max_pitch_velocity'] ?? null);
        $veloTrend = $trends['bullpen_avg_velocity'] ?? [];

        if ($strikeRate !== null && $strikeRate < 65 && ($maxVelo !== null || in_array($veloTrend['direction'] ?? null, ['stable', 'improving'], true))) {
            $limiters[] = $this->limiter(
                'command',
                'pitching',
                $strikeRate < 55 ? 'high' : 'medium',
                'Command',
                'Strike percentage is below target while velocity is present, stable, or improving.',
                [
                    'strike_percentage' => $strikeRate,
                    'max_pitch_velocity' => $maxVelo,
                    'bullpen_velocity_trend' => $veloTrend,
                ],
                $strikeRate < 55 ? 'high' : 'medium'
            );
        }
    }

    private function barrelControl(array &$limiters, array $assembled): void
    {
        $maxEv = max(array_filter([
            $this->numberOrNull($assembled['batting_summary']['max_exit_velocity'] ?? null),
            $this->numberOrNull($assembled['cage_summary']['max_exit_velocity'] ?? null),
            $this->numberOrNull($assembled['exit_velocity_summary']['max_exit_velocity'] ?? null),
        ], fn ($value) => $value !== null) ?: [null]);

        $battingScore = $this->numberOrNull($assembled['batting_summary']['score'] ?? null);
        $cageScore = $this->numberOrNull($assembled['cage_summary']['score'] ?? null);
        $contactScore = $this->numberOrNull($assembled['batting_summary']['score_breakdown']['contactScore'] ?? null);
        $launchScore = $this->numberOrNull($assembled['batting_summary']['score_breakdown']['launchScore'] ?? null);

        if ($maxEv !== null && $maxEv >= 85 && (($battingScore !== null && $battingScore < 70) || ($cageScore !== null && $cageScore < 70) || ($contactScore !== null && $contactScore < 65) || ($launchScore !== null && $launchScore < 65))) {
            $limiters[] = $this->limiter(
                'barrel-control',
                'hitting',
                'high',
                'Barrel Control',
                'Exit velocity is strong, but contact or launch quality is below target.',
                [
                    'max_exit_velocity' => $maxEv,
                    'batting_score' => $battingScore,
                    'cage_score' => $cageScore,
                    'contact_score' => $contactScore,
                    'launch_score' => $launchScore,
                ],
                'medium'
            );
        }
    }

    private function mobilityRestriction(array &$limiters, array $assembled): void
    {
        $strength = $this->numberOrNull($assembled['physical_development']['strength_score'] ?? null);
        $mobility = $this->numberOrNull($assembled['physical_development']['mobility_score'] ?? null);

        if ($strength !== null && $strength >= 75 && $mobility !== null && $mobility < 65) {
            $limiters[] = $this->limiter(
                'mobility-restriction',
                'physical',
                'medium',
                'Mobility Restriction',
                'Strength score is strong, but mobility score is limiting movement quality.',
                [
                    'strength_score' => $strength,
                    'mobility_score' => $mobility,
                ],
                'medium'
            );
        }
    }

    private function recoveryWorkloadRisk(array &$limiters, array $assembled, array $trends): void
    {
        $recoveryTrend = $assembled['physical_development']['trend']['recovery_score'] ?? [];
        $weightedTrend = $trends['weighted_ball_avg_velocity'] ?? [];
        $weightedThrows = $this->numberOrNull($assembled['weighted_ball_summary']['total_throws'] ?? null);
        $recovery = $this->numberOrNull($assembled['physical_development']['recovery_score'] ?? null);

        if (($weightedThrows !== null && $weightedThrows >= 35 || ($weightedTrend['direction'] ?? null) === 'improving')
            && (($recoveryTrend['direction'] ?? null) === 'down' || ($recovery !== null && $recovery < 60))) {
            $limiters[] = $this->limiter(
                'recovery-workload-risk',
                'recovery',
                'high',
                'Recovery / Workload Risk',
                'Throwing workload or intent is rising while recovery is low or declining.',
                [
                    'weighted_ball_total_throws' => $weightedThrows,
                    'weighted_ball_trend' => $weightedTrend,
                    'recovery_score' => $recovery,
                    'recovery_trend' => $recoveryTrend,
                ],
                'medium'
            );
        }
    }

    private function limiter(string $id, string $category, string $priority, string $title, string $why, array $evidence, string $confidence): array
    {
        return [
            'id' => $id,
            'category' => $category,
            'priority' => $priority,
            'limiter' => $title,
            'title' => $title,
            'why' => $why,
            'evidence' => $evidence,
            'confidence' => $confidence,
        ];
    }

    private function uniqueById(array $limiters): array
    {
        return collect($limiters)->unique('id')->values()->all();
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
