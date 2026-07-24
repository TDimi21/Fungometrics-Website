<?php

declare(strict_types=1);

namespace App\Services\Cage;

/**
 * FMTRX Cage Distance Validation Lab — additive, dev/admin-only tooling that
 * compares the mobile v1 estimate (src/utils/ballFlight.js, via the generated
 * tests/Fixtures/cage_distance_v1_reference.json fixture — see
 * scripts/generate-cage-v1-reference.mjs in the mobile repo) against
 * CageDistanceService v2, and checks v2's output against a set of physical
 * *behavior* rules (monotonicity, symmetry, ground-crossing, etc.) rather
 * than a hard-coded table of "correct" distances.
 *
 * This service never modifies CageDistanceService's physics, never touches
 * CageStatisticsService, and never writes to production cage results — it
 * only reads CageDistanceService::estimate() output.
 */
class CageDistanceValidationService
{
    public const DEFAULT_EXIT_VELOCITIES_MPH = [40, 50, 60, 70, 80, 90, 95, 100, 105, 110, 120];
    public const DEFAULT_LAUNCH_ANGLES_DEG = [-15, -10, -5, 0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 60];
    public const DEFAULT_SPRAY_ANGLES_DEG = [-30, -15, 0, 15, 30];

    public const SPEED_FAST = 'fast'; // 1 sample: cheapest, still seed-reproducible
    public const SPEED_GRID = 'grid'; // <=25 samples: default for full-matrix runs
    public const SPEED_FULL = 'full'; // CageDistanceService's real production default (500)

    private const GRID_SAMPLE_COUNTS = [
        self::SPEED_FAST => 1,
        self::SPEED_GRID => 25,
        self::SPEED_FULL => null,
    ];

    private const MPH_TO_MPS = 0.44704;

    // ── Rule tolerances / bands (see class docblock rules A-H) ──
    private const NEGATIVE_ANGLE_REFERENCE_LA_DEG = 15.0;
    private const NEGATIVE_ANGLE_MAX_HANG_TIME_S = 1.5;
    private const ZERO_ANGLE_PEAK_GROUP_MIN_DEG = 15.0;
    private const ZERO_ANGLE_PEAK_GROUP_MAX_DEG = 35.0;
    private const EV_MONOTONIC_TOLERANCE_FT = 1.0;
    private const LAUNCH_CURVE_MIN_EV_MPH = 70.0;
    private const LAUNCH_CURVE_PEAK_BAND_MIN_DEG = 20.0;
    private const LAUNCH_CURVE_PEAK_BAND_MAX_DEG = 40.0;
    private const LAUNCH_CURVE_PEAK_INVALID_BELOW_DEG = 15.0;
    private const LAUNCH_CURVE_PEAK_INVALID_ABOVE_DEG = 45.0;
    private const HIGH_ANGLE_DEG = 60.0;
    private const SPRAY_SYMMETRY_TOLERANCE_FT = 1.0;
    private const SPRAY_RADIAL_STABILITY_TOLERANCE_FT = 2.0;
    private const ZERO_SPRAY_LANDING_X_TOLERANCE_FT = 1.0;
    private const LANDING_GEOMETRY_TOLERANCE_FT = 1.0;
    private const GROUND_CROSSING_DETERMINISTIC_TOLERANCE_FT = 0.15;
    private const GROUND_CROSSING_RADIUS_SLACK_FT = 3.0;
    private const MAX_FLIGHT_TIME_S = 12.0;

    // Soft, informational-only signal (never trips --fail-on-invalid).
    private const LARGE_V1_V2_DIFFERENCE_PERCENT = 50.0;

    public function __construct(private readonly CageDistanceService $distanceService)
    {
    }

    /**
     * Evaluate a single EV/LA/spray point. Used by both the artisan command's
     * single-row mode and the admin API preview endpoint.
     *
     * @param  array<string,mixed>  $overrides  Optional CageDistanceService input
     *   overrides (contact_height_ft, ball_profile, measured_spin_rpm, mode,
     *   environment fields, ...). Grid generation never passes these — see
     *   buildMatrix()'s fixed "default validation conditions".
     */
    public function evaluatePoint(float $ev, float $la, float $spray, array $overrides = [], string $speed = self::SPEED_FULL): array
    {
        return $this->runOne($ev, $la, $spray, $overrides, $speed);
    }

