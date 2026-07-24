<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cage\CageDistanceService;
use Illuminate\Console\Command;

class CageDistanceAudit extends Command
{
    protected $signature = 'cage:distance-audit
        {--ev= : Exit velocity (mph), required}
        {--la= : Launch angle (deg), required}
        {--spray=0 : Spray angle (deg)}
        {--la-min= : Launch angle range min (deg), for a cage-cell sweep}
        {--la-max= : Launch angle range max (deg), for a cage-cell sweep}
        {--spray-min= : Spray angle range min (deg)}
        {--spray-max= : Spray angle range max (deg)}
        {--spin= : Measured backspin (rpm); omit to use the estimated spin class}
        {--contact-height= : Contact height (ft), default 3.0}
        {--ball-profile=standardized : standardized|flat_seam_pro|raised_seam|high_school|youth}
        {--mode=standardized : standardized|facility}
        {--elevation= : Facility elevation (ft), facility mode only}
        {--temp= : Facility temperature (F), facility mode only}
        {--humidity= : Facility relative humidity (%), facility mode only}
        {--wind= : Facility wind speed (mph), facility mode only}
        {--wind-dir= : Facility wind direction (deg), facility mode only}
        {--ground-ball : Force ground-ball handling}
        {--json : Output structured JSON instead of the formatted report}';

    protected $description = 'Compare the legacy client-side carry estimate (ballFlight.js vacuum+0.55 model) against CageDistanceService v2 for a given EV/LA/spray input.';

    public function handle(CageDistanceService $service): int
    {
        $ev = $this->option('ev');
        $la = $this->option('la');
        if ($ev === null || $la === null || !is_numeric($ev) || !is_numeric($la)) {
            $this->error('--ev and --la are required numeric options.');

            return self::FAILURE;
        }

        $input = array_filter([
            'exit_velocity_mph' => (float) $ev,
            'launch_angle_deg' => (float) $la,
            'spray_angle_deg' => (float) $this->option('spray'),
            'launch_angle_min_deg' => $this->numericOrNull($this->option('la-min')),
            'launch_angle_max_deg' => $this->numericOrNull($this->option('la-max')),
            'spray_angle_min_deg' => $this->numericOrNull($this->option('spray-min')),
            'spray_angle_max_deg' => $this->numericOrNull($this->option('spray-max')),
            'measured_spin_rpm' => $this->numericOrNull($this->option('spin')),
            'contact_height_ft' => $this->numericOrNull($this->option('contact-height')),
            'ball_profile' => $this->option('ball-profile'),
            'mode' => $this->option('mode'),
            'elevation_ft' => $this->numericOrNull($this->option('elevation')),
            'temperature_f' => $this->numericOrNull($this->option('temp')),
            'humidity_percent' => $this->numericOrNull($this->option('humidity')),
            'wind_speed_mph' => $this->numericOrNull($this->option('wind')),
            'wind_direction_deg' => $this->numericOrNull($this->option('wind-dir')),
            'ground_ball' => $this->option('ground-ball') ? true : null,
        ], static fn ($value) => $value !== null);

        $newResult = $service->estimate($input);
        $oldEstimateFt = $this->legacyBallFlightCarryFt((float) $ev, (float) $la);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'input' => $input,
                'old_estimate_ft' => round($oldEstimateFt, 1),
                'new_result' => $newResult,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX CAGE DISTANCE AUDIT');
        $this->line(sprintf(
            'Input: EV=%.1f mph, LA=%.1f°, Spray=%.1f°',
            (float) $ev,
            (float) $la,
            (float) $this->option('spray'),
        ));
        $this->newLine();

        $this->line('OLD MODEL (ballFlight.js: vacuum range × 0.55 carry factor)');
        $this->line('-------------------------------------------------------------');
        $this->line('Estimate: '.round($oldEstimateFt, 1).' ft');
        $this->newLine();

        $this->line('NEW MODEL (CageDistanceService '.CageDistanceService::MODEL_VERSION.')');
        $this->line('-------------------------------------------------------------');
        if ($newResult['batted_ball_type'] === 'ground_ball') {
            $this->line('Batted-ball type: ground_ball');
            $this->line('Air carry to first contact: '.round($newResult['air_carry_to_first_contact_ft'], 1).' ft');
            $this->line('(No total-distance estimate — roll is not modeled without surface/COR data.)');
        } else {
            $this->line('Median estimate: '.$newResult['estimated_carry_ft'].' ft');
            $this->line('Likely range (5th-95th pct): '.$newResult['carry_low_ft'].' - '.$newResult['carry_high_ft'].' ft');
            $this->line('Confidence: '.$newResult['confidence']);
        }
        $this->line('Hang time: '.$newResult['hang_time_seconds'].' s');
        $this->line('Maximum height: '.$newResult['maximum_height_ft'].' ft');
        $this->line('Landing (x,y): ('.$newResult['landing_x_ft'].', '.$newResult['landing_y_ft'].') ft');

        $this->newLine();
        $this->line('ASSUMPTIONS');
        $this->line('-----------');
        if (empty($newResult['assumptions'])) {
            $this->line('- none (all inputs explicitly provided)');
        } else {
            foreach ($newResult['assumptions'] as $assumption) {
                $this->line('- '.$assumption);
            }
        }

        $this->newLine();
        $delta = ($newResult['estimated_carry_ft'] ?? $newResult['air_carry_to_first_contact_ft']) - $oldEstimateFt;
        $this->line(sprintf('Delta (new - old): %+.1f ft', $delta));

        return self::SUCCESS;
    }

    private function numericOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Ported from MODERN_FungoMetrics's src/utils/ballFlight.js — the current
     * client-side model this audit compares against. Vacuum projectile range
     * scaled by a flat empirical carry factor; no drag/lift physics.
     */
    private function legacyBallFlightCarryFt(float $evMph, float $launchDeg): float
    {
        $mphToFps = 1.46667;
        $g = 32.174;
        $carryFactor = 0.55;

        $v0 = $evMph * $mphToFps;
        $laRad = deg2rad($launchDeg);

        return max(0.0, (($v0 ** 2 * sin(2 * $laRad)) / $g) * $carryFactor);
    }
}
