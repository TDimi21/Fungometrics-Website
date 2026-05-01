<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions\Results;

use App\Events\SentResults;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\SmsLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendSmsResultsTest extends TestCase
{
    public function test_send_sms_result_ok(): void
    {

        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $user1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user1->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user2->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user3->user_id,
        ]);

        $data = ['players'=>[
            ['id'=>$user1->user_id,'name'=>$user1->first_name,'phone'=>$user1->user->phone],
            ['id'=>$user2->user_id,'name'=>$user2->first_name,'phone'=>$user2->user->phone],
            ['id'=>$user3->user_id,'name'=>$user3->first_name,'phone'=>$user3->user->phone],]

        ];
        Event::fake([SentResults::class]);

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.$practice->id,
            $data
        );

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_send_sms_result_ok_sent(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $user1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user1->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user2->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user3->user_id,
        ]);
        Event::fake([SentResults::class]);
        SmsLog::factory()->create([
            'practice_id' => $practice->id,
            'response' => "TEST",
            'phone' => 'test',
            'user_id' => $user->id,
            'type' => "SMS SENT",
            "message" => 'test'
        ]);

        $data = ['players'=>[
            ['id'=>$user1->user_id,'name'=>$user1->first_name,'phone'=>$user1->user->phone],
            ['id'=>$user2->user_id,'name'=>$user2->first_name,'phone'=>$user2->user->phone],
            ['id'=>$user3->user_id,'name'=>$user3->first_name,'phone'=>$user3->user->phone],]

        ];

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.$practice->id,
            $data
        );

        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
        $data = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($data->data);
    }

    public function test_send_sms_result_error(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $user1 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user2 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);
        $user3 = Profile::factory()->create([
            'user_id' => User::factory()->create(['type' => UserTypes::PLAYER->value])
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user1->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user2->user_id,
        ]);

        BullpenPracticeResult::factory(5)->create([
            'practice_id' => $practice->id,
            'pitcher_id' => $user3->user_id,
        ]);
        Event::fake([SentResults::class]);
        SmsLog::factory()->create([
            'practice_id' => $practice->id,
            'response' => "TEST",
            'phone' => 'test',
            'user_id' => $user->id,
            'type' => "SMS SENT",
            "message" => 'test'
        ]);

        $data = ['players'=>[
            ['id'=>$user1->user_id,'name'=>$user1->first_name,'phone'=>$user1->user->phone],
            ['id'=>$user2->user_id,'name'=>$user2->first_name,'phone'=>$user2->user->phone],
            ['id'=>$user3->user_id,'name'=>$user3->first_name,'phone'=>$user3->user->phone],]

        ];

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.fake()->uuid,
            $data
        );

        $response->assertServerError()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);

    }

    public function test_send_sms_result_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);


        $data = [];

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.$practice->id,
            $data
        );

        $response->assertUnprocessable()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_send_sms_result_unauthorized(): void
    {

        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);

        $data = ['players'=>[]];

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.$practice->id,
            $data
        );

        $response->assertUnauthorized()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);

    }

    public function test_send_sms_result_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $practice = Practice::factory()->create([
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);


        $data = [];

        $response = $this->json(
            'POST',
            'api/coach/send/results/'.$practice->id,
            $data
        );

        $response->assertForbidden()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }


}