    /** @param  array<string,float>  $v1Lookup  From loadV1Reference(). */
    public function lookupV1(array $v1Lookup, float $ev, float $la, float $spray): ?float
    {
        return $v1Lookup[$this->rowKey($ev, $la, $spray)] ?? null;
    }

    /**
     * Single-point evaluation with the subset of physical-validation rules
     * that are meaningful for one ad-hoc point in isolation (used by the
     * admin preview endpoint). Rules that need a full launch-angle/EV/spray
     * sweep for context (monotonicity, launch-curve peak, spray symmetry) are
     * NOT evaluated here — those require cage:validation-matrix or the Vue
     * table mode. Rule A (negative angle) and B (zero angle) still get their
     * real comparison points, just computed on demand instead of from a
     * pre-built grid.
     *
     * @return array{row: array<string,mixed>, explanations: list<string>}
     */
    public function evaluateSinglePointWithFlags(float $ev, float $la, float $spray, array $overrides = []): array
    {
        $row = $this->runOne($ev, $la, $spray, $overrides, self::SPEED_FULL);
        $flags = [];
        $explanations = [];

        if ($la < 0.0) {
            $reference = $this->runOne($ev, self::NEGATIVE_ANGLE_REFERENCE_LA_DEG, $spray, $overrides, self::SPEED_FULL);
            $invalid = $row['initial_vertical_velocity_fps'] >= 0.0
                || $row['hang_time_seconds'] > self::NEGATIVE_ANGLE_MAX_HANG_TIME_S
                || ($row['v2_estimated_carry_ft'] !== null && $reference['v2_estimated_carry_ft'] !== null && $row['v2_estimated_carry_ft'] >= $reference['v2_estimated_carry_ft']);
            if ($invalid) {
                $flags[] = 'negative_angle_invalid';
                $explanations[] = sprintf(
                    'Negative launch angle should be a short, fast ground ball: got %.1fs hang time and %.1f ft carry vs %.1f ft at +15° for the same EV.',
                    $row['hang_time_seconds'],
                    $row['v2_estimated_carry_ft'] ?? 0.0,
                    $reference['v2_estimated_carry_ft'] ?? 0.0,
                );
            }
        }

        if (abs($la) < 1e-9) {
            $groupMax = null;
            foreach ([15.0, 20.0, 25.0, 30.0, 35.0] as $groupLa) {
                $groupRow = $this->runOne($ev, $groupLa, $spray, $overrides, self::SPEED_FULL);
                if ($groupRow['v2_estimated_carry_ft'] !== null) {
                    $groupMax = $groupMax === null ? $groupRow['v2_estimated_carry_ft'] : max($groupMax, $groupRow['v2_estimated_carry_ft']);
                }
            }
            if ($groupMax !== null && $row['v2_estimated_carry_ft'] !== null && $row['v2_estimated_carry_ft'] > $groupMax) {
                $flags[] = 'zero_angle_invalid';
                $explanations[] = sprintf('0° launch carried %.1f ft, farther than the best 15-35° carry of %.1f ft at the same EV.', $row['v2_estimated_carry_ft'], $groupMax);
            }
        }

        $radius = sqrt($row['landing_x_ft'] ** 2 + $row['landing_y_ft'] ** 2);
        $hasRealBand = $row['v2_low_ft'] !== null && abs($row['v2_high_ft'] - $row['v2_low_ft']) > 1.0e-6;
        $radiusInconsistent = match (true) {
            $row['v2_estimated_carry_ft'] === null => false,
            $row['v2_low_ft'] === null => abs($radius - $row['v2_estimated_carry_ft']) > self::GROUND_CROSSING_DETERMINISTIC_TOLERANCE_FT,
            $hasRealBand => $radius < $row['v2_low_ft'] - self::GROUND_CROSSING_RADIUS_SLACK_FT || $radius > $row['v2_high_ft'] + self::GROUND_CROSSING_RADIUS_SLACK_FT,
            default => false,
        };
        if ($radiusInconsistent || $row['maximum_height_ft'] < 0.0 || $row['hang_time_seconds'] <= 0.0 || $row['hang_time_seconds'] >= self::MAX_FLIGHT_TIME_S) {
            $flags[] = 'ground_crossing_invalid';
            $explanations[] = 'Reported landing point / hang time is inconsistent with the reported carry estimate.';
        }

        $row['validation_flags'] = $flags;

        return ['row' => $row, 'explanations' => $explanations];
    }

