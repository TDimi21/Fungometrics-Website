<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddPlayersTest extends TestCase
{
    public function test_add_new_player_to_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'phone' => fake()->phoneNumber,
            'team' => $team_coach->id,
            'name' => [
                'first' => fake()->firstName,
                'last' => fake()->lastName,
            ],
        ];

        Event::fake([UserCreated::class]);

        $response = $this->json('POST', 'api/coach/add/players', $data);
        $response->assertOk();
        Event::assertDispatched(UserCreated::class);
    }

    public function test_add_exist_player_with_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create([
            'user_id' => $player->id,
        ]);
        $team_player = Team::factory()->create();
        PlayerTeam::factory()->create([
            'user_id' => $player->id,
            'team_id' => $team_player->id,
            'actual' => true,
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'phone' => $player->phone,
            'team' => $team_coach->id,
            'name' => [
                'first' => $player->profile->first_name,
                'last' => $player->profile->last_name,
            ],
        ];
        Event::fake(UserChanged::class);
        $response = $this->json('POST', 'api/coach/add/players', $data);
        $response->assertOk();
        Event::assertDispatched(UserChanged::class);
    }

    public function test_add_exist_player_without_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create([
            'user_id' => $player->id,
        ]);

        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'phone' => $player->phone,
            'team' => $team_coach->id,
            'name' => [
                'first' => $player->profile->first_name,
                'last' => $player->profile->last_name,
            ],
        ];
        Event::fake(UserChanged::class);
        $response = $this->json('POST', 'api/coach/add/players', $data);
        $response->assertOk();
        Event::assertDispatched(UserChanged::class);
    }

    public function test_add_exist_player_fail(): void
    {
        $this->mock(AddUserRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });

        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/add/players', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_add_exist_player_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/add/players', []);
        $response->assertUnprocessable();
    }

    public function test_add_exist_player_unauthorized(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create([
            'user_id' => $player->id,
        ]);

        $data = [
            'phone' => $player->phone,
            'name' => [
                'first' => $player->profile->first_name,
                'last' => $player->profile->last_name,
            ],
        ];
        $response = $this->json('POST', 'api/coach/add/players', $data);
        $response->assertUnauthorized();
    }

    public function test_add_exist_player_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Profile::factory()->create([
            'user_id' => $player->id,
        ]);

        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $response = $this->json('POST', 'api/coach/add/players', []);
        $response->assertForbidden();
    }
}
