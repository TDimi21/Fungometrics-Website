<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\CoachTeam;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetFullListAllTrainingModeTest extends TestCase
{
    public function test_get_all_training_modes_ok_with_team(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS->value, 'team_id' => $team->id]),
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY->value, 'team_id' => $team->id]),
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL->value, 'team_id' => $team->id]),
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        $response = $this->json("GET", 'api/sessions/all/modes/', ['team'=>$team->id]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }


    public function test_get_all_training_modes_ok_without_team(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        Practice::factory(4)->create(['modes' => PracticeModes::LONG_TOSS->value,'user_id' => $user->id]);
        Practice::factory(4)->create(['modes' => PracticeModes::WEIGHT_BALL->value,'user_id' => $user->id]);
        Practice::factory(4)->create(['modes' => PracticeModes::EXIT_VELOCITY->value,'user_id' => $user->id]);



        $response = $this->json("GET", 'api/sessions/all/modes/');
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_training_modes_not_found(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $response = $this->json("GET", 'api/sessions/all/modes/');
        $response->assertNotFound()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_training_modes_unauthorized(): void
    {
        $response = $this->json("GET", 'api/sessions/all/modes/');
        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }
}