    /**
     * @param  ?list<float>  $evs
     * @param  ?list<float>  $las
     * @param  ?list<float>  $sprays
     * @param  ?array<string,float>  $v1Lookup  Keyed by rowKey() — see loadV1Reference().
     * @return array{rows: list<array<string,mixed>>, summary: array<string,mixed>}
     */
    public function buildMatrix(
        ?array $evs = null,
        ?array $las = null,
        ?array $sprays = null,
        string $speed = self::SPEED_GRID,
        ?array $v1Lookup = null,
    ): array {
        $evs ??= self::DEFAULT_EXIT_VELOCITIES_MPH;
        $las ??= self::DEFAULT_LAUNCH_ANGLES_DEG;
        $sprays ??= self::DEFAULT_SPRAY_ANGLES_DEG;

        $rows = [];
        foreach ($evs as $ev) {
            foreach ($las as $la) {
                foreach ($sprays as $spray) {
                    $key = $this->rowKey($ev, $la, $spray);
                    $rows[$key] = $this->runOne((float) $ev, (float) $la, (float) $spray, [], $speed);
                }
            }
        }

        $this->applyNegativeAngleAndZeroAngleAndPeakRules($rows, $evs, $las, $sprays);
        $this->applyMonotonicityRule($rows, $evs, $las, $sprays);
        $this->applySprayRules($rows, $evs, $las, $sprays);
        $this->applyGroundCrossingRule($rows);

        if ($v1Lookup !== null) {
            $this->joinV1($rows, $v1Lookup);
        }

        return [
            'rows' => array_values($rows),
            'summary' => $this->summarize($rows),
        ];
    }

    /** Loads the mobile v1 reference fixture into a rowKey()-keyed lookup. */
    public function loadV1Reference(?string $path = null): array
    {
        $path ??= base_path('tests/Fixtures/cage_distance_v1_reference.json');
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $lookup = [];
        foreach ($decoded as $row) {
            $key = $this->rowKey((float) $row['exit_velocity_mph'], (float) $row['launch_angle_deg'], (float) $row['spray_angle_deg']);
            $lookup[$key] = (float) $row['v1_distance_ft'];
        }

        return $lookup;
    }

    // ── Single-point evaluation ──────────────────────────────────────────────

    private function runOne(float $ev, float $la, float $spray, array $overrides, string $speed): array
    {
        $input = array_merge([
            'exit_velocity_mph' => $ev,
            'launch_angle_deg' => $la,
            'spray_angle_deg' => $spray,
            'mode' => 'standardized',
            'contact_height_ft' => 3.0,
            'ball_profile' => 'standardized',
        ], $overrides);

        $sampleOverride = self::GRID_SAMPLE_COUNTS[$speed] ?? null;
        $result = $this->distanceService->estimate($input, $sampleOverride);

        $isGroundBall = $result['batted_ball_type'] === 'ground_ball';
        $carryFt = $isGroundBall
            ? ($result['air_carry_to_first_contact_ft'] ?? null)
            : ($result['estimated_carry_ft'] ?? null);

        return [
            'exit_velocity_mph' => $ev,
            'launch_angle_deg' => $la,
            'spray_angle_deg' => $spray,
            'v2_estimated_carry_ft' => $carryFt,
            'v2_low_ft' => $isGroundBall ? null : ($result['carry_low_ft'] ?? null),
            'v2_high_ft' => $isGroundBall ? null : ($result['carry_high_ft'] ?? null),
            'hang_time_seconds' => $result['hang_time_seconds'],
            'maximum_height_ft' => $result['maximum_height_ft'],
            'landing_x_ft' => $result['landing_x_ft'],
            'landing_y_ft' => $result['landing_y_ft'],
            'batted_ball_type' => $result['batted_ball_type'],
            'confidence' => $result['confidence'],
            'initial_vertical_velocity_fps' => round($ev * self::MPH_TO_MPS * 3.280839895 * sin(deg2rad($la)), 2),
            'assumptions' => $result['assumptions'],
            'validation_flags' => [],
        ];
    }

