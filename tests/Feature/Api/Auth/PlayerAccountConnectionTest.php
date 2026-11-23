<?php
namespace Tests\Feature\Api\Auth;
use Tests\TestCase;
use App\Models\{User,Player,Team,Practice,LongTossPractice,PlayerTeam};
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Services\Security\PlayerRecordTransfer;

class PlayerAccountConnectionTest extends TestCase
{
    private $coach; private $source; private $target; private $team;
    protected function setUp(): void {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->coach=User::factory()->create(['type'=>'coach']);
        $this->source=User::factory()->create(['type'=>'player','password'=>null,'status'=>true]);
        $this->target=User::factory()->create(['type'=>'player','password'=>Hash::make('testPassword123')]);
        Player::factory()->create(['user_id'=>$this->target->id,'born_date'=>'2000-01-01']);
        $this->team=Team::factory()->create();
        $this->grantTeamAccess($this->coach,$this->team);$this->grantTeamAccess($this->source,$this->team);
    }
    private function createRequest(): string {
        Sanctum::actingAs($this->coach,['coach']);
        return $this->postJson('/api/player-account-connections',['source_id'=>$this->source->id,'team_id'=>$this->team->id,'email'=>$this->target->email])->assertCreated()->json('data.id');
    }
    private function approval(string $id): array {
        Sanctum::actingAs($this->target,['player']);
        $hash=$this->getJson("/api/player-account-connections/$id/preview")->assertOk()->json('data.preview_hash');
        return ['password'=>'testPassword123','confirmed'=>true,'preview_hash'=>$hash];
    }
    public function test_transfers_history_and_json_without_changing_login_or_team_scope(): void {
        $this->grantTeamAccess($this->target,$this->team);
        $practice=Practice::factory()->create(['team_id'=>$this->team->id,'user_id'=>$this->coach->id,
            'liveab_state'=>['hitter'=>$this->source->id,'scores'=>[0,0,1],'other'=>$this->target->id]]);
        $result=LongTossPractice::factory()->create(['practice_id'=>$practice->id,'team_id'=>$this->team->id,'user_id'=>$this->source->id]);
        $private=Practice::factory()->create(['user_id'=>$this->target->id,'team_id'=>null,'is_player_shared'=>true]);
        $original=$this->target->password;
        $id=$this->createRequest(); $approval=$this->approval($id);
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertOk();
        $this->assertDatabaseHas('long_toss_practices',['id'=>$result->id,'user_id'=>$this->target->id,'team_id'=>$this->team->id]);
        $state=json_decode(DB::table('practices')->where('id',$practice->id)->value('liveab_state'),true);
        $this->assertSame($this->target->id,$state['hitter']);$this->assertSame([0,0,1],$state['scores']);
        $this->assertSame($original,$this->target->fresh()->password);
        $this->assertDatabaseHas('practices',['id'=>$private->id,'user_id'=>$this->target->id,'team_id'=>null]);
        $this->assertSoftDeleted('users',['id'=>$this->source->id]);
        $this->assertSame(1,PlayerTeam::where('user_id',$this->target->id)->where('team_id',$this->team->id)->count());
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertOk();
        // Old clients cannot attach fresh results to the archived identity.
        Sanctum::actingAs($this->coach,['coach']);
        $this->postJson('/api/edit/players/'.$this->source->id,[])->assertStatus(409)->assertJsonPath('code','PLAYER_PROFILE_CONNECTED');
    }
    public function test_coach_cannot_accept_and_other_player_cannot_view_request(): void {
        $id=$this->createRequest();
        $this->postJson("/api/player-account-connections/$id/accept",['password'=>'testPassword123','confirmed'=>true,'preview_hash'=>'x'])->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['type'=>'player']),['player']);
        $this->getJson("/api/player-account-connections/$id/preview")->assertNotFound();
    }
    public function test_wrong_password_and_changed_preview_do_not_transfer(): void {
        $id=$this->createRequest();$approval=$this->approval($id);
        $this->postJson("/api/player-account-connections/$id/accept",array_replace($approval,['password'=>'wrong']))->assertForbidden();
        $p=Practice::factory()->create(['team_id'=>$this->team->id]);
        LongTossPractice::factory()->create(['practice_id'=>$p->id,'team_id'=>$this->team->id,'user_id'=>$this->source->id]);
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertStatus(409);
        $this->assertNotNull(User::find($this->source->id));
    }
    public function test_declined_expired_and_claimed_profiles_are_not_merged(): void {
        $id=$this->createRequest();$approval=$this->approval($id);
        $this->postJson("/api/player-account-connections/$id/cancel")->assertOk();
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertStatus(409);
        $id=$this->createRequest();$approval=$this->approval($id);
        DB::table('player_account_connections')->where('id',$id)->update(['expires_at'=>now()->subMinute()]);
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertStatus(409);
        $id=$this->createRequest();$approval=$this->approval($id);
        $this->source->update(['password'=>Hash::make('newPassword123')]);
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertUnprocessable();
    }
    public function test_other_team_history_requires_review(): void {
        $this->grantTeamAccess($this->source,Team::factory()->create());
        Sanctum::actingAs($this->coach,['coach']);
        $this->postJson('/api/player-account-connections',['source_id'=>$this->source->id,'team_id'=>$this->team->id,'email'=>$this->target->email])->assertUnprocessable();
    }
    public function test_unique_conflict_rolls_back_all_changes(): void {
        $p=Practice::factory()->create(['team_id'=>$this->team->id]);
        $result=LongTossPractice::factory()->create(['practice_id'=>$p->id,'team_id'=>$this->team->id,'user_id'=>$this->source->id]);
        foreach([$this->source,$this->target] as $user) DB::table('player_daily_readiness')->insert(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'date'=>'2026-11-20','timezone'=>'America/New_York','answers'=>'{}','created_at'=>now(),'updated_at'=>now()]);
        $id=$this->createRequest();$approval=$this->approval($id);
        $this->postJson("/api/player-account-connections/$id/accept",$approval)->assertUnprocessable();
        $this->assertDatabaseHas('long_toss_practices',['id'=>$result->id,'user_id'=>$this->source->id]);
        $this->assertNotNull(User::find($this->source->id));
        $this->assertDatabaseHas('player_account_connections',['id'=>$id,'status'=>'pending']);
    }
    public function test_json_map_collision_is_not_silently_overwritten(): void {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PlayerRecordTransfer::class)->replace([$this->source->id=>['count'=>1],$this->target->id=>['count'=>2]],$this->source->id,$this->target->id);
    }
}
