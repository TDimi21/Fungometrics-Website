<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\PlayerPositions;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerPosition>
 */
class PlayerPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'player_id' => User::factory()->create()->id,
            'position' => Arr::random(PlayerPositions::cases()),
        ];
    }
}
