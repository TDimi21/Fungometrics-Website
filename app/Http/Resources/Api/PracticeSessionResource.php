<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class PracticeSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $cage = '';
        if (isset($this['meta'])) {
            $cage = [
                'id' => $this['meta']->id,
                'width' => [
                    'ft' => $this['meta']->width_ft,
                    'inch' => $this['meta']->width_inch,
                    'complete' => $this['meta']->width_ft.".".$this['meta']->width_inch
                ],
                'height' => [
                    'ft' => $this['meta']->height_ft,
                    'inch' => $this['meta']->height_inch,
                    'complete' => $this['meta']->height_ft.".".$this['meta']->height_inch
                ],
                'length' => [
                    'ft' => $this['meta']->length_ft,
                    'inch' => $this['meta']->length_inch,
                    'complete' => $this['meta']->length_ft.".".$this['meta']->length_inch
                ],

            ];
        }

        return [
            'id' => $this['practice']->id,
            'team_id' => $this['practice']->team_id,
            'user_id' => $this['practice']->user_id,
            'is_player_shared' => (bool) $this['practice']->is_player_shared,
            'shared_teams' => $this['practice']->is_player_shared ? $this['practice']->sharedTeams()->get(['teams.id', 'teams.name']) : [],
            'note' => $this['practice']->note,
            'is_completed' => $this['practice']->is_completed,
            'start' => $this['practice']->started,
            'finish' => $this['practice']->finished,
            'end_note' => $this['practice']->end_note,
            'type' => $this['practice']->type,
            'modes' => $this['practice']->modes,
            'script_completed_reps' => $this['practice']->type === 'C' && $this['practice']->script_plan ? $this['practice']->cage()->count() : null,
            'game_field' => $this['practice']->game_field,
            'script_plan' => $this['practice']->script_plan,
            'long_toss_mode' => $this['practice']->modes === 'LT' && \App\Models\LongTossGuide::where('practice_id',$this['practice']->id)->exists() ? 'guided' : 'regular',
            'is_scripted' => (bool) $this['practice']->is_scripted,
            'players' => PracticePlayersResource::collection($this['players']),
            'cage_data' => $cage
        ];
    }
}
