<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\Login;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class PlayerResource extends JsonResource
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
            'id' => $this['player']['id'],
            'email' => $this['player']['email'],
            'phone' => $this['player']['phone'],
            'type'=>$this['player']['type'],
            'avatar' => $this['profile']['picture'],
            'name' => [
                'full' => sprintf('%s %s', $this['profile']['first_name'], $this['profile']['last_name']),
                'first' => $this['profile']['first_name'],
                'last' => $this['profile']['last_name'],
            ],
            'ft'=>$this['other']['height_in_ft']??0,
            'inch'=>$this['other']['height_in_inch']??0,
            'hit_side'=>$this['other']['hit_side']??"",
            'throw_side'=>$this['other']['throw_side']??"",
            'shirt_number'=>$this['other']['number_in_shirt']??"",
            'born' => [
                'date' => $this['other']['born_date'] ?? "",
                'age' => isset($this['other']['born_date']) ? Carbon::parse($this['other']['born_date'])->age : "",
            ],
            'city' => $this['profile']['city'],
            'state' => $this['profile']['state'],
            'zip' => $this['profile']['zip'],
            'level' => $this['profile']['level'],
            'token' => $this['token'],
            'fitness' => $this['fitness'],
            'positions' => $this['positions'],

        ];
    }
}
