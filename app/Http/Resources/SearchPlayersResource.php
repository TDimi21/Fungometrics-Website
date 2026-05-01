<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class SearchPlayersResource extends JsonResource
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
            'id'=>$this['id'],
            'email'=>$this['email'],
            'phone'=>$this['phone'],
            'avatar'=>$this['picture'],
            'name'=>[
                'first'=>$this['first_name'],
                'last'=>$this['last_name'],
                'full'=>"{$this['first_name']} {$this['last_name']}"
            ],
            'born'=>[
                'date'=>$this['born_date'],
                'age'=>Carbon::parse($this['born_date'])->age
            ],
            'actual_team'=>$this['teams']
        ];
    }
}
