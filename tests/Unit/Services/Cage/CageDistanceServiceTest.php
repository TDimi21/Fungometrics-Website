<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cage;

use App\Services\Cage\CageDistanceService;
use Tests\TestCase;

class CageDistanceServiceTest extends TestCase
{
    private CageDistanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CageDistanceService();
    }

    // ── Required-input validation ───────────────────────────────────────────

    public function test_missing_exit_velocity_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->estimate(['launch_angle_deg' => 25, 'spray_angle_deg' => 0]);
    }

    public function test_missing_launch_angle_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->estimate(['exit_velocity_mph' => 95, 'spray_angle_deg' => 0]);
    }

    public function test_missing_spray_angle_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->estimate(['exit_velocity_mph' => 95, 'launch_angle_deg' => 25]);
    }

    // ── EV/LA test grid — physical sanity checks ────────────────────────────
    // Reference envelope: MLB Statcast data puts well-struck fly balls
    // (95-105 mph, 25-35°) in the ~370-430 ft carry range. This isn't an
    // exact-value assertion (no calibration data exists yet — see the final
    // report) but a sanity envelope so a broken sign/unit-conversion error
    // fails loudly instead of silently shipping a 40 ft or 4000 ft carry.

    /** @dataProvider evLaunchGridProvider */
    public function test_ev_launch_grid_within_physical_envelope(
        float $ev,
        float $la,
        float $expectedMin,
        float $expectedMax,
    ): void {
        $result = $this->service->estimate([
            'exit_velocity_mph' => $ev,
            'launch_angle_deg' => $la,
            'spray_angle_deg' => 0,
            'measured_spin_rpm' => 2100, // isolate EV/LA effect from spin-estimation noise
        ]);

        $this->assertGreaterThanOrEqual(
            $expectedMin,
            $result['estimated_carry_ft'],
            "EV={$ev} LA={$la}: carry {$result['estimated_carry_ft']}ft below expected envelope",
        );
        $this->assertLessThanOrEqual(
            $expectedMax,
            $result['estimated_carry_ft'],
            "EV={$ev} LA={$la}: carry {$result['estimated_carry_ft']}ft above expected envelope",
        );
    }

    public static function evLaunchGridProvider(): array
    {
        return [
            'soft contact, low liner' => [70.0, 12.0, 90.0, 180.0],
            'medium EV, line drive'   => [85.0, 15.0, 190.0, 290.0],
            'hard contact, sweet spot fly' => [95.0, 25.0, 330.0, 420.0],
            'barrel, optimal launch'  => [100.0, 28.0, 370.0, 440.0],
            'max EV, high fly'        => [105.0, 35.0, 380.0, 460.0],
            'popup'                   => [90.0, 55.0, 90.0, 300.0],
        ];
    }

    public function test_higher_exit_velocity_always_carries_further_at_same_launch_angle(): void
    {
        $low = $this->service->estimate(['exit_velocity_mph' => 80, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100]);
        $high = $this->service->estimate(['exit_velocity_mph' => 100, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100]);

        $this->assertGreaterThan($low['estimated_carry_ft'], $high['estimated_carry_ft']);
    }

    public function test_spray_angle_does_not_meaningfully_change_radial_carry(): void
    {
        // Per the design constraint: spray angle controls direction, not
        // radial carry, since no sidespin is modeled without measured data.
        $center = $this->service->estimate(['exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100]);
        $pulled = $this->service->estimate(['exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => -30, 'measured_spin_rpm' => 2100]);

        $this->assertEqualsWithDelta($center['estimated_carry_ft'], $pulled['estimated_carry_ft'], 0.5);
    }

    // ── Ball profiles ────────────────────────────────────────────────────────

    public function test_flat_seam_pro_carries_further_than_raised_seam(): void
    {
        $base = ['exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100];

        $flatSeam = $this->service->estimate([...$base, 'ball_profile' => 'flat_seam_pro']);
        $standardized = $this->service->estimate([...$base, 'ball_profile' => 'standardized']);
        $raisedSeam = $this->service->estimate([...$base, 'ball_profile' => 'raised_seam']);

        $this->assertGreaterThan($standardized['estimated_carry_ft'], $flatSeam['estimated_carry_ft']);
        $this->assertGreaterThan($raisedSeam['estimated_carry_ft'], $standardized['estimated_carry_ft']);
    }

    public function test_unknown_ball_profile_falls_back_to_standardized_with_assumption_note(): void
    {
        $result = $this->service->estimate([
            'exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0,
            'ball_profile' => 'not_a_real_profile',
        ]);

        $this->assertSame('standardized', $result['inputs_used']['ball_profile']);
        $this->assertStringContainsString('Unknown ball_profile', implode(' ', $result['assumptions']));
    }

    // ── Environment modes ────────────────────────────────────────────────────

    public function test_standardized_mode_ignores_facility_environment_fields(): void
    {
        $result = $this->service->estimate([
            'exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0,
            'mode' => 'standardized', 'elevation_ft' => 5280, 'temperature_f' => 95,
        ]);

        $this->assertSame(70.0, $result['inputs_used']['temperature_f']);
        $this->assertSame(0.0, $result['inputs_used']['elevation_ft']);
    }

    public function test_facility_mode_high_altitude_carries_further_than_sea_level(): void
    {
        $base = ['exit_velocity_mph' => 100, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100];

        $seaLevel = $this->service->estimate([...$base, 'mode' => 'facility', 'elevation_ft' => 0]);
        $highAltitude = $this->service->estimate([...$base, 'mode' => 'facility', 'elevation_ft' => 5280]);

        $this->assertGreaterThan($seaLevel['estimated_carry_ft'], $highAltitude['estimated_carry_ft']);
    }

    // ── Ground balls ─────────────────────────────────────────────────────────

    public function test_negative_launch_angle_is_ground_ball_with_no_invented_roll(): void
    {
        $result = $this->service->estimate(['exit_velocity_mph' => 85, 'launch_angle_deg' => -5, 'spray_angle_deg' => 0]);

        $this->assertSame('ground_ball', $result['batted_ball_type']);
        $this->assertNull($result['estimated_carry_ft']);
        $this->assertArrayHasKey('air_carry_to_first_contact_ft', $result);
        $this->assertGreaterThan(0, $result['air_carry_to_first_contact_ft']);
    }

    public function test_explicit_ground_ball_flag_overrides_launch_angle(): void
    {
        $result = $this->service->estimate([
            'exit_velocity_mph' => 85, 'launch_angle_deg' => 12, 'spray_angle_deg' => 0, 'ground_ball' => true,
        ]);

        $this->assertSame('ground_ball', $result['batted_ball_type']);
    }

    // ── Monte Carlo determinism ──────────────────────────────────────────────

    public function test_monte_carlo_sweep_is_deterministic_for_same_seeded_inputs(): void
    {
        $input = [
            'exit_velocity_mph' => 100, 'launch_angle_deg' => 22, 'spray_angle_deg' => -5,
            'launch_angle_min_deg' => 19, 'launch_angle_max_deg' => 25,
            'spray_angle_min_deg' => -8, 'spray_angle_max_deg' => -2,
        ];

        $first = $this->service->estimate($input);
        $second = $this->service->estimate($input);

        $this->assertSame($first['estimated_carry_ft'], $second['estimated_carry_ft']);
        $this->assertSame($first['carry_low_ft'], $second['carry_low_ft']);
        $this->assertSame($first['carry_high_ft'], $second['carry_high_ft']);
    }

    public function test_wider_cell_bounds_widen_the_uncertainty_range(): void
    {
        $tight = $this->service->estimate([
            'exit_velocity_mph' => 100, 'launch_angle_deg' => 22, 'spray_angle_deg' => 0,
            'launch_angle_min_deg' => 21, 'launch_angle_max_deg' => 23,
        ]);
        $wide = $this->service->estimate([
            'exit_velocity_mph' => 100, 'launch_angle_deg' => 22, 'spray_angle_deg' => 0,
            'launch_angle_min_deg' => 5, 'launch_angle_max_deg' => 40,
        ]);

        $tightSpread = $tight['carry_high_ft'] - $tight['carry_low_ft'];
        $wideSpread = $wide['carry_high_ft'] - $wide['carry_low_ft'];

        $this->assertGreaterThan($tightSpread, $wideSpread);
    }

    public function test_confidence_is_high_only_for_measured_spin_and_no_ranges(): void
    {
        $precise = $this->service->estimate([
            'exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100,
        ]);
        $estimatedSpin = $this->service->estimate([
            'exit_velocity_mph' => 95, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0,
        ]);

        $this->assertSame('high', $precise['confidence']);
        $this->assertNotSame('high', $estimatedSpin['confidence']);
    }

    // ── Regression comparison against the CURRENT model ─────────────────────
    // Ported from MODERN_FungoMetrics's src/utils/ballFlight.js (vacuum
    // projectile range × a flat 0.55 empirical carry factor — no drag/lift
    // physics). This pins that model's known behavior and shows the new RK4
    // model isn't wildly divergent for a typical well-struck fly ball, while
    // documenting that they are NOT expected to match exactly — the new
    // model is a materially different (and more physically grounded) method.

    private function legacyBallFlightCarryFt(float $evMph, float $launchDeg): float
    {
        $mphToFps = 1.46667;
        $g = 32.174;
        $carryFactor = 0.55;

        $v0 = $evMph * $mphToFps;
        $laRad = deg2rad($launchDeg);

        return max(0.0, (($v0 ** 2 * sin(2 * $laRad)) / $g) * $carryFactor);
    }

    public function test_legacy_model_port_matches_known_reference_value(): void
    {
        // 100 mph / 25°: v0=146.667 fps, sin(50°)=0.766044, ×0.55 carry factor
        // → (146.667² × 0.766044 / 32.174) × 0.55 ≈ 281.7 ft.
        $this->assertEqualsWithDelta(281.7, $this->legacyBallFlightCarryFt(100.0, 25.0), 1.0);
    }

    public function test_new_model_is_in_the_same_order_of_magnitude_as_legacy_model(): void
    {
        $legacyCarry = $this->legacyBallFlightCarryFt(100.0, 25.0);
        $newResult = $this->service->estimate([
            'exit_velocity_mph' => 100, 'launch_angle_deg' => 25, 'spray_angle_deg' => 0, 'measured_spin_rpm' => 2100,
        ]);

        // Wide tolerance on purpose — the models disagree by design (legacy
        // has no drag/lift physics at all, and is known to underestimate
        // well-struck fly balls, which is the whole reason this new model
        // exists). This guards against a gross regression (e.g. a
        // unit-conversion bug producing 40 ft or 4000 ft), not against
        // normal, expected model-vs-model disagreement.
        $this->assertEqualsWithDelta($legacyCarry, $newResult['estimated_carry_ft'], 150.0);
    }
}
