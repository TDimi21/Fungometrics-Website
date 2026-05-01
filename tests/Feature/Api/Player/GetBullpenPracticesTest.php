<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetBullpenPracticesTest extends TestCase
{
    public function test_get_bullpen_practices_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        BullpenPracticeResult::factory(5)->create([
            'pitcher_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BULLPEN->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        BullpenPracticeResult::factory(5)->create([
            'pitcher_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BULLPEN->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        BullpenPracticeResult::factory(5)->create([
            'pitcher_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BULLPEN->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        $response = $this->json('GET', 'api/player/sessions/bullpen');
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_get_bullpen_practices_not_found(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);


        BullpenPracticeResult::factory(5)->create([
            'pitcher_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BULLPEN->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                ]
            )->id
        ]);

        $response = $this->json('GET', 'api/player/sessions/bullpen');
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_get_bullpen_practices_unauthorized(): void
    {
        $response = $this->json('GET', 'api/player/sessions/bullpen');
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

    public function test_get_bullpen_practices_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $response = $this->json('GET', 'api/player/sessions/bullpen');
        $response->assertForbidden()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }
}
