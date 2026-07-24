<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CageDistanceValidationControllerTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'exit_velocity_mph' => 60,
            'launch_angle_deg' => -5,
            'spray_angle_deg' => 0,
            'include_v1' => true,
        ], $overrides);
    }

    // 13. Requires authentication.
    public function test_requires_authentication(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => true]);
        $this->postJson('/api/admin/cage-distance/validate', $this->payload())->assertUnauthorized();
    }

    // 14. Requires admin/developer authorization.
    public function test_requires_admin_authorization(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => true]);
        Sanctum::actingAs(User::factory()->create(['type' => 'coach', 'email' => 'ordinary@example.com']), ['coach']);
        $this->postJson('/api/admin/cage-distance/validate', $this->payload())->assertForbidden();
    }

    // 15. Feature flag disables the endpoint even for an authorized admin.
    public function test_feature_flag_disables_endpoint(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => false]);
        $this->admin();
        $this->postJson('/api/admin/cage-distance/validate', $this->payload())->assertNotFound();
    }

    public function test_admin_can_preview_a_validation_point_with_v1_comparison(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => true]);
        $this->admin();

        $response = $this->postJson('/api/admin/cage-distance/validate', $this->payload())->assertOk();

        $response->assertJsonStructure([
            'inputs', 'v1' => ['distance_ft'], 'v2', 'comparison', 'validation' => ['status', 'flags', 'explanations'], 'model_versions',
        ]);
        $this->assertSame('ground_ball', $response->json('v2.batted_ball_type'));
        $this->assertNotNull($response->json('v1.distance_ft'));
    }

    public function test_preview_without_v1_omits_v1_block(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => true]);
        $this->admin();

        $response = $this->postJson('/api/admin/cage-distance/validate', $this->payload(['include_v1' => false]))->assertOk();
        $this->assertNull($response->json('v1'));
    }

    public function test_rejects_missing_required_inputs(): void
    {
        config(['fmtrx.cage_distance_validation_enabled' => true]);
        $this->admin();

        $this->postJson('/api/admin/cage-distance/validate', ['launch_angle_deg' => 25, 'spray_angle_deg' => 0])
            ->assertUnprocessable();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['type' => 'coach', 'email' => 'admin@fungometrics.com', 'subscription_plan' => 'free']);
        Sanctum::actingAs($admin, ['coach']);

        return $admin;
    }
}
