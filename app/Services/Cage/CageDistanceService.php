<?php

declare(strict_types=1);

namespace App\Services\Cage;

/**
 * FMTRX Cage Distance Model v2.
 *
 * Estimates batted-ball carry distance from exit velocity, launch angle, and
 * spray angle using a 3D RK4 numerical integration of gravity + quadratic
 * drag + Magnus lift, with backspin-only lift (no invented sidespin) and a
 * seeded Monte Carlo sweep over cage-cell angle bounds / contact-height /
 * spin uncertainty when exact values aren't measured.
 *
 * This does not replace or overwrite any existing `distance_travel` values —
 * it is a new, additive estimation path. See CageDistanceServiceTest for the
 * regression comparison against the current client-side model
 * (MODERN_FungoMetrics's src/utils/ballFlight.js).
 */
class CageDistanceService
{
    public const MODEL_VERSION = 'cage_v2.0';

    // ── Physical constants (SI internally; inputs/outputs are mph/deg/ft) ──
    private const GRAVITY_MPS2 = 9.80665;
    private const MPH_TO_MPS = 0.44704;
    private const FT_TO_M = 0.3048;
    private const M_TO_FT = 3.280839895;

    private const BALL_MASS_KG = 0.145;       // regulation baseball, ~5.125 oz
    private const BALL_RADIUS_M = 0.0369;     // ~2.9" diameter
    private const BALL_CROSS_SECTION_M2 = M_PI * self::BALL_RADIUS_M ** 2;

    // ── Nathan fitted aerodynamic model — coefficient clamps ──
    // CD/CL are fitted from tracked, well-hit fly balls (>=90 mph, 20-35°).
    // Clamped to keep the model numerically stable outside that regime
    // (e.g. very low EV or extreme launch angles) rather than letting the
    // linear/rational fits run away to unphysical values.
    private const CD_BASE_MIN = 0.20;
    private const CD_BASE_MAX = 0.55;
    private const CL_MIN = 0.0;
    private const CL_MAX = 0.45;
    private const SPIN_FACTOR_MAX = 0.5; // S = Rω/v; real batted balls rarely exceed ~0.3-0.4

    // ── Environment defaults ──
    private const DEFAULT_TEMPERATURE_F = 70.0;
    private const DEFAULT_HUMIDITY_PCT = 50.0;
    private const DEFAULT_ELEVATION_FT = 0.0;
    private const DEFAULT_WIND_MPH = 0.0;
    private const DEFAULT_WIND_DIRECTION_DEG = 0.0;
    private const STANDARD_SEA_LEVEL_PRESSURE_PA = 101325.0;

    // ── Contact height ──
    private const DEFAULT_CONTACT_HEIGHT_FT = 3.0;
    public const MIN_CONTACT_HEIGHT_FT = 2.25;
    public const MAX_CONTACT_HEIGHT_FT = 4.5;

    // ── Integration ──
    private const RK4_DT_S = 0.01;
    private const MAX_FLIGHT_TIME_S = 12.0;

    // ── Monte Carlo ──
    private const MONTE_CARLO_SAMPLES = 500;
    private const MONTE_CARLO_SEED = 42;
    // Multiplicative drag uncertainty band. This is a placeholder pending real
    // calibration data (see CageDistanceService::estimate() docblock / final
    // report) — ball-to-ball drag variation is the dominant source of
    // real-world carry variation per Nathan's tracked-flight research.
    private const DRAG_UNCERTAINTY_FRACTION = 0.04;

    /**
     * Ball construction profiles. Multiplies the Nathan-fitted base drag
     * coefficient. These specific multipliers are FMTRX modeling assumptions
     * seeded from published flat-seam-vs-raised-seam drag differences — they
     * are NOT laboratory-measured for every ball type and must be replaced
     * with fitted values once paired calibration data exists.
     */
    private const BALL_PROFILES = [
        'standardized'  => ['drag_multiplier' => 1.00, 'label' => 'Standardized'],
        'flat_seam_pro' => ['drag_multiplier' => 0.93, 'label' => 'MLB / Professional Flat Seam'],
        'raised_seam'   => ['drag_multiplier' => 1.05, 'label' => 'College/NCAA Raised Seam'],
        'high_school'   => ['drag_multiplier' => 1.03, 'label' => 'High School'],
        'youth'         => ['drag_multiplier' => 1.08, 'label' => 'Youth'],
    ];

