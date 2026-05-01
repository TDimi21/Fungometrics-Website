<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class BullpenResultRequest extends FormRequest
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
            'practice_id' => ['required', 'string'],
            'team_id' => ['nullable', 'string'],
            'pitcher_id' => ['nullable', 'string'],
            'pitch_side' => ['nullable', 'required'],
            'pitch_mark' => ['nullable', 'integer'],
            'is_strike' => ['nullable', 'boolean'],
            'miles_per_hour' => ['nullable', 'integer'],
            'type_throw' => ['required', 'string'],
            'trajectory' => ['nullable', 'string'],
            'is_in_match' => ['nullable', 'boolean'],
            'zone'=>['string'],
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => '001V',
            'message' => 'validations errors',
            'status' => false,
            'data' => ['errors' => $validator->errors()],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
