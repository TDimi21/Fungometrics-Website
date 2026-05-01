<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class BattingPracticeEditRequest extends FormRequest
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
            'is_contact' => ['nullable', 'boolean'],
            'pitch_location' => ['nullable', 'string'],
            'quality_of_contact' => ['nullable', 'string'],
            'type_of_hit' => ['nullable', 'string'],
            'field_mark' => ['nullable', 'integer'],
            'pitch_mark' => ['nullable', 'integer'],
            'field_direction' => ['nullable', 'string'],
            'velocity' => ['nullable', 'integer'],
            'sort' => ['nullable', 'integer'],
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
