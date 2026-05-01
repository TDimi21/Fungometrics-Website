<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerFitness>
 */
class PlayerFitnessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => fake()->uuid,
            'fitness_date' => fake()->date,
            'bench_press'=>fake()->numberBetween('10', 50),
            'front_squat'=>fake()->numberBetween(10, 50),
            'back_squat'=>fake()->numberBetween(10, 50),
            'power_clean'=>fake()->numberBetween(30, 90),
            'dead_lift'=>fake()->numberBetween(50, 100),
            'body_weight'=>fake()->numberBetween(50, 100),
            'yd_40_dash'=>fake()->numerify('##.##'),
            'yd_60_dash'=>fake()->numerify('##.##'),
        ];
    }
}
