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
     * Previously returned true. Combined with the route only requiring
     * `auth:sanctum` (no ability), that let ANY authenticated user — including a
     * player — edit any other user by id (IDOR). Restrict to the user editing
     * their own profile, or a coach (coaches manage roster players; this matches
     * the existing EditPlayer test expectations).
     *
     * NOTE: this still allows any coach to edit any player. Scoping a coach to
     * only players on their own teams is a tighter follow-up, but it changes
     * currently-tested behavior, so it needs product sign-off before landing.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $authUser = $this->user();
        if (! $authUser) {
            return false;
        }

        // Self-edit is always allowed; otherwise the caller must be a coach.
        return (string) $authUser->id === (string) $this->route('id')
            || $authUser->tokenCan('coach');
    }

    /**
     * Return a consistent JSON 403 instead of the default redirect/exception.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'code' => '033-A',
            'message' => 'You are not authorized to edit this player',
            'status' => false,
            'data' => [],
        ], Response::HTTP_FORBIDDEN));
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
            'picture' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
            'player.born' => ['required', 'date'],
            'player.ft' => ['required', 'integer'],
            'player.sides.pitch' => ['nullable', 'string', 'in:L,R'],
            'player.sides.hit' => ['nullable', 'string', 'in:L,R,S'],
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
