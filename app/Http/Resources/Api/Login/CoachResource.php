<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Login;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CoachResource extends JsonResource
{
    /**
     * @param $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this['user']['id'],
            'email' => $this['user']['email'],
            'phone' => $this['user']['phone'],
            'type'=>$this['user']['type'],
            'name' => [
                'full' => sprintf('%s %s', $this['profile']['first_name'], $this['profile']['last_name']),
                'first' => $this['profile']['first_name'],
                'last' => $this['profile']['last_name'],
            ],
            'city' => $this['profile']['city'],
            'state' => $this['profile']['state'],
            'zip' => $this['profile']['zip'],
            'level' => $this['profile']['level'],
            'token' => $this['token'],
            'teams' => CoachTeamResource::collection($this['teams']),
            'players' => $this['players']
        ];
    }
}
