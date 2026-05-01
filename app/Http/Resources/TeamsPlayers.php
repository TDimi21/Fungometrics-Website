<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class TeamsPlayers extends JsonResource
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
            'id'=>$this->id,
            'id_team'=>$this->team->id,
            'name'=>$this->team->name,
            'num_players'=>count($this->team->team_players),
            'players'=>Player::collection($this->team->team_players)
        ];
    }
}
