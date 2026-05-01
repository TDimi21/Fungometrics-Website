<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetLastSessionsTest extends TestCase
{
    public function test_get_last_sessions_by_team_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $profiles = Profile::factory(15)->create(['user_id' => User::factory()->create()->id]);
        $practices = Practice::factory(80)->create(['team_id' => $team->id])
            ->each(static function ($item) use ($profiles): void {
                PracticeLineUp::factory(random_int(1, 6))->create([
                    'practice_id' => $item->id,
                    'user_id' => $profiles->random()->user_id,
                ]);
            });

        $count = $practices->count();

        BattingPracticeResult::factory(4)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'batter_id' => $profiles->random()->user_id
        ]);
        BattingPracticeResult::factory(10)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'batter_id' => $profiles->random()->user_id
        ]);
        BullpenPracticeResult::factory(14)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'pitcher_id' => $profiles->random()->user_id
        ]);
        BullpenPracticeResult::factory(6)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'pitcher_id' => $profiles->random()->user_id
        ]);

        CagePracticeResult::factory(14)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'user_id' => $profiles->random()->user_id
        ]);
        CagePracticeResult::factory(17)->create([
            'team_id' => $team->id,
            'practice_id' => $practices->random()->id,
            'user_id' => $profiles->random()->user_id
        ]);





        $response = $this->json('GET', 'api/coach/sessions/lasts/'.$team->id);
        $response->assertOk()->assertJsonStructure([
            'status',
            'code',
            'message',
            'data'
        ]);
    }

    public function test_get_last_sessions_by_team_not_found(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $response = $this->json('GET', 'api/coach/sessions/lasts/'.$team->id);
        $response->assertNotFound()->assertJsonStructure([
            'status',
            'code',
            'message',
            'data'
        ]);
    }

    public function test_get_last_sessions_by_team_not_unauthorized(): void
    {
        $user = User::factory()->create();

        $team = Team::factory()->create();

        $response = $this->json('GET', 'api/coach/sessions/lasts/'.$team->id);
        $response->assertUnauthorized()->assertJsonStructure([
            'status',
            'code',
            'message',
            'data'
        ]);
    }

    public function test_get_last_sessions_by_team_not_forbidden(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
        $team = Team::factory()->create();

        $response = $this->json('GET', 'api/coach/sessions/lasts/'.$team->id);
        $response->assertForbidden()->assertJsonStructure([
            'status',
            'code',
            'message',
            'data'
        ]);
    }
}
