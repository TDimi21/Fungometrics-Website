<?php
namespace App\Http\Controllers\Api\LongToss;

use App\Http\Controllers\Controller;
use App\Models\{BullpenPracticeResult, CoachTeam, LongTossGuide, LongTossPlan, LongTossPractice, PlayerTeam, Practice, PracticeLineUp, User};
use App\Services\LongToss\GuidanceEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuidedLongTossController extends Controller
{
    public function __construct(private GuidanceEngine $engine) {}
    private function scope(Request $request, Practice $practice, string $player): User {
        abort_unless($practice->modes === 'LT' && $practice->type === 'T', 422, 'Choose a Long Toss session.');
        $user = User::whereKey($player)->where('type','player')->firstOrFail();
        abort_unless(PracticeLineUp::where('practice_id',$practice->id)->where('user_id',$player)->exists() || $practice->user_id === $player, 403);
        $actor=$request->user();
        if ($actor->type === 'player') abort_unless($actor->id === $player, 403);
        else abort_unless($actor->type === 'coach' && $practice->team_id
            && CoachTeam::where('coach_id',$actor->id)->where('team_id',$practice->team_id)->whereHas('team')->exists()
            && PlayerTeam::where('user_id',$player)->where('team_id',$practice->team_id)->where('actual',true)->exists(),403);
        return $user;
    }
    private function plan(Practice $practice, string $player): ?LongTossPlan {
        $teams=PlayerTeam::where('user_id',$player)->where('actual',true)->whereHas('team')->pluck('team_id');
        return LongTossPlan::where('player_id',$player)->whereIn('team_id',$teams)
            ->when($practice->team_id,fn($q)=>$q->where('team_id',$practice->team_id))
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('coach_teams')->whereNull('coach_teams.deleted_at')->whereColumn('coach_teams.coach_id','long_toss_plans.coach_id')->whereColumn('coach_teams.team_id','long_toss_plans.team_id'))->latest('updated_at')->first();
    }
    private function history(Request $request, Practice $practice, string $player) {
        return LongTossGuide::where('player_id',$player)->whereNotNull('finished_at')
            ->when($request->user()->type === 'coach',fn($q)=>$q->where('team_id',$practice->team_id))->latest('finished_at')->limit(10)->get();
    }
    private function baseline(Request $request, Practice $practice, User $player, ?LongTossPlan $plan): array {
        $history=$this->history($request,$practice,$player->id);
        $recent = LongTossPractice::where('user_id',$player->id)->where('distance','>',0)->where('distance','<=',500)
            ->where('hop',0)->where('created_at','>=',now()->subDays(180))->where('practice_id','!=',$practice->id)
            ->when($request->user()->type === 'coach',fn($q)=>$q->where('team_id',$practice->team_id))
            ->selectRaw('practice_id, MAX(distance) as distance')->groupBy('practice_id')->get();
        $mph=BullpenPracticeResult::where('pitcher_id',$player->id)->where('is_in_match',false)->where('type_throw','FB')
            ->whereBetween('miles_per_hour',[55,100])->where('created_at','>=',now()->subDays(90))
            ->when($request->user()->type === 'coach',fn($q)=>$q->where('team_id',$practice->team_id))->max('miles_per_hour');
        $estimate=$this->engine->estimate($mph ? (float)$mph : null);
        $reliable=$history->filter(fn($g)=>($g->review['confirmed']??false) && !($g->pain_after??false)
            && $g->status === 'complete' && $g->stage >= count($g->snapshot['stages']??[]) && ($g->review['distance_ft']??0)>0 && ($g->snapshot['day_type']??'') !== 'Light recovery');
        $individual=$reliable->count()>=3 ? (float)$reliable->pluck('review.distance_ft')->median() : null;
        $coachMax=$plan?->settings['measured_max_ft']??null;
        $observed=$recent->max('distance');
        // Keep PRs in the result history, but don't turn a single extreme entry into a prescribed target.
        $reference=$individual ?: $estimate;
        $consistent=$recent->filter(fn($r)=>!$reference || $r->distance <= $reference*1.25)->max('distance');
        $max=$coachMax ?: ($consistent ?: $individual);
        return ['maximum_ft'=>$max ? (float)$max : $estimate,'source'=>$coachMax?'Coach-measured maximum':($max?'Recorded no-hop distance':'Illustrative velocity estimate'),
            'recorded_pr_ft'=>$observed,'velocity_mph'=>$mph ? (float)$mph : null,'velocity_estimate_ft'=>$estimate,
            'review_reference_ft'=>$individual ?: $estimate,'individual_reference_sessions'=>$reliable->count(),
            'note'=>'Velocity estimates are illustrative, not a measure of effort or arm stress. Recorded PRs are preserved.'];
    }
    private function present(LongTossGuide $guide): array {
        $recorded=max(0,LongTossPractice::where('practice_id',$guide->practice_id)->where('user_id',$guide->player_id)->count()-$guide->starting_count);
        $prior=array_sum(array_column(array_slice($guide->snapshot['stages']??[],0,$guide->stage),'throws'));
        $stage=$guide->snapshot['stages'][$guide->stage]??null;
        return array_merge($guide->toArray(),['recorded_throws'=>$recorded,'stage_throws'=>max(0,$recorded-$prior),
            'current_stage'=>$stage,'can_record'=>$guide->status === 'active' && $stage && $recorded-$prior < $stage['throws']]);
    }
    public function context(Request $request, Practice $practice, string $player) {
        $user=$this->scope($request,$practice,$player); $plan=$this->plan($practice,$player);
        $guide=LongTossGuide::where('practice_id',$practice->id)->where('player_id',$player)->first();
        $history=$this->history($request,$practice,$player);
        $dob=$user->player?->born_date; $age=$dob ? Carbon::parse($dob)->age : null;
        return response()->json(['data'=>['guide'=>$guide?$this->present($guide):null, 'baseline'=>$this->baseline($request,$practice,$user,$plan),
            'plan'=>$plan?->settings,'age'=>$age,'can_configure'=>$request->user()->type === 'coach','history'=>$history->map(fn($g)=>[
                'date'=>$g->finished_at,'readiness'=>$g->readiness,'readiness_after'=>$g->readiness_after,'review'=>$g->review]),
            'coach_review_needed'=>$history->take(5)->filter(fn($g)=>$g->review['flagged']??false)->count()>=3,
            'today'=>now()->toDateString()]]);
    }
    public function savePlan(Request $request, Practice $practice, string $player) {
        $this->scope($request,$practice,$player); abort_unless($request->user()->type === 'coach',403);
        $data=$request->validate(['min_age'=>'required|integer|between:5,99','max_age'=>'required|integer|gte:min_age|lte:99',
            'season_phase'=>'required|in:offseason,preseason,inseason,returning','throwing_history'=>'required|string|max:500',
            'day_type'=>'required|in:recovery,moderate,high','scheduled_date'=>'required|date_format:Y-m-d',
            'measured_max_ft'=>'nullable|numeric|between:30,500','start_ft'=>'required|integer|between:15,90',
            'step_ft'=>'required|integer|between:15,90','distance_cap_ft'=>'required|integer|gte:start_ft|lte:500',
            'warmup_throws'=>'required|integer|between:1,30','throws_per_step'=>'required|integer|between:1,20',
            'finish_throws'=>'required|integer|between:1,20','rest_seconds'=>'required|integer|between:10,300','max_throws'=>'required|integer|between:3,120']);
        LongTossPlan::updateOrCreate(['player_id'=>$player,'team_id'=>$practice->team_id],['coach_id'=>$request->user()->id,'settings'=>$data]);
        return $this->context($request,$practice,$player);
    }
    public function setupPlayers(Request $request) {
        $data=$request->validate(['player_ids'=>'required|array|min:1|max:100','player_ids.*'=>'required|uuid|distinct']);
        $actor=$request->user(); $ids=$data['player_ids'];
        if($actor->type==='player') abort_unless(count($ids)===1 && $ids[0]===$actor->id,403);
        else abort_unless($actor->type==='coach',403);
        $teams=$actor->type==='coach'?CoachTeam::where('coach_id',$actor->id)->whereHas('team')->pluck('team_id'):collect();
        $rows=[];
        foreach($ids as $id) {
            $visibleTeams=$actor->type==='coach'?PlayerTeam::where('user_id',$id)->where('actual',true)->whereIn('team_id',$teams)->pluck('team_id'):collect();
            if($actor->type==='coach')abort_if($visibleTeams->isEmpty(),403);
            $player=User::whereKey($id)->where('type','player')->firstOrFail();
            $maximum=LongTossPractice::where('user_id',$id)->whereBetween('distance',[1,500])
                ->when($actor->type==='coach',fn($q)=>$q->whereIn('team_id',$visibleTeams))->max('distance');
            $rows[]=['player_id'=>$id,'maximum_ft'=>$maximum===null?null:(int)$maximum];
        }
        return response()->json(['data'=>$rows]);
    }
    public function alerts(Request $request, string $team) {
        abort_unless($request->user()->type==='coach' && CoachTeam::where('coach_id',$request->user()->id)->where('team_id',$team)->whereHas('team')->exists(),403);
        $ids=PlayerTeam::where('team_id',$team)->where('actual',true)->pluck('user_id');
        $rows=LongTossGuide::where('team_id',$team)->whereIn('player_id',$ids)->whereNotNull('finished_at')->where('finished_at','>=',now()->subDays(90))->latest('finished_at')->get();
        $players=User::whereIn('id',$ids)->with('profile')->get()->keyBy('id');
        $alerts=$rows->groupBy('player_id')->map(function($history,$id)use($players){
            $latest=$history->first();$flags=$history->take(5)->filter(fn($g)=>$g->review['flagged']??false)->count();
            $fatigue=$latest->review['fatigue_attention']??false;
            if($flags<3&&!$fatigue)return null;
            $p=$players[$id]??null;if(!$p)return null;
            return ['player_id'=>$id,'name'=>trim(($p->profile?->first_name??'').' '.($p->profile?->last_name??''))?:'Player',
                'flagged_sessions'=>$flags,'fatigue_attention'=>$fatigue,'date'=>$latest->finished_at,
                'message'=>$fatigue?'Review arm feel and recovery. Pain or declining readiness was reported.':'Review distance measurements, velocity baseline age, and throwing history: 3 unusual sessions within the latest 5.'];
        })->filter()->values();
        return response()->json(['data'=>$alerts]);
    }
    public function preview(Request $request, Practice $practice, string $player) {
        $user=$this->scope($request,$practice,$player);
        $data=$request->validate(['readiness'=>'required|integer|between:1,10','pain'=>'required|boolean','timezone'=>'required|timezone']);
        $plan=$this->plan($practice,$player); $baseline=$this->baseline($request,$practice,$user,$plan);
        $age=$user->player?->born_date ? Carbon::parse($user->player->born_date)->age : null;
        return response()->json(['data'=>$this->engine->build($data['readiness'],$data['pain'],$baseline['maximum_ft'],$plan?->settings,Carbon::now($data['timezone'])->toDateString(),$age)]);
    }
    public function checkIn(Request $request, Practice $practice, string $player) {
        $user=$this->scope($request,$practice,$player); abort_if($practice->is_completed,409,'This session is complete.');
        $data=$request->validate(['readiness'=>'required|integer|between:1,10','pain'=>'required|boolean','timezone'=>'required|timezone']);
        $guide=DB::transaction(function()use($request,$practice,$player,$user,$data){
            Practice::whereKey($practice->id)->lockForUpdate()->firstOrFail();
            $existing=LongTossGuide::where('practice_id',$practice->id)->where('player_id',$player)->first();
            if ($existing) return $existing; // Retries and a second device cannot reset a stop signal.
            $plan=$this->plan($practice,$player); $baseline=$this->baseline($request,$practice,$user,$plan);
            $age=$user->player?->born_date ? Carbon::parse($user->player->born_date)->age : null;
            $snapshot=$this->engine->build($data['readiness'],$data['pain'],$baseline['maximum_ft'],$plan?->settings,Carbon::now($data['timezone'])->toDateString(),$age);
            abort_if($snapshot['status']==='needs_plan',422,$snapshot['message']);
            $snapshot['baseline']=$baseline; $snapshot['plan']=$plan?->settings; $snapshot['timezone']=$data['timezone'];
            return LongTossGuide::create(['practice_id'=>$practice->id,'player_id'=>$player,'team_id'=>$practice->team_id,
                'readiness'=>$data['readiness'],'pain'=>$data['pain'],'snapshot'=>$snapshot,
                'status'=>$snapshot['status']==='rest'?'stopped':'active','starting_count'=>LongTossPractice::where('practice_id',$practice->id)->where('user_id',$player)->count()]);
        });
        return response()->json(['data'=>$this->present($guide)]);
    }
    public function checkpoint(Request $request, Practice $practice, string $player) {
        $this->scope($request,$practice,$player);
        $data=$request->validate(['stage'=>'required|integer|min:0','pain'=>'required|boolean','fatigue'=>'required|boolean']);
        $guide=DB::transaction(function()use($practice,$player,$data){
            $g=LongTossGuide::where('practice_id',$practice->id)->where('player_id',$player)->lockForUpdate()->firstOrFail();
            if($g->status!=='active') return $g;
            if($data['pain']||$data['fatigue']) { $snapshot=$g->snapshot; $snapshot['stop_reason']=$data['pain']?'pain':'fatigue'; $g->update(['status'=>'stopped','pain_after'=>$data['pain'],'snapshot'=>$snapshot]); return $g; }
            if($g->stage!==$data['stage']) return $g;
            $state=$this->present($g);
            abort_unless($state['current_stage'] && $state['stage_throws'] >= $state['current_stage']['throws'],422,'Record your programmed throws before progressing.');
            $g->stage++; if($g->stage>=count($g->snapshot['stages'])) $g->status='awaiting_finish'; $g->save(); return $g;
        });
        return response()->json(['data'=>$this->present($guide)]);
    }
    public function finish(Request $request, Practice $practice, string $player) {
        $this->scope($request,$practice,$player);
        $data=$request->validate(['readiness_after'=>'required|integer|between:1,10','pain_after'=>'required|boolean',
            'measurement'=>'required|in:tape,marked_field,rangefinder,estimate','confirmed'=>'required|boolean']);
        $guide=DB::transaction(function()use($request,$practice,$player,$data){
            $g=LongTossGuide::where('practice_id',$practice->id)->where('player_id',$player)->lockForUpdate()->firstOrFail();
            if($g->finished_at) return $g;
            $distance=(float)LongTossPractice::where('practice_id',$practice->id)->where('user_id',$player)->where('hop',0)->max('distance');
            $review=$this->engine->review($distance,$g->snapshot['baseline']['review_reference_ft']??null,$g->readiness,$data['readiness_after'],$data['pain_after']||$g->pain||$g->pain_after,$g->snapshot['day_type']??'');
            if (($g->snapshot['stop_reason']??null)==='fatigue') { $review['fatigue_attention']=true; $review['message']='Increasing fatigue was reported. Stop throwing and review recovery with your coach.'; }
            $review['measurement']=$data['measurement']; $review['confirmed']=$data['confirmed'] && $data['measurement']!=='estimate';
            $previous=$this->history($request,$practice,$player)->take(4)->filter(fn($old)=>$old->review['flagged']??false)->count();
            $review['coach_review_needed']=$previous+($review['flagged']?1:0)>=3;
            $g->update(['status'=>$g->status==='stopped'?'stopped':'complete','finished_at'=>now(),
                'readiness_after'=>$data['readiness_after'],'pain_after'=>$data['pain_after']||$g->pain||$g->pain_after,'review'=>$review]); return $g;
        });
        return response()->json(['data'=>$this->present($guide)]);
    }
}
