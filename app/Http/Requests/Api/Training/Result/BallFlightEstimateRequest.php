<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Training\Result;

use Illuminate\Foundation\Http\FormRequest;

final class BallFlightEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return null !== $this->user();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exit_velocity_mph' => ['required', 'numeric', 'gt:0', 'max:130'],
            'launch_angle_deg' => ['required', 'numeric', 'between:-90,90'],
            'spray_angle_deg' => ['required', 'numeric', 'between:-60,60'],
            'ground_ball' => ['sometimes', 'boolean'],
            'contact_height_ft' => ['sometimes', 'numeric', 'between:2.25,4.5'],
        ];
    }
}
