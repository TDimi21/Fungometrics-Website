<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Concerns\UserTypes;
use App\Models\Profile;
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
        Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::COACH->value, 'phone' => '678330333'])->id
        ]);
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

        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data'
        ]);
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
