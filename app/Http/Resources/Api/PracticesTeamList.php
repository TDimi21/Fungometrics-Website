<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Http\Resources\LineUpResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PracticesTeamList extends JsonResource
{
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "is_completed" => $this->is_completed,
            "start" => $this->start,
            "note" => $this->note,
            "type" => $this->type,
            "mode" => $this->modes,
            "team" => $this->team,
            "lineup" => LineUpResource::collection($this->lineup),

        ];
    }
}
