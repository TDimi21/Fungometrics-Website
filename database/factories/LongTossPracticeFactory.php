<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LongTossPractice>
 */
class LongTossPracticeFactory extends Factory
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
            'user_id'=>User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'set'=>fake()->randomDigitNotZero(),
            'sort'=>fake()->randomDigitNotZero(),
            'hop'=>fake()->randomElement([1,2,3]),
            'distance'=>fake()->numberBetween(10, 130),
            'created_at' => Carbon::now()

        ];
    }
}
