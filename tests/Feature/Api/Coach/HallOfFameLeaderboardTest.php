<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Coach;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\CoachTeam;
use App\Models\LongTossPractice;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerPosition;
use App\Models\PlayerTeam;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HallOfFameLeaderboardTest extends TestCase
{
    public function test_coach_pro_receives_one_team_scoped_twelve_category_wall(): void
    {
        Carbon::setTestNow('2026-07-18 12:00:00');
        [$coach, $team] = $this->coachTeam('coach_pro');
        $leader = $this->player($team, 'Jake', 'Hall');
        $challenger = $this->player($team, 'John', 'Smith');

        foreach ([150, 100, 50, 5] as $daysAgo) {
            BattingPracticeResult::factory()->create([
                'team_id' => $team->id,
                'batter_id' => $leader->id,
                'is_contact' => true,
                'quality_of_contact' => 'HARD',
                'type_of_hit' => 'LINE_DRIVE',
                'velocity' => 100 - ($daysAgo / 50),
                'created_at' => now()->subDays($daysAgo),
            ]);
        }
        BattingPracticeResult::factory()->create([
            'team_id' => $team->id,
            'batter_id' => $challenger->id,
            'is_contact' => true,
            'quality_of_contact' => 'AVERAGE',
            'type_of_hit' => 'GROUND_BALL',
            'velocity' => 82,
            'created_at' => now()->subDays(4),
        ]);

        foreach ([92, 94, 95] as $index => $velocity) {
            BullpenPracticeResult::factory()->create([
                'team_id' => $team->id,
                'pitcher_id' => $leader->id,
                'miles_per_hour' => $velocity,
                'type_throw' => 'FB',
                'zone' => 'S',
                'sort' => $index + 1,
                'created_at' => now()->subDays(20 - ($index * 7)),
            ]);
        }
        CagePracticeResult::factory()->count(3)->create([
            'team_id' => $team->id,
            'user_id' => $leader->id,
            'launch_angle' => 18,
            'launch_angle_velocity' => 98,
            'distance_travel' => 330,
            'created_at' => now()->subDays(3),
        ]);
        foreach ([330, 325, 320, 315, 310] as $index => $distance) {
            LongTossPractice::factory()->create([
                'team_id' => $team->id,
                'user_id' => $leader->id,
                'distance' => $distance,
                'hop' => 0,
                'sort' => $index + 1,
                'created_at' => now()->subDays(2),
            ]);
        }
        foreach ([340, 290, 250, 220, 180] as $index => $distance) {
            LongTossPractice::factory()->create([
                'team_id' => $team->id,
                'user_id' => $challenger->id,
                'distance' => $distance,
                'hop' => 0,
                'sort' => $index + 1,
                'created_at' => now()->subDays(2),
            ]);
        }
        PlayerFitness::factory()->create([
            'user_id' => $leader->id,
            'fitness_date' => now()->subDay()->toDateString(),
            'strength_score' => 93,
            'mobility_score' => 89,
            'recovery_score' => 95,
            'sleep_hours' => 8,
        ]);

        // A larger record for another team must never appear in this team's wall.
        $otherTeam = Team::factory()->create();
        $outsider = $this->player($otherTeam, 'Other', 'Team');
        BattingPracticeResult::factory()->create([
            'team_id' => $otherTeam->id,
            'batter_id' => $outsider->id,
            'velocity' => 125,
            'created_at' => now(),
        ]);

        Sanctum::actingAs($coach, ['coach']);
        $response = $this->getJson("/api/coach/leaderboard/{$team->id}")->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.range.label', 'All time');

        $categories = collect($response->json('data.categories'));
        $this->assertSame([
            'hitter', 'pitcher', 'avg_ev', 'max_ev', 'avg_fb', 'max_fb',
            'bullpen', 'cage', 'long_toss', 'strength', 'mobility', 'recovery',
        ], $categories->pluck('key')->all());
        $this->assertTrue($categories->every(fn (array $category) => count($category['rows']) <= 25));
        $this->assertSame('Jake Hall', $categories->firstWhere('key', 'hitter')['featured']['name']);
        $this->assertSame('Jake Hall', $categories->firstWhere('key', 'bullpen')['featured']['name']);
        $this->assertNotNull($categories->firstWhere('key', 'bullpen')['featured']['bigValue']);
        $longToss = $categories->firstWhere('key', 'long_toss');
        $this->assertSame('Jake Hall', $longToss['featured']['name']);
        $this->assertSame(330.0, (float) collect($longToss['featured']['subMetrics'])->firstWhere('label', 'Max Carry')['value']);
        $this->assertGreaterThan(90, (float) $longToss['featured']['bigValue']);
        $this->assertSame(93.0, (float) $categories->firstWhere('key', 'strength')['featured']['bigValue']);
        $this->assertGreaterThanOrEqual(2, count($categories->firstWhere('key', 'hitter')['featured']['spark']));
        $this->assertNotContains('Other Team', $categories->flatMap(fn (array $category) => array_column($category['rows'], 'name'))->all());
    }

    public function test_leaderboard_enforces_plan_and_team_membership(): void
    {
        [$freeCoach, $team] = $this->coachTeam('free');
        Sanctum::actingAs($freeCoach, ['coach']);
        $this->getJson("/api/coach/leaderboard/{$team->id}")->assertForbidden();

        [$proCoach] = $this->coachTeam('coach_pro');
        Sanctum::actingAs($proCoach, ['coach']);
        $this->getJson("/api/coach/leaderboard/{$team->id}")->assertForbidden();
    }

    public function test_empty_team_keeps_the_complete_contract_and_validates_range(): void
    {
        [$coach, $team] = $this->coachTeam('coach_pro');
        Sanctum::actingAs($coach, ['coach']);

        $response = $this->getJson("/api/coach/leaderboard/{$team->id}?range=6")->assertOk();
        $this->assertCount(12, $response->json('data.categories'));
        $this->assertSame(30, $response->json('data.range.days'));
        $this->assertSame([], $response->json('data.categories.0.rows'));

        $this->getJson("/api/coach/leaderboard/{$team->id}?range=99")
            ->assertStatus(422)
            ->assertJsonPath('code', 'LB-V');
    }

    /** @return array{User, Team} */
    private function coachTeam(string $plan): array
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => $plan]);
        $team = Team::factory()->create(['name' => 'FMTRX Select']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);

        return [$coach, $team];
    }

    private function player(Team $team, string $firstName, string $lastName): User
    {
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'free']);
        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'level' => '16U',
        ]);
        Player::factory()->create([
            'user_id' => $user->id,
            'height_in_ft' => 6,
            'height_in_inch' => 1,
            'born_date' => now()->subYears(16)->toDateString(),
            'throw_side' => 'R',
            'hit_side' => 'R',
        ]);
        PlayerPosition::factory()->create(['player_id' => $user->id, 'position' => 'RHP']);
        PlayerTeam::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'actual' => true]);

        return $user;
    }
}
