<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\TypeSMS;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SmsLog>
 */
class SmsLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id'=>fake()->uuid,
            'practice_id'=>fake()->uuid,
            'type'=>fake()->randomElement([TypeSMS::CREATED->value,TypeSMS::TRAINING->value]),
            'phone'=>fake()->phoneNumber,
            'message'=>fake()->words(12, true),
            'response'=>fake()->paragraph(1),
            'status'=>fake()->boolean,
        ];
    }
}
