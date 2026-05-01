<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class FilterRequest extends FormRequest
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
            'dates'=>['array','required'],
            'players'=>['array','required'],
            'options'=>['required']
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
