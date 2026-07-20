<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Concerns\UserTypes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $type = Arr::random(UserTypes::cases());

        return [
            'id' => fake()->uuid,
            'phone' => fake()->phoneNumber,
            'type' => $type,
            'email' => fake()->unique()->safeEmail(),
            'status' => fake()->boolean,
            'password' => fake()->password,
            // Most legacy feature tests exercise application behavior rather
            // than plan denial. Access-control tests explicitly override this.
            // Resolve from the final attributes so an explicit `type` override
            // cannot inherit the randomly generated type's plan.
            'subscription_plan' => static function (array $attributes): string {
                $type = $attributes['type'] instanceof UserTypes
                    ? $attributes['type']->value
                    : (string) $attributes['type'];

                return UserTypes::COACH->value === $type ? 'coach_pro' : 'player_pro';
            },
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }
}
