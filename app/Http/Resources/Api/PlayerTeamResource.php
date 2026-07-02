<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class PlayerTeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        //todo poner el valor del peso
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'name' => [
                'first' => $this->profile->first_name,
                'last' => $this->profile->last_name,
                'full' => sprintf('%s %s', $this->profile->first_name, $this->profile->last_name),
            ],
            'avatar' => $this->profile?->picture ?? config('services.images.logo'),
            'body' => [
                'ft' => $this->player?->height_in_ft,
                'inch' => $this->player?->height_in_inch,
                'weight' => $this->fitness?->body_weight ?? $this->player?->weight,
                'full_height' => $this->player?->height_in_ft.".".$this->player?->height_in_inch,
            ],
            'fitness' => [
                'date' => $this->fitness?->fitness_date,
                'body_weight' => $this->fitness?->body_weight,
                'bench_press' => $this->fitness?->bench_press,
                'front_squat' => $this->fitness?->front_squat,
                'back_squat' => $this->fitness?->back_squat,
                'power_clean' => $this->fitness?->power_clean,
                'dead_lift' => $this->fitness?->dead_lift,
                'yd_40_dash' => $this->fitness?->yd_40_dash,
                'yd_60_dash' => $this->fitness?->yd_60_dash,
            ],
            'born' => [
                'date' => $this->player?->born_date,
                'age' => Carbon::parse($this->player?->born_date)->age,
            ],
            'grad_year' => $this->player?->grad_year,
            'graduation_year' => $this->player?->grad_year,
            'shirt_number' => $this->player?->number_in_shirt,
            'throw_side' => $this->player?->throw_side,
            'hit_side' => $this->player?->hit_side,
            'positions' => $this->positions,
            'claimed' => (bool) ($this->pivot?->actual ?? true),
        ];
    }
}
