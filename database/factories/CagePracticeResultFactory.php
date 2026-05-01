<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\SidesPitchPosition;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CagePracticeResult>
 */
class CagePracticeResultFactory extends Factory
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
            'launch_angle'=>fake()->numberBetween(6, 45),
            'launch_angle_velocity'=>fake()->numberBetween(10, 130),
            'spray_angle'=>fake()->boolean ? ''.fake()->numberBetween(0, 90) : '-'.fake()->numberBetween(1, 90),
            'distance_travel'=>fake()->numberBetween(10, 300),
            'ground_ball'=>fake()->boolean(),
            'cage_mark'=>fake()->numberBetween(0, 90),
            'cage_position'=>Arr::random(SidesPitchPosition::cases()),
            'created_at' => Carbon::now()
        ];
    }
}
