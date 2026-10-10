<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Models\{WorkoutProgram,WorkoutTemplate,DailyPlan,DailyPlanAssignment};
use App\Services\Workouts\WorkoutTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WorkoutProgramController extends Controller
{
    public function index(Request $r, WorkoutTemplateService $s)
    {
        return response()->json(['planner_contract_version'=>'2.0','data' => WorkoutProgram::whereIn('team_id', $s->teamIds($r->user()))->latest()->get()]);
    }
    public function save(Request $r, WorkoutTemplateService $s)
    {
        $v = $r->validate(['id' => 'required|uuid','version' => 'required|integer|min:0','team_id' => 'required|string','name' => 'required|string|max:200','start_date' => 'required|date_format:Y-m-d','weeks' => 'required|integer|min:1|max:52','schedule' => 'present|array|max:1000','schedule.*.id' => 'required|uuid|distinct','schedule.*.day_offset' => 'required|integer|min:0','schedule.*.template_id' => 'required|uuid','schedule.*.phase' => 'required|string|max:60','schedule.*.player_ids' => 'present|array','schedule.*.player_ids.*' => 'string','schedule.*.snapshot' => 'sometimes|array']);
        $s->team($r->user(), $v['team_id']);
        $program = DB::transaction(function () use ($r, $s, $v) {
            $r->user()->newQuery()->whereKey($r->user()->id)->lockForUpdate()->first();
            $old = WorkoutProgram::lockForUpdate()->find($v['id']);
            if($old) {
                $s->team($r->user(), $old->team_id);
                abort_unless($old->created_by === $r->user()->id, 403, 'Only the creator can edit this master program.');
                abort_if($old->status === 'published' && ($old->start_date !== $v['start_date'] || $old->team_id !== $v['team_id']),409,'Keep published program start date and team unchanged.');
                abort_if($old->version !== $v['version'], 409, 'Program changed. Reload before saving.');
            }
            foreach($old?->schedule??[] as $previousEntry)abort_if(isset($previousEntry['published_at']) && !in_array($previousEntry['id'],array_column($v['schedule'],'id'),true),409,'Keep published days in program history. Adjust their selected future assignments instead.');
            $schedule = [];
            foreach($v['schedule'] as $entry) {
                abort_if($entry['day_offset'] >= $v['weeks'] * 7, 422, 'A workout is outside the program dates.');
                $template = $s->get($r->user(), $entry['template_id']);
                $snapshot = $entry['snapshot'] ?? $template->toArray();
                $s->validate($snapshot);
                $previous=collect($old?->schedule??[])->firstWhere('id',$entry['id'])??[];
                $publication=collect($previous)->only(['daily_plan_ids','daily_plan_id','published_version','published_at'])->all();
                $schedule[] = $publication + ['id' => $entry['id'],'day_offset' => $entry['day_offset'],'phase' => $entry['phase'],'template_id' => $template->id,'template_version' => $snapshot['version'] ?? $template->version,'snapshot' => $snapshot,'player_ids' => $s->athletes($r->user(), $v['team_id'], $entry['player_ids'])];
            }
            return WorkoutProgram::updateOrCreate(['id' => $v['id']], ['created_by' => $old->created_by ?? $r->user()->id,'team_id' => $v['team_id'],'name' => $v['name'],'start_date' => $v['start_date'],'weeks' => $v['weeks'],'version' => ($old->version ?? 0) + 1,'schedule' => $schedule]);
        });
        return response()->json(['planner_contract_version'=>'2.0','data' => $program]);
    }
    public function publish(Request $r, string $id, WorkoutTemplateService $s)
    {
        $v=$r->validate(['resolutions'=>'sometimes|array|max:1000','resolutions.*'=>'array','workload_approved'=>'required|accepted','version'=>'required|integer|min:1','entry_ids'=>'sometimes|array|min:1','entry_ids.*'=>'uuid','player_ids'=>'sometimes|array|min:1','player_ids.*'=>'string']);
        $program=DB::transaction(function()use($r,$id,$s,$v){
            $program=WorkoutProgram::lockForUpdate()->findOrFail($id);
            $s->team($r->user(),$program->team_id);
            abort_unless($program->created_by===$r->user()->id,403,'Only the creator can publish this master.');
            if(!isset($v['entry_ids']) && !isset($v['player_ids']) && $program->schedule && collect($program->schedule)->every(fn($e)=>isset($e['published_at'])))return $program;
            abort_if((int)$program->version!==(int)$v['version'],409,'This program changed on another device. Reload before publishing.');
            $schedule=$program->schedule;
            abort_unless($schedule,422,'Add workouts before publishing.');
            $requested=$v['entry_ids']??array_column($schedule,'id');
            abort_if(array_diff($requested,array_column($schedule,'id')),422,'Unknown program day.');
            foreach($schedule as &$entry){
                if(!in_array($entry['id'],$requested,true))continue;
                if(isset($entry['published_at']) && !isset($v['player_ids']))continue;
                $players=$s->athletes($r->user(),$program->team_id,$v['player_ids']??$entry['player_ids']);
                abort_unless($players,422,'Select athletes before publishing this day.');
                $snapshot=$entry['snapshot'];
                $t=new WorkoutTemplate(collect($snapshot)->except('sections')->all());$t->id=$entry['template_id'];$t->version=$entry['template_version'];
                $t->setRelation('sections',collect($snapshot['sections'])->map(function($row){
                    $section=new \App\Models\WorkoutTemplateSection(collect($row)->except('exercises')->all());
                    $section->setRelation('exercises',collect($row['exercises'])->map(fn($e)=>new \App\Models\WorkoutTemplateExercise($e)));return $section;
                }));
                $buckets=$s->buckets($t);
                foreach($buckets as &$bucket)$bucket['program_source']=['id'=>$program->id,'entry_id'=>$entry['id'],'name'=>$program->name,'version'=>$program->version];unset($bucket);
                $date=Carbon::parse($program->start_date)->addDays($entry['day_offset'])->toDateString();
                foreach($players as $player){
                    \App\Models\User::whereKey($player)->lockForUpdate()->firstOrFail();
                    $oldId=$entry['daily_plan_ids'][$player]??$entry['daily_plan_id']??null;
                    if($oldId){
                        $oldAssignment=DailyPlanAssignment::with('plan')->where('plan_id',$oldId)->where('user_id',$player)->firstOrFail();
                        $oldDate=($oldAssignment->scheduled_date??$oldAssignment->plan->date)->format('Y-m-d');
                        abort_if($date<now()->toDateString() || $oldDate<now()->toDateString(),409,'Historical prescriptions cannot be replaced.');
                        abort_if(\App\Models\DailyPlanProgress::where('plan_id',$oldId)->where('user_id',$player)->exists() || DB::table('workout_session_links')->where('plan_id',$oldId)->where('user_id',$player)->exists(),409,'This athlete has recorded work. Use a player-specific adjustment.');
                    }
                    $resolution=app(\App\Services\Planner\PlannerConflictResolutionService::class)->resolve($program->team_id,$player,$date,$buckets,$oldId?[$oldId]:[],$v['resolutions']??[]);
                    if($resolution['skip'])continue;
                    $plan=DailyPlan::create(['id'=>(string)Str::uuid(),'team_id'=>$program->team_id,'created_by'=>$program->created_by,'name'=>$snapshot['name'],'date'=>$resolution['date'],'phase'=>$entry['phase'],'primary_goal'=>Str::limit($snapshot['description']??'',200,''),'workload_level'=>$snapshot['intensity_label']??null,'estimated_minutes'=>$snapshot['estimated_duration_minutes']??null,'buckets'=>$resolution['buckets'],'status'=>'published','published_at'=>now(),'settings'=>['readiness_required'=>true,'post_check_required'=>true]]);
                    DailyPlanAssignment::create(['plan_id'=>$plan->id,'user_id'=>$player]);
                    foreach($resolution['replaced'] as $replacedId) {
                        DailyPlanAssignment::where('plan_id',$replacedId)->where('user_id',$player)->update(['schedule_status'=>'superseded']);
                        $revision=app(\App\Services\Planner\DailyPlanRevisionService::class)->createRevision($plan->id,DailyPlan::findOrFail($replacedId)->toArray(),$plan->toArray(),['created_by_user_id'=>$r->user()->id,'source'=>'conflict_resolution','reason'=>'Coach explicitly resolved session conflict']);
                        abort_if($revision['revision_status']==='failed',500,'Could not record conflict resolution.');
                    }
                    if($oldId){
                        DailyPlanAssignment::where('plan_id',$oldId)->where('user_id',$player)->update(['schedule_status'=>'superseded']);
                        $revision=app(\App\Services\Planner\DailyPlanRevisionService::class)->createRevision($plan->id,DailyPlan::findOrFail($oldId)->toArray(),$plan->toArray(),['created_by_user_id'=>$r->user()->id,'source'=>'selective_program_update','reason'=>'Selected athlete update']);
                        abort_if($revision['revision_status']==='failed',500,'Could not record revision.');
                    }
                    $entry['daily_plan_ids'][$player]=$plan->id;
                }
                $entry['published_at']=now()->toIso8601String();$entry['published_version']=$program->version;
            }unset($entry);
            $program->update(['schedule'=>$schedule,'status'=>'published','version'=>$program->version+1]);return $program;
        });
        return response()->json(['planner_contract_version'=>'2.0','data'=>$program]);
    }
}
