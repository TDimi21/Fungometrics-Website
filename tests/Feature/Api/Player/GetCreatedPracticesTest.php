<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetCreatedPracticesTest extends TestCase
{
    public function test_get_created_practices_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        Practice::factory(2)->create([
            'type' => PracticeTypes::BATTING->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'user_id' => $user->id,
        ]);

        $response = $this->json('GET', 'api/player/sessions/created');
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }

    public function test_get_created_practices_unauthorized(): void
    {
        $this->json('GET', 'api/player/sessions/created')->assertUnauthorized();
    }

    public function test_get_created_practices_forbidden_without_player_ability(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $this->json('GET', 'api/player/sessions/created')->assertForbidden();
    }
}
