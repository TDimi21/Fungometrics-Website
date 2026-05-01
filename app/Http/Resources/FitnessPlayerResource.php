<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class FitnessPlayerResource extends JsonResource
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
            "id"=> $this->id,
            "date"=>Carbon::parse($this->fitness_date)->format('Y-m-d'),
            "bench_press"=> $this->bench_press,
            "front_squat"=> $this->front_squat,
            "back_squat"=> $this->back_squat,
            "power_clean"=> $this->power_clean,
            "dead_lift"=>$this->dead_lift,
            "yd_40_dash"=> $this->yd_40_dash,
            "yd_60_dash"=> $this->yd_60_dash,
            "weight"=> $this->weight,
        ];
    }
}
