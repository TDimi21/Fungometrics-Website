<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Http\Requests\Api\Training\AddNewLiveABSessionRequest;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AddNewLiveABSessionTest extends TestCase
{
    public function test_add_new_session_training_ok(): void
    {
        $user = User::factory()->create([
            'type' => UserTypes::COACH->value
        ]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $teamA = Team::factory()->create()->id;
        $teamB = Team::factory()->create()->id;
        $data = [
            'teams' => ['a' => $teamA, 'b' => $teamB],
            'type' => PracticeTypes::LIVE_AB->value,
            'note' => fake()->paragraph,
        ];
        $i = 0;
        $temp_players = User::factory()->count(4)->create(['type' => UserTypes::PLAYER->value]);
        $data['players']['a'] = $temp_players->map(function ($temp_item) use (&$i) {
            $i++;
            return ['id' => $temp_item->id, 'sort' => $i];
        });

        foreach ($temp_players as $player) {
            Profile::factory()->create([
                'user_id' => $player->id,
            ]);
            Player::factory()->create([
                'user_id' => $player->id,
            ]);
        }

        $j = 0;
        $temp_players2 = User::factory()->count(4)->create(['type' => UserTypes::PLAYER->value]);
        $data['players']['b'] = $temp_players2->map(function ($temp_item) use (&$j) {
            $j++;
            return ['id' => $temp_item->id, 'sort' => $j];
        });

        foreach ($temp_players2 as $player) {
            Profile::factory()->create([
                'user_id' => $player->id,
            ]);
            Player::factory()->create([
                'user_id' => $player->id,
            ]);
        }

        $response = $this->json('POST', 'api/coach/trainingab', $data);
        $response->assertCreated();
    }

    public function test_add_new_session_training_unauthorized(): void
    {
        $response = $this->json('POST', 'api/coach/trainingab', []);
        $response->assertUnauthorized();
    }

    public function test_add_new_session_training_validations(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/trainingab', []);
        $response->assertUnprocessable();
    }

    public function test_add_new_session_training_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $response = $this->json('POST', 'api/coach/trainingab', []);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_add_new_session_training_fail(): void
    {
        $this->mock(AddNewLiveABSessionRequest::class, function ($mock): void {
            $mock->shouldReceive('passes')->andReturn(true);
        });
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $response = $this->json('POST', 'api/coach/trainingab', []);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
