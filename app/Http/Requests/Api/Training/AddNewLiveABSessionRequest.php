<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class AddNewLiveABSessionRequest extends FormRequest
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
            'liveab_config' => ['nullable', 'array'],
            'liveab_config.mode' => ['required_with:liveab_config', 'in:practice,game'],
            'liveab_config.innings' => ['required_with:liveab_config', 'integer', 'between:1,30'],
            'liveab_config.unlimited' => ['required_with:liveab_config', 'boolean'],
            'liveab_config.continuous' => ['required_with:liveab_config', 'boolean'],
            'liveab_config.date' => ['nullable', 'date_format:Y-m-d'],
            'liveab_config.*.hitPositions' => ['array', 'max:20'],
            'liveab_config.*.pitchPositions' => ['array', 'max:20'],
            'liveab_config.*.hitPositions.*' => ['in:P,C,1B,2B,3B,SS,LF,CF,RF,DH'],
            'liveab_config.*.pitchPositions.*' => ['in:P,C,1B,2B,3B,SS,LF,CF,RF,DH'],
            'liveab_config.name' => ['required_with:liveab_config', 'string', 'max:100'],
            'liveab_config.away.team_id' => ['required_with:liveab_config', 'uuid', 'exists:teams,id'],
            'liveab_config.home.team_id' => ['required_with:liveab_config', 'uuid', 'exists:teams,id'],
            'liveab_config.*.hitters' => ['array', 'max:20'],
            'liveab_config.*.pitchers' => ['array', 'max:20'],
            'liveab_config.*.hitters.*' => ['uuid', 'exists:users,id'],
            'liveab_config.*.pitchers.*' => ['uuid', 'exists:users,id'],
            'teams' => ['required', 'array'],
            'type' => ['required'],
            'note' => ['required'],
            'players' => ['required', 'array'],
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
