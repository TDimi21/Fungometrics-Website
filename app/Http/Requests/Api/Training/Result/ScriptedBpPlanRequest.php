<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ScriptedBpPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'practice_id'              => ['required', 'string', 'exists:practices,id'],
            'rounds'                   => ['required', 'array', 'min:1'],
            'rounds.*.round_type'      => ['required', 'string'],
            'rounds.*.swing_count'     => ['required', 'integer', 'min:1', 'max:20'],
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
