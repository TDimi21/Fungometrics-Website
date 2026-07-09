<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Coach;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class GetPlayerDevelopmentDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'team' => $this->route('team') ? (string) $this->route('team') : null,
            'player' => (string) $this->route('player'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team' => ['nullable', 'string', 'exists:teams,id'],
            'player' => ['required', 'string', 'exists:users,id'],
            'days' => ['nullable', 'integer', 'min:30', 'max:365'],
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
