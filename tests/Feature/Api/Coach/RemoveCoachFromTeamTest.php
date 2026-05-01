<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemoveCoachFromTeamTest extends TestCase
{
    public function test_remove_coach_from_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $coach = User::factory()->create(['type'=>UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main'=>false
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.$teamCoach->id);
        $response->assertOk();

    }

    public function test_remove_coach_from_team_fail(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $coach = User::factory()->create(['type'=>UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main'=>false
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.fake()->uuid);
        $response->assertServerError();

    }

    public function test_remove_coach_from_team_unauthorized(): void
    {
        $coach = User::factory()->create(['type'=>UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main'=>false
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.fake()->uuid);
        $response->assertUnauthorized();
    }

    public function test_remove_coach_from_team_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $coach = User::factory()->create(['type'=>UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main'=>false
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.fake()->uuid);
        $response->assertForbidden();

    }
}
