<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result;

use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\PracticeModes;
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
    public function test_shared_creation_route_denies_every_premium_session_type_to_free_coaches(): void
    {
        [$coach, $team, $player] = $this->sessionContext('free');
        Sanctum::actingAs($coach, ['coach']);

        foreach ($this->premiumSessionPayloads($team, $player) as [$payload, $required]) {
            $this->postJson('/api/training', $payload)
                ->assertForbidden()
                ->assertJsonPath('required_entitlement', $required);
        }

        $this->assertDatabaseCount('practices', 0);
    }

    public function test_shared_creation_route_allows_every_premium_session_type_for_coach_pro(): void
    {
        [$coach, $team, $player] = $this->sessionContext('coach_pro');
        Sanctum::actingAs($coach, ['coach']);

        foreach ($this->premiumSessionPayloads($team, $player) as [$payload]) {
            $this->postJson('/api/training', $payload)->assertCreated();
        }

        $this->assertDatabaseCount('practices', 6);
    }

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
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
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

    /** @return array{User, Team, User} */
    private function sessionContext(string $plan): array
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => $plan]);
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);

        return [$coach, $team, $player];
    }

    /** @return array<int, array{array<string, mixed>, string}> */
    private function premiumSessionPayloads(Team $team, User $player): array
    {
        $base = [
            'team' => $team->id,
            'note' => 'Plan gate verification',
            'players' => [['id' => $player->id, 'sort' => 0]],
        ];

        return [
            [$base + ['type' => PracticeTypes::LIVE_AB->value], 'liveab_sessions'],
            [$base + ['type' => PracticeTypes::TRAINING->value, 'modes' => PracticeModes::EXIT_VELOCITY->value], 'exit_velocity_sessions'],
            [$base + ['type' => PracticeTypes::TRAINING->value, 'modes' => PracticeModes::LONG_TOSS->value], 'long_toss_sessions'],
            [$base + ['type' => PracticeTypes::TRAINING->value, 'modes' => PracticeModes::WEIGHT_BALL->value], 'weighted_ball_sessions'],
            [$base + ['type' => PracticeTypes::BATTING->value, 'scripted' => true], 'scripted_bp'],
            [$base + ['type' => PracticeTypes::BULLPEN->value, 'scripted' => true], 'scripted_bullpen'],
        ];
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
        $this->grantTeamAccess($coach, $team);
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
