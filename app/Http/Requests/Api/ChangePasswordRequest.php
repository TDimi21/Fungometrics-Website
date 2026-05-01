<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordRequest extends FormRequest
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
            'old'=>['required','string'],
            'password'=>['required','string','confirmed'],
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
