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
        $type = $this->user()?->type;
        if (($type instanceof \BackedEnum ? $type->value : $type) !== 'player') return true;
        if ($this->input('type') === 'L') return false;
        $players = $this->input('players');
        // Missing/empty lineups cannot create a session; let required validation report them.
        if (!$this->filled('players')) return true;
        if ($this->filled('share_with_team_id') && (!is_string($this->input('share_with_team_id')) || !\App\Models\PlayerTeam::where('user_id', $this->user()->id)->where('team_id', $this->input('share_with_team_id'))->where('actual', true)->exists())) return false;
        if ($this->has('share_with_team_ids')) {
            $ids = $this->input('share_with_team_ids');
            if (!is_array($ids) || collect($ids)->contains(fn ($id) => !is_string($id))) return false;
            if (\App\Models\PlayerTeam::where('user_id', $this->user()->id)->whereIn('team_id', $ids)->where('actual', true)->distinct()->count('team_id') !== count(array_unique($ids))) return false;
        }
        return !$this->filled('team') && !$this->filled('team_id')
            && is_array($players) && count($players) === 1
            && ($players[0]['id'] ?? null) === (string) $this->user()->id;
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
            'share_with_team_id' => ['nullable', 'uuid', 'prohibited_with:share_with_team_ids'],
            'share_with_team_ids' => ['sometimes', 'array', 'max:50'],
            'share_with_team_ids.*' => ['required', 'uuid', 'distinct'],
            'team' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(PracticeTypes::class)],
            'modes' => ['nullable', Rule::enum(PracticeModes::class)],
            'scripted' => ['nullable', 'boolean'],
            'note' => ['required'],
            'players' => ['required', 'array'],
            'cage' => ['nullable', 'array'],
            'game_field' => ['nullable', 'array:levelId,levelLabel,basePathFt,pitchingFt,fences'],
            'game_field.levelId' => ['required_with:game_field', 'string', 'max:30'],
            'game_field.levelLabel' => ['required_with:game_field', 'string', 'max:100'],
            'game_field.basePathFt' => ['required_with:game_field', 'numeric', 'between:30,100'],
            'game_field.pitchingFt' => ['required_with:game_field', 'numeric', 'between:20,70'],
            'game_field.fences' => ['required_with:game_field', 'array:lineL,gapL,center,gapR,lineR', 'size:5'],
            'game_field.fences.*' => ['required', 'numeric', 'between:50,600'],
            'script_plan' => ['nullable', 'array', 'min:1', 'max:200'],
            ...$this->scriptRules(),
            ...\App\Services\LongToss\SessionSetup::rules(),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('long_toss_setup')) {
                $setups=$this->input('long_toss_setup'); $players=$this->input('players');
                if ($this->input('type')!=='T' || $this->input('modes')!=='LT' || !is_array($setups) || !is_array($players)
                    || collect($setups)->pluck('player_id')->sort()->values()->all() !== collect($players)->pluck('id')->sort()->values()->all()) {
                    $validator->errors()->add('long_toss_setup','Guided Long Toss requires one setup for every selected player.');
                }
            }

            if ($this->filled('game_field') && ($this->input('type') !== 'C' || $this->filled('script_plan') || $this->boolean('scripted'))) {
                $validator->errors()->add('game_field', 'Game mode requires a cage session without scripted rounds.');
            }
        });
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
