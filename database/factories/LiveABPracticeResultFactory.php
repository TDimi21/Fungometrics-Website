<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory
 */
class LiveABPracticeResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $hit = fake()->boolean;
        return [
            'turn'=>fake()->randomDigitNotZero(),
            'turn_pitches'=>fake()->randomDigit(),
            'turn_is_over'=>fake()->boolean,
            'turn_strike'=>$hit ? fake()->randomElement([1,2,3]) : 0,
            'turn_ball'=>$hit ? fake()->randomElement([1,2,3,4]) : '0',
            'sort'=>fake()->randomDigitNotZero(),
            'bases'=>$hit ? fake()->randomElement(['1','2','3','4']) : '0',
            'practice_id'=>fake()->uuid,
            'is_hit'=>$hit,
            'is_strike'=>fake()->boolean,
            'is_ball'=>fake()->boolean,
            'batting_result_id'=>fake()->uuid,
            'pitching_result_id'=>fake()->uuid,
            'count_b_s'=>fake()->randomElement(['0','1','2']).'-'.fake()->randomElement(['0','1','2','3']),
            'created_at' => Carbon::now()

        ];
    }
}