    /**
     * Default/estimated backspin by launch-angle class, used only when
     * measured_spin_rpm isn't provided. Boundaries match the app's existing
     * batted-ball classification (src/utils/hitClassification.js: Ground
     * Ball < 8°, Line Drive 8-24°, Fly Ball 25-44°, Pop Fly >= 45°) so the
     * spin class agrees with whatever trajectory label the rest of the app
     * already shows for the same swing.
     *
     * These rpm values are FMTRX presets, not per-ball measured truth — they
     * need calibration against real device data (Rapsodo/TrackMan) before
     * being trusted as more than a reasonable starting range.
     */
    private const SPIN_CLASS_TABLE = [
        ['maxLaunchDeg' => 8,    'default' => 500,  'min' => 0,    'max' => 1500, 'label' => 'Ground/low contact'],
        ['maxLaunchDeg' => 25,   'default' => 1500, 'min' => 800,  'max' => 2400, 'label' => 'Line drive'],
        ['maxLaunchDeg' => 45,   'default' => 2100, 'min' => 1400, 'max' => 3000, 'label' => 'Fly ball'],
        ['maxLaunchDeg' => null, 'default' => 2500, 'min' => 1500, 'max' => 3800, 'label' => 'High fly/pop-up'],
    ];

    /**
     * Estimate carry distance for a single batted ball.
     *
     * @param  array<string,mixed>  $input  See class docblock / README for the full input contract:
     *   Required: exit_velocity_mph, launch_angle_deg, spray_angle_deg.
     *   Optional: launch_angle_min_deg, launch_angle_max_deg, spray_angle_min_deg,
     *   spray_angle_max_deg, contact_height_ft, contact_height_min_ft,
     *   contact_height_max_ft, measured_spin_rpm, ground_ball, ball_profile,
     *   mode ('standardized'|'facility'), temperature_f, pressure_inhg,
     *   humidity_percent, elevation_ft, wind_speed_mph, wind_direction_deg.
     * @param  ?int  $monteCarloSampleOverride  Additive knob for callers that need a
     *   cheaper (or reproducible single-draw) uncertainty sweep than the
     *   production default (500 samples) — e.g. CageDistanceValidationService's
     *   grid generator. Leave null for every regular caller; behavior and
     *   output are byte-for-byte identical to before this parameter existed.
     *
     * @return array<string,mixed>
     */
    public function estimate(array $input, ?int $monteCarloSampleOverride = null): array
    {
        $assumptions = [];

        $ev = $this->requireNumeric($input, 'exit_velocity_mph');
        $laCenter = $this->requireNumeric($input, 'launch_angle_deg');
        $saCenter = $this->requireNumeric($input, 'spray_angle_deg');

        $laMin = isset($input['launch_angle_min_deg']) ? (float) $input['launch_angle_min_deg'] : $laCenter;
        $laMax = isset($input['launch_angle_max_deg']) ? (float) $input['launch_angle_max_deg'] : $laCenter;
        $saMin = isset($input['spray_angle_min_deg']) ? (float) $input['spray_angle_min_deg'] : $saCenter;
        $saMax = isset($input['spray_angle_max_deg']) ? (float) $input['spray_angle_max_deg'] : $saCenter;

        // Ground ball threshold matches hitClassification.js's "Ground Ball < 8°"
        // boundary so this model's batted-ball type never disagrees with what
        // the rest of the app already shows for the same swing.
        $isGroundBall = (bool) ($input['ground_ball'] ?? false) || $laCenter < 8.0;

        $ballProfileKey = (string) ($input['ball_profile'] ?? 'standardized');
        if (!isset(self::BALL_PROFILES[$ballProfileKey])) {
            $assumptions[] = "Unknown ball_profile '{$ballProfileKey}'; falling back to 'standardized'.";
            $ballProfileKey = 'standardized';
        }
        $dragMultiplier = self::BALL_PROFILES[$ballProfileKey]['drag_multiplier'];

        $mode = (string) ($input['mode'] ?? 'standardized');
        [$tempF, $pressureInHg, $humidityPct, $elevationFt, $windMph, $windDirDeg] =
            $this->resolveEnvironment($input, $mode, $assumptions);

        $airDensity = $this->airDensityKgM3($tempF, $pressureInHg, $humidityPct, $elevationFt);

        $contactHeightFt = isset($input['contact_height_ft'])
            ? (float) $input['contact_height_ft']
            : self::DEFAULT_CONTACT_HEIGHT_FT;
        if (!isset($input['contact_height_ft'])) {
            $assumptions[] = sprintf(
                'contact_height_ft not provided; defaulted to %.2f ft (clamped range %.2f-%.2f ft).',
                self::DEFAULT_CONTACT_HEIGHT_FT,
                self::MIN_CONTACT_HEIGHT_FT,
                self::MAX_CONTACT_HEIGHT_FT,
            );
        }
        $contactHeightFt = $this->clamp($contactHeightFt, self::MIN_CONTACT_HEIGHT_FT, self::MAX_CONTACT_HEIGHT_FT);
        $contactHeightMinFt = isset($input['contact_height_min_ft'])
            ? $this->clamp((float) $input['contact_height_min_ft'], self::MIN_CONTACT_HEIGHT_FT, self::MAX_CONTACT_HEIGHT_FT)
            : $contactHeightFt;
        $contactHeightMaxFt = isset($input['contact_height_max_ft'])
            ? $this->clamp((float) $input['contact_height_max_ft'], self::MIN_CONTACT_HEIGHT_FT, self::MAX_CONTACT_HEIGHT_FT)
            : $contactHeightFt;

        // ── Spin: measured takes priority; otherwise estimate from launch class ──
        $measuredSpinRpm = $input['measured_spin_rpm'] ?? null;
        if ($measuredSpinRpm !== null) {
            $spinSource = 'measured';
            $spinDefault = (float) $measuredSpinRpm;
            $spinMin = $spinMax = $spinDefault;
        } else {
            $spinSource = 'estimated';
            $spinClass = $this->spinClassFor($laCenter);
            $override = is_array($input['estimated_spin_class'] ?? null) ? $input['estimated_spin_class'] : [];
            $spinDefault = isset($override['default']) ? (float) $override['default'] : (float) $spinClass['default'];
            $spinMin = isset($override['min']) ? (float) $override['min'] : (float) $spinClass['min'];
            $spinMax = isset($override['max']) ? (float) $override['max'] : (float) $spinClass['max'];
            $assumptions[] = sprintf(
                "No measured_spin_rpm provided; using estimated spin class '%s' (default %d rpm, range %d-%d rpm).",
                $spinClass['label'],
                (int) $spinDefault,
                (int) $spinMin,
                (int) $spinMax,
            );
        }

        // ── Deterministic center-point simulation (also used for hang time / apex) ──
        $center = $this->simulateOne(
            $ev,
            $laCenter,
            $saCenter,
            $spinDefault,
            $contactHeightFt,
            $airDensity,
            $dragMultiplier,
            $windMph,
            $windDirDeg,
        );

        if ($isGroundBall) {
            return [
                'estimated_carry_ft' => null,
                'carry_low_ft' => null,
                'carry_high_ft' => null,
                'confidence' => 'low',
                'hang_time_seconds' => round($center['hangTimeS'], 2),
                'maximum_height_ft' => round($center['maxHeightFt'], 1),
                'landing_x_ft' => round($center['landingXFt'], 1),
                'landing_y_ft' => round($center['landingYFt'], 1),
                'air_carry_to_first_contact_ft' => round($center['carryFt'], 1),
                'batted_ball_type' => 'ground_ball',
                'inputs_used' => $this->buildInputsUsed(
                    $ev, $laCenter, $saCenter, $laMin, $laMax, $saMin, $saMax,
                    $contactHeightFt, $spinDefault, $spinSource, $ballProfileKey, $mode,
                    $tempF, $humidityPct, $elevationFt, $windMph, $windDirDeg, $airDensity,
                ),
                'assumptions' => array_merge($assumptions, [
                    'Ground ball detected (launch angle < 8° or ground_ball flag set); roll distance is not estimated — no surface/COR/friction model exists yet.',
                ]),
                'model_version' => self::MODEL_VERSION,
            ];
        }

        // ── Monte Carlo uncertainty sweep across cell/contact-height/spin ranges ──
        $rangesVary = ($laMax > $laMin) || ($saMax > $saMin)
            || ($contactHeightMaxFt > $contactHeightMinFt) || ($spinMax > $spinMin);

        if ($rangesVary) {
            $sampleCount = $monteCarloSampleOverride ?? self::MONTE_CARLO_SAMPLES;
            $rngState = self::MONTE_CARLO_SEED === 0 ? 1 : self::MONTE_CARLO_SEED;
            $samples = [];
            for ($i = 0; $i < $sampleCount; $i++) {
                $laSample = $this->uniform($rngState, $laMin, $laMax);
                $saSample = $this->uniform($rngState, $saMin, $saMax);
                $chSample = $this->uniform($rngState, $contactHeightMinFt, $contactHeightMaxFt);
                $spinSample = $this->uniform($rngState, $spinMin, $spinMax);
                $dragNoise = 1.0 + $this->uniform($rngState, -self::DRAG_UNCERTAINTY_FRACTION, self::DRAG_UNCERTAINTY_FRACTION);

                $res = $this->simulateOne(
                    $ev,
                    $laSample,
                    $saSample,
                    $spinSample,
                    $chSample,
                    $airDensity,
                    $dragMultiplier * $dragNoise,
                    $windMph,
                    $windDirDeg,
                );
                $samples[] = $res['carryFt'];
            }
            sort($samples);
            $n = count($samples);
            $median = $samples[(int) floor(($n - 1) * 0.50)];
            $p05 = $samples[(int) floor(($n - 1) * 0.05)];
            $p95 = $samples[(int) floor(($n - 1) * 0.95)];
        } else {
            $median = $center['carryFt'];
            $p05 = $p95 = $median;
        }

        $spreadFt = $p95 - $p05;
        $confidence = $spreadFt <= 20.0 ? 'high' : ($spreadFt <= 45.0 ? 'medium' : 'low');
        if ($spinSource === 'estimated' && $confidence === 'high') {
            // Estimated (not measured) spin caps confidence at medium even if
            // the geometric ranges happen to be tight.
            $confidence = 'medium';
        }

        return [
            'estimated_carry_ft' => round($median, 1),
            'carry_low_ft' => round($p05, 1),
            'carry_high_ft' => round($p95, 1),
            'confidence' => $confidence,
            'hang_time_seconds' => round($center['hangTimeS'], 2),
            'maximum_height_ft' => round($center['maxHeightFt'], 1),
            'landing_x_ft' => round($center['landingXFt'], 1),
            'landing_y_ft' => round($center['landingYFt'], 1),
            'batted_ball_type' => $this->battedBallType($laCenter),
            'inputs_used' => $this->buildInputsUsed(
                $ev, $laCenter, $saCenter, $laMin, $laMax, $saMin, $saMax,
                $contactHeightFt, $spinDefault, $spinSource, $ballProfileKey, $mode,
                $tempF, $humidityPct, $elevationFt, $windMph, $windDirDeg, $airDensity,
            ),
            'assumptions' => $assumptions,
            'model_version' => self::MODEL_VERSION,
        ];
    }