    private function rowKey(float $ev, float $la, float $spray): string
    {
        return sprintf('%.2f|%.2f|%.2f', $ev, $la, $spray);
    }

    private function flag(array &$rows, string $key, string $flagName): void
    {
        if (!isset($rows[$key])) {
            return;
        }
        if (!in_array($flagName, $rows[$key]['validation_flags'], true)) {
            $rows[$key]['validation_flags'][] = $flagName;
        }
    }

    // ── Rule A (negative angle), B (zero angle), D (launch-angle curve) ─────
    // All three compare rows within one (ev, spray) launch-angle sweep, so
    // they share one grouping pass.

    private function applyNegativeAngleAndZeroAngleAndPeakRules(array &$rows, array $evs, array $las, array $sprays): void
    {
        foreach ($evs as $ev) {
            foreach ($sprays as $spray) {
                $referenceKey = $this->rowKey((float) $ev, self::NEGATIVE_ANGLE_REFERENCE_LA_DEG, (float) $spray);
                $referenceCarry = $rows[$referenceKey]['v2_estimated_carry_ft'] ?? null;

                foreach ($las as $la) {
                    $la = (float) $la;
                    $key = $this->rowKey((float) $ev, $la, (float) $spray);
                    if (!isset($rows[$key])) {
                        continue;
                    }
                    $row = $rows[$key];

                    // A. Negative launch angle.
                    if ($la < 0.0) {
                        $invalid = $row['initial_vertical_velocity_fps'] >= 0.0
                            || $row['hang_time_seconds'] > self::NEGATIVE_ANGLE_MAX_HANG_TIME_S
                            || ($referenceCarry !== null && $row['v2_estimated_carry_ft'] !== null && $row['v2_estimated_carry_ft'] >= $referenceCarry);
                        if ($invalid) {
                            $this->flag($rows, $key, 'negative_angle_invalid');
                        }
                    }

                    // B. Zero-degree rule.
                    if (abs($la) < 1e-9) {
                        $peakGroupMax = $this->maxCarryInLaunchRange($rows, (float) $ev, (float) $spray, $las, self::ZERO_ANGLE_PEAK_GROUP_MIN_DEG, self::ZERO_ANGLE_PEAK_GROUP_MAX_DEG);
                        if ($peakGroupMax !== null && $row['v2_estimated_carry_ft'] !== null && $row['v2_estimated_carry_ft'] > $peakGroupMax) {
                            $this->flag($rows, $key, 'zero_angle_invalid');
                        }
                    }
                }

                // D. Launch-angle curve (EV >= 70 mph only).
                if ((float) $ev >= self::LAUNCH_CURVE_MIN_EV_MPH) {
                    $this->applyLaunchCurveRule($rows, (float) $ev, (float) $spray, $las);
                }
            }
        }
    }

    private function maxCarryInLaunchRange(array $rows, float $ev, float $spray, array $las, float $min, float $max): ?float
    {
        $best = null;
        foreach ($las as $la) {
            $la = (float) $la;
            if ($la < $min || $la > $max) {
                continue;
            }
            $carry = $rows[$this->rowKey($ev, $la, $spray)]['v2_estimated_carry_ft'] ?? null;
            if ($carry !== null && ($best === null || $carry > $best)) {
                $best = $carry;
            }
        }

        return $best;
    }

