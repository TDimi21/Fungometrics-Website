<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserLoginHistoryAdminTest extends TestCase
{
    public function test_admin_can_see_only_the_coachs_latest_twenty_logins(): void
    {
        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
        ]);
        $coach = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($admin, ['coach']);

        foreach (range(1, 25) as $hoursAgo) {
            DB::table('user_login_history')->insert([
                'user_id' => $coach->id,
                'logged_in_at' => now()->subHours($hoursAgo),
            ]);
        }

        $response = $this->getJson("/api/admin/users/{$coach->id}/login-history");

        $response->assertOk()->assertJsonCount(20, 'data');
        $this->assertSame(
            now()->subHour()->startOfSecond()->toIso8601String(),
            $response->json('data.0.logged_in_at')
        );
    }

    public function test_login_history_requires_admin_access_and_a_coach_target(): void
    {
        $coach = User::factory()->create(['type' => 'coach']);
        $this->getJson("/api/admin/users/{$coach->id}/login-history")->assertUnauthorized();

        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
        ]);
        Sanctum::actingAs($admin, ['coach']);
        $player = User::factory()->create(['type' => 'player']);

        $this->getJson("/api/admin/users/{$player->id}/login-history")->assertNotFound();
    }
}