    /**
     * @return array{carryFt:float,hangTimeS:float,maxHeightFt:float,landingXFt:float,landingYFt:float}
     */
    private function simulateOne(
        float $evMph,
        float $launchDeg,
        float $sprayDeg,
        float $spinRpm,
        float $contactHeightFt,
        float $airDensity,
        float $dragMultiplier,
        float $windMph,
        float $windDirDeg,
    ): array {
        $v0 = $evMph * self::MPH_TO_MPS;
        $laRad = deg2rad($launchDeg);
        $saRad = deg2rad($sprayDeg);

        // x = lateral (spray) axis, y = downrange/center-field axis, z = up.
        $state = [
            0.0,
            0.0,
            $contactHeightFt * self::FT_TO_M,
            $v0 * cos($laRad) * sin($saRad),
            $v0 * cos($laRad) * cos($saRad),
            $v0 * sin($laRad),
        ];

        // Wind vector uses the same 0°=downrange-center, +°=toward spray-right
        // convention as spray angle, for consistency within this service.
        $windSpeedMps = $windMph * self::MPH_TO_MPS;
        $windDirRad = deg2rad($windDirDeg);
        $windX = $windSpeedMps * sin($windDirRad);
        $windY = $windSpeedMps * cos($windDirRad);

        $spinRadPerSec = $spinRpm * 2.0 * M_PI / 60.0;
        $cdBase = $this->clamp(0.297 + 0.0292 * ($spinRpm / 1000.0), self::CD_BASE_MIN, self::CD_BASE_MAX);
        $cd = $cdBase * $dragMultiplier;

        $maxHeightM = $state[2];
        $t = 0.0;
        $dt = self::RK4_DT_S;
        $prevState = $state;
        $prevT = 0.0;

        $derivative = function (array $y) use ($airDensity, $cd, $spinRadPerSec, $windX, $windY): array {
            [, , , $vx, $vy, $vz] = $y;
            $relVx = $vx - $windX;
            $relVy = $vy - $windY;
            $relVz = $vz;
            $speed = sqrt($relVx ** 2 + $relVy ** 2 + $relVz ** 2);

            if ($speed < 1.0e-6) {
                return [$vx, $vy, $vz, 0.0, 0.0, -self::GRAVITY_MPS2];
            }

            $horizSpeed = sqrt($relVx ** 2 + $relVy ** 2);
            $spinFactor = $horizSpeed > 1.0e-6
                ? $this->clamp((self::BALL_RADIUS_M * $spinRadPerSec) / $speed, 0.0, self::SPIN_FACTOR_MAX)
                : 0.0;
            $cl = $this->clamp((1.120 * $spinFactor) / (0.583 + 2.333 * max($spinFactor, 1.0e-9)), self::CL_MIN, self::CL_MAX);

            $dragCoeff = 0.5 * $airDensity * self::BALL_CROSS_SECTION_M2 * $cd * $speed / self::BALL_MASS_KG;
            $dragAx = -$dragCoeff * $relVx;
            $dragAy = -$dragCoeff * $relVy;
            $dragAz = -$dragCoeff * $relVz;

            // Backspin-only Magnus lift: spin axis is horizontal and
            // perpendicular to the ball's current horizontal direction of
            // travel (no sidespin/curve is modeled — see class docblock).
            $liftAx = 0.0;
            $liftAy = 0.0;
            $liftAz = 0.0;
            if ($horizSpeed > 1.0e-6 && $cl > 0.0) {
                $hx = $relVx / $horizSpeed;
                $hy = $relVy / $horizSpeed;
                // axis = horizontal vector rotated +90° from travel direction
                $axisX = -$hy;
                $axisY = $hx;
                $axisZ = 0.0;
                // liftDir = axis × v_rel (unnormalized), then normalize.
                $vrx = $relVx / $speed;
                $vry = $relVy / $speed;
                $vrz = $relVz / $speed;
                $crossX = $axisY * $vrz - $axisZ * $vry;
                $crossY = $axisZ * $vrx - $axisX * $vrz;
                $crossZ = $axisX * $vry - $axisY * $vrx;
                $crossMag = sqrt($crossX ** 2 + $crossY ** 2 + $crossZ ** 2);
                if ($crossMag > 1.0e-9) {
                    // Orient so lift has a positive z-component (backspin lifts).
                    $sign = $crossZ < 0 ? -1.0 : 1.0;
                    $liftCoeff = $sign * 0.5 * $airDensity * self::BALL_CROSS_SECTION_M2 * $cl * $speed ** 2 / self::BALL_MASS_KG;
                    $liftAx = $liftCoeff * ($crossX / $crossMag);
                    $liftAy = $liftCoeff * ($crossY / $crossMag);
                    $liftAz = $liftCoeff * ($crossZ / $crossMag);
                }
            }

            return [
                $vx, $vy, $vz,
                $dragAx + $liftAx,
                $dragAy + $liftAy,
                $dragAz + $liftAz - self::GRAVITY_MPS2,
            ];
        };

        $add = fn (array $a, array $b, float $scale) => array_map(fn ($ai, $bi) => $ai + $bi * $scale, $a, $b);

        while ($t < self::MAX_FLIGHT_TIME_S) {
            $k1 = $derivative($state);
            $k2 = $derivative($add($state, $k1, $dt / 2));
            $k3 = $derivative($add($state, $k2, $dt / 2));
            $k4 = $derivative($add($state, $k3, $dt));

            $prevState = $state;
            $prevT = $t;

            for ($i = 0; $i < 6; $i++) {
                $state[$i] += ($dt / 6) * ($k1[$i] + 2 * $k2[$i] + 2 * $k3[$i] + $k4[$i]);
            }
            $t += $dt;

            $maxHeightM = max($maxHeightM, $state[2]);

            if ($state[2] <= 0.0) {
                break;
            }
        }

        // Interpolate the ground crossing between the last two steps instead
        // of just taking the (possibly below-ground) final step.
        $prevZ = $prevState[2];
        $currZ = $state[2];
        if ($currZ <= 0.0 && $prevZ > $currZ) {
            $fraction = $prevZ / ($prevZ - $currZ);
            $fraction = $this->clamp($fraction, 0.0, 1.0);
            $landingXM = $prevState[0] + $fraction * ($state[0] - $prevState[0]);
            $landingYM = $prevState[1] + $fraction * ($state[1] - $prevState[1]);
            $hangTimeS = $prevT + $fraction * ($t - $prevT);
        } else {
            // Hit MAX_FLIGHT_TIME_S without landing (shouldn't happen for
            // realistic inputs) — use the last computed point.
            $landingXM = $state[0];
            $landingYM = $state[1];
            $hangTimeS = $t;
        }

        $carryM = sqrt($landingXM ** 2 + $landingYM ** 2);

        return [
            'carryFt' => $carryM * self::M_TO_FT,
            'hangTimeS' => $hangTimeS,
            'maxHeightFt' => $maxHeightM * self::M_TO_FT,
            'landingXFt' => $landingXM * self::M_TO_FT,
            'landingYFt' => $landingYM * self::M_TO_FT,
        ];
    }