    private function applyLaunchCurveRule(array &$rows, float $ev, float $spray, array $las): void
    {
        $peakLa = null;
        $peakCarry = null;
        $bandMax = null;
        $highAngleKey = $this->rowKey($ev, self::HIGH_ANGLE_DEG, $spray);
        $highAngleCarry = $rows[$highAngleKey]['v2_estimated_carry_ft'] ?? null;

        foreach ($las as $la) {
            $la = (float) $la;
            $key = $this->rowKey($ev, $la, $spray);
            $carry = $rows[$key]['v2_estimated_carry_ft'] ?? null;
            if ($carry === null) {
                continue;
            }
            if ($peakCarry === null || $carry > $peakCarry) {
                $peakCarry = $carry;
                $peakLa = $la;
            }
            if ($la >= self::LAUNCH_CURVE_PEAK_BAND_MIN_DEG && $la <= self::LAUNCH_CURVE_PEAK_BAND_MAX_DEG) {
                $bandMax = $bandMax === null ? $carry : max($bandMax, $carry);
            }
        }

        if ($peakLa === null) {
            return;
        }

        $peakInvalid = $peakLa < self::LAUNCH_CURVE_PEAK_INVALID_BELOW_DEG || $peakLa > self::LAUNCH_CURVE_PEAK_INVALID_ABOVE_DEG;
        if ($peakInvalid) {
            foreach ($las as $la) {
                $this->flag($rows, $this->rowKey($ev, (float) $la, $spray), 'launch_curve_peak_invalid');
            }
        }

        if ($highAngleCarry !== null && $bandMax !== null && $highAngleCarry > $bandMax) {
            $this->flag($rows, $highAngleKey, 'high_angle_not_declining');
        }
    }

    // ── Rule C: EV monotonicity at fixed (LA, spray) ─────────────────────────

    private function applyMonotonicityRule(array &$rows, array $evs, array $las, array $sprays): void
    {
        $sortedEvs = array_map('floatval', $evs);
        sort($sortedEvs);

        foreach ($las as $la) {
            foreach ($sprays as $spray) {
                $prevCarry = null;
                $prevKey = null;
                foreach ($sortedEvs as $ev) {
                    $key = $this->rowKey($ev, (float) $la, (float) $spray);
                    $carry = $rows[$key]['v2_estimated_carry_ft'] ?? null;
                    if ($carry === null) {
                        continue;
                    }
                    if ($prevCarry !== null && $carry < $prevCarry - self::EV_MONOTONIC_TOLERANCE_FT) {
                        $this->flag($rows, $key, 'ev_non_monotonic');
                        $this->flag($rows, $prevKey, 'ev_non_monotonic');
                    }
                    $prevCarry = $carry;
                    $prevKey = $key;
                }
            }
        }
    }

    // ── Rules E (spray symmetry), F (radial stability), G (landing geometry) ─

    private function applySprayRules(array &$rows, array $evs, array $las, array $sprays): void
    {
        foreach ($evs as $ev) {
            foreach ($las as $la) {
                $ev = (float) $ev;
                $la = (float) $la;

                // F. Radial stability across all spray angles at this (ev, la).
                $carriesBySpray = [];
                foreach ($sprays as $spray) {
                    $carry = $rows[$this->rowKey($ev, $la, (float) $spray)]['v2_estimated_carry_ft'] ?? null;
                    if ($carry !== null) {
                        $carriesBySpray[(float) $spray] = $carry;
                    }
                }
                if (count($carriesBySpray) >= 2 && (max($carriesBySpray) - min($carriesBySpray)) > self::SPRAY_RADIAL_STABILITY_TOLERANCE_FT) {
                    foreach (array_keys($carriesBySpray) as $spray) {
                        $this->flag($rows, $this->rowKey($ev, $la, $spray), 'spray_changes_radial_carry');
                    }
                }

                // E. +/- spray symmetry.
                foreach ($sprays as $spray) {
                    $spray = (float) $spray;
                    if ($spray <= 0.0) {
                        continue;
                    }
                    $posKey = $this->rowKey($ev, $la, $spray);
                    $negKey = $this->rowKey($ev, $la, -$spray);
                    $posCarry = $rows[$posKey]['v2_estimated_carry_ft'] ?? null;
                    $negCarry = $rows[$negKey]['v2_estimated_carry_ft'] ?? null;
                    if ($posCarry !== null && $negCarry !== null && abs($posCarry - $negCarry) > self::SPRAY_SYMMETRY_TOLERANCE_FT) {
                        $this->flag($rows, $posKey, 'spray_asymmetry');
                        $this->flag($rows, $negKey, 'spray_asymmetry');
                    }
                }

                // G. Landing-coordinate geometry.
                $zeroKey = $this->rowKey($ev, $la, 0.0);
                if (isset($rows[$zeroKey]) && abs($rows[$zeroKey]['landing_x_ft']) > self::ZERO_SPRAY_LANDING_X_TOLERANCE_FT) {
                    $this->flag($rows, $zeroKey, 'landing_geometry_invalid');
                }
                foreach ($sprays as $spray) {
                    $spray = (float) $spray;
                    if ($spray <= 0.0) {
                        continue;
                    }
                    $posKey = $this->rowKey($ev, $la, $spray);
                    $negKey = $this->rowKey($ev, $la, -$spray);
                    if (!isset($rows[$posKey], $rows[$negKey])) {
                        continue;
                    }
                    $pos = $rows[$posKey];
                    $neg = $rows[$negKey];
                    $forwardMismatch = abs($pos['landing_y_ft'] - $neg['landing_y_ft']) > self::LANDING_GEOMETRY_TOLERANCE_FT;
                    $lateralMismatch = abs($pos['landing_x_ft'] + $neg['landing_x_ft']) > self::LANDING_GEOMETRY_TOLERANCE_FT
                        || abs(abs($pos['landing_x_ft']) - abs($neg['landing_x_ft'])) > self::LANDING_GEOMETRY_TOLERANCE_FT;
                    if ($forwardMismatch || $lateralMismatch) {
                        $this->flag($rows, $posKey, 'landing_geometry_invalid');
                        $this->flag($rows, $negKey, 'landing_geometry_invalid');
                    }
                }
            }
        }
    }

