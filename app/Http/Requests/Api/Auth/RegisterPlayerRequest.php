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
        $optionalRules = [
            'phone' => ['required', 'unique:users'],
            'email' => ['required', 'email', 'unique:users'],
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
            'password' => ['required'],
            'profile.name.first' => ['required'],
            'profile.name.last' => ['required'],
            'picture' => ['required', 'file'],
            'player.born' => ['required', 'date'],
            'player.ft' => ['required', 'integer'],
            'player.inch' => ['required', 'integer'],
            'player.shirt' => ['required', 'integer'],
            'positions' => ['required'],
            'team_code' => ['nullable', 'string', 'size:6'],
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
                    'next_action' => UserTypes::PLAYER->value === (string) $user->type && $claimable
                        ? 'claim_player_profile'
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
