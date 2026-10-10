<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Models\{WorkoutTemplate,DailyPlan,DailyPlanAssignment};
use App\Services\Workouts\WorkoutTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkoutTemplateController extends Controller
{
    public function index(Request $r, WorkoutTemplateService $service)
    {
        $recent=[];
        foreach(DailyPlan::where('created_by',$r->user()->id)->latest()->limit(100)->get(['buckets','created_at']) as $plan)foreach($plan->buckets??[] as $block){$id=$block['template_source']['id']??null;if($id&&!isset($recent[$id]))$recent[$id]=$plan->created_at;}
        return response()->json(['planner_contract_version'=>'2.0','data' => $service->visible($r->user())->with('sections.exercises')->orderBy('name')->get()->map(function($template)use($recent){$row=$template->toArray();$row['last_used_at']=$recent[$template->id]??null;$row['planner_contract_version']='2.0';return $row;})])->header('Cache-Control', 'no-store');
    }
    public function show(Request $r, string $id, WorkoutTemplateService $service)
    {
        return response()->json(['planner_contract_version'=>'2.0','data' => $service->get($r->user(), $id)]);
    }
    public function store(Request $r, WorkoutTemplateService $service)
    {
        $data = $service->validate($r->all());
        unset($data['slug']);
        return response()->json(['planner_contract_version'=>'2.0','data' => $service->write($data, null, $r->user()->id)], 201);
    }
    public function update(Request $r, string $id, WorkoutTemplateService $service)
    {
        $t = $service->get($r->user(), $id);
        abort_unless( ! $t->is_premade && $t->created_by === $r->user()->id, 403, 'Duplicate the master template before editing.');
        $r->validate(['version' => 'required|integer|min:1']);
        $data = $service->validate($r->all());
        unset($data['slug']);
        return response()->json(['planner_contract_version'=>'2.0','data' => $service->write($data, $t)]);
    }
    public function duplicate(Request $r, string $id, WorkoutTemplateService $service)
    {
        $data = $service->get($r->user(), $id)->toArray();
        unset($data['slug'],$data['version']);
        $data['name'] = Str::limit($data['name'], 193, '').' (copy)';
        return response()->json(['planner_contract_version'=>'2.0','data' => $service->write($data, null, $r->user()->id)], 201);
    }
    public function instantiate(Request $r, string $id, WorkoutTemplateService $service)
    {
        $data = $r->validate(['id' => 'required|uuid','team_id' => 'required|string','date' => 'required|date_format:Y-m-d','player_ids' => 'present|array','player_ids.*' => 'string','group_ids' => 'sometimes|array','group_ids.*' => 'string','whole_team' => 'sometimes|boolean']);
        $t = $service->get($r->user(), $id);
        $players = $service->athletes($r->user(), $data['team_id'], $data['player_ids'], $data['group_ids'] ?? [], $data['whole_team'] ?? false);
        $plan = DB::transaction(function () use ($r, $data, $t, $players, $service) {
            // Serialize retries and competing submissions by locking the owning user.
            $r->user()->newQuery()->whereKey($r->user()->id)->lockForUpdate()->first();
            $old = DailyPlan::withTrashed()->find($data['id']);
            if($old) {
                abort_unless( ! $old->trashed() && $old->created_by === $r->user()->id && $old->team_id === $data['team_id'] && ($old->buckets[0]['template_source']['id'] ?? null) === $t->id, 409, 'This request ID already belongs to another plan.');
                return $old;
            }
            $plan = DailyPlan::create(['id' => $data['id'],'team_id' => $data['team_id'],'created_by' => $r->user()->id,'name' => $t->name,'date' => $data['date'],'primary_goal' => Str::limit($t->description ?? '', 200, ''),'phase' => 'Foundation','workload_level' => $t->intensity_label,'estimated_minutes' => $t->estimated_duration_minutes,'status' => 'draft','buckets' => $service->buckets($t)]);
            foreach($players as $player) {
                DailyPlanAssignment::create(['plan_id' => $plan->id,'user_id' => $player]);
            }
            return $plan;
        });
        return response()->json(['planner_contract_version'=>'2.0','data' => app(\App\Services\Planner\PlannerContract::class)->plan($plan->fresh())], 201);
    }
}
