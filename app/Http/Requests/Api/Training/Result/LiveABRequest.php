<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class LiveABRequest extends FormRequest
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
            'play_result' => ['nullable', 'string', 'max:50'],
            'outs_recorded' => ['nullable', 'integer', 'between:0,3'],
            'runs_scored' => ['nullable', 'integer', 'between:0,4'],
            'rbi' => ['nullable', 'integer', 'between:0,4'],
            'is_safe' => ['nullable', 'boolean'],
            'sac_fly' => ['nullable', 'boolean'],
            'sac_bunt' => ['nullable', 'boolean'],
            'runners_before' => ['nullable', 'array', 'size:3'],
            'runners_after' => ['nullable', 'array', 'size:3'],
            'practice_id' => ['required', 'string'],
            'turn.turn' => ['required', 'integer'],
            'turn.pitches' => ['required', 'integer'],
            'turn.strike' => ['nullable', 'integer'],
            'turn.ball' => ['nullable', 'integer'],
            'turn.is_over' => ['nullable', 'boolean'],
            'bases' => ['nullable', 'integer'],
            'pitch_location' => ['nullable', 'string'],
            'pitch_mark' => ['nullable', 'integer'],
            'type_of_hit' => ['nullable', 'string'],
            'is_contact' => ['nullable', 'boolean'],
            'zone' => ['nullable', 'string'],
            'batting.team_id' => ['required', 'string'],
            'batting.batter_id' => ['required', 'string'],
            'batting.quality_of_contact' => ['nullable', 'string'],
            'batting.field_mark' => ['nullable', 'integer'],
            'batting.field_direction' => ['nullable', 'string'],
            'batting.velocity' => ['nullable', 'integer'],
            'pitching.team_id' => ['required', 'string'],
            'pitching.pitcher_id' => ['required', 'string'],
            'pitching.miles_per_hour' => ['nullable', 'integer'],
            'pitching.type_throw' => ['nullable', 'string'],

        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['code' => '001V', 'message' => 'validations errors', 'status' => false, 'data' => ['errors' => $validator->errors()]], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
