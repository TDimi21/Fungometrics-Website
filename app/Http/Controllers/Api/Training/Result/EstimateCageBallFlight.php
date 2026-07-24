<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\BallFlightEstimateRequest;
use App\Services\BallFlight\BallFlightEngine;
use Illuminate\Http\JsonResponse;

final class EstimateCageBallFlight extends Controller
{
    public function __invoke(
        BallFlightEstimateRequest $request,
        BallFlightEngine $engine,
    ): JsonResponse {
        $validated = $request->validated();
        $flight = $engine->analyze([
            'exit_velocity_mph' => (float) $validated['exit_velocity_mph'],
            'launch_angle_deg' => (float) $validated['launch_angle_deg'],
            'spray_angle_deg' => (float) $validated['spray_angle_deg'],
            'ground_ball' => (bool) ($validated['ground_ball'] ?? false),
            'contact_height_ft' => isset($validated['contact_height_ft'])
                ? (float) $validated['contact_height_ft']
                : null,
            'mode' => 'standardized',
        ]);

        $physics = $flight['physics'];
        $distance = $physics['estimated_carry_ft']
            ?? $physics['air_carry_to_first_contact_ft']
            ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'distance_ft' => $distance,
                'estimated_carry_ft' => $physics['estimated_carry_ft'] ?? null,
                'air_carry_to_first_contact_ft' => $physics['air_carry_to_first_contact_ft'] ?? null,
                'carry_low_ft' => $physics['carry_low_ft'] ?? null,
                'carry_high_ft' => $physics['carry_high_ft'] ?? null,
                'hang_time_seconds' => $physics['hang_time_seconds'] ?? null,
                'maximum_height_ft' => $physics['maximum_height_ft'] ?? null,
                'batted_ball_type' => $physics['batted_ball_type'] ?? null,
                'confidence' => $physics['confidence'] ?? null,
                'engine_version' => $flight['engine_version'],
                'model_version' => $physics['model_version'] ?? null,
            ],
        ]);
    }
}
