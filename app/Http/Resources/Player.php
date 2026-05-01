<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class Player extends JsonResource
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
            'id' => $this->user->id,
            'name' => [
                'first' => $this->user->profile->first_name,
                'last' => $this->user->profile->last_name,
                'full' => sprintf('%s %s', $this->user->profile->first_name, $this->user->profile->last_name),
            ],
            'avatar'=>$this->user->profile?->picture ?? config('services.images.logo'),
            'body' => [
                'ft' => $this->user->player?->height_in_ft,
                'inch' => $this->user->player?->height_in_inch,
                'weight' => $this->user->player?->weight,
                'full_height' => $this->user->player?->height_in_ft."'".$this->user->player?->height_in_inch."”",
                'weight_data' => $this->user->player?->weight.' lb',
            ],
            'born' => [
                'date' => $this->player?->born_date,
                'age' => Carbon::parse($this->user->player?->born_date)->age,
            ],
            'shirt_number' => $this->user->player?->number_in_shirt,
            'throw_side' => $this->user->player?->throw_side,
            'hit_side' => $this->user->player?->hit_side,
            'positions'=>$this->user->positions
        ];
    }
}
