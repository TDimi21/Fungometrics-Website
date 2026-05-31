<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Http\Resources\LineUpResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PracticesTeamList extends JsonResource
{
    public function toArray($request)
    {
        $totalResults = match ($this->type) {
            'B' => $this->batting()->count(),
            'P' => $this->bullpen()->count(),
            'C' => $this->cage()->count(),
            'T' => match ($this->modes) {
                'EV' => $this->exitVelocity()->count(),
                'WB' => $this->weightBall()->count(),
                default => $this->longToss()->count(),
            },
            default => 0,
        };

        return [
            "id" => $this->id,
            "is_completed" => $this->is_completed,
            "start" => $this->started,
            "created_at" => optional($this->created_at)?->toDateTimeString(),
            "note" => $this->note,
            "type" => $this->type,
            "mode" => $this->modes,
            "team" => $this->team,
            "lineup" => LineUpResource::collection($this->lineup),
            "result_count" => $totalResults,

        ];
    }
}
