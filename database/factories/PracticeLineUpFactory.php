<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PracticeLineUp>
 */
class PracticeLineUpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bool = fake()->boolean;
        return [
            'id' => fake()->uuid,
            'practice_id' => fake()->uuid,
            'user_id' => fake()->uuid,
            'is_pitching' => $bool,
            'is_batting' => ! $bool,
            'sort' => fake()->randomDigit(),
        ];
    }
}
