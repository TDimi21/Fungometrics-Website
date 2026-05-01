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

class GetBattingPracticesTest extends TestCase
{
    public function test_get_batting_practices_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        BattingPracticeResult::factory(5)->create([
            'batter_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BATTING->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        BattingPracticeResult::factory(5)->create([
            'batter_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BATTING->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        BattingPracticeResult::factory(5)->create([
            'batter_id' => $user->id,
            'practice_id' => Practice::factory()->create(
                [
                    'type' => PracticeTypes::BATTING->value,
                    'modes' => PracticeModes::HIT_OR_PITCH->value,
                    'user_id' => $user->id]
            )->id
        ]);

        $response = $this->json('GET', 'api/player/sessions/batting');
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data'
        ]);
    }

     public function test_get_batting_practices_not_found(): void
     {
         $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
         Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

         BattingPracticeResult::factory(5)->create([
             'batter_id' => $user->id,
             'practice_id' => Practice::factory()->create(
                 [
                     'type' => PracticeTypes::BATTING->value,
                     'modes' => PracticeModes::HIT_OR_PITCH->value,
                     'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                 ]
             )->id
         ]);

         BattingPracticeResult::factory(5)->create([
             'batter_id' => $user->id,
             'practice_id' => Practice::factory()->create(
                 [
                     'type' => PracticeTypes::BATTING->value,
                     'modes' => PracticeModes::HIT_OR_PITCH->value,
                     'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                 ]
             )->id
         ]);

         BattingPracticeResult::factory(5)->create([
             'batter_id' => $user->id,
             'practice_id' => Practice::factory()->create(
                 [
                     'type' => PracticeTypes::BATTING->value,
                     'modes' => PracticeModes::HIT_OR_PITCH->value,
                     'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                 ]
             )->id
         ]);

         $response = $this->json('GET', 'api/player/sessions/batting');
         $response->assertNotFound()->assertJsonStructure([
             'code',
             'status',
             'message',
             'data'
         ]);
     }

     public function test_get_batting_practices_unauthorized(): void
     {
         $response = $this->json('GET', 'api/player/sessions/batting');
         $response->assertUnauthorized()->assertJsonStructure([
             'code',
             'status',
             'message',
             'data'
         ]);
     }

     public function test_get_batting_practices_forbidden(): void
     {
         $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
         Sanctum::actingAs($user);

         $response = $this->json('GET', 'api/player/sessions/batting');
         $response->assertForbidden()->assertJsonStructure([
             'code',
             'status',
             'message',
             'data'
         ]);
     }

}
