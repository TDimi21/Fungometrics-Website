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

class GetAllSessionByModeTest extends TestCase
{
    public function test_get_all_trainings_by_mode_velocity(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        $team2 = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id
        ]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team2->id
        ]);
        Practice::factory()->create(['team_id' => $team->id, 'modes' => PracticeModes::EXIT_VELOCITY]);
        $practice2 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice8 = Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY, 'team_id' => $team2->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice8->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::EXIT_VELOCITY
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_long_toss(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id
        ]);
        Practice::factory()->create(['team_id' => $team->id, 'modes' => PracticeModes::LONG_TOSS]);
        $practice2 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::LONG_TOSS
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_weight_ball(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id
        ]);
        Practice::factory()->create(['team_id' => $team->id, 'modes' => PracticeModes::WEIGHT_BALL]);
        $practice2 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice3 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice4 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice4->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice5 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice5->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice6 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice6->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $practice7 = Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL, 'team_id' => $team->id]);
        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice7->id,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);
        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::WEIGHT_BALL
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_velocity_without_team(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::EXIT_VELOCITY,'user_id' => $user->id]);

        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::EXIT_VELOCITY
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_long_toss_without_team(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::LONG_TOSS,'user_id' => $user->id]);

        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::LONG_TOSS
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_weight_ball_without_team(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $team = Team::factory()->create();
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);
        Practice::factory()->create(['modes' => PracticeModes::WEIGHT_BALL,'user_id' => $user->id]);

        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::WEIGHT_BALL
        ]);
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data' => []
        ]);
    }

    public function test_get_all_trainings_by_mode_unauthorized(): void
    {
        $response = $this->json("GET", 'api/sessions/all/mode', [
            'modes' => PracticeModes::WEIGHT_BALL
        ]);
        $response->assertUnauthorized();
    }
}
