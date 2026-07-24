<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

use App\Models\BallFlightPredictionEvaluation;
use App\Models\BallFlightReferenceObservation;

final class BallFlightEvaluationService
{
    public function __construct(private readonly BallFlightEngine $engine)
    {
    }

    public function evaluate(BallFlightReferenceObservation $observation, string $spin, string $mode = 'standardized', bool $force = false, bool $fast = false): BallFlightPredictionEvaluation
    {
        $input = [
            'exit_velocity_mph' => (float) $observation->exit_velocity_mph,
            'launch_angle_deg' => (float) $observation->launch_angle_deg,
            'spray_angle_deg' => (float) ($observation->spray_angle_deg ?? 0),
            'mode' => $mode,
        ];
        if ($spin === 'measured') {
            if ($observation->measured_spin_rpm === null) {
                throw new \InvalidArgumentException('Measured-spin evaluation requires measured spin.');
            }
            $input['measured_spin_rpm'] = (float) $observation->measured_spin_rpm;
        }
        $flight = $this->engine->analyze($input, null, $fast ? 25 : null);
        $distance = $flight['carry_ft'];
        $measuredDistance = $observation->measured_distance_ft;
        $error = $distance !== null && $measuredDistance !== null ? $distance - (float) $measuredDistance : null;
        $hangError = $observation->measured_hang_time_seconds !== null
            ? $flight['hang_time_seconds'] - (float) $observation->measured_hang_time_seconds : null;
        $heightError = $observation->measured_max_height_ft !== null
            ? $flight['maximum_height_ft'] - (float) $observation->measured_max_height_ft : null;
        $identity = [
            'reference_observation_id' => $observation->id,
            'engine_version' => $flight['engine_version'],
            'prediction_mode' => $mode,
            'spin_source' => $spin,
        ];
        if ($force) BallFlightPredictionEvaluation::query()->where($identity)->delete();

        return BallFlightPredictionEvaluation::query()->firstOrCreate($identity, [
            'physics_version' => $flight['physics_model_version'],
            'calibration_version' => null,
            'predicted_distance_ft' => $distance,
            'predicted_low_ft' => $flight['carry_low_ft'],
            'predicted_high_ft' => $flight['carry_high_ft'],
            'predicted_hang_time_seconds' => $flight['hang_time_seconds'],
            'predicted_max_height_ft' => $flight['maximum_height_ft'],
            'distance_error_ft' => $error,
            'absolute_distance_error_ft' => $error === null ? null : abs($error),
            'hang_time_error_seconds' => $hangError,
            'max_height_error_ft' => $heightError,
            'confidence_percent' => $flight['confidence']['percent'],
            'assumptions' => $flight['assumptions'],
            'created_at' => now(),
        ]);
    }
}
