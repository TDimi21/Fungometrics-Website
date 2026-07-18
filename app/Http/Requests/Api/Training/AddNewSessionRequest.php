<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class AddNewSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'team' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(PracticeTypes::class)],
            'modes' => ['nullable', Rule::enum(PracticeModes::class)],
            'scripted' => ['nullable', 'boolean'],
            'note' => ['required'],
            'players' => ['required', 'array'],
            'cage' => ['nullable', 'array']
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['code' => '001V', 'message' => 'validations errors', 'status' => false, 'data' => ['errors' => $validator->errors()]], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
