<?php
namespace Tests\Concerns;
trait VerifiesPlayerContact {
    protected function claimVerifiedTeam(array $payload) {
        $code=null;
        $this->mock(\App\Services\Security\PlayerClaimCodeDelivery::class, function($mock) use (&$code) {
            $mock->shouldReceive('send')->once()->andReturnUsing(function($contact,$sent) use (&$code) {$code=$sent;});
        });
        $start=$this->postJson('/api/player/join',$payload)->assertOk()->assertJsonMissingPath('data.token');
        $id=$start->json('data.challenge_id');
        $verified=$this->postJson('/api/player/claim/verify-contact',['challenge_id'=>$id,'code'=>$code])->assertOk();
        return $this->postJson('/api/player/claim/confirm-profile',['challenge_id'=>$id,'selection_token'=>$verified->json('data.selection_token'),'player_id'=>$verified->json('data.profiles.0.id')]);
    }
}
