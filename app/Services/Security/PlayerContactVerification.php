<?php
namespace App\Services\Security;
use App\Models\{PlayerClaimChallenge,Team,User};
use Illuminate\Support\Facades\{DB,Hash,RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlayerContactVerification {
    private function fail(string $message='Use the contact your coach entered, or ask your coach to update it.'): never {
        throw ValidationException::withMessages(['claim'=>$message]);
    }
    public function normalize(string $contact): string {
        if (\App\Support\TestPhoneNumber::matches($contact)) $this->fail('Use your email address; the test phone number cannot receive verification codes.');
        $contact=trim($contact);
        if (filter_var($contact,FILTER_VALIDATE_EMAIL)) return strtolower($contact);
        if (!preg_match('/^[+()\d\s.-]+$/',$contact)) $this->fail();
        $digits=preg_replace('/\D/','',$contact);
        if (strlen($digits)<10 || strlen($digits)>15) $this->fail();
        return strlen($digits)===11 && str_starts_with($digits,'1') ? substr($digits,1) : $digits;
    }
    private function phoneMatches(string $phone,string $contact): bool {
        try { return $this->normalize($phone)===$contact; }
        catch (ValidationException $e) { return false; }
    }
    private function matches(string $team, string $contact) {
        $familyIds=DB::table('player_claim_contacts')->where('team_id',$team)
            ->where('contact_hash',hash_hmac('sha256',$contact,(string)config('app.key')))->pluck('user_id')->all();
        return User::with('profile')->where('type','player')->where('status',true)
            ->whereHas('team_players',fn($q)=>$q->where('team_id',$team)->where('actual',true))->get()
            ->filter(fn($user)=>in_array($user->id,$familyIds,true) || (str_contains($contact,'@') ? strtolower((string)$user->email)===$contact : $this->phoneMatches((string)$user->phone,$contact)));
    }
    public function start(string $contact, string $teamCode): array {
        $contact=$this->normalize($contact);
        $team=Team::where('join_code',strtoupper(trim($teamCode)))->first();
        $hash=hash_hmac('sha256',$contact,(string)config('app.key'));
        $key='player-claim-contact:'.$hash;
        if (RateLimiter::tooManyAttempts($key,3)) $this->fail('Too many verification requests. Wait a few minutes before trying again.');
        RateLimiter::hit($key,600);
        if (!$team || $this->matches($team->id,$contact)->isEmpty()) $this->fail();
        $code=(string)random_int(100000,999999);
        $challenge=PlayerClaimChallenge::create(['team_id'=>$team->id,'contact'=>$contact,'contact_hash'=>$hash,'code_hash'=>Hash::make($code),'expires_at'=>now()->addMinutes(10)]);
        try { app(PlayerClaimCodeDelivery::class)->send($contact,$code); }
        catch (\Throwable $e) { $challenge->delete(); $this->fail('We could not send your verification code. Try again when connected, or ask your coach to check the contact.'); }
        return ['next_action'=>'verify_contact','challenge_id'=>$challenge->id,'delivery'=>str_contains($contact,'@')?'email':'sms','expires_in'=>600];
    }
    public function verify(string $id,string $code): array {
        // Return errors outside the transaction so failed-attempt counters commit.
        $result=DB::transaction(function() use($id,$code) {
            $c=PlayerClaimChallenge::lockForUpdate()->find($id);
            if (!$c || $c->used_at || $c->verified_at || $c->expires_at->isPast() || $c->attempts>=5) return null;
            $c->increment('attempts');
            if (!Hash::check($code,$c->code_hash)) return null;
            $ticket=Str::random(64);
            $c->update(['verified_at'=>now(),'selection_hash'=>hash('sha256',$ticket)]);
            $players=$this->matches($c->team_id,$c->contact)->map(fn($p)=>['id'=>$p->id,'name'=>trim(($p->profile?->first_name ?? '').' '.($p->profile?->last_name ?? '')) ?: 'Player','connected'=>filled($p->password)])->values()->all();
            return ['selection_token'=>$ticket,'profiles'=>$players,'team_name'=>Team::find($c->team_id)?->name];
        });
        if (!$result) $this->fail('The verification code is incorrect, expired or already used. Request a new code if needed.');
        return $result;
    }
    public function confirm(string $id,string $ticket,string $playerId): array {
        return DB::transaction(function() use($id,$ticket,$playerId) {
            $c=PlayerClaimChallenge::lockForUpdate()->find($id);
            if (!$c || !$c->verified_at || $c->used_at || $c->expires_at->isPast() || !hash_equals((string)$c->selection_hash,hash('sha256',$ticket))) $this->fail('Verification expired. Find your profile again.');
            if (!$this->matches($c->team_id,$c->contact)->contains('id',$playerId)) $this->fail();
            $player=User::lockForUpdate()->findOrFail($playerId);
            $c->update(['used_at'=>now()]);
            app(SecurityAuditLogger::class)->record('player_claim.contact_verified', $player->id, $c->team_id, request());
            if (filled($player->password)) return ['next_action'=>'sign_in','team_code'=>Team::find($c->team_id)?->join_code];
            $player->tokens()->where('name','like','profile_claim%')->delete();
            $token=$player->createToken('profile_claim:'.$c->team_id,['profile-claim'],now()->addMinutes(10));
            return ['next_action'=>'setup','token'=>$token->plainTextToken];
        });
    }
}
