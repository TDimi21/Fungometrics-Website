<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\UserTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\User;
use App\Models\WeightBallPractice;
use App\Services\Statistics\PlayerDashboardSummaryService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerDashboardSummaryTest extends TestCase
{
    public function test_dashboard_summary_requires_authentication(): void
    {
        $this->json('GET', 'api/player/dashboard-summary')->assertUnauthorized();
    }

    public function test_dashboard_summary_requires_player_ability(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user);

        $this->json('GET', 'api/player/dashboard-summary')->assertForbidden();
    }

    public function test_dashboard_summary_returns_counts_breakdowns_and_recent_sessions(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        $ownBatting = $this->makePractice($user->id, PracticeTypes::BATTING->value, PracticeModes::HIT_OR_PITCH->value);
        $otherOwner = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        $joinedBatting = $this->makePractice($otherOwner->id, PracticeTypes::BATTING->value, PracticeModes::HIT_OR_PITCH->value);

        $this->makeBattingRow($user->id, $ownBatting->id, ['velocity' => 80, 'quality_of_contact' => 'H', 'type_of_hit' => 'LD', 'field_direction' => 'LF']);
        $this->makeBattingRow($user->id, $ownBatting->id, ['velocity' => 100, 'quality_of_contact' => 'A', 'type_of_hit' => 'FB', 'field_direction' => 'CF']);
        $this->makeBattingRow($user->id, $ownBatting->id, ['velocity' => 0, 'is_contact' => false, 'quality_of_contact' => 'N', 'type_of_hit' => 'SM', 'field_direction' => 'N']);
        $this->makeBattingRow($user->id, $joinedBatting->id, ['velocity' => 90, 'quality_of_contact' => 'H', 'type_of_hit' => 'LD', 'field_direction' => 'LF']);
        $this->makeBattingRow($user->id, $joinedBatting->id, ['velocity' => 110, 'quality_of_contact' => 'A', 'type_of_hit' => 'FB', 'field_direction' => 'RF']);

        $bullpen = $this->makePractice($user->id, PracticeTypes::BULLPEN->value, PracticeModes::HIT_OR_PITCH->value);
        $this->makeBullpenRow($user->id, $bullpen->id, ['is_strike' => true, 'type_throw' => 'FB', 'miles_per_hour' => 80]);
        $this->makeBullpenRow($user->id, $bullpen->id, ['is_strike' => true, 'type_throw' => 'FB', 'miles_per_hour' => 85]);
        // pitch_mark 1 and 2 sit in the top-left corner of the location grid, outside the strike zone.
        $this->makeBullpenRow($user->id, $bullpen->id, ['is_strike' => false, 'type_throw' => 'SL', 'miles_per_hour' => 70, 'pitch_mark' => 1]);
        $this->makeBullpenRow($user->id, $bullpen->id, ['is_strike' => false, 'type_throw' => 'SL', 'miles_per_hour' => 70, 'pitch_mark' => 2]);

        $cage = $this->makePractice($user->id, PracticeTypes::CAGE->value, PracticeModes::HIT_OR_PITCH->value);
        CagePracticeResult::factory()->create(['user_id' => $user->id, 'practice_id' => $cage->id, 'launch_angle' => 20, 'launch_angle_velocity' => 95, 'spray_angle' => '-20']);
        CagePracticeResult::factory()->create(['user_id' => $user->id, 'practice_id' => $cage->id, 'launch_angle' => 10, 'launch_angle_velocity' => 80, 'spray_angle' => '10']);

        $weightBall = $this->makePractice($user->id, PracticeTypes::TRAINING->value, PracticeModes::WEIGHT_BALL->value);
        WeightBallPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $weightBall->id, 'weight' => 5, 'velocity' => 70]);
        WeightBallPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $weightBall->id, 'weight' => 5, 'velocity' => 80]);

        $exitVelocity = $this->makePractice($user->id, PracticeTypes::TRAINING->value, PracticeModes::EXIT_VELOCITY->value);
        ExitVelocityPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $exitVelocity->id, 'trajectory' => 'LD', 'velocity' => 92]);
        ExitVelocityPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $exitVelocity->id, 'trajectory' => 'GB', 'velocity' => 70]);

        $longToss = $this->makePractice($user->id, PracticeTypes::TRAINING->value, PracticeModes::LONG_TOSS->value);
        LongTossPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $longToss->id, 'hop' => 0, 'distance' => 200]);
        LongTossPractice::factory()->create(['user_id' => $user->id, 'practice_id' => $longToss->id, 'hop' => 2, 'distance' => 150]);

        $response = $this->json('GET', 'api/player/dashboard-summary');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.counts.batting', 2)
            ->assertJsonPath('data.counts.bullpen', 1)
            ->assertJsonPath('data.counts.cage', 1)
            ->assertJsonPath('data.counts.training', 3)
            ->assertJsonPath('data.counts.weighted', 1)
            ->assertJsonPath('data.counts.exitVel', 1)
            ->assertJsonPath('data.counts.longToss', 1);

        $batting = $response->json('data.breakdowns.batting');
        $this->assertSame(5, $batting['swings']);
        $this->assertEqualsWithDelta(95.0, $batting['avgEV'], 0.01);
        $this->assertEqualsWithDelta(110.0, $batting['maxEV'], 0.01);
        $this->assertEqualsWithDelta(75.0, $batting['hardPct'], 0.01);
        $this->assertEqualsWithDelta(20.0, $batting['missPct'], 0.01);
        $this->assertSame(4, $batting['sprayTotal']);
        $this->assertEqualsWithDelta(50.0, $batting['lfPct'], 0.01);
        $this->assertEqualsWithDelta(50.0, $batting['ldPct'], 0.01);
        $this->assertEqualsWithDelta(50.0, $batting['fbPct'], 0.01);

        $bullpenStats = $response->json('data.breakdowns.bullpen');
        $this->assertSame(4, $bullpenStats['total']);
        $this->assertEqualsWithDelta(50.0, $bullpenStats['strikePct'], 0.01);
        $this->assertEqualsWithDelta(85.0, $bullpenStats['maxFB'], 0.01);
        $this->assertEqualsWithDelta(82.5, $bullpenStats['avgFB'], 0.01);
        $types = collect($bullpenStats['pitchTypeStats'])->keyBy('type');
        $this->assertSame(2, $types['FB']['count']);
        $this->assertEqualsWithDelta(100.0, $types['FB']['strikePct'], 0.01);
        $this->assertEqualsWithDelta(0.0, $types['SL']['strikePct'], 0.01);

        $cageStats = $response->json('data.breakdowns.cage');
        $this->assertSame(2, $cageStats['swings']);
        $this->assertEqualsWithDelta(87.5, $cageStats['avgEV'], 0.01);
        $this->assertEqualsWithDelta(50.0, $cageStats['hardPct'], 0.01);
        $this->assertEqualsWithDelta(100.0, $cageStats['sweetPct'], 0.01);
        $this->assertEqualsWithDelta(50.0, $cageStats['pullPct'], 0.01);
        $this->assertEqualsWithDelta(50.0, $cageStats['centerPct'], 0.01);

        $weighted = $response->json('data.breakdowns.weighted');
        $this->assertSame(2, $weighted['throws']);
        $this->assertEqualsWithDelta(75.0, $weighted['avgVelo'], 0.01);
        $this->assertEqualsWithDelta(80.0, $weighted['maxVelo'], 0.01);
        $this->assertCount(1, $weighted['byWeight']);

        $exitVel = $response->json('data.breakdowns.exitVel');
        $this->assertSame(2, $exitVel['swings']);
        $this->assertEqualsWithDelta(81.0, $exitVel['avgEV'], 0.01);
        $this->assertEqualsWithDelta(50.0, $exitVel['hardPct'], 0.01);
        $this->assertSame(1, $exitVel['ldCount']);
        $this->assertEqualsWithDelta(92.0, $exitVel['ldAvgEV'], 0.01);

        $longTossStats = $response->json('data.breakdowns.longToss');
        $this->assertSame(2, $longTossStats['throws']);
        $this->assertEqualsWithDelta(200.0, $longTossStats['maxDist'], 0.01);
        $this->assertEqualsWithDelta(175.0, $longTossStats['avgDist'], 0.01);
        $this->assertSame(1, $longTossStats['hop0Count']);
        $this->assertEqualsWithDelta(50.0, $longTossStats['hop0Pct'], 0.01);

        $this->assertCount(7, $response->json('data.recent_sessions'));
        $firstRecent = $response->json('data.recent_sessions.0');
        $this->assertArrayHasKey('id', $firstRecent);
        $this->assertArrayHasKey('type', $firstRecent);
        $this->assertArrayHasKey('mode', $firstRecent);
        $this->assertArrayHasKey('date', $firstRecent);
        $this->assertArrayHasKey('total_balls', $firstRecent);
        $this->assertArrayHasKey('is_completed', $firstRecent);
        $this->assertArrayHasKey('end_note', $firstRecent);
    }

    public function test_dashboard_summary_sets_cache_key(): void
    {
        $user = User::factory()->create(['type' => UserTypes::PLAYER->value]);
        Sanctum::actingAs($user, [UserTypes::PLAYER->value]);

        $cacheKey = PlayerDashboardSummaryService::cacheKey((string) $user->id);
        $this->assertFalse(Cache::has($cacheKey));

        $this->json('GET', 'api/player/dashboard-summary')->assertOk();

        $this->assertTrue(Cache::has($cacheKey));

        // A second request inside the TTL serves the cached payload: new rows
        // don't change the response until the cache expires.
        $practice = $this->makePractice($user->id, PracticeTypes::BATTING->value, PracticeModes::HIT_OR_PITCH->value);
        $this->makeBattingRow($user->id, $practice->id, ['velocity' => 90, 'quality_of_contact' => 'H', 'type_of_hit' => 'LD', 'field_direction' => 'LF']);

        $this->json('GET', 'api/player/dashboard-summary')
            ->assertOk()
            ->assertJsonPath('data.counts.batting', 0);
    }

    private function makePractice(string $userId, string $type, string $mode): Practice
    {
        return Practice::factory()->create([
            'user_id' => $userId,
            'type' => $type,
            'modes' => $mode,
        ]);
    }

    private function makeBattingRow(string $batterId, string $practiceId, array $attributes): BattingPracticeResult
    {
        return BattingPracticeResult::factory()->create(array_merge([
            'batter_id' => $batterId,
            'practice_id' => $practiceId,
            'is_contact' => true,
            'is_in_match' => false,
            'pitch_mark' => 1000,
        ], $attributes));
    }

    private function makeBullpenRow(string $pitcherId, string $practiceId, array $attributes): BullpenPracticeResult
    {
        return BullpenPracticeResult::factory()->create(array_merge([
            'pitcher_id' => $pitcherId,
            'practice_id' => $practiceId,
            'is_in_match' => false,
        ], $attributes));
    }
}
