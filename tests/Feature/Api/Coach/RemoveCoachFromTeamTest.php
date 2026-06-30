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
        // Actor must be the head coach of this team to remove coaches.
        CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $user->id,
            'is_main' => true,
        ]);
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main'=>false
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.$teamCoach->id);
        $response->assertOk();

    }

    public function test_remove_coach_forbidden_when_not_head(): void
    {
        // Actor is only an assistant on the team → cannot remove coaches.
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $user->id,
            'is_main' => false,
        ]);
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $coach->id,
            'is_main' => false,
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.$teamCoach->id);
        $response->assertForbidden();
    }

    public function test_cannot_remove_only_head_coach(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $team = Team::factory()->create();
        // The actor is the sole head coach and is the removal target.
        $teamCoach = CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $user->id,
            'is_main' => true,
        ]);
        $response = $this->json('DELETE', 'api/coach/remove/coach/'.$teamCoach->id);
        $response->assertStatus(\Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY);
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
