<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PlayerDNAEngine
{
    public function build(array $assembled, array $trends, array $limiters): array
    {
        $categories = [
            'strength' => $this->category('strength', $this->firstNumber([
                $assembled['physical_development']['strength_score'] ?? null,
                $assembled['assessment_summary']['strength_overall_score'] ?? null,
            ]), [
                'strength_score' => $assembled['physical_development']['strength_score'] ?? null,
                'assessment_strength_score' => $assembled['assessment_summary']['strength_overall_score'] ?? null,
            ]),
            'mobility' => $this->category('mobility', $this->firstNumber([
                $assembled['physical_development']['mobility_score'] ?? null,
                $assembled['assessment_summary']['mobility_overall_score'] ?? null,
            ]), [
                'mobility_score' => $assembled['physical_development']['mobility_score'] ?? null,
                'assessment_mobility_score' => $assembled['assessment_summary']['mobility_overall_score'] ?? null,
            ]),
            'arm_development' => $this->armDevelopment($assembled, $trends),
            'pitching' => $this->pitching($assembled),
            'hitting' => $this->hitting($assembled),
            'athleticism' => $this->category('athleticism', $this->firstNumber([
                $assembled['physical_development']['overall_api_score'] ?? null,
                $assembled['assessment_summary']['overall_score'] ?? null,
            ]), [
                'overall_api_score' => $assembled['physical_development']['overall_api_score'] ?? null,
                'assessment_overall_score' => $assembled['assessment_summary']['overall_score'] ?? null,
            ]),
            'recovery' => $this->category('recovery', $this->firstNumber([
                $assembled['physical_development']['recovery_score'] ?? null,
                $assembled['assessment_summary']['arm_health_score'] ?? null,
            ]), [
                'recovery_score' => $assembled['physical_development']['recovery_score'] ?? null,
                'arm_health_score' => $assembled['assessment_summary']['arm_health_score'] ?? null,
            ]),
        ];

        $available = collect($categories)->filter(fn ($item) => ($item['score'] ?? null) !== null);
        $primary = $available->sortByDesc('score')->first();
        $need = $available->sortBy('score')->first();
        if ($need && ($need['score'] ?? null) !== null && (float) $need['score'] >= 70) {
            $need = null;
        }

        return [
            'categories' => $categories,
            'primary_strength' => $primary['category'] ?? null,
            'primary_strength_detail' => $primary ? [
                'category' => $primary['category'],
                'score' => $primary['score'],
                'label' => $primary['label'],
            ] : null,
            'biggest_need' => $need['category'] ?? null,
            'biggest_need_detail' => $need ? [
                'category' => $need['category'],
                'score' => $need['score'],
                'label' => $need['label'],
            ] : null,
            'development_stage' => $this->developmentStage($available->avg('score')),
            'player_type_labels' => $this->playerTypes($categories, $assembled, $limiters),
        ];
    }

    private function armDevelopment(array $assembled, array $trends): array
    {
        $values = array_filter([
            $assembled['bullpen_summary']['score'] ?? null,
            $assembled['assessment_summary']['pitching_score'] ?? null,
            $this->scoreByRange($assembled['bullpen_summary']['max_pitch_velocity'] ?? null, 65, 88),
            $this->scoreByRange($assembled['weighted_ball_summary']['five_oz_max_velocity'] ?? null, 65, 90),
            $this->scoreByRange($assembled['long_toss_summary']['max_distance'] ?? null, 90, 330),
        ], fn ($value) => is_numeric($value));

        return $this->category('arm_development', count($values) ? round(array_sum($values) / count($values), 1) : null, [
            'bullpen_score' => $assembled['bullpen_summary']['score'] ?? null,
            'assessment_pitching_score' => $assembled['assessment_summary']['pitching_score'] ?? null,
            'max_pitch_velocity' => $assembled['bullpen_summary']['max_pitch_velocity'] ?? null,
            'weighted_ball_5oz_max_velocity' => $assembled['weighted_ball_summary']['five_oz_max_velocity'] ?? null,
            'long_toss_max_distance' => $assembled['long_toss_summary']['max_distance'] ?? null,
            'weighted_ball_profile' => $assembled['weighted_ball_summary']['profile_label'] ?? null,
            'bullpen_velocity_trend' => $trends['bullpen_avg_velocity'] ?? null,
        ]);
    }

    private function pitching(array $assembled): array
    {
        $values = array_filter([
            $assembled['bullpen_summary']['score'] ?? null,
            $assembled['bullpen_summary']['strike_rate'] ?? null,
            $assembled['assessment_summary']['pitching_score'] ?? null,
            $this->scoreByRange($assembled['bullpen_summary']['max_pitch_velocity'] ?? null, 65, 88),
        ], fn ($value) => is_numeric($value));

        return $this->category('pitching', count($values) ? round(array_sum($values) / count($values), 1) : null, [
            'bullpen_score' => $assembled['bullpen_summary']['score'] ?? null,
            'strike_rate' => $assembled['bullpen_summary']['strike_rate'] ?? null,
            'assessment_pitching_score' => $assembled['assessment_summary']['pitching_score'] ?? null,
            'max_pitch_velocity' => $assembled['bullpen_summary']['max_pitch_velocity'] ?? null,
        ]);
    }

    private function hitting(array $assembled): array
    {
        $values = array_filter([
            $assembled['batting_summary']['score'] ?? null,
            $assembled['cage_summary']['score'] ?? null,
            $assembled['exit_velocity_summary']['score'] ?? null,
            $assembled['assessment_summary']['hitting_score'] ?? null,
            $this->scoreByRange(max(array_filter([
                $this->numberOrNull($assembled['batting_summary']['max_exit_velocity'] ?? null),
                $this->numberOrNull($assembled['cage_summary']['max_exit_velocity'] ?? null),
                $this->numberOrNull($assembled['exit_velocity_summary']['max_exit_velocity'] ?? null),
            ], fn ($value) => $value !== null) ?: [null]), 60, 100),
        ], fn ($value) => is_numeric($value));

        return $this->category('hitting', count($values) ? round(array_sum($values) / count($values), 1) : null, [
            'batting_score' => $assembled['batting_summary']['score'] ?? null,
            'cage_score' => $assembled['cage_summary']['score'] ?? null,
            'exit_velocity_score' => $assembled['exit_velocity_summary']['score'] ?? null,
            'assessment_hitting_score' => $assembled['assessment_summary']['hitting_score'] ?? null,
            'max_exit_velocity' => max(array_filter([
                $this->numberOrNull($assembled['batting_summary']['max_exit_velocity'] ?? null),
                $this->numberOrNull($assembled['cage_summary']['max_exit_velocity'] ?? null),
                $this->numberOrNull($assembled['exit_velocity_summary']['max_exit_velocity'] ?? null),
            ], fn ($value) => $value !== null) ?: [null]),
        ]);
    }

    private function category(string $category, mixed $score, array $evidence): array
    {
        $score = is_numeric($score) ? round((float) $score, 1) : null;
        $sample = collect($evidence)->filter(fn ($value) => $value !== null && $value !== [])->count();

        return [
            'category' => $category,
            'score' => $score,
            'label' => $score === null ? null : $this->label($score),
            'confidence' => $score === null ? 'low' : ($sample >= 2 ? 'medium' : 'low'),
            'evidence' => $evidence,
        ];
    }

    private function playerTypes(array $categories, array $assembled, array $limiters): array
    {
        $labels = [];
        $maxPitchVelo = $this->numberOrNull($assembled['bullpen_summary']['max_pitch_velocity'] ?? null);
        $strikeRate = $this->numberOrNull($assembled['bullpen_summary']['strike_rate'] ?? null);
        $weightedProfile = $assembled['weighted_ball_summary']['profile_label'] ?? null;
        $maxEv = max(array_filter([
            $this->numberOrNull($assembled['batting_summary']['max_exit_velocity'] ?? null),
            $this->numberOrNull($assembled['cage_summary']['max_exit_velocity'] ?? null),
            $this->numberOrNull($assembled['exit_velocity_summary']['max_exit_velocity'] ?? null),
        ], fn ($value) => $value !== null) ?: [null]);

        if ($maxPitchVelo !== null && $maxPitchVelo >= 85 && ($strikeRate === null || $strikeRate >= 62)) {
            $labels[] = 'Power Arm';
        }
        if ($strikeRate !== null && $strikeRate >= 70) {
            $labels[] = 'Command Arm';
        }
        if ($maxPitchVelo !== null && $maxPitchVelo >= 85 && $strikeRate !== null && $strikeRate < 60) {
            $labels[] = 'Stuff Without Command';
        }
        if (($categories['arm_development']['score'] ?? null) !== null && $categories['arm_development']['score'] < 60) {
            $labels[] = 'Developing Arm';
        }
        if ($maxEv !== null && $maxEv >= 88) {
            $labels[] = 'Power Hitter';
        }
        if (($categories['hitting']['score'] ?? null) !== null && $categories['hitting']['score'] >= 75) {
            $labels[] = 'Contact Hitter';
        }
        if (collect($limiters)->contains(fn ($limiter) => ($limiter['id'] ?? null) === 'barrel-control')) {
            $labels[] = 'High EV / Low Contact';
        }
        if (($categories['strength']['score'] ?? null) !== null && $categories['strength']['score'] >= 75) {
            $labels[] = 'Strength Dominant';
        }
        if ($weightedProfile) {
            $labels[] = $weightedProfile;
        }
        if (collect($limiters)->contains(fn ($limiter) => ($limiter['id'] ?? null) === 'mobility-restriction')) {
            $labels[] = 'Mobility Limited';
        }
        if (collect($limiters)->contains(fn ($limiter) => ($limiter['id'] ?? null) === 'recovery-workload-risk')) {
            $labels[] = 'Recovery Risk';
        }

        return array_values(array_unique($labels));
    }

    private function label(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Elite',
            $score >= 75 => 'Strong',
            $score >= 65 => 'Solid',
            $score >= 55 => 'Developing',
            default => 'Needs Work',
        };
    }

    private function scoreByRange(mixed $value, float $floor, float $ceiling): ?float
    {
        $value = $this->numberOrNull($value);
        if ($value === null) {
            return null;
        }

        return round(max(0, min(100, (($value - $floor) / max(1, $ceiling - $floor)) * 100)), 1);
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function firstNumber(array $values): ?float
    {
        foreach ($values as $value) {
            $number = $this->numberOrNull($value);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function developmentStage(mixed $average): ?string
    {
        $score = $this->numberOrNull($average);
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 85 => 'Elite Development',
            $score >= 75 => 'Strong Development',
            $score >= 65 => 'Solid Development',
            $score >= 55 => 'Developing',
            default => 'Needs Foundation',
        };
    }
}
