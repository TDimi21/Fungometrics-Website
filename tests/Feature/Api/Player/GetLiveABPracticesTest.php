<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\BattingPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetLiveABPracticesTest extends TestCase
{
    public function test_get_liveab_practices_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        $practice = Practice::factory()->create([
            'type' => PracticeTypes::LIVE_AB->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
            'user_id' => $user->id,
        ]);
        BattingPracticeResult::factory(3)->create([
            'batter_id' => $user->id,
            'practice_id' => $practice->id,
            'is_in_match' => true,
        ]);

        $response = $this->json('GET', 'api/player/sessions/liveab');
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }

    public function test_get_liveab_practices_unauthorized(): void
    {
        $this->json('GET', 'api/player/sessions/liveab')->assertUnauthorized();
    }

    public function test_get_liveab_practices_forbidden_without_player_ability(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $this->json('GET', 'api/player/sessions/liveab')->assertForbidden();
    }
}
