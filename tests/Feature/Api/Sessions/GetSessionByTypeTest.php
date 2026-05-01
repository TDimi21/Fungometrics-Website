<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetSessionByTypeTest extends TestCase
{
    public function test_get_all_trainings_by_type_batting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::BATTING]);
        $practice2 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['type' => PracticeTypes::BATTING, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => $team->id,
            'type' => PracticeTypes::BATTING
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_bullpen(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::BULLPEN]);
        $practice2 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_liveab(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::LIVE_AB]);
        $practice2 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['type' => PracticeTypes::LIVE_AB, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => $team->id,
            'type' => PracticeTypes::LIVE_AB
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_cage(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['team_id' => $team->id, 'type' => PracticeTypes::CAGE]);
        $practice2 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['type' => PracticeTypes::CAGE, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => $team->id,
            'type' => PracticeTypes::CAGE
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_batting_without_team_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Team::factory()->create();
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id,]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BATTING, 'user_id' => $user->id]);
        $response = $this->json("GET", 'api/sessions/type', [
            'type' => PracticeTypes::BATTING
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_bullpen_without_team_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Team::factory()->create();
        Practice::factory()->create(['user_id' => $user->id, 'type' => PracticeTypes::BULLPEN]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id,]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::BULLPEN, 'user_id' => $user->id]);
        $response = $this->json("GET", 'api/sessions/type', [
            'type' => PracticeTypes::BULLPEN
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_cage_without_team_ok(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Team::factory()->create();
        Practice::factory()->create(['user_id' => $user->id, 'type' => PracticeTypes::CAGE]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id,]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id]);
        Practice::factory()->create(['type' => PracticeTypes::CAGE, 'user_id' => $user->id]);
        $response = $this->json("GET", 'api/sessions/type', [
            'type' => PracticeTypes::CAGE
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_type_not_result(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BATTING
        ]);
        $response->assertNotFound();
    }

    public function test_get_all_trainings_by_type_unauthorized(): void
    {
        $response = $this->json("GET", 'api/sessions/type', [
            'team_id' => Team::factory()->create()->id,
            'type' => PracticeTypes::BATTING
        ]);
        $response->assertUnauthorized();
    }
}
