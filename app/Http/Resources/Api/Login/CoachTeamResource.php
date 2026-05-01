<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Login;

use App\Models\PlayerTeam;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CoachTeamResource extends JsonResource
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
            'id' => $this['team']['id'],
            'name' => $this['team']['name'],
            'logo' => $this['team']['logo'],
            'zip' => $this['team']['zip'],
            'state' => $this['team']['state'],
            'num_players'=>PlayerTeam::where(
                ['team_id' => $this['team']['id'],
                    'actual' => true]
            )->count(),
        ];
    }
}
