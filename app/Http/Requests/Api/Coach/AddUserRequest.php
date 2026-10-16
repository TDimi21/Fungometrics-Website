<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Coach;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class AddUserRequest extends FormRequest
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
            'phone' => $this->is('api/coach/add/players') ? ['nullable', 'string', 'max:32'] : ['required'],
            'email' => ['nullable', 'email', 'max:191'],
            'team' => ['required'],
            'name.first' => ['required'],
            'name.last' => ['required'],
            'send_invitation' => ['sometimes', 'boolean'],
            'player.born_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'player.position' => ['nullable', 'string', 'in:P,C,1B,2B,3B,SS,LF,CF,RF,DH,OF,IF'],
            'player.grad_year' => ['nullable', 'integer', 'min:1900', 'max:2200'],
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
