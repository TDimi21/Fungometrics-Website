<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LongTossResults extends JsonResource
{
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "is_completed" => $this->is_completed,
            "team_id" => $this->team_id,
            "started" => $this->started,
            "note" => $this->note,
            "type" => $this->type,
            "modes" => $this->modes,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "lineup" => LineUpResource::collection($this->lineup),
            "results"=>$this->longToss
        ];
    }
}