    /**
     * Air density (kg/m³) from temperature, pressure (or elevation-derived
     * standard pressure if no pressure given), and relative humidity.
     * Dry-air + water-vapor partial pressure model (ideal gas law), water
     * vapor saturation pressure via the Tetens approximation.
     */
    private function airDensityKgM3(float $tempF, ?float $pressureInHg, float $humidityPct, float $elevationFt): float
    {
        $tempK = ($tempF - 32.0) * 5.0 / 9.0 + 273.15;
        $tempC = $tempK - 273.15;

        if ($pressureInHg !== null) {
            $pressurePa = $pressureInHg * 3386.389;
        } else {
            $elevationM = $elevationFt * self::FT_TO_M;
            // Barometric formula (standard atmosphere), pressure only — this
            // does not also apply an elevation-based temperature lapse; the
            // caller-provided/default temperature is used as-is.
            $pressurePa = self::STANDARD_SEA_LEVEL_PRESSURE_PA * (1.0 - 2.25577e-5 * $elevationM) ** 5.25588;
        }

        $satVaporPressurePa = 610.78 * exp((17.27 * $tempC) / ($tempC + 237.3));
        $vaporPressurePa = ($humidityPct / 100.0) * $satVaporPressurePa;
        $dryPressurePa = max(0.0, $pressurePa - $vaporPressurePa);

        $rDry = 287.05;   // J/(kg·K)
        $rVapor = 461.495; // J/(kg·K)

        return ($dryPressurePa / ($rDry * $tempK)) + ($vaporPressurePa / ($rVapor * $tempK));
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  list<string>  $assumptions
     * @return array{0:float,1:?float,2:float,3:float,4:float,5:float}
     */
    private function resolveEnvironment(array $input, string $mode, array &$assumptions): array
    {
        if ($mode === 'standardized') {
            if (isset($input['temperature_f']) || isset($input['humidity_percent']) || isset($input['elevation_ft']) || isset($input['wind_speed_mph'])) {
                $assumptions[] = "mode='standardized' ignores provided environment fields; using fixed 70°F/50% humidity/sea level/0 wind for cross-player comparability. Pass mode='facility' to use them.";
            }

            return [self::DEFAULT_TEMPERATURE_F, null, self::DEFAULT_HUMIDITY_PCT, 0.0, 0.0, self::DEFAULT_WIND_DIRECTION_DEG];
        }

        $tempF = isset($input['temperature_f']) ? (float) $input['temperature_f'] : self::DEFAULT_TEMPERATURE_F;
        $pressureInHg = isset($input['pressure_inhg']) ? (float) $input['pressure_inhg'] : null;
        $humidityPct = isset($input['humidity_percent']) ? (float) $input['humidity_percent'] : self::DEFAULT_HUMIDITY_PCT;
        $elevationFt = isset($input['elevation_ft']) ? (float) $input['elevation_ft'] : self::DEFAULT_ELEVATION_FT;
        $windMph = isset($input['wind_speed_mph']) ? (float) $input['wind_speed_mph'] : self::DEFAULT_WIND_MPH;
        $windDirDeg = isset($input['wind_direction_deg']) ? (float) $input['wind_direction_deg'] : self::DEFAULT_WIND_DIRECTION_DEG;

        $missing = array_filter([
            'temperature_f' => !isset($input['temperature_f']),
            'elevation_ft' => !isset($input['elevation_ft']),
        ]);
        if ($missing) {
            $assumptions[] = 'mode=\'facility\' but no facility profile provided for: ' . implode(', ', array_keys($missing)) . '; falling back to standardized defaults for those fields.';
        }

        return [$tempF, $pressureInHg, $humidityPct, $elevationFt, $windMph, $windDirDeg];
    }

    /** @return array{default:int,min:int,max:int,label:string} */
    private function spinClassFor(float $launchDeg): array
    {
        foreach (self::SPIN_CLASS_TABLE as $row) {
            if ($row['maxLaunchDeg'] === null || $launchDeg < $row['maxLaunchDeg']) {
                return $row;
            }
        }

        return end(self::SPIN_CLASS_TABLE);
    }

    /** Matches src/utils/hitClassification.js boundaries exactly. */
    private function battedBallType(float $launchDeg): string
    {
        return match (true) {
            $launchDeg < 8.0 => 'ground_ball',
            $launchDeg < 25.0 => 'line_drive',
            $launchDeg < 45.0 => 'fly_ball',
            default => 'pop_fly',
        };
    }

    private function requireNumeric(array $input, string $key): float
    {
        if (!isset($input[$key]) || !is_numeric($input[$key])) {
            throw new \InvalidArgumentException("CageDistanceService::estimate() requires numeric '{$key}'.");
        }

        return (float) $input[$key];
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * Deterministic, self-contained xorshift32 PRNG — does not touch PHP's
     * global mt_rand()/rand() state, so Monte Carlo sampling here has no side
     * effects on other code's randomness. Same seed always produces the same
     * sample sequence (required for reproducible audits/tests).
     */
    private function uniform(int &$state, float $min, float $max): float
    {
        if ($max <= $min) {
            return $min;
        }

        $state ^= ($state << 13) & 0xFFFFFFFF;
        $state ^= ($state >> 17);
        $state ^= ($state << 5) & 0xFFFFFFFF;
        $state &= 0xFFFFFFFF;

        $fraction = $state / 0xFFFFFFFF;

        return $min + $fraction * ($max - $min);
    }

    /** @return array<string,mixed> */
    private function buildInputsUsed(
        float $ev,
        float $laCenter,
        float $saCenter,
        float $laMin,
        float $laMax,
        float $saMin,
        float $saMax,
        float $contactHeightFt,
        float $spinRpm,
        string $spinSource,
        string $ballProfile,
        string $mode,
        float $tempF,
        float $humidityPct,
        float $elevationFt,
        float $windMph,
        float $windDirDeg,
        float $airDensity,
    ): array {
        return [
            'exit_velocity_mph' => $ev,
            'launch_angle_deg' => $laCenter,
            'spray_angle_deg' => $saCenter,
            'launch_angle_range_deg' => [$laMin, $laMax],
            'spray_angle_range_deg' => [$saMin, $saMax],
            'contact_height_ft' => $contactHeightFt,
            'spin_rpm' => $spinRpm,
            'spin_source' => $spinSource,
            'ball_profile' => $ballProfile,
            'mode' => $mode,
            'temperature_f' => $tempF,
            'humidity_percent' => $humidityPct,
            'elevation_ft' => $elevationFt,
            'wind_speed_mph' => $windMph,
            'wind_direction_deg' => $windDirDeg,
            'air_density_kg_m3' => round($airDensity, 4),
        ];
    }
}
