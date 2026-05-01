<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LineUpPlayerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'=>$this->user_id,
            'name' => [
                'full' => sprintf('%s %s', $this->first_name, $this->last_name),
                'first' => $this->first_name,
                'last' => $this->last_name,
            ],
            'picture'=>$this->picture,
            'shirt_number'=>$this->player->number_in_shirt??''
        ];
    }
}
