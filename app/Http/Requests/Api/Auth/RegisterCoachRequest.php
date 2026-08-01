<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Models\Concerns\UserTypes;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RegisterCoachRequest extends FormRequest
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
        $optionalRules = [
            'phone' => ['required', 'unique:users'],
            'email' => ['required', 'email', 'unique:users'],
            'logo' => ['required','file'],
            'team' => ['required'],
        ];

        $claimedUser = $this->attributes->get('account_claim')?->user;
        $existingUser = $this->route('user') ?? $claimedUser;
        if($existingUser) {
            $optionalRules = [
                'phone' => ['required', Rule::unique('users')->ignore($existingUser->id)],
                'email' => ['required', 'email', Rule::unique('users')->ignore($existingUser->id)],
            ];
        }

        return [
            ...$optionalRules,
            'password' => ['required','min:6'],
            'city' => ['required'],
            'state' => ['required'],
            'zip' => ['required'],
            'profile.name.first' => ['required'],
            'profile.name.last' => ['required'],
            'profile.level' => ['required'],
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        $existingAccount = null;
        if ($validator->errors()->has('phone')) {
            $user = User::query()->where('phone', $this->input('phone'))->first();
            if ($user) {
                $claimable = ! filled($user->email) && ! filled($user->password);
                $existingAccount = [
                    'type' => (string) $user->type,
                    'claimable' => $claimable,
                    'next_action' => UserTypes::COACH->value === (string) $user->type && $claimable
                        ? 'claim_coach_invitation'
                        : 'login_or_recover',
                ];
            }
        }

        throw new HttpResponseException(response()->json([
            'code' => '001V',
            'message' => 'validations errors',
            'status' => false,
            'data' => [
                'errors' => $validator->errors(),
                'existing_account' => $existingAccount,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
