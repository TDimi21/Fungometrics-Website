<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class LimiterEngine
{
    public function detect(array $assembled, array $trends): array
    {
        $limiters = [];

        $this->longTossTransfer($limiters, $assembled, $trends);
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

    private function command(array &$limiters, array $assembled, array $trends): void
    {
        $strikeRate = $this->numberOrNull($assembled['bullpen_summary']['strike_rate'] ?? null);
        $maxVelo = $this->numberOrNull($assembled['bullpen_summary']['max_pitch_velocity'] ?? null);
        $veloTrend = $trends['bullpen_avg_velocity'] ?? [];

        if ($strikeRate !== null && $strikeRate < 60 && ($maxVelo !== null || in_array($veloTrend['direction'] ?? null, ['stable', 'improving'], true))) {
            $limiters[] = $this->limiter(
                'command',
                'pitching',
                'high',
                'Command',
                'Strike percentage is below target while velocity is present, stable, or improving.',
                [
                    'strike_percentage' => $strikeRate,
                    'max_pitch_velocity' => $maxVelo,
                    'bullpen_velocity_trend' => $veloTrend,
                ],
                $strikeRate < 50 ? 'high' : 'medium'
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
        $contactScore = $this->numberOrNull($assembled['batting_summary']['score_breakdown']['contactScore'] ?? null);
        $launchScore = $this->numberOrNull($assembled['batting_summary']['score_breakdown']['launchScore'] ?? null);

        if ($maxEv !== null && $maxEv >= 85 && (($battingScore !== null && $battingScore < 70) || ($contactScore !== null && $contactScore < 65) || ($launchScore !== null && $launchScore < 65))) {
            $limiters[] = $this->limiter(
                'barrel-control',
                'hitting',
                'high',
                'Barrel Control',
                'Exit velocity is strong, but contact or launch quality is below target.',
                [
                    'max_exit_velocity' => $maxEv,
                    'batting_score' => $battingScore,
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
        return compact('id', 'category', 'priority', 'title', 'why', 'evidence', 'confidence');
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
