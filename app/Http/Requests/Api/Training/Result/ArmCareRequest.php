<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ArmCareRequest extends FormRequest
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
            'user_id' => ['required', 'string'],
            'team_id' => ['nullable', 'string'],
            'routine_key' => ['required', 'string'],
            'routine_label' => ['nullable', 'string'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'grade' => ['nullable', 'string'],
            'assigned' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'integer', 'min:0'],
            'completed_total' => ['nullable', 'integer', 'min:0'],
            'skipped' => ['nullable', 'integer', 'min:0'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'breakdown' => ['nullable', 'array'],
            'client_id' => ['nullable', 'string'],
            'performed_at' => ['nullable', 'date'],
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
