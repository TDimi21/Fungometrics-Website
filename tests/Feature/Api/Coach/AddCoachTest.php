<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Events\UserChanged;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\CoachTeam;
use App\Models\AccountClaim;
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
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'free',
        ]);
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

        $response = $this->json('POST', 'api/coach/add/coaches', $data);
        $response->assertOk()
            ->assertJsonPath('data.next_action', 'claim_coach_invitation')
            ->assertJsonStructure(['data' => ['claim_code', 'claim_url', 'expires_at']]);

        $claimCode = $response->json('data.claim_code');
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{12}$/', $claimCode);
        $this->assertDatabaseHas('account_claims', ['token_hash' => hash('sha256', $claimCode)]);
        $this->assertSame(1, AccountClaim::query()->count());
    }

    public function test_add_exist_coach_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team_coach = Team::factory()->create();
        // Actor must be the head coach of the team to manage coach seats.
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team_coach->id,
            'is_main' => true,
        ]);
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
        $response->assertOk()
            ->assertJsonPath('data.account_state', 'claimed')
            ->assertJsonPath('data.next_action', 'login_or_recover')
            ->assertJsonMissingPath('data.claim_code');
        Event::assertDispatched(UserChanged::class);
    }

    public function test_add_coach_forbidden_when_not_head_coach(): void
    {
        // Actor is an assistant (is_main = false) on the team.
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id,
            'is_main' => false,
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $response = $this->json('POST', 'api/coach/add/coaches', [
            'phone' => fake()->phoneNumber,
            'team' => $team->id,
            'name' => ['first' => fake()->firstName, 'last' => fake()->lastName],
        ]);
        $response->assertForbidden();
    }

    public function test_add_coach_blocked_at_seat_limit(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'free',
        ]);
        $team = Team::factory()->create();
        // Head coach (1 seat) on a non-pro plan.
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id,
            'is_main' => true,
        ]);
        // Fill the remaining seats up to the limit.
        for ($i = 1; $i < \App\Http\Controllers\Api\Coach\CoachUtils::COACH_SEAT_LIMIT; $i++) {
            CoachTeam::factory()->create([
                'team_id' => $team->id,
                'is_main' => false,
            ]);
        }
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $response = $this->json('POST', 'api/coach/add/coaches', [
            'phone' => fake()->phoneNumber,
            'team' => $team->id,
            'name' => ['first' => fake()->firstName, 'last' => fake()->lastName],
        ]);
        $response->assertForbidden();
        $this->assertSame('005-LIMIT', $response->json('code'));
    }

    public function test_coach_pro_unlimited_seats_allow_another_coach(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value,
            'subscription_plan' => 'coach_pro',
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id, 'is_main' => true]);
        CoachTeam::factory()->count(8)->create(['team_id' => $team->id, 'is_main' => false]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        $this->json('POST', 'api/coach/add/coaches', [
            'phone' => fake()->unique()->phoneNumber,
            'team' => $team->id,
            'name' => ['first' => fake()->firstName, 'last' => fake()->lastName],
        ])->assertOk();

        $this->assertSame(10, CoachTeam::query()->where('team_id', $team->id)->count());
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
