<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ScriptedBpSwingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'web_result' => ['sometimes', 'array:pitch_mark,field_mark,pitch_location,zone,field_direction'],
            'web_result.pitch_mark' => ['nullable', 'integer', 'between:1,3600'],
            'web_result.field_mark' => ['nullable', 'integer', 'between:1,6400'],
            'web_result.pitch_location' => ['nullable', 'string', 'max:30'],
            'web_result.zone' => ['nullable', 'string', 'max:30'],
            'web_result.field_direction' => ['nullable', 'string', 'max:30'],
            'practice_id'       => ['required', 'string'],
            'batter_id'         => ['required', 'string'],
            'round_type'        => ['required', 'string'],
            'round_swing_index' => ['required', 'integer', 'min:1'],
            'contact_type'      => ['required', 'string', 'in:Barrel,Hard,Average,Weak,Miss'],
            'trajectory'        => ['nullable', 'string', 'in:LineDrive,FlyBall,GroundBall,PopUp,Foul'],
            'direction'         => ['nullable', 'string', 'in:Pull,Middle,Oppo'],
            'exit_velocity'     => ['nullable', 'integer', 'min:0', 'max:130'],
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
