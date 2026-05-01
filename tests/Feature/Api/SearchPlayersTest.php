<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchPlayersTest extends TestCase
{
    public function test_search_players_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);


        $player1=Player::factory()->create(['user_id' => Profile::factory()->create([
            'first_name' => 'Jhon',
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value, 'phone' => '678330333'])->id
        ])->user_id]);

        PlayerTeam::factory()->create([
            'user_id'=>$player1->user_id,
            'team_id' => Team::factory()->create()->id,
            'actual' => true,
        ]);


        $player2=Player::factory()->create(['user_id' => Profile::factory()->create([
            'first_name' => 'Jhony',
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value, 'phone' => '454567838'])->id
        ])->user_id]);
        PlayerTeam::factory()->create([
            'user_id'=>$player2->user_id,
            'team_id' => Team::factory()->create()->id,
            'actual' => true,
        ]);
        $player3=Player::factory()->create(['user_id' => Profile::factory()->create([
            'first_name' => 'Martin',
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value, 'phone' => '99839933'])->id
        ])->user_id]);
        PlayerTeam::factory()->create([
            'user_id'=>$player3->user_id,
            'team_id' => Team::factory()->create()->id,
            'actual' => true,
        ]);
        $response = $this->json('GET', 'api/coach/search/players', ['phone' => '678', 'name' => 'Jhon']);

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
        $dataResponse = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(0, count($dataResponse->data));
    }

    public function test_search_players_not_found(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);

        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/players', ['phone' => '678', 'name' => 'Jhon']);

        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_search_players_unauthorized(): void
    {
        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/players', ['phone' => '678', 'name' => 'Jhon']);

        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }

    public function test_search_players_forbidden(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        Profile::factory(30)->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
        ]);

        $response = $this->json('GET', 'api/coach/search/players', ['phone' => '678', 'name' => 'Jhon']);

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
    }
}
