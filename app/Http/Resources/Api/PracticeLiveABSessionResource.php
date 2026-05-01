<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class PracticeLiveABSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this['practice']->id,
            'note' => $this['practice']->note,
            'is_completed' => $this['practice']->is_completed,
            'start' => $this['practice']->started,
            'finish' => $this['practice']->finished,
            'end_note' => $this['practice']->end_note,
            'type' => $this['practice']->type,
            'modes' => $this['practice']->modes,
            'players' => [
                'batters' => PracticePlayersResource::collection($this['players']['a']),
                'pitchers' => PracticePlayersResource::collection($this['players']['b'])
            ],
            'teams' => $this['teams']
        ];
    }
}
