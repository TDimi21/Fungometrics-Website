<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

final class StrengthBenchmarkRegistry
{
    public const VERSION = '1.0.0';

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return [
            'body_weight' => $this->definition('Body Weight', 'body_context', 'body_mass', 'lbs', 'descriptive', 'none', ['scale'], ['age_group', 'level'], ['fmtrx_population'], 'body_weight', false, 'descriptive'),
            'front_squat' => $this->lift('Front Squat', 'front_squat'),
            'back_squat' => $this->lift('Back Squat', 'back_squat'),
            'bench_press' => $this->lift('Bench Press', 'bench_press', 'upper_body_push'),
            'deadlift' => $this->lift('Conventional Deadlift', 'deadlift'),
            'trap_bar_deadlift' => $this->lift('Trap-bar Deadlift', 'trap_bar_deadlift', 'lower_body', ['fmtrx_population', 'provisional_v1']),
            'power_clean' => $this->definition('Power Clean', 'explosive_strength', 'triple_extension_power', 'lbs', 'higher', 'body_weight_at_test', ['tested_1rm', 'rep_max'], ['body_weight_at_test', 'age_group'], ['fmtrx_population', 'provisional_v1'], 'power_clean', true),
            'pull_ups' => $this->endurance('Pull-ups', 'pull_ups'),
            'pushups' => $this->endurance('Push-ups', 'pushups'),
            'plank_hold' => $this->endurance('Plank Hold', 'plank_hold', 'seconds', ['fmtrx_population', 'provisional_v1']),
            'grip_strength_left' => $this->grip('Grip Strength Left', 'grip_strength_left'),
            'grip_strength_right' => $this->grip('Grip Strength Right', 'grip_strength_right'),
            'grip_strength_average' => $this->grip('Average Grip Strength', 'grip_strength_average', false),
            'grip_strength_best' => $this->grip('Best-hand Grip Strength', 'grip_strength_best', false),
            'grip_asymmetry_percentage' => $this->definition('Grip Asymmetry', 'grip_strength', 'bilateral_balance', '%', 'lower', 'bilateral_asymmetry', ['bilateral_grip'], [], [], null, true, 'balance'),
            'vertical_jump' => $this->power('Vertical Jump', 'vertical_jump', 'in'),
            'broad_jump' => $this->power('Broad Jump', 'broad_jump', 'in'),
            'med_ball_rotational_throw' => $this->power('Medicine-ball Rotational Throw', 'med_ball_rotational_throw', 'ft', ['fmtrx_population', 'provisional_v1']),
            'sprint_10yd' => $this->speed('10 Yard Sprint', 'sprint_10yd'),
            'forty_yard_dash' => $this->speed('40 Yard Dash', 'forty_yard_dash'),
            'sixty_yard_dash' => $this->speed('60 Yard Dash', 'sixty_yard_dash'),
        ];
    }

    public function get(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public function strengthBodyweightBand(mixed $weight, ?int $age): string
    {
        if ( ! is_numeric($weight) || (float) $weight <= 0) {
            return 'unknown';
        }

        $weight = (float) $weight;
        if (null !== $age && $age < 15) {
            return match (true) {
                $weight < 90 => 'under_90',
                $weight < 110 => '90_109',
                $weight < 130 => '110_129',
                $weight < 150 => '130_149',
                $weight < 170 => '150_169',
                $weight < 190 => '170_189',
                default => '190_plus',
            };
        }

        return match (true) {
            $weight < 130 => 'under_130',
            $weight < 150 => '130_149',
            $weight < 170 => '150_169',
            $weight < 190 => '170_189',
            $weight < 210 => '190_209',
            $weight < 230 => '210_229',
            $weight < 250 => '230_249',
            default => '250_plus',
        };
    }

    private function lift(string $label, string $key, string $quality = 'lower_body', array $sources = ['fmtrx_population', 'benchmark_library', 'community_reference']): array
    {
        return $this->definition($label, 'maximum_strength', $quality, 'lbs', 'higher', 'body_weight_at_test', ['tested_1rm', 'rep_max'], ['body_weight_at_test', 'age_group'], $sources, $key, true);
    }

    private function endurance(string $label, string $key, string $unit = 'reps', array $sources = ['fmtrx_population', 'benchmark_library', 'community_reference']): array
    {
        return $this->definition($label, 'strength_endurance', 'strength_endurance', $unit, 'higher', 'none', ['max_repetitions', 'timed_test'], ['test_protocol', 'age_group'], $sources, $key, true);
    }

    private function grip(string $label, string $key, bool $scoreEligible = true): array
    {
        return $this->definition($label, 'grip_strength', 'grip_strength', 'lbs', 'higher', 'age_weight_regression', ['dynamometer'], ['age_group', 'body_weight_at_test', 'test_protocol'], ['fmtrx_population', 'approved_research'], $key, $scoreEligible);
    }

    private function power(string $label, string $key, string $unit, array $sources = ['fmtrx_population', 'benchmark_library']): array
    {
        return $this->definition($label, 'explosive_strength', 'explosive_power', $unit, 'higher', 'none', ['best_attempt'], ['age_group'], $sources, $key, true);
    }

    private function speed(string $label, string $key): array
    {
        return $this->definition($label, 'speed_support', 'linear_speed', 'sec', 'lower', 'none', ['best_attempt'], ['age_group'], ['fmtrx_population', 'benchmark_library'], $key, false);
    }

    private function definition(
        string $label,
        string $category,
        string $quality,
        string $unit,
        string $direction,
        string $normalization,
        array $testMethods,
        array $requiredContext,
        array $sources,
        ?string $populationKey,
        bool $scoreEligible,
        string $goalPolicy = 'next_percentile_tier',
    ): array {
        return [
            'canonical_key' => $populationKey ?? str($label)->snake()->toString(),
            'label' => $label,
            'category' => $category,
            'physical_quality' => $quality,
            'data_type' => 'number',
            'canonical_unit' => $unit,
            'direction' => $direction,
            'normalization_method' => $normalization,
            'supported_test_methods' => $testMethods,
            'required_context' => $requiredContext,
            'benchmark_sources' => $sources,
            'population_metric_key' => $populationKey,
            'minimum_sample_size' => PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            'fallback_behavior' => 'benchmark_needs_data',
            'display_precision' => 'sec' === $unit ? 2 : 1,
            'score_eligibility' => $scoreEligible,
            'goal_policy' => $goalPolicy,
            'lifecycle_status' => 'active',
            'version' => self::VERSION,
        ];
    }
}
