<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Security;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\CoachTeam;
use App\Models\Concerns\PracticeTypes;
use App\Models\Concerns\PracticeModes;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\Team;
use App\Models\User;
use App\Models\WeightBallPractice;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResultScopeAuthorizationTest extends TestCase
{
    public static function resultTypes(): array
    {
        return [
            'batting' => ['batting', BattingPracticeResult::class],
            'bullpen' => ['bullpen', BullpenPracticeResult::class],
            'cage' => ['cage', CagePracticeResult::class],
            'liveab' => ['liveab', LiveABPracticeResult::class],
            'longtoss' => ['longtoss', LongTossPractice::class],
            'exitvelocity' => ['exitvelocity', ExitVelocityPractice::class],
            'weightball' => ['weightball', WeightBallPractice::class],
        ];
    }

    /** @dataProvider resultTypes */
    public function test_unrelated_account_cannot_create_read_or_edit_results(string $type, string $model): void
    {
        $owner = User::factory()->create(['type' => 'coach']);
        $stranger = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $foreign = $this->practice($owner);
        $own = $this->practice($stranger);
        // Only the target session matters to these denial checks; no result
        // payload validation or downstream controller should be reached.
        $result = $model::factory()->create(['practice_id' => $foreign->id]);
        $before = $result->fresh()->getAttributes();
        $count = $model::count();
        Sanctum::actingAs($stranger, ['coach']);

        $this->postJson("/api/result/{$type}", ['practice_id' => $foreign->id])->assertNotFound();
        $this->getJson("/api/result/{$type}/{$result->id}")->assertNotFound();
        $this->putJson("/api/result/{$type}/{$result->id}", ['practice_id' => $own->id])->assertNotFound();

        $this->assertSame($count, $model::count());
        $this->assertSame($before, $result->fresh()->getAttributes());
    }

    public function test_owner_can_create_read_and_edit_bullpen_results(): void
    {
        $owner = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $practice = $this->practice($owner);
        Sanctum::actingAs($owner, ['coach']);

        $saved = $this->postJson('/api/result/bullpen', $this->pitch($practice))->assertCreated();
        $id = $saved->json('data.id');
        $this->getJson("/api/result/bullpen/{$id}")->assertOk();
        $this->putJson("/api/result/bullpen/{$id}", [
            'practice_id' => $practice->id,
            'type_throw' => 'FB', 'pitch_side' => 'MC',
            'miles_per_hour' => 82,
        ])->assertOk();
        $this->assertDatabaseHas('bullpen_practice_results', ['id' => $id, 'miles_per_hour' => 82]);
    }

    public function test_team_head_and_assistant_coaches_can_record_results(): void
    {
        $owner = User::factory()->create(['type' => 'coach']);
        $team = Team::factory()->create();
        $practice = $this->practice($owner, $team);
        foreach ([true, false] as $main) {
            $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
            CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $team->id, 'is_main' => $main]);
            Sanctum::actingAs($coach, ['coach']);
            $this->postJson('/api/result/bullpen', $this->pitch($practice))->assertCreated();
        }
    }

    public function test_edit_cannot_reparent_result_even_to_another_owned_session(): void
    {
        $owner = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $practice = $this->practice($owner);
        $other = $this->practice($owner);
        $result = BullpenPracticeResult::factory()->create(['practice_id' => $practice->id]);
        Sanctum::actingAs($owner, ['coach']);

        foreach ([$other->id, null] as $replacement) {
            $this->putJson("/api/result/bullpen/{$result->id}", [
                'practice_id' => $replacement, 'type_throw' => 'FB', 'pitch_side' => 'MC',
            ])->assertUnprocessable();
        }
        $this->assertSame($practice->id, $result->fresh()->practice_id);
    }

    public function test_deleted_practice_and_former_membership_do_not_grant_access(): void
    {
        $owner = User::factory()->create(['type' => 'coach']);
        $team = Team::factory()->create();
        $practice = $this->practice($owner, $team);
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        PlayerTeam::factory()->create(['user_id' => $player->id, 'team_id' => $team->id, 'actual' => false]);
        $result = BullpenPracticeResult::factory()->create(['practice_id' => $practice->id]);
        Sanctum::actingAs($player, ['player']);
        $this->postJson('/api/result/bullpen', $this->pitch($practice))->assertNotFound();
        $this->getJson("/api/result/bullpen/{$result->id}")->assertNotFound();

        Sanctum::actingAs($owner, ['coach']);
        $practice->delete();
        $this->getJson("/api/result/bullpen/{$result->id}")->assertNotFound();
        $this->postJson('/api/result/bullpen', $this->pitch($practice))->assertNotFound();
    }

    public function test_missing_or_malformed_practice_id_does_not_reach_a_write(): void
    {
        $user = User::factory()->create(['type' => 'coach']);
        Sanctum::actingAs($user, ['coach']);
        $this->postJson('/api/result/bullpen', [])->assertUnprocessable();
        $this->postJson('/api/result/bullpen', ['practice_id' => ['invalid']])->assertUnprocessable();
        $this->postJson('/api/result/bullpen', ['practice_id' => 'missing-session'])->assertNotFound();
        $this->assertSame(0, BullpenPracticeResult::count());
    }

    private function practice(User $owner, ?Team $team = null): Practice
    {
        return Practice::factory()->create([
            'user_id' => $owner->id, 'team_id' => $team?->id,
            'type' => PracticeTypes::BULLPEN->value, 'modes' => PracticeModes::HIT_OR_PITCH->value, 'is_scripted' => false,
        ]);
    }

    private function pitch(Practice $practice): array
    {
        return ['practice_id' => $practice->id, 'pitcher_id' => $practice->user_id, 'type_throw' => 'FB', 'pitch_side' => 'MC', 'miles_per_hour' => 80, 'zone' => 'S'];
    }
}
