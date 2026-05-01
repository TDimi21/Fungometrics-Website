<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Coach;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class EditPlayerRequest extends FormRequest
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
        $id = $this->route('id');
        return [
            'phone' => ['required','unique:users,phone,'.$id],
            'email' => ['required'],
            'profile.name.first' => ['required'],
            'profile.name.last' => ['required'],
            'picture' => ['present'],
            'player.born' => ['required', 'date'],
            'player.ft' => ['required', 'integer'],
            'player.side.pitch' => ['string'],
            'player.side.hit' => ['string'],
            'player.inch' => ['required', 'integer'],
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
