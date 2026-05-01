<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Practice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CagePracticeMeta>
 */
class CagePracticeMetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'practice_id'=>Practice::factory()->create()->id,
            'height_ft'=>fake()->randomDigitNotZero(),
            'height_inch'=>fake()->randomDigitNotZero(),
            'width_ft'=>fake()->randomDigitNotZero(),
            'width_inch'=>fake()->randomDigitNotZero(),
            'length_ft'=>fake()->randomDigitNotZero(),
            'length_inch'=>fake()->randomDigitNotZero()
        ];
    }
}
