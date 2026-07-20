<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions\Results;

use App\Models\Concerns\TypeSMS;
use App\Models\Concerns\UserTypes;
use App\Models\CoachTeam;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\SmsLog;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListSmsResultsTest extends TestCase
{
    public function test_get_list_sms_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        $practice = Practice::factory()->create([
            'team_id' => $team->id,
        ]);

        SmsLog::factory(12)->create([
            'practice_id' => $practice->id,
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => User::factory()->create()->id
                ])->user_id
            ])->user_id,
            'type' => TypeSMS::TRAINING->value,
            'phone' => fake()->phoneNumber
        ]);


        $response = $this->json(
            'GET',
            'api/coach/list/results/'.$practice->id
        );

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_list_sms_not_found(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
        ]);

        SmsLog::factory(12)->create([
            'practice_id' => $practice->id,
            'user_id' => Player::factory()->create([
                'user_id' => Profile::factory()->create([
                    'user_id' => User::factory()->create()->id
                ])->user_id
            ])->user_id,
            'type' => TypeSMS::TRAINING->value,
            'phone' => fake()->phoneNumber
        ]);


        $response = $this->json(
            'GET',
            'api/coach/list/results/'.fake()->uuid
        );

        $response->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_get_list_sms_unauthorized(): void
    {

        $response = $this->json(
            'GET',
            'api/coach/list/results/'.fake()->uuid
        );

        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_list_sms_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $response = $this->json(
            'GET',
            'api/coach/list/results/'.fake()->uuid
        );

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

}
