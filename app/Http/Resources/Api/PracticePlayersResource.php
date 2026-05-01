<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\User;
use App\Services\ListServiceData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class PracticePlayersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $data = (new ListServiceData(new User()))->findByUuid($this->user_id);

        return [
            'id' => $data->id,
            'sort' => $this->sort,
            'name' => [
                'first' => $data->profile?->first_name,
                'last' => $data->profile?->last_name,
                'full' => $data->profile?->first_name.' '.$data->profile?->last_name,
            ],
            'picture' => $data->profile?->picture,
            'shirt_number' => $data->player->number_in_shirt ?? '?',
            'body' => [
                'ft' => $data->player?->height_in_ft,
                'inch' => $data->player?->height_in_inch,
                'full_height' => $data->player?->height_in_ft.".".$data->player?->height_in_inch,
            ],
        ];
    }
}
