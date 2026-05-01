<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\SidesPLayer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'height_in_ft' => fake()->randomDigit(),
            'height_in_inch' => fake()->randomDigit(),
            'hit_side' => Arr::random(SidesPLayer::cases()),
            'throw_side' => Arr::random(SidesPLayer::cases()),
            'number_in_shirt' => fake()->randomDigit(),
            'born_date' => fake()->date(),
        ];
    }
}
