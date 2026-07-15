<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result;

use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PremiumSessionPlanGatingTest extends TestCase
{
    // ── Long Toss ─────────────────────────────────────────────────────────────

    public function test_free_coach_cannot_save_longtoss_result(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'free']);
        $team  = Team::factory()->create();
        $practice = Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::TRAINING->value]);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', 'api/result/longtoss', [
            'practice_id' => $practice->id,
            'user_id'     => $coach->id,
            'distance'    => 80,
            'set'         => 1,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_coach_pro_can_save_longtoss_result(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team  = Team::factory()->create();
        $practice = Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::TRAINING->value]);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', 'api/result/longtoss', [
            'practice_id' => $practice->id,
            'user_id'     => $coach->id,
            'team_id'     => $team->id,
            'distance'    => 80,
            'set'         => 1,
        ]);

        // 201 Created (or 422 if validation catches missing fields — either way not 403)
        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
    }

    // ── Exit Velocity ─────────────────────────────────────────────────────────

    public function test_free_coach_cannot_save_exitvelocity_result(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'free']);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', 'api/result/exitvelocity', []);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    // ── Weight Ball ───────────────────────────────────────────────────────────

    public function test_free_coach_cannot_save_weightball_result(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'free']);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('POST', 'api/result/weightball', []);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    // ── Performance Overview ──────────────────────────────────────────────────

    public function test_free_coach_cannot_access_performance_overview(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'free']);
        $team  = Team::factory()->create();
        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('GET', "api/coach/performance-overview/{$team->id}");

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_coach_pro_can_access_performance_overview(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team  = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);

        Sanctum::actingAs($coach, ['coach']);

        $response = $this->json('GET', "api/coach/performance-overview/{$team->id}");

        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
    }
}
