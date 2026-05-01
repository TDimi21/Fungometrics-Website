<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends Factory
 */
class PracticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $practice = Arr::random(PracticeTypes::cases());
        return [
            'id' => fake()->uuid,
            'is_completed' => false,
            'started' => Carbon::now(),
            'finished' => null,
            'note' => fake()->paragraph,
            'end_note' => null,
            'type' => $practice === PracticeTypes::TRAINING->value ? PracticeTypes::TRAINING->value : fake()
                ->randomElement([
                    PracticeTypes::CAGE->value,
                    PracticeTypes::BATTING->value,
                    PracticeTypes::BULLPEN->value,
                    PracticeTypes::LIVE_AB->value,
                ]),
            'modes' => $practice === PracticeTypes::TRAINING->value ? fake()->randomElement([
                PracticeModes::EXIT_VELOCITY->value,
                PracticeModes::WEIGHT_BALL->value,
                PracticeModes::LONG_TOSS->value,
            ])
                : PracticeModes::HIT_OR_PITCH->value,
        ];
    }
}
