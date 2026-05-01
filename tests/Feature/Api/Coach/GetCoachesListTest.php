<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetCoachesListTest extends TestCase
{
    public function test_get_all_coaches_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        Profile::factory()->create(['user_id' => $user->id]);
        $teamBase =Team::factory()->create();
        $team2 =Team::factory()->create();
        $team3 =Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $teamBase->id,
            'is_main' => true
        ]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team2->id,
            'is_main' => true
        ]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team3->id,
            'is_main' => true
        ]);
        CoachTeam::factory()->create([
            'coach_id' => Profile::factory()->create(['user_id' => User::factory()->create([
                'type'=>UserTypes::COACH->value
            ])])->user_id,
            'team_id' => $teamBase->id,
            'is_main' => false
        ]);
        CoachTeam::factory()->create([
            'coach_id' => Profile::factory()->create(['user_id' => User::factory()->create([
                'type'=>UserTypes::COACH->value
            ])])->user_id,
            'team_id' => $teamBase->id,
            'is_main' => false
        ]);
        CoachTeam::factory()->create([
            'coach_id' => Profile::factory()->create(['user_id' => User::factory()->create([
                'type'=>UserTypes::COACH->value
            ])])->user_id,
            'team_id' => $team2->id,
            'is_main' => false
        ]);
        CoachTeam::factory()->create([
            'coach_id' => Profile::factory()->create(['user_id' => User::factory()->create([
                'type'=>UserTypes::COACH->value
            ])])->user_id,
            'team_id' => $team2->id,
            'is_main' => false
        ]);
        CoachTeam::factory()->create([
            'coach_id' => Profile::factory()->create(['user_id' => User::factory()->create([
                'type'=>UserTypes::COACH->value
            ])])->user_id,
            'team_id' => $team3->id,
            'is_main' => false
        ]);

        $response = $this->json('GET', 'api/coach/roster/coaches');
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_get_all_coaches_not_found(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => Team::factory()->create(),
            'is_main' => true
        ]);

        $response = $this->json('GET', 'api/coach/roster/coaches');
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_get_all_coaches_not_unauthorized(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);

        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => Team::factory()->create(),
            'is_main' => true
        ]);

        $response = $this->json('GET', 'api/coach/roster/coaches');
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
    public function test_get_all_coaches_not_forbidden(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::PLAYER->value
        ]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => Team::factory()->create(),
            'is_main' => true
        ]);

        $response = $this->json('GET', 'api/coach/roster/coaches');
        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
