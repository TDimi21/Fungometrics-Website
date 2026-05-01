<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\PitchThrowTypes;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Arr;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BullpenPracticeResult>
 */
class BullpenPracticeResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $isStrike = fake()->boolean;

        return [
            'practice_id'=>Practice::factory()->create()->id,
            'team_id'=>Team::factory()->create()->id,
            'pitcher_id'=>User::factory()->create()->id,
            'pitch_side'=>Arr::random(SidesPitchPosition::cases()),
            'pitch_mark'=>$this->faker->numberBetween(1, 3200),
            'is_strike'=>$isStrike,
            'miles_per_hour'=>($this->faker->numberBetween(1, 13))*10,
            'type_throw'=>Arr::random(PitchThrowTypes::cases()),
            'trajectory'=>$isStrike ? BattingTrajectory::SWING_MISS : $this->faker->randomElement([
                BattingTrajectory::LINE_DRIVE,
                BattingTrajectory::FLY_BALL,
                BattingTrajectory::POP_FLY,
                BattingTrajectory::GROUND_BALL
            ]),
            'is_in_match'=>$this->faker->boolean,
            'sort'=>$this->faker->numberBetween(1, 10),
            'zone'=>$this->faker->randomElement(['B','S']),
            'created_at' => Carbon::now()
        ];
    }
}
