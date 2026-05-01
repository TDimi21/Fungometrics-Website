<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTeamTest extends TestCase
{
    public function test_get_team_by_id_ok(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'team_id' => $team->id,
            'coach_id' => $user->id
        ]);
        PlayerTeam::factory()->create([
            'actual'=>true,
            'team_id' => $team->id,
            'user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' =>User::factory()
                ->create()->id])
              ->user_id])
              ->user_id,
        ]);
        PlayerTeam::factory()->create([
            'actual'=>true,
            'team_id' => $team->id,
            'user_id' => Player::factory()->create(['user_id' => Profile::factory()->create(['user_id' =>User::factory()
                ->create()->id])
              ->user_id])
              ->user_id,
        ]);
        $response = $this->json('GET', 'api/coach/teams/'.$team->id);
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_get_team_by_id_not_found(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('GET', 'api/coach/teams/'.$this->faker->uuid);
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_get_team_by_id_unauthorized(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
        $response = $this->json('GET', 'api/coach/teams/'.$this->faker->uuid);
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
    public function test_get_team_by_id_forbidden(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $response = $this->json('GET', 'api/coach/teams/'.$this->faker->uuid);
        $response->assertForbidden()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
}
