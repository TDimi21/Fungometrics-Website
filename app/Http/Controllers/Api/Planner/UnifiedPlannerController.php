<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api\Planner;
use App\Http\Controllers\Controller;
use App\Models\{DailyPlan,DailyPlanAssignment,DailyPlanProgress,PlayerTeam};
use App\Services\Planner\{PlannerContract,PlannerWellnessService,PlannerScheduleService,DailyThrowLedgerService,LinkedSessionRegistry};
use App\Services\Workouts\WorkoutTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UnifiedPlannerController extends Controller
{
    public function day(Request $r, PlannerContract $contract, WorkoutTemplateService $teams)
    {
        $v=$r->validate(['date'=>'required|date_format:Y-m-d','team_id'=>'sometimes|required|string','player_id'=>'sometimes|required|string']);
        $coach=$r->is('api/coach/*');$uid=(string)$r->user()->id;
        if($coach){$teams->team($r->user(),$v['team_id']??'');$uid=$v['player_id']??'';if($uid)abort_unless(PlayerTeam::where('team_id',$v['team_id'])->where('user_id',$uid)->exists(),403);}
        $assignments=DailyPlanAssignment::with(['plan.assignments','plan.progress'])->where('schedule_status','active')->when($uid,fn($q)=>$q->where('user_id',$uid))->whereHas('plan',fn($q)=>$q->where('status','published')->when($coach,fn($q)=>$q->where('team_id',$v['team_id'])))->where(fn($q)=>$q->whereDate('scheduled_date',$v['date'])->orWhere(fn($q)=>$q->whereNull('scheduled_date')->whereHas('plan',fn($p)=>$p->whereDate('date',$v['date']))))->get();
        $rows=$assignments->map(fn($a)=>$contract->plan($a->plan,$a->user_id));
        $upcoming=[];
        if(!$coach){
            $start=\Carbon\Carbon::parse($v['date'])->addDay()->toDateString();$end=\Carbon\Carbon::parse($v['date'])->addDays(7)->toDateString();
            $upcoming=DailyPlanAssignment::with('plan')->where('user_id',$uid)->where('schedule_status','active')->whereHas('plan',fn($q)=>$q->where('status','published'))->where(fn($q)=>$q->whereBetween('scheduled_date',[$start,$end])->orWhere(fn($q)=>$q->whereNull('scheduled_date')->whereHas('plan',fn($p)=>$p->whereBetween('date',[$start,$end]))))->get()->map(fn($a)=>['id'=>$a->plan_id,'name'=>$a->plan->name,'date'=>($a->scheduled_date??$a->plan->date)->format('Y-m-d')])->sortBy('date')->values();
        }
        return response()->json(['planner_contract_version'=>'2.0','data'=>$rows,'upcoming'=>$upcoming,'registry'=>app(LinkedSessionRegistry::class)->all(),'throw_ledger'=>$uid?app(DailyThrowLedgerService::class)->build($uid,$v['date'],$coach?$v['team_id']:null):null]);
    }
    public function adjust(Request $r,string $id, WorkoutTemplateService $teams)
    {
        $v=$r->validate(['player_id'=>'sometimes|string','date'=>'required|date_format:Y-m-d','behavior'=>'required|in:shift,replace','request_id'=>'required|uuid']);
        $plan=DailyPlan::where('status','published')->findOrFail($id);$uid=(string)$r->user()->id;
        if($r->is('api/coach/*')){$teams->team($r->user(),$plan->team_id);$uid=$v['player_id']??'';}
        return response()->json(['planner_contract_version'=>'2.0','data'=>app(PlannerScheduleService::class)->adjust($plan,$uid,$v['date'],$v['behavior'],(string)$r->user()->id,$v['request_id'])]);
    }
    public function adjustPrescription(Request $r,string $id,WorkoutTemplateService $teams)
    {
        $v=$r->validate(['player_id'=>'required|string','version'=>'required|integer|min:0','reason'=>'required|string|max:2000','buckets'=>'required|array','buckets.*.type'=>'required|string|max:60','buckets.*.items'=>'present|array','buckets.*.items.*.id'=>'required|string|max:64','buckets.*.items.*.name'=>'required|string|max:200']);
        // Preserve existing prescription fields after validating the nested structure.
        $v['buckets']=$r->input('buckets');
        $plan=DailyPlan::where('status','published')->findOrFail($id);$teams->team($r->user(),$plan->team_id);
        DB::transaction(function()use($plan,$v,$r){
            \App\Models\User::whereKey($v['player_id'])->lockForUpdate()->firstOrFail();
            $plan=DailyPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $a=DailyPlanAssignment::where('plan_id',$plan->id)->where('user_id',$v['player_id'])->where('schedule_status','active')->lockForUpdate()->firstOrFail();
            abort_if((int)($a->prescription_override['version']??0)!==$v['version'],409,'This player day changed. Reload before editing.');
            $progress=DailyPlanProgress::where('plan_id',$plan->id)->where('user_id',$v['player_id'])->first();
            abort_if($progress?->completed_at,409,'This workout is complete. Its prescription is preserved.');
            $before=$plan->toArray();$before['buckets']=$plan->bucketsFor($v['player_id']);$before['assigned_player_ids']=[$v['player_id']];
            $oldItems=collect($before['buckets'])->flatMap(fn($b)=>$b['items']??[])->keyBy('id');
            $buckets=$v['buckets'];
            foreach($buckets as &$b)foreach($b['items'] as &$i){unset($i['linked_session'],$i['execution_type']);}unset($b,$i);
            $newItems=collect($buckets)->flatMap(fn($b)=>$b['items']);
            abort_if($newItems->pluck('id')->unique()->count()!==$newItems->count(),422,'Exercise IDs must be unique.');$newItems=$newItems->keyBy('id');
            $protected=array_unique(array_merge(array_keys($progress?->items??[]),DB::table('workout_session_links')->where('plan_id',$plan->id)->where('user_id',$v['player_id'])->pluck('item_id')->all()));
            foreach($protected as $itemId){$original=$oldItems[$itemId]??null;if($original){unset($original['execution_type'],$original['linked_session']);}abort_unless(isset($newItems[$itemId]) && $newItems[$itemId]==$original,409,'Preserve all recorded exercises. Only adjust the remaining work.');}
            $conflicts=app(\App\Services\Planner\PlannerConflictService::class)->check($plan->team_id,$v['player_id'],($a->scheduled_date??$plan->date)->format('Y-m-d'),$buckets,[$plan->id]);
            if($conflicts)throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message'=>'SESSION CONFLICT','conflicts'=>$conflicts],409));
            $a->update(['prescription_override'=>['version'=>$v['version']+1,'buckets'=>$buckets,'reason'=>$v['reason'],'actor'=>$r->user()->id,'at'=>now()->toIso8601String()]]);
            $after=array_merge($before,['buckets'=>$buckets]);
            $revision=app(\App\Services\Planner\DailyPlanRevisionService::class)->createRevision($plan->id,$before,$after,['created_by_user_id'=>$r->user()->id,'source'=>'player_day_adjustment','reason'=>$v['reason']]);
            abort_if($revision['revision_status']==='failed',500,'Could not save the revision.');
        });
        return response()->json(['planner_contract_version'=>'2.0','data'=>app(PlannerContract::class)->plan($plan->fresh(),$v['player_id'])]);
    }

    public function reviewAlert(Request $r,string $id, WorkoutTemplateService $teams)
    {
        $v=$r->validate(['player_id'=>'required|string','action'=>'required|string|max:2000','version'=>'required|integer']);
        $plan=DailyPlan::findOrFail($id);$teams->team($r->user(),$plan->team_id);
        $progress=DB::transaction(function()use($v,$id,$r){$p=DailyPlanProgress::where('plan_id',$id)->where('user_id',$v['player_id'])->lockForUpdate()->firstOrFail();abort_if($p->version!==$v['version'],409,'Reload the latest player response.');$p->update(['alert_review'=>['reviewed_by'=>$r->user()->id,'reviewed_at'=>now()->toIso8601String(),'action'=>$v['action']],'version'=>$p->version+1]);return $p;});
        return response()->json(['planner_contract_version'=>'2.0','data'=>$progress]);
    }
}
