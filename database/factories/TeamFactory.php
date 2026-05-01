<?php

declare(strict_types=1);

namespace Database\Factories;

use Faker\Provider\en_US\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => fake()->word.' '.fake()->city,
            'logo' => fake()->imageUrl,
            'state' => Address::state(),
            'zip' => Address::postcode(),
        ];
    }
}
