<?php

declare(strict_types=1);

namespace Tests\Unit\Relations;

use App\Models\CoachTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use App\Services\ListServiceData;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_get_user_profile(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);
        $userTest = new ListServiceData(new User());
        $result = $userTest->findByUuid($user->id);
        $this->assertNotNull($result->profile);
        $this->assertEquals($result->profile->id, $profile->id);
        $this->assertEquals($result->profile->city, $profile->city);
        $this->assertEquals($result->profile->first_name, $profile->first_name);
    }

    public function test_not_get_user_profile(): void
    {
        $user = User::factory()->create();
        $userTest = new ListServiceData(new User());
        $result = $userTest->findByUuid($user->id);
        $this->assertNull($result->profile);
    }

    public function test_get_coach_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $teamCoach = CoachTeam::factory()->create(['coach_id' => $user->id, 'team_id' => $team->id]);
        $userTemp = new ListServiceData(new User());
        $result = $userTemp->findByUuid($user->id);
        $this->assertNotNull($result->teamsCoach);
        $this->assertEquals(1, $result->teamsCoach->count());
        $this->assertEquals($result->teamsCoach->first()->team_id, $team->id);
    }

    public function test_get_user_teams(): void
    {
        $times = 5;
        $user = User::factory()->create();
        $team = Team::factory()->count($times)->create();
        $team->each(function ($item) use ($user): void {
            CoachTeam::factory()->create([
                'team_id' => $item->id,
                'coach_id' => $user->id,
                'is_main' => fake()->boolean,
            ]);
        });
        $userTemp = new ListServiceData(new User());
        $result = $userTemp->findByUuid($user->id);
        $this->assertNotNull($result->teamsCoach);
        $this->assertGreaterThanOrEqual($times, $result->teamsCoach->count());
    }

    public function test_get_user_team_not_relation(): void
    {
        $user = User::factory()->create();
        $userTemp = new ListServiceData(new User());
        $result = $userTemp->findByUuid($user->id);
        $this->assertEquals(0, $result->teamsCoach->count());
    }
}
