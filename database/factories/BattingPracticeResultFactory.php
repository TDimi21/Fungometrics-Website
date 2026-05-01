<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BattingPracticeResult;
use App\Models\Concerns\BattingTrajectory;
use App\Models\Concerns\ContactQuality;
use App\Models\Concerns\SidesFieldPosition;
use App\Models\Concerns\SidesPitchPosition;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory<BattingPracticeResult>
 */
class BattingPracticeResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $isContact = fake()->boolean;
        return [
            'practice_id' => Practice::factory()->create()->id,
            'team_id' => Team::factory()->create()->id,
            'batter_id' => User::factory()->create()->id,
            'is_contact' => $isContact,
            'pitch_location' => Arr::random(SidesPitchPosition::cases())->value,
            'quality_of_contact' => $isContact ? $this->faker->randomElement([
                ContactQuality::AVERAGE->value,
                ContactQuality::HARD->value,
                ContactQuality::WEAK->value,
            ]) : ContactQuality::NONE->value,
            'type_of_hit' => $isContact ? $this->faker->randomElement([
                BattingTrajectory::GROUND_BALL->value,
                BattingTrajectory::POP_FLY->value,
                BattingTrajectory::FLY_BALL->value,
                BattingTrajectory::LINE_DRIVE->value,

            ]) : BattingTrajectory::SWING_MISS->value,
            'field_mark' => $isContact ? $this->faker->numberBetween(1, 3600) : 0,
            'pitch_mark' => $this->faker->numberBetween(1, 3200),
            'field_direction' => $this->faker->randomElement([
                SidesFieldPosition::LEFT->value,
                SidesFieldPosition::CENTER->value,
                SidesFieldPosition::RIGHT->value,
            ]),
            'velocity' => $this->faker->numberBetween(50, 130),
            'sort' => $this->faker->randomDigit(),
            'zone'=>$this->faker->randomElement(['B','S']),
            'created_at' => Carbon::now()
        ];
    }
}