    // ── Rule H: ground crossing self-consistency ─────────────────────────────
    // CageDistanceService doesn't expose its internal RK4 step trace (kept
    // additive/minimal on purpose). landing_x_ft/landing_y_ft/hang_time/
    // maximum_height_ft always come from ONE deterministic center-point
    // trajectory, while v2_estimated_carry_ft/v2_low_ft/v2_high_ft come from
    // the (up to 500-sample) Monte Carlo sweep over spin/drag uncertainty —
    // so for non-ground-ball rows the landing radius is checked for falling
    // within the reported low/high band (with slack, since it's a different
    // statistic — the center draw — not literally the median), rather than
    // exactly equaling the point estimate. For ground-ball rows, radius and
    // the reported carry are the SAME center computation, so the tolerance is
    // tight (float/rounding noise only).

    private function applyGroundCrossingRule(array &$rows): void
    {
        foreach ($rows as $key => $row) {
            if ($row['v2_estimated_carry_ft'] === null) {
                continue;
            }
            $radius = sqrt($row['landing_x_ft'] ** 2 + $row['landing_y_ft'] ** 2);

            // A single-sample ("fast" speed) band collapses to one random
            // draw, not a real percentile band — there's nothing meaningful
            // to compare the deterministic center radius against, so skip
            // the radius check in that degenerate case (height/hang-time
            // sanity checks below still apply).
            $hasRealBand = $row['v2_low_ft'] !== null && abs($row['v2_high_ft'] - $row['v2_low_ft']) > 1.0e-6;

            $radiusInconsistent = match (true) {
                $row['v2_low_ft'] === null => abs($radius - $row['v2_estimated_carry_ft']) > self::GROUND_CROSSING_DETERMINISTIC_TOLERANCE_FT,
                $hasRealBand => $radius < $row['v2_low_ft'] - self::GROUND_CROSSING_RADIUS_SLACK_FT || $radius > $row['v2_high_ft'] + self::GROUND_CROSSING_RADIUS_SLACK_FT,
                default => false,
            };

            $invalid = $radiusInconsistent
                || $row['maximum_height_ft'] < 0.0
                || $row['hang_time_seconds'] <= 0.0
                || $row['hang_time_seconds'] >= self::MAX_FLIGHT_TIME_S;
            if ($invalid) {
                $this->flag($rows, $key, 'ground_crossing_invalid');
            }
        }
    }

    // ── v1/v2 join ────────────────────────────────────────────────────────────

