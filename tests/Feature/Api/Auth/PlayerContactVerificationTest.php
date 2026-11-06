<?php
namespace Tests\Feature\Api\Auth;
use Tests\TestCase;
use App\Models\{User,Team,PlayerTeam,PlayerClaimChallenge};
use App\Services\Security\PlayerClaimCodeDelivery;
use Laravel\Sanctum\PersonalAccessToken;
class PlayerContactVerificationTest extends TestCase {
    private string $code='';
    private function roster($phone='5558675309') {
        $team=Team::factory()->create(['join_code'=>'ABC123']);
        $player=User::factory()->create(['type'=>'player','phone'=>$phone,'password'=>null,'status'=>true]);
        PlayerTeam::factory()->create(['user_id'=>$player->id,'team_id'=>$team->id,'actual'=>true]);
        $this->mock(PlayerClaimCodeDelivery::class,function($mock){$mock->shouldReceive('send')->andReturnUsing(function($contact,$code){$this->code=$code;});});
        return [$team,$player];
    }
    private function start() { return $this->postJson('/api/player/join',['contact'=>'5558675309','team_code'=>'ABC123'])->assertOk()->assertJsonMissingPath('data.token')->assertJsonMissingPath('data.profiles')->json('data.challenge_id'); }
    private function verify($id) {return $this->postJson('/api/player/claim/verify-contact',['challenge_id'=>$id,'code'=>$this->code]);}
    public function test_team_code_never_grants_access_and_verified_siblings_are_not_merged(): void {
        [$team,$one]=$this->roster();
        $two=User::factory()->create(['type'=>'player','phone'=>null,'password'=>null,'status'=>true]);
        PlayerTeam::factory()->create(['user_id'=>$two->id,'team_id'=>$team->id,'actual'=>true]);
        \Illuminate\Support\Facades\DB::table('player_claim_contacts')->insert(['team_id'=>$team->id,'user_id'=>$two->id,'contact_hash'=>hash_hmac('sha256',$one->phone,(string)config('app.key')),'created_at'=>now(),'updated_at'=>now()]);
        $id=$this->start();
        $this->assertSame(0,PersonalAccessToken::count());
        $challenge=PlayerClaimChallenge::findOrFail($id);
        $this->assertNotSame($this->code,$challenge->code_hash);
        $verified=$this->verify($id)->assertOk()->assertJsonCount(2,'data.profiles');
        $payload=['challenge_id'=>$id,'selection_token'=>$verified->json('data.selection_token'),'player_id'=>$two->id];
        $response=$this->postJson('/api/player/claim/confirm-profile',$payload)->assertOk();
        $this->assertSame($two->id,PersonalAccessToken::findToken($response->json('data.token'))->tokenable_id);
        $this->assertSame(0,$one->tokens()->count());
        $this->assertDatabaseCount('users',2);
        $this->postJson('/api/player/claim/confirm-profile',$payload)->assertUnprocessable();
    }
    public function test_wrong_codes_lock_after_five_attempts_and_expired_codes_fail(): void {
        $this->roster();$id=$this->start();
        for($i=0;$i<5;$i++)$this->postJson('/api/player/claim/verify-contact',['challenge_id'=>$id,'code'=>'000000'])->assertUnprocessable();
        $this->assertSame(5,(int)PlayerClaimChallenge::find($id)->attempts);
        $this->verify($id)->assertUnprocessable();
        $this->travel(61)->seconds();
        $id=$this->start();PlayerClaimChallenge::whereKey($id)->update(['expires_at'=>now()->subMinute()]);
        $this->verify($id)->assertUnprocessable();
        $this->assertSame(0,PersonalAccessToken::count());
    }
    public function test_confirmation_rechecks_membership_and_rejects_wrong_player_or_ticket(): void {
        [$team,$player]=$this->roster();$other=User::factory()->create();$id=$this->start();
        $v=$this->verify($id)->assertOk()->json('data.selection_token');
        $this->postJson('/api/player/claim/confirm-profile',['challenge_id'=>$id,'selection_token'=>$v,'player_id'=>$other->id])->assertUnprocessable();
        $this->postJson('/api/player/claim/confirm-profile',['challenge_id'=>$id,'selection_token'=>str_repeat('x',64),'player_id'=>$player->id])->assertUnprocessable();
        PlayerTeam::where('user_id',$player->id)->update(['actual'=>false]);
        $this->postJson('/api/player/claim/confirm-profile',['challenge_id'=>$id,'selection_token'=>$v,'player_id'=>$player->id])->assertUnprocessable();
        $this->assertSame(0,PersonalAccessToken::count());
    }
    public function test_delivery_failure_never_leaves_a_valid_challenge(): void {
        $this->roster();
        $this->mock(PlayerClaimCodeDelivery::class,fn($mock)=>$mock->shouldReceive('send')->andThrow(new \RuntimeException('secret provider error')));
        $response=$this->postJson('/api/player/join',['contact'=>'5558675309','team_code'=>'ABC123'])->assertUnprocessable();
        $this->assertStringNotContainsString('secret provider error',$response->getContent());
        $this->assertDatabaseCount('player_claim_challenges',0);
    }
}
