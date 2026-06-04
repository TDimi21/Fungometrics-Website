<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Player;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FitnessRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id'=>['required'],
            'fitness_date'=>['nullable','date'],
            'bench_press'=>['nullable','integer'],
            'front_squat'=>['nullable','integer'],
            'back_squat'=>['nullable','integer'],
            'power_clean'=>['nullable','integer'],
            'hand_strength'=>['nullable','numeric','min:0'],
            'dead_lift'=>['nullable','integer'],
            'pull_ups'=>['nullable','integer','min:0'],
            'push_ups'=>['nullable','integer','min:0'],
            'vertical_jump'=>['nullable','numeric','min:0'],
            'broad_jump'=>['nullable','numeric','min:0'],
            'med_ball_rotational_throw'=>['nullable','numeric','min:0'],
            'sprint_10yd'=>['nullable','numeric','min:0'],
            'exit_velo'=>['nullable','numeric','min:0'],
            'bat_speed'=>['nullable','numeric','min:0'],
            'throwing_velo'=>['nullable','numeric','min:0'],
            'pitch_velo'=>['nullable','numeric','min:0'],
            'yd_40_dash'=>['nullable','numeric'],
            'yd_60_dash'=>['nullable','numeric'],
            'body_weight'=>['nullable','numeric'],
            'sleep_hours'=>['nullable','numeric','min:0','max:24'],
            'sleep_quality_1_to_5'=>['nullable','integer','min:1','max:5'],
            'recovery_score'=>['nullable','integer','min:0','max:100'],
            'mobility_score'=>['nullable','integer','min:0','max:100'],
            'strength_score'=>['nullable','integer','min:0','max:100'],
        ];
    }

     public function failedValidation(Validator $validator): void
     {
         throw new HttpResponseException(response()->json([
             'code' => '001V',
             'message' => 'validations errors',
             'status' => false,
             'data' => ['errors'=>$validator->errors()],
         ], Response::HTTP_UNPROCESSABLE_ENTITY));
     }
}
