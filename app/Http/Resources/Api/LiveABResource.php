<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveABResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'turn' => [
                'turn' => $this->turn,
                'turn_pitches' => $this->turn_pitches,
                'strike' => $this->turn_strike,
                'ball' => $this->turn_ball,
                'is_over' => $this->turn_is_over,
                'count' => $this->count_b_s,
            ],
        ];
    }
}
