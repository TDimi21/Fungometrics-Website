<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\{User,Team,CoachTeam,PlayerTeam,Practice,LiveABPracticeResult};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebLiveABSessionTest extends TestCase
{
    public function test_finalization_notifies_each_player_once_without_copying_results(): void
    {
        config(['communications.enabled' => false, 'communications.frontend_url' => 'https://app.fmtrx.com']);
        [$id, $config] = $this->setupSession();
        $state = $this->getJson('/api/coach/liveab-session/'.$id)->assertOk()->json('data.state');
        $this->postJson('/api/coach/liveab-session/'.$id.'/pitch', $this->pitch($id, $config, $state))->assertCreated();
        foreach ([true, true, false, true] as $completed) {
            $this->putJson('/api/training/'.$id, ['is_completed' => $completed, 'end_note' => 'Game complete'])->assertOk();
        }
        $this->assertSame(1, LiveABPracticeResult::where('practice_id', $id)->count());
        $this->assertSame(1, Practice::find($id)->batting()->count());
        $this->assertSame(1, Practice::find($id)->bullpen()->count());
        $this->assertSame(1, \App\Models\CommunicationEvent::where('resource_id', $id)->where('event_type', 'LiveABCompleted')->count());
        $deliveries = \App\Models\CommunicationDelivery::where('resource_id', $id)->get();
        $this->assertCount(2, $deliveries);
        \Illuminate\Support\Facades\Notification::fake();
        config(['communications.enabled' => true, 'communications.send_enabled' => true]);
        $delivery = $deliveries->first();
        \App\Models\CommunicationPreference::updateOrCreate(['user_id' => $delivery->recipient_id], ['team_announcements' => false]);
        (new \App\Jobs\SendCommunicationDelivery($delivery->id))->handle(app(\App\Services\Communications\CommunicationContent::class));
        $this->assertSame('preference_disabled', $delivery->fresh()->failure_code);
        \Illuminate\Support\Facades\Notification::assertNothingSent();

        foreach ($deliveries as $delivery) {
            $this->assertStringContainsString('/session/report/'.$id.'/live_ab?player='.$delivery->recipient_id, $delivery->payload['action_url']);
        }
        $player = User::find($config['away']['hitters'][0]);
        Sanctum::actingAs($player, ['player']);
        $this->getJson('/api/me/communications')->assertOk()->assertJsonCount(1, 'data');
        $player->update(['subscription_plan' => 'player_pro']);
        $this->getJson('/api/statistics/'.$id.'/liveab')->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.game_config', null);
        $this->putJson('/api/training/'.$id, ['is_completed' => false, 'end_note' => 'Not allowed'])->assertForbidden();
        $this->postJson('/api/coach/liveab-session/'.$id.'/state', [])->assertForbidden();
        $this->postJson('/api/result/liveab', [])->assertForbidden();
    }

    public function test_liveab_cannot_be_a_player_assignment_or_template_session(): void
    {
        $registry = new \App\Services\Planner\LinkedSessionRegistry();
        $this->assertArrayNotHasKey('live_ab', $registry->all());
        $legacy = $registry->item(['metadata' => ['session_type' => 'live_ab']], 'throwing');
        $this->assertSame('READ_ONLY', $legacy['execution_type']);
        $this->assertNull($legacy['linked_session']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Services\Planner\PlayerAssignmentRules::validate([['items' => [['metadata' => ['session_type' => 'live_ab']]]]]);
    }

    private function setupSession(): array
    {
        $coach = User::factory()->create(['type' => 'coach','subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($coach, ['coach']);
        $sides = [];
        foreach(['away','home'] as $side) {
            $team = Team::factory()->create();
            CoachTeam::factory()->create(['coach_id' => $coach->id,'team_id' => $team->id]);
            $player = User::factory()->create(['type' => 'player']);
            PlayerTeam::factory()->create(['user_id' => $player->id,'team_id' => $team->id,'actual' => true]);
            $sides[$side] = ['team_id' => $team->id,'hitters' => [$player->id],'pitchers' => [$player->id]];
        }
        $config = ['mode' => 'game','name' => 'Game','innings' => 7,'unlimited' => false,'continuous' => false] + $sides;
        $payload = ['type' => 'L','note' => 'Game','teams' => ['a' => $sides['away']['team_id'],'b' => $sides['home']['team_id']],'players' => ['a' => [['id' => $sides['away']['hitters'][0],'sort' => 0]],'b' => [['id' => $sides['home']['pitchers'][0],'sort' => 0]]],'liveab_config' => $config];
        $id = $this->postJson('/api/coach/trainingab', $payload)->assertCreated()->json('data.id');
        return [$id,$config];
    }
    private function pitch(string $id, array $config, array $state): array
    {
        unset($state['revision']);
        $state['pitches']++;
        $state['strikes'] = 1;
        return ['practice_id' => $id,'client_id' => (string)Str::uuid(),'revision' => 0,'state' => $state,
            'turn' => ['turn' => 1,'pitches' => 1,'strike' => 0,'ball' => 0,'is_over' => false],'bases' => 0,'pitch_mark' => 1800,'pitch_location' => 'MC','zone' => 'S','type_of_hit' => 'TK','is_contact' => false,
            'batting' => ['team_id' => $config['away']['team_id'],'batter_id' => $config['away']['hitters'][0],'quality_of_contact' => 'N','field_mark' => null,'field_direction' => null,'velocity' => 0],
            'pitching' => ['team_id' => $config['home']['team_id'],'pitcher_id' => $config['home']['pitchers'][0],'miles_per_hour' => 90,'type_throw' => 'FB']];
    }
    public function test_pitch_resume_retry_conflict_and_undo_are_atomic(): void
    {
        [$id,$config] = $this->setupSession();
        $url = '/api/coach/liveab-session/'.$id;
        $data = $this->getJson($url)->assertOk()->assertJsonPath('data.config.mode', 'game')->assertJsonCount(2, 'data.players.batters')->json('data');
        $payload = $this->pitch($id, $config, $data['state']);
        $this->postJson($url.'/pitch', $payload)->assertCreated()->assertJsonPath('data.state.strikes', 1)->assertJsonCount(1, 'data.rows');
        $this->postJson($url.'/pitch', $payload)->assertOk()->assertJsonCount(1, 'data.rows');
        $payload['client_id'] = (string)Str::uuid();
        $this->postJson($url.'/pitch', $payload)->assertStatus(409);
        $this->getJson($url)->assertOk()->assertJsonPath('data.state.revision', 1)->assertJsonPath('data.state.pitches', 1);
        $this->postJson($url.'/undo', ['revision' => 1])->assertOk()->assertJsonPath('data.state.strikes', 0)->assertJsonPath('data.state.pitches', 0)->assertJsonCount(0, 'data.rows');
        $this->assertSame(0, LiveABPracticeResult::where('practice_id', $id)->count());
        $this->assertSame(0, Practice::find($id)->batting()->count());
        $this->assertSame(0, Practice::find($id)->bullpen()->count());
    }
    public function test_play_outcome_is_saved_and_unauthorized_users_are_blocked(): void
    {
        [$id,$config] = $this->setupSession();
        $url = '/api/coach/liveab-session/'.$id;
        $state = $this->getJson($url)->assertOk()->json('data.state');
        $payload = $this->pitch($id, $config, $state);
        $payload['type_of_hit'] = 'GB';
        $payload['is_contact'] = true;
        $payload['play_result'] = 'SINGLE';
        $payload['bases'] = 1;
        $payload['runs_scored'] = 1;
        $payload['rbi'] = 1;
        $payload['outs_recorded'] = 0;
        $payload['is_safe'] = true;
        $this->postJson($url.'/pitch', $payload)->assertCreated()->assertJsonPath('data.rows.0.play_result', 'SINGLE');
        $this->getJson('/api/statistics/'.$id.'/liveab')->assertOk()->assertJsonPath('data.ball_x_ball.0.play_result', 'SINGLE');
        $other = User::factory()->create(['type' => 'coach','subscription_plan' => 'coach_pro']);
        Sanctum::actingAs($other, ['coach']);
        $this->getJson($url)->assertNotFound();
        $this->postJson($url.'/undo', ['revision' => 1])->assertNotFound();
        Sanctum::actingAs(User::factory()->create(['type' => 'player','subscription_plan' => 'player_pro']), ['player']);
        $this->getJson($url)->assertForbidden();
    }
    public function test_invalid_state_rolls_back_both_result_records_and_completed_sessions_block_writes(): void
    {
        [$id,$config] = $this->setupSession();
        $url = '/api/coach/liveab-session/'.$id;
        $state = $this->getJson($url)->assertOk()->json('data.state');
        $payload = $this->pitch($id, $config, $state);
        $payload['state']['pitches'] = 8;
        $this->postJson($url.'/pitch', $payload)->assertStatus(422);
        $this->assertSame(0, Practice::find($id)->live()->count());
        $this->assertSame(0, Practice::find($id)->batting()->count());
        $this->assertSame(0, Practice::find($id)->bullpen()->count());
        Practice::find($id)->update(['is_completed' => true]);
        $this->postJson($url.'/pitch', $this->pitch($id, $config, $state))->assertStatus(409);
    }

    public function test_runner_state_and_roster_additions_survive_reload_and_undo(): void
    {
        [$id,$config] = $this->setupSession();
        $url = '/api/coach/liveab-session/'.$id;
        $state = $this->getJson($url)->assertOk()->json('data.state');
        unset($state['revision']);
        $state['runners'] = [$config['away']['hitters'][0],null,null];
        $this->postJson($url.'/state', ['state' => $state,'revision' => 0,'client_id' => (string)Str::uuid()])->assertOk()->assertJsonPath('data.state.runners.0', $config['away']['hitters'][0]);
        $this->postJson($url.'/undo', ['revision' => 1])->assertOk()->assertJsonPath('data.state.runners', [null,null,null]);
        $player = User::factory()->create(['type' => 'player']);
        $body = ['revision' => 2,'side' => 'away','role' => 'hitters','player_id' => $player->id];
        $this->postJson($url.'/lineup', $body)->assertStatus(422);
        PlayerTeam::factory()->create(['user_id' => $player->id,'team_id' => $config['away']['team_id'],'actual' => true]);
        $this->postJson($url.'/lineup', $body)->assertOk()->assertJsonPath('data.config.away.hitters.1', $player->id);
        $this->postJson($url.'/lineup', $body)->assertOk()->assertJsonCount(2, 'data.config.away.hitters');
        $this->getJson($url)->assertOk()->assertJsonPath('data.state.revision', 3);
    }

}
