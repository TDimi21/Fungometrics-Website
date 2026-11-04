<?php
namespace Tests\Feature\Api\Training;

use App\Models\{User, Profile, Player, Practice, Team, LongTossPractice, ExitVelocityPractice, WeightBallPractice};
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineWorkspaceTest extends TestCase
{
    private function player(): User
    {
        $user = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        Profile::factory()->create(['user_id' => $user->id]);
        Player::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['player']);
        return $user;
    }
    public static function modes(): array { return [['LT', 'longtoss', LongTossPractice::class, ['distance' => 150, 'hop' => 0]], ['EV', 'exitvelocity', ExitVelocityPractice::class, ['velocity' => 80, 'trajectory' => 'LD']], ['WB', 'weightball', WeightBallPractice::class, ['velocity' => 75, 'weight' => 5]]]; }
    /** @dataProvider modes */
    public function test_create_score_finish_and_lost_response_retries(string $mode, string $endpoint, string $model, array $score): void
    {
        $user = $this->player();
        $this->getJson('/api/offline/capabilities')->assertOk()->assertJsonPath('data.version', 1);
        $recorded = now()->subDays(2)->startOfSecond();
        $payload = ['offline_recorded_at' => $recorded->toIso8601String(), 'type' => 'T', 'modes' => $mode, 'note' => 'Offline test', 'players' => [['id' => $user->id, 'sort' => 0]]];
        $headers = ['X-Offline-Request-ID' => 'offline_create_12345'];
        $first = $this->postJson('/api/training', $payload, $headers)->assertCreated()->assertJsonPath('offline_receipt', 'offline_create_12345');
        $id = $first->json('data.id');
        $this->postJson('/api/training', $payload, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Practice::where('user_id', $user->id)->count());
        $this->postJson('/api/training', array_replace($payload, ['note' => 'changed']), $headers)->assertStatus(409);
        $this->assertSame($recorded->toDateTimeString(), Practice::findOrFail($id)->started);
        $score += ['offline_recorded_at' => $recorded->copy()->addMinute()->toIso8601String(), 'practice_id' => $id, 'user_id' => $user->id, 'set' => 1];
        $headers = ['X-Offline-Request-ID' => 'offline_score_12345'];
        $this->postJson('/api/result/'.$endpoint, $score, $headers)->assertCreated();
        $this->postJson('/api/result/'.$endpoint, $score, $headers)->assertCreated();
        $this->assertSame(1, $model::where('practice_id', $id)->count());
        $headers = ['X-Offline-Request-ID' => 'offline_finish_12345'];
        $end = ['offline_recorded_at' => $recorded->copy()->addHour()->toIso8601String(), 'is_completed' => true, 'end_note' => 'Done'];
        $this->putJson('/api/training/'.$id, $end, $headers)->assertOk();
        $this->putJson('/api/training/'.$id, $end, $headers)->assertOk();
        $this->assertTrue((bool) Practice::findOrFail($id)->is_completed);
        $this->assertSame($recorded->copy()->addHour()->toDateTimeString(), Practice::findOrFail($id)->finished);
        $this->assertSame($recorded->copy()->addMinute()->toDateTimeString(), $model::where('practice_id', $id)->first()->created_at->toDateTimeString());
        $this->assertSame(3, DB::table('offline_write_receipts')->where('owner_id', $user->id)->count());
    }
    public function test_failed_validation_has_no_receipt_and_cross_account_replay_is_denied(): void
    {
        $user = $this->player();
        $practice = Practice::factory()->create(['user_id' => $user->id, 'team_id' => null, 'type' => 'T', 'modes' => 'LT']);
        $headers = ['X-Offline-Request-ID' => 'offline_test_12345'];
        $payload = ['practice_id' => $practice->id, 'user_id' => $user->id, 'distance' => 501];
        $this->postJson('/api/result/longtoss', $payload, $headers)->assertStatus(422);
        $this->assertSame(0, DB::table('offline_write_receipts')->count());
        $payload['distance'] = 150;
        $this->postJson('/api/result/longtoss', $payload, $headers)->assertCreated();
        $this->player();
        $this->postJson('/api/result/longtoss', $payload, $headers)->assertNotFound();
        $this->assertSame(1, LongTossPractice::where('practice_id', $practice->id)->count());
    }
    public function test_coach_can_upload_team_and_player_without_logo_or_duplicate_creation(): void
    {
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($coach, ['coach']);
        $headers = ['X-Offline-Request-ID' => 'offline_team_123456'];
        $team = ['name' => 'Offline Team', 'zip' => '30004', 'state' => 'GA'];
        $id = $this->postJson('/api/coach/add/teams', $team, $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/coach/add/teams', $team, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Team::where('name', 'Offline Team')->count());
        $policy = app(\App\Services\Consent\PlayerConsentService::class)->policies();
        $player = ['team' => $id, 'name' => ['first' => 'Offline', 'last' => 'Player'], 'email' => 'offline@example.com', 'policy_accepted' => true,
            'terms_version' => $policy['terms_version'], 'privacy_version' => $policy['privacy_version'], 'acknowledgment_version' => $policy['acknowledgment_version']];
        $headers = ['X-Offline-Request-ID' => 'offline_player_12345'];
        $playerId = $this->postJson('/api/coach/add/players', $player, $headers)->assertOk()->json('data.id');
        $this->postJson('/api/coach/add/players', $player, $headers)->assertOk()->assertJsonPath('data.id', $playerId);
        $this->assertSame(1, User::where('email', 'offline@example.com')->count());
    }
}
