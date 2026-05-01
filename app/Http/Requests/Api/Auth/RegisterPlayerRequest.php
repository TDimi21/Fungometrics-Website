<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RegisterPlayerRequest extends FormRequest
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
        $optionalRules =[
            'phone' => ['required', 'unique:users'],
            'email' => ['required', 'email', 'unique:users'],
        ];

        if($this->route('user')) {
            $optionalRules = [
                'phone' => ['required', Rule::unique('users')->ignore($this->route('user')->id)],
                'email' => ['required', 'email', Rule::unique('users')->ignore($this->route('user')->id)],
            ];
        }

        return [
            ...$optionalRules,
            'password' => ['required'],
            'profile.name.first' => ['required'],
            'profile.name.last' => ['required'],
            'picture' => ['required', 'file'],
            'player.born' => ['required', 'date'],
            'player.ft' => ['required', 'integer'],
            'player.inch' => ['required', 'integer'],
            'player.shirt' => ['required', 'integer'],
            'positions' => ['required'],
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
