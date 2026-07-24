<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cage;

use App\Services\Cage\CageDistanceService;
use App\Services\Cage\CageDistanceValidationService;
use Tests\TestCase;

/**
 * Validates PHYSICAL BEHAVIOR (monotonicity, symmetry, sign conventions,
 * ground-crossing termination) rather than snapshotting every exact distance
 * — see the Cage Distance Validation Lab report for why: there is no
 * universally "correct" carry table, only physics constraints the model must
 * satisfy (Statcast/Nathan references in the final report).
 */
class CageDistanceValidationServiceTest extends TestCase
{
    private CageDistanceValidationService $validation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validation = new CageDistanceValidationService(new CageDistanceService());
    }

    // 1. Negative initial vertical velocity.
    public function test_negative_launch_angle_has_negative_initial_vertical_velocity(): void
    {
        $row = $this->validation->evaluatePoint(60, -5, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertLessThan(0.0, $row['initial_vertical_velocity_fps']);
    }

    // 2. Lands sooner than +15deg.
    public function test_negative_launch_angle_lands_sooner_than_positive_fifteen(): void
    {
        $negative = $this->validation->evaluatePoint(60, -5, 0, [], CageDistanceValidationService::SPEED_GRID);
        $positive = $this->validation->evaluatePoint(60, 15, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertLessThan($positive['hang_time_seconds'], $negative['hang_time_seconds']);
        $this->assertLessThan($positive['v2_estimated_carry_ft'], $negative['v2_estimated_carry_ft']);
    }

    // 3. Ground-ball classification.
    public function test_negative_launch_angle_is_classified_ground_ball(): void
    {
        $row = $this->validation->evaluatePoint(60, -5, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertSame('ground_ball', $row['batted_ball_type']);
    }

    // 4. No ground-roll baked into the ground-ball figure.
    public function test_negative_launch_angle_air_carry_excludes_ground_roll(): void
    {
        // Ground-ball rows report air-carry-to-first-contact only (see
        // CageDistanceService::estimate()'s ground-ball branch) — there is no
        // surface/COR/friction model, so this can never include rollout. A
        // short value here (well under v1's ~104ft rollout-dominated figure
        // for the same input) demonstrates that.
        $row = $this->validation->evaluatePoint(60, -5, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertLessThan(40.0, $row['v2_estimated_carry_ft']);
    }

    // 5. EV monotonicity at fixed LA.
    public function test_carry_increases_with_exit_velocity_at_fixed_launch_angle(): void
    {
        $low = $this->validation->evaluatePoint(70, 25, 0, [], CageDistanceValidationService::SPEED_GRID);
        $high = $this->validation->evaluatePoint(100, 25, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertGreaterThan($low['v2_estimated_carry_ft'], $high['v2_estimated_carry_ft']);
    }

    // 6. Launch-curve peak in [20,40] for 90-110mph.
    /** @dataProvider launchCurvePeakProvider */
    public function test_launch_angle_curve_peaks_between_20_and_40_degrees(float $ev): void
    {
        $matrix = $this->validation->buildMatrix([$ev], null, [0.0], CageDistanceValidationService::SPEED_GRID);
        $flags = array_merge(...array_map(static fn (array $r) => $r['validation_flags'], $matrix['rows']));
        $this->assertNotContains('launch_curve_peak_invalid', $flags);

        $peak = $matrix['summary']['peak_launch_angle_by_ev'][$ev];
        $this->assertGreaterThanOrEqual(20.0, $peak);
        $this->assertLessThanOrEqual(40.0, $peak);
    }

    public static function launchCurvePeakProvider(): array
    {
        return [[90.0], [100.0], [110.0]];
    }

    // 7. 60deg carry below peak carry.
    public function test_carry_at_60_degrees_is_below_peak_carry(): void
    {
        $matrix = $this->validation->buildMatrix([100.0], null, [0.0], CageDistanceValidationService::SPEED_GRID);
        $byLa = [];
        foreach ($matrix['rows'] as $row) {
            $byLa[$row['launch_angle_deg']] = $row;
        }
        $this->assertLessThan($matrix['summary']['max_carry_by_ev'][100.0], $byLa[60.0]['v2_estimated_carry_ft']);
    }

    // 8. +/-15 spray symmetry.
    public function test_spray_fifteen_has_matching_radial_carry(): void
    {
        $pos = $this->validation->evaluatePoint(95, 25, 15, [], CageDistanceValidationService::SPEED_GRID);
        $neg = $this->validation->evaluatePoint(95, 25, -15, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertEqualsWithDelta($pos['v2_estimated_carry_ft'], $neg['v2_estimated_carry_ft'], 1.0);
    }

    // 9. +/-30 spray symmetry.
    public function test_spray_thirty_has_matching_radial_carry(): void
    {
        $pos = $this->validation->evaluatePoint(95, 25, 30, [], CageDistanceValidationService::SPEED_GRID);
        $neg = $this->validation->evaluatePoint(95, 25, -30, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertEqualsWithDelta($pos['v2_estimated_carry_ft'], $neg['v2_estimated_carry_ft'], 1.0);
    }

    // 10. spray=0 lands near-zero laterally.
    public function test_zero_spray_produces_near_zero_lateral_landing_coordinate(): void
    {
        $row = $this->validation->evaluatePoint(95, 25, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertLessThan(1.0, abs($row['landing_x_ft']));
    }

    // 11. Ground crossing is interpolated (no ground_crossing_invalid on a healthy point).
    public function test_ground_crossing_is_interpolated_and_flagged_consistent(): void
    {
        $result = $this->validation->evaluateSinglePointWithFlags(95, 25, 0);
        $this->assertNotContains('ground_crossing_invalid', $result['row']['validation_flags']);
    }

    // 12. Deterministic seed => repeatable output.
    public function test_deterministic_seed_produces_repeatable_output(): void
    {
        $a = $this->validation->evaluatePoint(95, 25, 0, [], CageDistanceValidationService::SPEED_GRID);
        $b = $this->validation->evaluatePoint(95, 25, 0, [], CageDistanceValidationService::SPEED_GRID);
        $this->assertSame($a['v2_estimated_carry_ft'], $b['v2_estimated_carry_ft']);
        $this->assertSame($a['landing_x_ft'], $b['landing_x_ft']);
    }

    // 16. v1 fixture joins correctly to v2 rows.
    public function test_v1_fixture_joins_correctly_to_v2_rows(): void
    {
        $lookup = $this->validation->loadV1Reference();
        $this->assertNotEmpty($lookup, 'tests/Fixtures/cage_distance_v1_reference.json missing/empty — run scripts/generate-cage-v1-reference.mjs (mobile repo) and copy it in.');

        $matrix = $this->validation->buildMatrix([60.0], [-5.0, 5.0, 15.0], [0.0], CageDistanceValidationService::SPEED_GRID, $lookup);
        foreach ($matrix['rows'] as $row) {
            $this->assertArrayHasKey('v1_distance_ft', $row);
            $this->assertNotNull($row['v1_distance_ft']);
            $this->assertArrayHasKey('difference_ft', $row);
        }
    }

    public function test_zero_grid_symmetry_and_monotonicity_hold_across_the_whole_default_matrix(): void
    {
        $matrix = $this->validation->buildMatrix([60.0, 95.0], null, null, CageDistanceValidationService::SPEED_GRID);
        $this->assertSame(0, $matrix['summary']['monotonicity_failures']);
        $this->assertSame(0, $matrix['summary']['spray_symmetry_failures']);
        $this->assertSame(0, $matrix['summary']['negative_angle_failures']);
    }

    // ── Regression anchors ───────────────────────────────────────────────────
    // Bands are generous (physical sanity), not exact-float snapshots — see
    // class docblock. Centered on the actual grid-speed output measured while
    // building this lab; a real model change should still land inside these.

    /** @dataProvider regressionAnchorProvider */
    public function test_regression_anchor(float $ev, float $la, float $expectedMin, float $expectedMax): void
    {
        $row = $this->validation->evaluatePoint($ev, $la, 0, [], CageDistanceValidationService::SPEED_GRID);
        $carry = $row['v2_estimated_carry_ft'];
        $this->assertNotNull($carry);
        $this->assertGreaterThanOrEqual($expectedMin, $carry, "EV={$ev} LA={$la}: {$carry}ft below regression anchor band");
        $this->assertLessThanOrEqual($expectedMax, $carry, "EV={$ev} LA={$la}: {$carry}ft above regression anchor band");
    }

    public static function regressionAnchorProvider(): array
    {
        return [
            '60mph -5deg (ground-ball air-carry)' => [60.0, -5.0, 10.0, 40.0],
            '60mph 5deg (ground-ball air-carry)' => [60.0, 5.0, 40.0, 95.0],
            '60mph 15deg' => [60.0, 15.0, 110.0, 175.0],
            '80mph 25deg' => [80.0, 25.0, 250.0, 340.0],
            '100mph 25deg' => [100.0, 25.0, 350.0, 450.0],
            '100mph 60deg' => [100.0, 60.0, 180.0, 280.0],
        ];
    }
}
