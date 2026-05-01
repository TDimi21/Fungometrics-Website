<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Sessions;

use App\Models\CoachTeam;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\Team;
use App\Models\TeamsLiveAB;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetListLiveABSessionsTest extends TestCase
{
    public function test_get_all_sessions_liveab_ok(): void
    {
        $user = User::factory()->create(['type' => UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        $team = Team::factory()->create();
        $team2 = Team::factory()->create();
        $team3 = Team::factory()->create();
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team->id
        ]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team2->id
        ]);
        CoachTeam::factory()->create([
            'coach_id' => $user->id,
            'team_id' => $team3->id
        ]);
        $practice = Practice::factory()->create(['modes' => PracticeModes::HIT_OR_PITCH->value,'type' =>
          PracticeTypes::LIVE_AB->value]);

        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice->id,
            'team_id' => $team->id
        ]);
        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice->id,
            'team_id' => $team2->id
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice->id,
            'is_pitching' => false,
            'is_batting' => true,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice->id,
            'is_pitching' => true,
            'is_batting' => false,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);


        $practice2 = Practice::factory()->create(['modes' => PracticeModes::HIT_OR_PITCH->value,'type' =>
          PracticeTypes::LIVE_AB->value]);

        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice2->id,
            'team_id' => $team2->id
        ]);
        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice2->id,
            'team_id' => $team3->id
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice2->id,
            'is_pitching' => false,
            'is_batting' => true,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        PracticeLineUp::factory(2)->create([
            'practice_id' => $practice2->id,
            'is_pitching' => true,
            'is_batting' => false,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        $practice3 = Practice::factory()->create(['modes' => PracticeModes::HIT_OR_PITCH->value,'type' =>
          PracticeTypes::LIVE_AB->value]);

        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice3->id,
            'team_id' => $team3->id
        ]);
        TeamsLiveAB::factory()->create([
            'practice_id'=>$practice3->id,
            'team_id' => $team->id
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'is_pitching' => false,
            'is_batting' => true,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        PracticeLineUp::factory(4)->create([
            'practice_id' => $practice3->id,
            'is_pitching' => true,
            'is_batting' => false,
            'user_id' => Profile::factory()->create(['user_id' => User::factory()->create()->id])->user_id,
        ]);

        $response = $this->json("GET", 'api/sessions/all/liveab');
        $response->assertOk()->assertJsonStructure([
            'code',
            'message',
            'status',
            'data',
            'links',
            'meta'
        ]);
    }

  public function test_get_all_sessions_liveab_not_found(): void
  {
      $user = User::factory()->create(['type' => UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      $team = Team::factory()->create();
      $team2 = Team::factory()->create();
      $team3 = Team::factory()->create();
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team->id
      ]);
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team2->id
      ]);
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team3->id
      ]);


      $response = $this->json("GET", 'api/sessions/all/liveab');
      $response->assertNotFound()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data' => []
      ]);
  }

  public function test_get_all_sessions_liveab_forbidden(): void
  {
      $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
      $team = Team::factory()->create();
      $team2 = Team::factory()->create();
      $team3 = Team::factory()->create();
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team->id
      ]);
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team2->id
      ]);
      CoachTeam::factory()->create([
          'coach_id' => $user->id,
          'team_id' => $team3->id
      ]);


      $response = $this->json("GET", 'api/sessions/all/liveab');
      $response->assertForbidden()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data' => []
      ]);
  }

  public function test_get_all_sessions_liveab_unauthorized(): void
  {
      $response = $this->json("GET", 'api/sessions/all/liveab');
      $response->assertUnauthorized()->assertJsonStructure([
          'code',
          'message',
          'status',
          'data' => []
      ]);
  }
}