    private function joinV1(array &$rows, array $v1Lookup): void
    {
        foreach ($rows as $key => $row) {
            if (!isset($v1Lookup[$key])) {
                continue;
            }
            $v1 = $v1Lookup[$key];
            $v2 = $row['v2_estimated_carry_ft'];
            $rows[$key]['v1_distance_ft'] = $v1;
            $rows[$key]['v2_distance_ft'] = $v2;
            if ($v2 !== null) {
                $diff = $v2 - $v1;
                $rows[$key]['difference_ft'] = round($diff, 2);
                $rows[$key]['difference_percent'] = $v1 != 0.0 ? round(($diff / $v1) * 100, 1) : null;
                if ($rows[$key]['difference_percent'] !== null && abs($rows[$key]['difference_percent']) >= self::LARGE_V1_V2_DIFFERENCE_PERCENT) {
                    $this->flag($rows, $key, 'large_v1_v2_difference');
                }
            } else {
                $rows[$key]['difference_ft'] = null;
                $rows[$key]['difference_percent'] = null;
            }
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /** @return list<string> Flags that count as a hard failure (vs. a soft/warning-only flag). */
    public static function hardFlags(): array
    {
        return self::HARD_FLAGS;
    }

    private const HARD_FLAGS = [
        'negative_angle_invalid',
        'zero_angle_invalid',
        'ev_non_monotonic',
        'launch_curve_peak_invalid',
        'high_angle_not_declining',
        'spray_asymmetry',
        'spray_changes_radial_carry',
        'landing_geometry_invalid',
        'ground_crossing_invalid',
    ];

    private function summarize(array $rows): array
    {
        $total = count($rows);
        $failing = 0;
        $warning = 0;
        $peakByEv = [];
        $maxCarryByEv = [];
        $negativeAngleFailures = 0;
        $monotonicityFailures = 0;
        $sprayFailures = 0;
        $largestDiff = null;
        $largestDiffRow = null;

        foreach ($rows as $row) {
            $hardFlags = array_intersect($row['validation_flags'], self::HARD_FLAGS);
            if (!empty($hardFlags)) {
                $failing++;
            } elseif (!empty($row['validation_flags'])) {
                $warning++;
            }

            if (in_array('negative_angle_invalid', $row['validation_flags'], true)) {
                $negativeAngleFailures++;
            }
            if (in_array('ev_non_monotonic', $row['validation_flags'], true)) {
                $monotonicityFailures++;
            }
            if (in_array('spray_asymmetry', $row['validation_flags'], true) || in_array('spray_changes_radial_carry', $row['validation_flags'], true)) {
                $sprayFailures++;
            }

            $ev = $row['exit_velocity_mph'];
            if ($row['v2_estimated_carry_ft'] !== null) {
                if (!isset($maxCarryByEv[$ev]) || $row['v2_estimated_carry_ft'] > $maxCarryByEv[$ev]) {
                    $maxCarryByEv[$ev] = $row['v2_estimated_carry_ft'];
                }
                if (!isset($peakByEv[$ev]) || $row['v2_estimated_carry_ft'] > $peakByEv[$ev]['carry']) {
                    $peakByEv[$ev] = ['launch_angle_deg' => $row['launch_angle_deg'], 'carry' => $row['v2_estimated_carry_ft']];
                }
            }

            if (isset($row['difference_ft']) && $row['difference_ft'] !== null) {
                if ($largestDiff === null || abs($row['difference_ft']) > abs($largestDiff)) {
                    $largestDiff = $row['difference_ft'];
                    $largestDiffRow = [
                        'exit_velocity_mph' => $row['exit_velocity_mph'],
                        'launch_angle_deg' => $row['launch_angle_deg'],
                        'spray_angle_deg' => $row['spray_angle_deg'],
                        'difference_ft' => $row['difference_ft'],
                        'difference_percent' => $row['difference_percent'],
                    ];
                }
            }
        }

        return [
            'total_cases' => $total,
            'passing_cases' => $total - $failing - $warning,
            'warning_cases' => $warning,
            'failing_cases' => $failing,
            'peak_launch_angle_by_ev' => array_map(fn ($v) => $v['launch_angle_deg'], $peakByEv),
            'max_carry_by_ev' => $maxCarryByEv,
            'negative_angle_failures' => $negativeAngleFailures,
            'monotonicity_failures' => $monotonicityFailures,
            'spray_symmetry_failures' => $sprayFailures,
            'largest_v1_v2_difference' => $largestDiffRow,
        ];
    }
}
