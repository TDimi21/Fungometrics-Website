<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Player;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTrainingPracticesTest extends TestCase
{
    public function test_get_training_practices_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        $practice = Practice::factory()->create([
            'type' => PracticeTypes::TRAINING->value,
            'modes' => PracticeModes::WEIGHT_BALL->value,
            'user_id' => $user->id,
        ]);
        WeightBallPractice::factory(3)->create([
            'user_id' => $user->id,
            'practice_id' => $practice->id,
        ]);

        $response = $this->json('GET', 'api/player/sessions/training');
        $response->assertOk()->assertJsonStructure([
            'code',
            'status',
            'message',
            'data',
        ]);
    }

    public function test_get_training_practices_unauthorized(): void
    {
        $this->json('GET', 'api/player/sessions/training')->assertUnauthorized();
    }

    public function test_get_training_practices_forbidden_without_player_ability(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $this->json('GET', 'api/player/sessions/training')->assertForbidden();
    }
}
