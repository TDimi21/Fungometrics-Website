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
        return response()->json(['data' => WorkoutProgram::whereIn('team_id', $s->teamIds($r->user()))->latest()->get()]);
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
                abort_if('published' === $old->status, 409, 'Published programs are snapshots. Copy the program to change future schedules.');
                abort_if($old->version !== $v['version'], 409, 'Program changed. Reload before saving.');
            }
            $schedule = [];
            foreach($v['schedule'] as $entry) {
                abort_if($entry['day_offset'] >= $v['weeks'] * 7, 422, 'A workout is outside the program dates.');
                $template = $s->get($r->user(), $entry['template_id']);
                $snapshot = $entry['snapshot'] ?? $template->toArray();
                $s->validate($snapshot);
                $schedule[] = ['id' => $entry['id'],'day_offset' => $entry['day_offset'],'phase' => $entry['phase'],'template_id' => $template->id,'template_version' => $snapshot['version'] ?? $template->version,'snapshot' => $snapshot,'player_ids' => $s->athletes($r->user(), $v['team_id'], $entry['player_ids'])];
            }
            return WorkoutProgram::updateOrCreate(['id' => $v['id']], ['created_by' => $old->created_by ?? $r->user()->id,'team_id' => $v['team_id'],'name' => $v['name'],'start_date' => $v['start_date'],'weeks' => $v['weeks'],'version' => ($old->version ?? 0) + 1,'schedule' => $schedule]);
        });
        return response()->json(['data' => $program]);
    }
    public function publish(Request $r, string $id, WorkoutTemplateService $s)
    {
        $r->validate(['workload_approved' => 'required|accepted','version' => 'required|integer|min:1']);
        $program = DB::transaction(function () use ($r, $id, $s) {
            $program = WorkoutProgram::lockForUpdate()->findOrFail($id);
            $s->team($r->user(), $program->team_id);
            if('published' === $program->status) {
                return $program;
            }
            abort_if($program->version !== (int)$r->input('version'), 409, 'Reload the latest program before publishing.');
            abort_if( ! $program->schedule, 422, 'Add workouts before publishing.');
            $schedule = $program->schedule;
            foreach($schedule as &$entry) {
                $players = $s->athletes($r->user(), $program->team_id, $entry['player_ids']);
                abort_if( ! $players, 422, 'Assign at least one athlete to every workout.');
                $snapshot = $entry['snapshot'];
                $t = new WorkoutTemplate(collect($snapshot)->except('sections')->all());
                $t->id = $entry['template_id'];
                $t->version = $entry['template_version'];
                $sections = collect($snapshot['sections'])->map(function ($row) {
                    $section = new \App\Models\WorkoutTemplateSection(collect($row)->except('exercises')->all());
                    $section->setRelation('exercises', collect($row['exercises'])->map(fn ($e) => new \App\Models\WorkoutTemplateExercise($e)));
                    return $section;
                });
                $t->setRelation('sections', $sections);
                $buckets = $s->buckets($t);
                foreach($buckets as &$b) {
                    $b['program_source'] = ['id' => $program->id,'entry_id' => $entry['id'],'name' => $program->name];
                }unset($b);
                $plan = DailyPlan::create(['id' => (string)Str::uuid(),'team_id' => $program->team_id,'created_by' => $program->created_by,'name' => $snapshot['name'],'date' => Carbon::parse($program->start_date)->addDays($entry['day_offset'])->toDateString(),'phase' => $entry['phase'],'primary_goal' => Str::limit($snapshot['description'] ?? '', 200, ''),'workload_level' => $snapshot['intensity_label'] ?? null,'estimated_minutes' => $snapshot['estimated_duration_minutes'] ?? null,'buckets' => $buckets,'status' => 'published','published_at' => now()]);
                foreach($players as $player) {
                    DailyPlanAssignment::create(['plan_id' => $plan->id,'user_id' => $player]);
                }
                $entry['daily_plan_id'] = $plan->id;
            }
            unset($entry);
            $program->update(['schedule' => $schedule,'status' => 'published']);
            return $program;
        });
        return response()->json(['data' => $program]);
    }
}
