<?php
namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Models\{User, PlayerTeam, Team};
use App\Services\Security\{PlayerRecordTransfer, SecurityAuditLogger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash, Cache};
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class PlayerAccountConnectionController extends Controller
{
    private function fail(string $message): never { throw ValidationException::withMessages(['connection'=>$message]); }
    private function authorizeSource(User $coach, User $source, string $team): void {
        abort_unless($coach->type==='coach' && CoachUtils::isHeadCoach($coach->id,$team),403);
        if ($source->type!=='player' || filled($source->password) || !$source->status) $this->fail('Only an active, unclaimed roster profile can be connected.');
        $teams=PlayerTeam::where('user_id',$source->id)->where('actual',true)->pluck('team_id');
        if (!$teams->contains($team)) $this->fail('This profile is no longer on the selected team.');
        // A coach cannot give away another team's player records.
        foreach (PlayerTeam::withTrashed()->where('user_id',$source->id)->pluck('team_id')->unique() as $id) {
            if (!CoachUtils::isHeadCoach($coach->id,$id)) $this->fail('This profile has history with another team. Contact support to review the connection.');
        }
        foreach (['practice_line_ups'=>['user_id','practice_id','practices'], 'daily_plan_assignments'=>['user_id','plan_id','daily_plans']] as $table=>[$column,$parent,$parentTable]) {
            foreach (DB::table($parentTable)->whereIn('id',DB::table($table)->where($column,$source->id)->select($parent))->whereNotNull('team_id')->pluck('team_id')->unique() as $id) {
                if (!CoachUtils::isHeadCoach($coach->id,$id)) $this->fail('Some sessions or assignments belong to another team. Contact support to review the connection.');
            }
        }
        foreach (PlayerRecordTransfer::REFERENCES as $table=>$columns) {
            if (!app(PlayerRecordTransfer::class)->has($table,'team_id')) continue;
            foreach ($columns as $column) {
                if (!app(PlayerRecordTransfer::class)->has($table,$column)) continue;
                foreach (DB::table($table)->where($column,$source->id)->whereNotNull('team_id')->distinct()->pluck('team_id') as $id) {
                    if (!CoachUtils::isHeadCoach($coach->id,$id)) $this->fail('Some history belongs to another team. Contact support to review the connection.');
                }
            }
        }
    }
    public function candidates(Request $r) {
        abort_unless($r->user()->type==='coach',403);
        $v=$r->validate(['team_id'=>'required|uuid']);
        abort_unless(CoachUtils::isHeadCoach($r->user()->id,$v['team_id']),403);
        return ['data'=>User::with('profile')->where('type','player')->where('status',true)->where(function($q){$q->whereNull('password')->orWhere('password','');})
            ->whereHas('team_players',fn($q)=>$q->where('team_id',$v['team_id'])->where('actual',true))->get()->map(fn($p)=>['id'=>$p->id,'name'=>$this->name($p)])];
    }
    private function name(User $u): string { return trim(($u->profile?->first_name??'').' '.($u->profile?->last_name??'')) ?: 'Player'; }
    public function index(Request $r) {
        $u=$r->user(); abort_unless(in_array($u->type,['coach','player']),403);
        $rows=DB::table('player_account_connections')->where($u->type==='coach'?'coach_id':'target_id',$u->id)->orderByDesc('created_at')->limit(50)->get();
        return ['data'=>$rows->map(function($c){
            $source=User::withTrashed()->with('profile')->find($c->source_id);
            return ['id'=>$c->id,'source_name'=>$source?$this->name($source):'Player','team_name'=>Team::find($c->team_id)?->name,
                'status'=>$c->status==='pending' && now()->gt($c->expires_at)?'expired':$c->status,'expires_at'=>$c->expires_at];
        })];
    }
    public function store(Request $r) {
        $v=$r->validate(['source_id'=>'required|uuid','team_id'=>'required|uuid','email'=>'required|email']);
        return DB::transaction(function()use($r,$v){
            $source=User::lockForUpdate()->findOrFail($v['source_id']);
            $this->authorizeSource($r->user(),$source,$v['team_id']);
            $target=User::whereRaw('LOWER(email) = ?',[strtolower(trim($v['email']))])->where('type','player')->first();
            if (!$target || !filled($target->password) || $target->id===$source->id) $this->fail('Ask the player to create their own account first, then enter its exact email address.');
            DB::table('player_account_connections')->where('source_id',$source->id)->where('status','pending')->update(['status'=>'cancelled','updated_at'=>now()]);
            $id=(string)Str::uuid();
            DB::table('player_account_connections')->insert(['id'=>$id,'source_id'=>$source->id,'target_id'=>$target->id,'coach_id'=>$r->user()->id,'team_id'=>$v['team_id'],'status'=>'pending','expires_at'=>now()->addDays(7),'created_at'=>now(),'updated_at'=>now()]);
            app(SecurityAuditLogger::class)->record('player_connection.requested',$source->id,$v['team_id'],$r,['connection_id'=>$id]);
            return response()->json(['data'=>['id'=>$id,'message'=>'Request ready. Ask the player to sign in and open Player Account Connections to review it.']],201);
        });
    }
    private function requestRow(Request $r, string $id) {
        $c=DB::table('player_account_connections')->where('id',$id)->lockForUpdate()->first();
        abort_unless($c && ($c->target_id===$r->user()->id || $c->coach_id===$r->user()->id),404);
        return $c;
    }
    public function preview(Request $r,string $id) {
        $c=$this->requestRow($r,$id);
        abort_unless($c->target_id===$r->user()->id,403);
        abort_unless($c->status==='pending' && now()->lt($c->expires_at),409,'This connection is no longer pending.');
        $source=User::with('profile')->findOrFail($c->source_id);
        $counts=app(PlayerRecordTransfer::class)->preview($source->id);
        return ['data'=>['id'=>$id,'source_name'=>$this->name($source),'target_name'=>$this->name($r->user()),
            'teams'=>Team::whereIn('id',PlayerTeam::where('user_id',$source->id)->where('actual',true)->pluck('team_id'))->pluck('name'),
            'records'=>$counts,'preview_hash'=>$this->fingerprint($c),
            'notice'=>'These are my records. Keep my login and personal profile, move this roster history to my account, and archive the unclaimed profile. Original team ownership and video sharing stay unchanged.']];
    }
    private function fingerprint($c): string {
        // Counts alone are insufficient: consent is tied to both identities and memberships too.
        return hash_hmac('sha256',json_encode([$c->source_id,$c->target_id,app(PlayerRecordTransfer::class)->preview($c->source_id),
            PlayerTeam::withTrashed()->where('user_id',$c->source_id)->orderBy('team_id')->get(['team_id','actual','deleted_at'])->toArray()]),config('app.key'));
    }
    public function accept(Request $r,string $id) {
        $v=$r->validate(['password'=>'required|string','preview_hash'=>'required|string','confirmed'=>'accepted']);
        abort_unless($r->user()->type==='player' && Hash::check($v['password'],(string)$r->user()->password),403,'Sign in to your player account and confirm your password.');
        try { return DB::transaction(function()use($r,$id,$v){
            $c=$this->requestRow($r,$id); abort_unless($c->target_id===$r->user()->id,403);
            if ($c->status==='completed') return ['data'=>['status'=>'completed']];
            abort_unless($c->status==='pending' && now()->lt($c->expires_at),409,'This request expired or was cancelled. Ask your coach for a new request.');
            // Serialize account changes with claim and credential setup.
            $users=User::whereIn('id',[$c->source_id,$c->target_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source=$users->get($c->source_id); $target=$users->get($c->target_id);
            abort_unless($source && $target,409,'A profile is no longer available.');
            $coach=User::findOrFail($c->coach_id); $this->authorizeSource($coach,$source,$c->team_id);
            // Registered accounts predate claim-setup records; preserve their login/consent.
            app(\App\Services\Communications\InvitationEligibility::class)->assertPlayerAge($target);
            if (\App\Models\PlayerClaimSetup::find($target->id)) app(\App\Services\Consent\PlayerConsentService::class)->assertReady($target);
            if ($source->player?->born_date) {
                app(\App\Services\Communications\InvitationEligibility::class)->assertPlayerAge($source);
                if ($source->player->born_date !== $target->player?->born_date) $this->fail('The birth dates on these profiles differ. Contact support to verify the correct player before connecting.');
            }
            abort_unless(hash_equals($this->fingerprint($c),$v['preview_hash']),409,'The roster history changed. Refresh the preview before connecting.');
            $summary=app(PlayerRecordTransfer::class)->transfer($source->id,$target->id);
            foreach (PlayerTeam::withTrashed()->where('user_id',$source->id)->lockForUpdate()->get() as $link) {
                $existing=PlayerTeam::withTrashed()->where('user_id',$target->id)->where('team_id',$link->team_id)->lockForUpdate()->first();
                if ($existing) {
                    if (!$link->trashed() && $link->actual) { if ($existing->trashed()) $existing->restore(); $existing->update(['actual'=>true]); }
                    $link->delete();
                } else { $link->update(['user_id'=>$target->id]); }
                $team=$link->team_id; DB::afterCommit(fn()=>Cache::forget('roster_team_'.$team));
            }
            DB::afterCommit(function()use($source,$target) {
                foreach ([$source->id,$target->id] as $player) {
                    app(\App\Services\Development\PlayerDevelopmentDashboardCache::class)->forgetPlayer($player);
                    Cache::forget(\App\Services\Statistics\PlayerDashboardSummaryService::cacheKey($player));
                    foreach (PlayerTeam::withTrashed()->where('user_id',$player)->pluck('team_id') as $team) {
                        Cache::forget(\App\Services\Statistics\PlayerDashboardSummaryService::cacheKey($player,$team));
                    }
                }
            });
            $source->tokens()->delete();
            DB::table('account_claims')->where('user_id',$source->id)->delete();
            DB::table('team_invitations')->where('user_id',$source->id)->whereNull('accepted_at')->update(['revoked_at'=>now(),'token_hash'=>null,'claim_code_hash'=>null]);
            $source->update(['status'=>false]); $source->delete();
            DB::table('player_account_connections')->where('id',$id)->update(['status'=>'completed','completed_at'=>now(),'transfer_summary'=>json_encode($summary),'updated_at'=>now()]);
            DB::table('player_account_connections')->where('source_id',$source->id)->where('status','pending')->update(['status'=>'cancelled','updated_at'=>now()]);
            app(SecurityAuditLogger::class)->record('player_connection.completed',$target->id,$c->team_id,$r,['connection_id'=>$id,'source_id'=>$source->id,'counts'=>$summary]);
            return ['data'=>['status'=>'completed','message'=>'Your roster history is connected. Refresh your sessions and roster.']];
        }); } catch (QueryException $e) {
            if (in_array((string)$e->getCode(),['23000','23505'])) $this->fail('Both profiles contain overlapping records. Nothing was transferred. Contact support to reconcile the records.');
            throw $e;
        }
    }
    public function cancel(Request $r,string $id) {
        return DB::transaction(function()use($r,$id){$c=$this->requestRow($r,$id);
            abort_unless($c->status==='pending',409);
            DB::table('player_account_connections')->where('id',$id)->update(['status'=>$r->user()->id===$c->target_id?'declined':'cancelled','updated_at'=>now()]);
            return ['data'=>['status'=>'cancelled']];
        });
    }
}
