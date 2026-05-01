<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTeamPlayerTest extends TestCase
{
    public function test_get_all_teams_with_players_ok(): void
    {
        $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
        Sanctum::actingAs($user, [UserTypes::COACH->value]);
        Team::factory(4)->create()->each(function ($item) use ($user): void {
            CoachTeam::factory()->create(['coach_id' => $user->id,'team_id' => $item->id]);
            PlayerTeam::factory(5)->create([
                'user_id' => Player::factory()->create([
                    'user_id' => Profile::factory()->create([
                        'user_id'=>User::factory()
                            ->create(['type'=>UserTypes::PLAYER->value])->id])->user_id])
                  ->user_id,
                'team_id' => $item->id,
                'actual' => true
            ]);
        });

        $response = $this->json('GET', 'api/coach/teams');
        $response->assertOk();
    }

  public function test_get_all_teams_with_players_not_found_data(): void
  {
      $user = User::factory()->create(['type'=>UserTypes::COACH->value]);
      Sanctum::actingAs($user, [UserTypes::COACH->value]);
      Team::factory(1)->create()->each(function ($item): void {
          CoachTeam::factory()->create(['coach_id' => User::factory()->create()->id,'team_id' => $item->id]);
          PlayerTeam::factory(2)->create([
              'user_id' => Player::factory()->create([
                  'user_id' => Profile::factory()->create([
                      'user_id'=>User::factory()
                          ->create(['type'=>UserTypes::PLAYER->value])->id])->user_id])
                ->user_id,
              'team_id' => $item->id,
              'actual' => true
          ]);
      });

      $response = $this->json('GET', 'api/coach/teams');
      $response->assertNotFound();
  }
  public function test_get_all_teams_with_players_unauthorized(): void
  {
      $response = $this->json('GET', 'api/coach/teams');
      $response->assertUnauthorized();
  }

  public function test_get_all_teams_with_players_forbidden(): void
  {
      $user = User::factory()->create(['type'=>UserTypes::PLAYER->value]);
      Sanctum::actingAs($user, [UserTypes::PLAYER->value]);
      $response = $this->json('GET', 'api/coach/teams');
      $response->assertForbidden();
  }
}
