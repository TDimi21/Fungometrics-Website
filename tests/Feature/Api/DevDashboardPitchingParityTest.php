<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BullpenPracticeResult;
use App\Models\CoachTeam;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Player;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\Profile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevDashboardPitchingParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_self_view_returns_the_same_pitching_numbers_as_the_coach_team_scoped_view(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'player_pro']);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);

        $practice = Practice::factory()->create([
            'user_id' => $player->id,
            'team_id' => $team->id,
            'type' => PracticeTypes::BULLPEN->value,
            'modes' => PracticeModes::HIT_OR_PITCH->value,
        ]);
        foreach ([88, 90, 84] as $mph) {
            BullpenPracticeResult::factory()->create([
                'pitcher_id' => $player->id,
                'practice_id' => $practice->id,
                'team_id' => $team->id,
                'is_in_match' => false,
                'is_strike' => true,
                'type_throw' => 'FB',
                'miles_per_hour' => $mph,
            ]);
        }

        Sanctum::actingAs($coach, [UserTypes::COACH->value]);
        $coachView = $this->getJson("api/coach/development/teams/{$team->id}/players/{$player->id}?days=365")
            ->assertOk();

        Sanctum::actingAs($player, [UserTypes::PLAYER->value]);
        $playerView = $this->getJson("api/player/development/players/{$player->id}?days=365")
            ->assertOk();

        $coachAvg = $coachView->json('data.current.avg_fb_velocity');
        $coachMax = $coachView->json('data.current.max_fb_velocity');
        $coachBullpenScore = $coachView->json('data.current.bullpen_score');
        $playerAvg = $playerView->json('data.current.avg_fb_velocity');
        $playerMax = $playerView->json('data.current.max_fb_velocity');
        $playerBullpenScore = $playerView->json('data.current.bullpen_score');

        $this->assertNotNull($coachAvg, 'Coach view should have avg_fb_velocity.');
        $this->assertSame($coachAvg, $playerAvg, 'avg_fb_velocity differs between coach and player-self views.');
        $this->assertSame($coachMax, $playerMax, 'max_fb_velocity differs between coach and player-self views.');
        $this->assertSame($coachBullpenScore, $playerBullpenScore, 'bullpen_score differs between coach and player-self views.');
    }

    public function test_player_self_view_returns_the_same_sprint_and_hand_strength_numbers_as_the_coach_view(): void
    {
        $coach = User::factory()->create(['type' => UserTypes::COACH->value, 'subscription_plan' => 'coach_pro']);
        $team = Team::factory()->create();
        $player = User::factory()->create(['type' => UserTypes::PLAYER->value, 'subscription_plan' => 'player_pro']);
        Profile::factory()->create(['user_id' => $player->id]);
        Player::factory()->create(['user_id' => $player->id]);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id]);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => true]);

        PlayerFitness::factory()->create([
            'user_id' => $player->id,
            'fitness_date' => now()->toDateString(),
            'yd_40_dash' => 6.8,
            'yd_60_dash' => 7.9,
            'hand_strength' => 55,
        ]);

        Sanctum::actingAs($coach, [UserTypes::COACH->value]);
        $coachView = $this->getJson("api/coach/development/teams/{$team->id}/players/{$player->id}?days=365")
            ->assertOk();
        $coachIntelligence = $this->getJson("api/coach/teams/{$team->id}/players/{$player->id}/intelligence?days=365")
            ->assertOk();

        Sanctum::actingAs($player, [UserTypes::PLAYER->value]);
        $playerView = $this->getJson("api/player/development/players/{$player->id}?days=365")
            ->assertOk();
        $playerIntelligence = $this->getJson('api/player/intelligence?days=365')
            ->assertOk();

        $this->assertSame($coachView->json('data.current.hand_strength'), $playerView->json('data.current.hand_strength'), 'hand_strength differs between coach and player-self live views.');
        $this->assertEquals(55, $playerView->json('data.current.hand_strength'));

        $coachMetrics = collect($coachIntelligence->json('benchmark_profile.metrics'))->keyBy('metric_key');
        $playerMetrics = collect($playerIntelligence->json('benchmark_profile.metrics'))->keyBy('metric_key');
        foreach (['forty_yard_dash', 'sixty_yard_dash'] as $key) {
            $this->assertSame($coachMetrics->get($key)['raw_value'] ?? null, $playerMetrics->get($key)['raw_value'] ?? null, "{$key} raw_value differs between coach and player-self intelligence.");
            $this->assertSame($coachMetrics->get($key)['percentile'] ?? null, $playerMetrics->get($key)['percentile'] ?? null, "{$key} percentile differs between coach and player-self intelligence.");
        }
    }
}
