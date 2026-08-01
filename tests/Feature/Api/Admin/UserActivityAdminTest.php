<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Practice;
use App\Models\Profile;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserActivityAdminTest extends TestCase
{
    public function test_activity_requires_subscription_admin_access(): void
    {
        $this->getJson('/api/admin/activity')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create([
            'type' => 'coach',
            'email' => 'ordinary@example.com',
        ]), ['coach']);

        $this->getJson('/api/admin/activity')->assertForbidden();
    }

    public function test_activity_returns_real_logins_and_session_counts_for_the_selected_window(): void
    {
        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
        ]);
        Sanctum::actingAs($admin, ['coach']);

        $coach = User::factory()->create(['type' => 'coach', 'last_login_at' => now()->subHours(2)]);
        $player = User::factory()->create(['type' => 'player', 'last_login_at' => now()->subHours(5)]);
        $olderPlayer = User::factory()->create(['type' => 'player', 'last_login_at' => now()->subDays(3)]);
        Profile::factory()->create(['user_id' => $coach->id, 'first_name' => 'Casey', 'last_name' => 'Coach']);
        Profile::factory()->create(['user_id' => $player->id, 'first_name' => 'Parker', 'last_name' => 'Player']);
        Profile::factory()->create(['user_id' => $olderPlayer->id]);

        Practice::factory()->count(2)->create([
            'user_id' => $coach->id,
            'created_at' => now()->subHours(3),
        ]);
        Practice::factory()->create([
            'user_id' => $player->id,
            'created_at' => now()->subHours(4),
        ]);
        Practice::factory()->create([
            'user_id' => $olderPlayer->id,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/admin/activity?range=day&role=all');

        $response->assertOk()
            ->assertJsonPath('data.range', 'day')
            ->assertJsonPath('data.summary.active_users', 2)
            ->assertJsonPath('data.summary.coach_logins', 1)
            ->assertJsonPath('data.summary.player_logins', 1)
            ->assertJsonPath('data.summary.sessions_recorded', 3)
            ->assertJsonPath('data.users.total', 2)
            ->assertJsonFragment([
                'name' => 'Casey Coach',
                'role' => 'coach',
                'sessions_recorded' => 2,
            ])
            ->assertJsonFragment([
                'name' => 'Parker Player',
                'role' => 'player',
                'sessions_recorded' => 1,
            ]);

        $this->getJson('/api/admin/activity?range=week&role=player')
            ->assertOk()
            ->assertJsonPath('data.summary.active_users', 2)
            ->assertJsonPath('data.summary.sessions_recorded', 2)
            ->assertJsonPath('data.users.total', 2);
    }

    public function test_activity_rejects_unknown_ranges_and_roles(): void
    {
        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
        ]);
        Sanctum::actingAs($admin, ['coach']);

        $this->getJson('/api/admin/activity?range=year&role=owner')->assertUnprocessable();
    }
}
