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
            'planner_plan_id' => ['nullable', 'string', 'required_with:planner_item_id'],
            'planner_item_id' => ['nullable', 'string', 'required_with:planner_plan_id'],
            'team' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(PracticeTypes::class)],
            'modes' => ['nullable', Rule::enum(PracticeModes::class)],
            'scripted' => ['nullable', 'boolean'],
            'note' => ['required'],
            'players' => ['required', 'array'],
            'cage' => ['nullable', 'array'],
            'script_plan' => ['nullable', 'array', 'min:1', 'max:200'],
            ...$this->scriptRules(),
        ];
    }

    private function scriptRules(): array
    {
        if (!$this->filled('script_plan')) return [];
        $base = ['type' => ['required', 'in:P,B,C']];
        if ($this->input('type') === 'P') return $base + [
            'scripted' => ['required', 'accepted'],
            'script_plan.*' => ['array:pitchType,intendedCoord'],
            'script_plan.*.pitchType' => ['required', 'in:FB,CH,SL,CV,OTHER'],
            'script_plan.*.intendedCoord' => ['required', 'integer', 'between:1,3600'],
        ];
        if ($this->input('type') === 'B') return $base + [
            'scripted' => ['required', 'accepted'],
            'script_plan.*' => ['array:round_type,swing_count'],
            'script_plan.*.round_type' => ['required', 'string', 'max:40', 'distinct', 'in:BARREL,OPPO_GAP,PULL_DAMAGE,TWO_STRIKE,SAC_FLY,HIT_AND_RUN,LINE_DRIVE,HARD_CONTACT,FASTBALL_HUNT,OFFSPEED_ADJ,GAP_TO_GAP,INSIDE_PITCH,OUTSIDE_PITCH,FIRST_PITCH_ATK,BEHIND_COUNT,ADVANTAGE_COUNT,OPPO_POWER,CONTACT,PRESSURE,CHAMPIONSHIP'],
            'script_plan.*.swing_count' => ['required', 'integer', 'between:1,20'],
        ];
        return $base + [
            'script_plan.*' => ['array:drillId,drillLabel,skill,objective,coachingCues,swing_count'],
            'script_plan.*.drillId' => ['required', 'string', 'max:40'],
            'script_plan.*.drillLabel' => ['required', 'string', 'max:150'],
            'script_plan.*.skill' => ['required', 'string', 'max:100'],
            'script_plan.*.objective' => ['required', 'string', 'max:1000'],
            'script_plan.*.coachingCues' => ['required', 'string', 'max:1000'],
            'script_plan.*.swing_count' => ['required', 'integer', 'between:1,50'],
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['code' => '001V', 'message' => 'validations errors', 'status' => false, 'data' => ['errors' => $validator->errors()]], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
