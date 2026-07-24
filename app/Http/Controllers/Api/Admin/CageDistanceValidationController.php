<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cage\CageDistanceService;
use App\Services\Cage\CageDistanceValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dev/admin-only preview for the Cage Distance Validation Lab. Never writes
 * production cage results, never changes Cage Mode scoring — read-only
 * comparison of the mobile v1 model, CageDistanceService v2, and v2's
 * physical-behavior rules for one ad-hoc EV/LA/spray point.
 */
class CageDistanceValidationController extends Controller
{
    public function check(Request $request, CageDistanceValidationService $validation): JsonResponse
    {
        abort_unless((bool) config('fmtrx.cage_distance_validation_enabled'), 404);

        $data = $request->validate([
            'exit_velocity_mph' => ['required', 'numeric'],
            'launch_angle_deg' => ['required', 'numeric'],
            'spray_angle_deg' => ['required', 'numeric'],
            'mode' => ['nullable', 'string', 'in:standardized,facility'],
            'contact_height_ft' => ['nullable', 'numeric'],
            'ball_profile' => ['nullable', 'string'],
            'measured_spin_rpm' => ['nullable', 'numeric'],
            'include_v1' => ['nullable', 'boolean'],
        ]);

        $ev = (float) $data['exit_velocity_mph'];
        $la = (float) $data['launch_angle_deg'];
        $spray = (float) $data['spray_angle_deg'];

        $overrides = array_filter([
            'mode' => $data['mode'] ?? null,
            'contact_height_ft' => $data['contact_height_ft'] ?? null,
            'ball_profile' => $data['ball_profile'] ?? null,
            'measured_spin_rpm' => $data['measured_spin_rpm'] ?? null,
        ], static fn ($value) => $value !== null);

        ['row' => $row, 'explanations' => $explanations] = $validation->evaluateSinglePointWithFlags($ev, $la, $spray, $overrides);

        $includeV1 = (bool) ($data['include_v1'] ?? false);
        $v1Distance = null;
        if ($includeV1) {
            $v1Distance = $validation->lookupV1($validation->loadV1Reference(), $ev, $la, $spray);
            if ($v1Distance === null) {
                $explanations[] = 'No v1 reference value for this exact EV/LA/spray combination — v1 comparison is only available for the standard validation grid (see CageDistanceValidationService::DEFAULT_*). Run cage:validation-matrix for a grid-aligned comparison.';
            }
        }

        $difference = ($includeV1 && $v1Distance !== null && $row['v2_estimated_carry_ft'] !== null)
            ? $row['v2_estimated_carry_ft'] - $v1Distance
            : null;
        $differencePercent = ($difference !== null && $v1Distance != 0.0) ? round(($difference / $v1Distance) * 100, 1) : null;

        $hardFlags = array_intersect($row['validation_flags'], CageDistanceValidationService::hardFlags());
        $status = !empty($hardFlags) ? 'fail' : (!empty($row['validation_flags']) ? 'warning' : 'pass');

        return response()->json([
            'inputs' => [
                'exit_velocity_mph' => $ev,
                'launch_angle_deg' => $la,
                'spray_angle_deg' => $spray,
                ...$overrides,
            ],
            'v1' => $includeV1 ? ['distance_ft' => $v1Distance] : null,
            'v2' => [
                'estimated_carry_ft' => $row['v2_estimated_carry_ft'],
                'carry_low_ft' => $row['v2_low_ft'],
                'carry_high_ft' => $row['v2_high_ft'],
                'hang_time_seconds' => $row['hang_time_seconds'],
                'maximum_height_ft' => $row['maximum_height_ft'],
                'landing_x_ft' => $row['landing_x_ft'],
                'landing_y_ft' => $row['landing_y_ft'],
                'batted_ball_type' => $row['batted_ball_type'],
                'confidence' => $row['confidence'],
            ],
            'comparison' => [
                'difference_ft' => $difference !== null ? round($difference, 2) : null,
                'difference_percent' => $differencePercent,
            ],
            'validation' => [
                'status' => $status,
                'flags' => $row['validation_flags'],
                'explanations' => $explanations,
            ],
            'model_versions' => [
                'v1' => 'ballFlight.js (mobile, src/utils/ballFlight.js)',
                'v2' => CageDistanceService::MODEL_VERSION,
            ],
        ]);
    }
}
