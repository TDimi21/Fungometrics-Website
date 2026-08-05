<?php

declare(strict_types=1);

namespace App\Http\Resources\DataHub;

use Illuminate\Http\Resources\Json\JsonResource;

final class RapsodoPitchingSessionReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
