<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LineUpResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'=>$this->id,
            'is_pitching'=>$this->is_pitching,
            'is_batting'=>$this->is_batting,
            'sort'=>$this->sort,
            'player'=>new LineUpPlayerResource($this->user->profile),
        ];
    }
}
