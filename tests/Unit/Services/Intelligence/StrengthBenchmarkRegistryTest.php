<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\StrengthBenchmarkRegistry;
use PHPUnit\Framework\TestCase;

class StrengthBenchmarkRegistryTest extends TestCase
{
    public function test_registry_covers_every_accepted_and_derived_strength_metric(): void
    {
        $registry = (new StrengthBenchmarkRegistry())->all();

        $this->assertEqualsCanonicalizing([
            'body_weight', 'front_squat', 'back_squat', 'bench_press', 'deadlift',
            'trap_bar_deadlift', 'power_clean', 'pull_ups', 'pushups', 'plank_hold',
            'grip_strength_left', 'grip_strength_right', 'grip_strength_average',
            'grip_strength_best', 'grip_asymmetry_percentage', 'vertical_jump',
            'broad_jump', 'med_ball_rotational_throw', 'sprint_10yd',
            'forty_yard_dash', 'sixty_yard_dash',
        ], array_keys($registry));

        foreach ($registry as $key => $definition) {
            foreach ([
                'canonical_key', 'label', 'category', 'physical_quality', 'data_type',
                'canonical_unit', 'direction', 'normalization_method', 'supported_test_methods',
                'required_context', 'benchmark_sources', 'population_metric_key',
                'minimum_sample_size', 'fallback_behavior', 'display_precision',
                'score_eligibility', 'goal_policy', 'lifecycle_status', 'version',
            ] as $required) {
                $this->assertArrayHasKey($required, $definition, "{$key} is missing {$required}");
            }
        }

        $this->assertNotSame($registry['front_squat']['canonical_key'], $registry['back_squat']['canonical_key']);
        $this->assertNotSame($registry['deadlift']['canonical_key'], $registry['trap_bar_deadlift']['canonical_key']);
        $this->assertSame('descriptive', $registry['body_weight']['direction']);
        $this->assertFalse($registry['sprint_10yd']['score_eligibility']);
    }

    public function test_strength_specific_bodyweight_bands_preserve_youth_and_older_boundaries(): void
    {
        $registry = new StrengthBenchmarkRegistry();

        $this->assertSame('under_90', $registry->strengthBodyweightBand(89, 14));
        $this->assertSame('90_109', $registry->strengthBodyweightBand(90, 14));
        $this->assertSame('190_plus', $registry->strengthBodyweightBand(190, 14));
        $this->assertSame('under_130', $registry->strengthBodyweightBand(129, 15));
        $this->assertSame('170_189', $registry->strengthBodyweightBand(182, 16));
        $this->assertSame('250_plus', $registry->strengthBodyweightBand(250, 19));
        $this->assertSame('unknown', $registry->strengthBodyweightBand(null, 16));
    }
}
