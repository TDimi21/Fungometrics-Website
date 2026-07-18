<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training\Result;

use App\Models\CoachTeam;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScriptedBullpenEntitlementTest extends TestCase
{
    public function test_free_coach_can_create_regular_bullpen_but_not_scripted_bullpen(): void
    {
        [$coach, $team, $pitcher] = $this->coachTeam('free');
        Sanctum::actingAs($coach, ['coach']);

        $regular = $this->postJson('/api/training', $this->payload($team, $pitcher, false));
        $regular->assertCreated();
        $this->assertDatabaseHas('practices', ['team_id' => $team->id, 'is_scripted' => false]);

        $this->postJson('/api/training', $this->payload($team, $pitcher, true))
            ->assertForbidden()
            ->assertJsonPath('required_entitlement', 'scripted_bullpen');

        $this->assertSame(1, Practice::query()->count());
    }

    public function test_coach_pro_can_create_and_use_scripted_bullpen_routes(): void
    {
        [$coach, $team, $pitcher] = $this->coachTeam('coach_pro');
        Sanctum::actingAs($coach, ['coach']);

        $this->postJson('/api/training', $this->payload($team, $pitcher, true))->assertCreated();

        $practiceId = Practice::query()->where('team_id', $team->id)->where('is_scripted', true)->sole()->id;
        $this->getJson("/api/training/{$practiceId}")->assertOk();

        $pitch = $this->postJson('/api/result/bullpen', $this->pitchPayload($practiceId, $team, $pitcher));
        $this->assertNotSame(403, $pitch->status());
    }

    public function test_revocation_blocks_direct_scripted_reads_writes_reports_and_delete(): void
    {
        [$coach, $team, $pitcher] = $this->coachTeam('coach_pro');
        $practice = Practice::factory()->create([
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value,
            'is_scripted' => true,
        ]);
        $coach->update(['subscription_plan' => 'free']);
        Sanctum::actingAs($coach->fresh(), ['coach']);

        $this->getJson("/api/training/{$practice->id}")->assertForbidden();
        $this->postJson('/api/result/bullpen', $this->pitchPayload($practice->id, $team, $pitcher))->assertForbidden();
        $this->getJson("/api/statistics/{$practice->id}/bullpen")->assertForbidden();
        $this->putJson("/api/training/{$practice->id}", ['end_note' => 'done'])->assertForbidden();
        $this->deleteJson("/api/training/{$practice->id}")->assertForbidden();

        $this->assertDatabaseHas('practices', ['id' => $practice->id, 'deleted_at' => null]);
    }

    public function test_entitlement_never_bypasses_team_membership(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        $pitcher = User::factory()->create(['type' => 'player']);
        Sanctum::actingAs($coach, ['coach']);

        $this->postJson('/api/training', $this->payload($team, $pitcher, true))->assertForbidden();
    }

    /** @return array{User, Team, User} */
    private function coachTeam(string $plan): array
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => $plan]);
        $team = Team::factory()->create();
        $pitcher = User::factory()->create(['type' => 'player']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => true]);

        return [$coach, $team, $pitcher];
    }

    /** @return array<string, mixed> */
    private function payload(Team $team, User $pitcher, bool $scripted): array
    {
        return [
            'team' => $team->id,
            'type' => PracticeTypes::BULLPEN->value,
            'note' => $scripted ? 'Scripted bullpen' : 'Regular bullpen',
            'scripted' => $scripted,
            'players' => [['id' => $pitcher->id, 'sort' => 0]],
        ];
    }

    /** @return array<string, mixed> */
    private function pitchPayload(string $practiceId, Team $team, User $pitcher): array
    {
        return [
            'practice_id' => $practiceId,
            'team_id' => $team->id,
            'pitcher_id' => $pitcher->id,
            'pitch_side' => 1,
            'pitch_mark' => 1771,
            'is_strike' => true,
            'miles_per_hour' => 80,
            'type_throw' => 'FB',
            'trajectory' => 'TK',
            'zone' => 'middle',
            'intended_location' => 1771,
        ];
    }
}
