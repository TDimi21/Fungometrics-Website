<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class WeightBallRequest extends FormRequest
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
            'practice_id' => ['required','string'],
            'user_id' => ['required','string'],
            'team_id' => ['nullable','string'],
            'set' => ['nullable','integer'],
            'weight' =>  ['nullable','integer'],
            'velocity' =>  ['nullable','integer'],
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
