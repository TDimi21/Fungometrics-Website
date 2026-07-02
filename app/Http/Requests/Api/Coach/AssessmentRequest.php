<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Coach;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'          => ['required', 'string'],
            'team_id'          => ['nullable', 'string'],
            'assessed_by'      => ['nullable', 'string'],
            'assessment_date'  => ['nullable', 'date'],
            'grad_year'        => ['nullable', 'integer', 'min:1900', 'max:2200'],
            'type'             => ['nullable', 'in:strength,mobility,full'],
            'notes'            => ['nullable', 'string', 'max:2000'],

            // raw values
            'squat_lbs'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'deadlift_lbs'     => ['nullable', 'integer', 'min:0', 'max:999'],
            'bench_lbs'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'broad_jump_in'    => ['nullable', 'integer', 'min:0', 'max:999'],
            'vertical_jump_in' => ['nullable', 'integer', 'min:0', 'max:99'],
            'sprint_10yd_sec'  => ['nullable', 'numeric', 'min:0', 'max:9.99'],

            // strength percentiles
            'squat_percentile'               => ['nullable', 'integer', 'min:0', 'max:100'],
            'deadlift_percentile'            => ['nullable', 'integer', 'min:0', 'max:100'],
            'lunge_percentile'               => ['nullable', 'integer', 'min:0', 'max:100'],
            'bench_press_percentile'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'pull_up_percentile'             => ['nullable', 'integer', 'min:0', 'max:100'],
            'push_up_percentile'             => ['nullable', 'integer', 'min:0', 'max:100'],
            'broad_jump_percentile'          => ['nullable', 'integer', 'min:0', 'max:100'],
            'vertical_jump_percentile'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'sprint_10yd_percentile'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'med_ball_rotational_percentile' => ['nullable', 'integer', 'min:0', 'max:100'],
            'exit_velocity_percentile'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'bat_speed_percentile'           => ['nullable', 'integer', 'min:0', 'max:100'],

            // mobility (0-10)
            'hip_mobility'        => ['nullable', 'integer', 'min:0', 'max:10'],
            'shoulder_mobility'   => ['nullable', 'integer', 'min:0', 'max:10'],
            'ankle_mobility'      => ['nullable', 'integer', 'min:0', 'max:10'],
            'hip_flexor_mobility' => ['nullable', 'integer', 'min:0', 'max:10'],
            'rotational_mobility' => ['nullable', 'integer', 'min:0', 'max:10'],

            // computed scores (set by API, but accept from client as fallback)
            'strength_lower_body_score'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'strength_upper_body_score'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'strength_explosive_score'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'strength_rotational_score'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'strength_overall_score'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'mobility_overall_score'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'overall_score'                => ['nullable', 'integer', 'min:0', 'max:100'],

            // throwing workload + arm health
            'body_weight_lbs'         => ['nullable', 'numeric', 'min:0', 'max:999'],
            'throwing_workload_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'throwing_workload_level' => ['nullable', 'string', 'max:20'],
            // Accept either an array (new app) or a JSON string (older builds).
            'throwing_workload_data'  => ['nullable'],
            'arm_health_score'        => ['nullable', 'integer', 'min:0', 'max:100'],

            // hitting + pitching assessment
            'hitting_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'hitting_data'  => ['nullable'],
            'pitching_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pitching_data'  => ['nullable'],
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code'    => '001V',
            'message' => 'validation errors',
            'status'  => false,
            'data'    => ['errors' => $validator->errors()],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
