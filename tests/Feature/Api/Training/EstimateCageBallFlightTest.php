<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EstimateCageBallFlightTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required(): void
    {
        $this->postJson('/api/result/cage/estimate', [
            'exit_velocity_mph' => 95,
            'launch_angle_deg' => 29.5,
            'spray_angle_deg' => 0,
        ])->assertUnauthorized();
    }

    public function test_it_returns_the_backend_ball_flight_estimate(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);

        $this->postJson('/api/result/cage/estimate', [
            'exit_velocity_mph' => 95,
            'launch_angle_deg' => 29.5,
            'spray_angle_deg' => 0,
            'ground_ball' => false,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.distance_ft', 384.7)
            ->assertJsonPath('data.engine_version', 'bfi_v1.0')
            ->assertJsonPath('data.model_version', 'cage_v2.0');
    }

    public function test_input_bounds_are_enforced(): void
    {
        Sanctum::actingAs(User::factory()->create(['type' => 'coach']), ['coach']);

        $this->postJson('/api/result/cage/estimate', [
            'exit_velocity_mph' => 131,
            'launch_angle_deg' => 29.5,
            'spray_angle_deg' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('exit_velocity_mph');
    }
}
