<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetWeightBallPracticeResultsTest extends TestCase
{
    public function test_get_statistics_weight_ball_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $practice = Practice::factory()->create(['team_id' => Team::factory()->create()->id,
            'modes' => PracticeModes::WEIGHT_BALL->value, 'type' => PracticeTypes::TRAINING->value]);

        WeightBallPractice::factory(6)->create(['practice_id' => $practice->id, 'user_id' => Player::factory()
            ->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                ])->user_id
            ])->user_id]);

        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/weightball');
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }


    public function test_get_statistics_weight_ball_ok_without_team(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $player = Player::factory()
            ->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])->id
                ])->user_id
            ])->user_id;
        $practice = Practice::factory()->create(['team_id' => Team::factory()->create()->id,
            'modes' => PracticeModes::WEIGHT_BALL->value, 'type' => PracticeTypes::TRAINING->value]);

        WeightBallPractice::factory(6)->create(['practice_id' => $practice->id, 'user_id' => $player]);

        $response = $this->json('GET', 'api/statistics/'.$practice->id.'/weightball');
        $response->assertOk()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_weight_ball_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/weightball');
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }

    public function test_get_statistics_weight_ball_unauthorized(): void
    {
        $response = $this->json('GET', 'api/statistics/'.fake()->uuid.'/weightball');
        $response->assertUnauthorized()->assertJsonStructure([
            'status',
            'message',
            'code',
            'data' => []
        ]);
    }
}
