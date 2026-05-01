<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CoachTeam>
 */
class CoachTeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'coach_id' => User::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'is_main' => fake()->boolean,
        ];
    }
}
