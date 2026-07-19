<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchCoachesTest extends TestCase
{
    public function test_search_coaches_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::COACH->value])->id
        ]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        $matchingCoach = User::factory()->create(['type' => UserTypes::COACH->value, 'phone' => '678330333']);
        Profile::factory()->create(['user_id' => $matchingCoach->id]);
        CoachTeam::factory()->create(['coach_id' => $matchingCoach->id, 'team_id' => $team->id]);
        Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::COACH->value, 'phone' => '454567838'])->id
        ]);
        $response = $this->json('GET', 'api/coach/search/coaches', ['search' => '678']);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
        $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(0, count($dataResponse->data->data));
        $this->assertObjectNotHasProperty('phone', $dataResponse->data->data[0]);
        $this->assertObjectNotHasProperty('email', $dataResponse->data->data[0]);
    }

    public function test_non_admin_cannot_list_all_coaches_or_search_outside_their_teams(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($coach, [UserTypes::COACH->value]);
        $outside = User::factory()->create(['type' => UserTypes::COACH->value, 'phone' => '678330333']);
        Profile::factory()->create(['user_id' => $outside->id]);

        $this->getJson('/api/coach/search/coaches?search=')->assertUnprocessable();
        $this->getJson('/api/coach/search/coaches?search=678')->assertOk()
            ->assertJsonCount(0, 'data.data');
    }

    public function test_search_coaches_not_found(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::COACH->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/coaches', ['search' => 'ABC']);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ])->assertJsonCount(0, 'data.data');
    }

    public function test_search_coaches_unauthorized(): void
    {
        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/coaches', ['search' => 'ABC']);

        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_search_coaches_forbidden(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/coaches', ['search' => 'ABC']);

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
