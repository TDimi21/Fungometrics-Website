<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CoachesResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'=>$this->id,
            'coach_id'=>$this->profile->user_id,
            'name'=>[
                'first'=>$this->profile->first_name,
                'last'=>$this->profile->last_name,
                'full'=>$this->profile->first_name." ".$this->profile->last_name,
            ],
            'city'=>$this->profile->city,
            'state'=>$this->profile->state,
            'country_code'=>$this->profile->country_code,
            'zip'=>$this->profile->zip,
            'level'=>$this->profile->level,
            'picture'=>$this->profile->picture,
            'team_associate'=>$this->team_id,
            'is_main_coach'=>$this->is_main

        ];
    }
}
