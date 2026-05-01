<?php

declare(strict_types=1);

namespace Database\Factories;

use Faker\Provider\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return  [
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'user_id' => fake()->uuid,
            'picture' => fake()->imageUrl,
            'city' => fake()->city,
            'zip' => Address::postcode(),
            'state' => \Faker\Provider\en_US\Address::state(),
        ];
    }
}
