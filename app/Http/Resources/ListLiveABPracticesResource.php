<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class ListLiveABPracticesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'is_completed' => $this->is_completed,
            'start' => $this->started,
            'finish' => $this->finished,
            'end_note' => $this->end_note,
            'type' => $this->type,
            'modes' => $this->modes,
            'players' => [
                'batters' => LineUpResource::collection($this->lineup->where('is_batting', true)),
                'pitchers' =>LineUpResource::collection($this->lineup->where('is_pitching', true)),
            ],
            'teams' => ListLiveABTeamsResource::collection($this->liveABTeams)
        ];
    }
}
