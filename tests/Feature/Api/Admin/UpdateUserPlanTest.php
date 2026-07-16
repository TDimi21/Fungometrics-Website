<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Concerns\UserTypes;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UpdateUserPlanTest extends TestCase
{
    public function test_subscription_admin_can_update_user_plan(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'email' => 'admin@fungometrics.com']);
        $target = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'free']);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('PATCH', "api/admin/users/{$target->id}/plan", [
            'subscription_plan' => 'player_pro',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'subscription_plan' => 'player_pro']);
    }

    public function test_ordinary_coach_cannot_update_user_plan(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $target = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'free']);
        Sanctum::actingAs($coach, ['coach']);

        $this->patchJson("api/admin/users/{$target->id}/plan", ['subscription_plan' => 'player_pro'])
            ->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'subscription_plan' => 'free']);
    }

    public function test_player_cannot_update_user_plan(): void
    {
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $target = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'free']);

        Sanctum::actingAs($player, ['player']);

        $response = $this->json('PATCH', "api/admin/users/{$target->id}/plan", [
            'subscription_plan' => 'player_pro',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'subscription_plan' => 'free']);
    }

    public function test_unauthenticated_cannot_update_user_plan(): void
    {
        $target = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'free']);

        $response = $this->json('PATCH', "api/admin/users/{$target->id}/plan", [
            'subscription_plan' => 'player_pro',
        ]);

        $response->assertUnauthorized();
    }

    public function test_invalid_plan_is_rejected(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'email' => 'admin@fungometrics.com']);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('PATCH', "api/admin/users/{$coach->id}/plan", [
            'subscription_plan' => 'hacker_plan',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_dual_write_rolls_back_when_plan_audience_is_invalid(): void
    {
        $admin = User::factory()->create(['type' => UserTypes::COACH->value, 'email' => 'admin@fungometrics.com']);
        $target = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'free']);
        Sanctum::actingAs($admin, ['coach']);

        $this->patchJson("api/admin/users/{$target->id}/plan", ['subscription_plan' => 'player_pro'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'subscription_plan' => 'free']);
        $this->assertDatabaseMissing('subscriptions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('subscription_audits', ['target_user_id' => $target->id]);
    }
}
