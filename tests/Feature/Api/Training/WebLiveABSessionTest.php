<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Training;

use App\Models\{User,Team,CoachTeam,PlayerTeam,Practice,LiveABPracticeResult};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebLiveABSessionTest extends TestCase
{
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
