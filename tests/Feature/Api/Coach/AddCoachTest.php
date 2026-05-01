<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddCoachTest extends TestCase
{
    public function test_add_coach_ok(): void
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
        $response = $this->json('POST', 'api/coach/add/coaches', $data);
        $response->assertOk();
        Event::assertDispatched(UserCreated::class);
    }

    public function test_add_exist_coach_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        Profile::factory()->create([
            'user_id' => $coach->id,
        ]);

        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $data = [
            'phone' => $coach->phone,
            'team' => $team_coach->id,
            'name' => [
                'first' => $coach->profile->first_name,
                'last' => $coach->profile->last_name,
            ],
        ];
        Event::fake([UserChanged::class]);
        $response = $this->json('POST', 'api/coach/add/coaches', $data);
        $response->assertOk();
        Event::assertDispatched(UserChanged::class);
    }

    public function test_add_exist_coach_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/add/coaches', []);
        $response->assertUnprocessable();
    }

    public function test_add_exist_coach_fail(): void
    {
        $this->mock(AddUserRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/add/coaches', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_add_exist_coach_unauthorized(): void
    {
        $response = $this->json('POST', 'api/coach/add/coaches', []);
        $response->assertUnauthorized();
    }

    public function test_add_exist_coach_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $response = $this->json('POST', 'api/coach/add/coaches', []);
        $response->assertForbidden();
    }
}
