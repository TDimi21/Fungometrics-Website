<?php

declare(strict_types=1);

namespace App\Services\Workouts;

use App\Models\{DailyPlan,DailyPlanAssignment,DailyPlanProgress,Practice,PracticeLineUp,WeightBallPractice};
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Support\Str;

class WorkoutPerformanceService
{
    public function save(DailyPlan $plan, string $userId, array $data): DailyPlanProgress
    {
        return DB::transaction(function () use ($plan, $userId, $data) {
            $plan = DailyPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $assignment = DailyPlanAssignment::where('plan_id', $plan->id)->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($assignment && $assignment->schedule_status === 'active' && 'published' === $plan->status, 403, 'Only assigned published workouts can be submitted.');
            if(isset($data['assignment_version']))abort_if((int)$data['assignment_version']!==(int)($assignment->prescription_override['version']??0),409,'Your coach adjusted this day. Reload the latest prescription.');
            $prescribed = collect($plan->bucketsFor($userId))->flatMap(fn ($b) => array_map(fn($i)=>$i+['_bucket_type'=>$b['type']??''], $b['items'] ?? []))->keyBy('id');
            $old = DailyPlanProgress::where('plan_id', $plan->id)->where('user_id', $userId)->first();
            if (isset($data['version'])) abort_if((int)$data['version'] !== (int)($old?->version ?? 0), 409, 'This workout changed on another device. Reload before saving.');
            if(!empty($data['completed_at']) && ($plan->settings['post_check_required']??false)) validator($data,['post_training.overall_effort'=>'required|integer|between:0,10','post_training.overall_fatigue'=>'required|integer|between:0,10','post_training.arm_fatigue'=>'required|integer|between:0,10','post_training.arm_soreness'=>'required|integer|between:0,10'])->validate();
            $history=$old?->actual_history??[];
            if($old) $history[]=['at'=>now()->toIso8601String(),'items'=>$old->items,'post_training'=>$old->post_training,'actor'=>$userId];
            $items = array_replace($old?->items ?? [], $data['items'] ?? []);
            foreach($items as $itemId => &$actual) {
                abort_unless($prescribed->has($itemId), 422, 'An exercise does not belong to this workout.');
                unset($actual['strength_fitness_id'],$actual['strength_summary']);
                $exercise = $prescribed[$itemId];
                $exercise=app(\App\Services\Planner\LinkedSessionRegistry::class)->item($exercise, $exercise['_bucket_type']);
                validator($actual, ['quick_throws'=>'sometimes|array|max:100','quick_throws.*.count'=>'required|integer|min:0|max:1000','quick_throws.*.category'=>'required|in:warmup_throws,catch_play_throws,flat_ground_throws,other_throws','quick_throws.*.ball_weight'=>'nullable|string|max:60','quick_throws.*.intent'=>'nullable|integer|min:0|max:100','quick_throws.*.timestamp'=>'required|date','sets'=>'sometimes|array|max:100','sets.*.done'=>'sometimes|boolean','sets.*.weight'=>'nullable|numeric|min:0|max:3000','sets.*.reps'=>'nullable|integer|min:0|max:1000','sets.*.rpe'=>'nullable|numeric|min:1|max:10'])->validate();
                if(isset($data['items'][$itemId])) { $actual['edited_at']=now()->toIso8601String(); if(!empty($actual['done']))$actual['completed_at']=$old?->items[$itemId]['completed_at']??now()->toIso8601String(); }

                $actual = array_replace($old?->items[$itemId] ?? [], $actual);
                validator($actual, ['done' => 'sometimes|boolean','actual_reps' => 'nullable|integer|min:0','actual_sets' => 'nullable|integer|min:0','actual_distance_yards' => 'nullable|numeric|min:0','actual_rpe' => 'nullable|numeric|min:1|max:10','player_note' => 'nullable|string|max:2000','session_id' => 'nullable|uuid','radar' => 'sometimes|array|max:1000','radar.*.id' => 'required|uuid|distinct','radar.*.weight' => 'required|integer|min:1','radar.*.velocity' => 'required|integer|min:1','radar.*.attempt' => 'required|integer|min:1','radar.*.timestamp' => 'required|date'])->validate();
                if( ! empty($actual['radar'])) {
                    abort_unless(($exercise['metadata']['tracking_type'] ?? null) === 'radar', 422, 'Radar results are not enabled for this exercise.');
                    $link = DB::table('workout_session_links')->where(['plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId])->first();
                    if( ! $link) {
                        $practice = Practice::create(['team_id' => $plan->team_id,'user_id' => $userId,'started' => \Carbon\Carbon::parse($actual['radar'][0]['timestamp'])->format('Y-m-d H:i:s'),'type' => 'T','modes' => 'WB','note' => 'Workout: '.$plan->name,'is_completed' => false]);
                        PracticeLineUp::create(['practice_id' => $practice->id,'user_id' => $userId,'sort' => 1]);
                        DB::table('workout_session_links')->insert(['id' => (string)Str::uuid(),'plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId,'practice_id' => $practice->id,'type' => 'weighted_ball','created_for_workout' => true,'created_at' => now(),'updated_at' => now()]);
                        $practiceId = $practice->id;
                    } else {
                        $practiceId = $link->practice_id;
                        abort_unless('weighted_ball' === $link->type, 422);
                    }
                    abort_unless(Practice::whereKey($practiceId)->where('team_id', $plan->team_id)->where(fn ($q) => $q->where('user_id', $userId)->orWhereHas('lineup', fn ($l) => $l->where('user_id', $userId)))->exists(), 404);
                    foreach($actual['radar'] as $throw) {
                        $existing = WeightBallPractice::withTrashed()->find($throw['id']);
                        if($existing) {
                            abort_unless( ! $existing->trashed() && $existing->practice_id === $practiceId && $existing->user_id === $userId && (int)$existing->weight === $throw['weight'] && (int)$existing->velocity === $throw['velocity'] && (int)$existing->sort === $throw['attempt'], 409, 'An existing throw cannot be replaced. Use the session editor to correct it.');
                            continue;
                        }
                        $record = new WeightBallPractice(['practice_id' => $practiceId,'user_id' => $userId,'team_id' => $plan->team_id,'set' => 1,'sort' => $throw['attempt'],'weight' => $throw['weight'],'velocity' => $throw['velocity']]);
                        $record->id = $throw['id'];
                        $record->created_at = \Carbon\Carbon::parse($throw['timestamp']);
                        $record->saveQuietly();
                    }
                    $actual['session_id'] = $practiceId;
                    if( ! empty($data['completed_at']) && ( ! $link || $link->created_for_workout)) {
                        Practice::whereKey($practiceId)->where('is_completed', false)->update(['is_completed' => true,'finished' => \Carbon\Carbon::parse($data['completed_at'])->format('Y-m-d H:i:s')]);
                    }
                } elseif( ! empty($actual['session_id'])) {
                    $session = Practice::whereKey($actual['session_id'])->where('team_id', $plan->team_id)->where(fn ($q) => $q->where('user_id', $userId)->orWhereHas('lineup', fn ($l) => $l->where('user_id', $userId)))->firstOrFail();
                    $expected = $exercise['metadata']['session_type'] ?? null;
                    abort_unless(app(\App\Services\Planner\LinkedSessionRegistry::class)->matches((string)$expected, $session), 422, 'Choose a session matching this exercise.');
                    $existing = DB::table('workout_session_links')->where(['plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId])->first();
                    abort_if($existing && $existing->practice_id !== $session->id, 409, 'This exercise already links to another session.');
                    if( ! $existing) {
                        DB::table('workout_session_links')->insert(['id' => (string)Str::uuid(),'plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId,'practice_id' => $session->id,'type' => $expected,'created_at' => now(),'updated_at' => now()]);
                    }
                }
                if($exercise['execution_type']==='LAUNCH_SESSION' && ($exercise['metadata']['tracking_type']??'')!=='radar') {
                    $link=DB::table('workout_session_links')->where(['plan_id'=>$plan->id,'user_id'=>$userId,'item_id'=>$itemId])->first();
                    $canonical=$link?Practice::find($link->practice_id):null;
                    $actual['done']=$canonical?(bool)$canonical->is_completed:false;
                    if($canonical)$actual['session_id']=$canonical->id;
                }
                if(isset($actual['sets']) && str_starts_with($exercise['_bucket_type'],'strength'))$actual=app(\App\Services\Planner\PlannerStrengthMetricsService::class)->record($plan,$userId,$exercise,$actual,$old?->items[$itemId]??[]);
                // Preserve accepted observed records even if an older/offline client submits a partial item.
                $prior = $old?->items[$itemId] ?? [];
                if( ! empty($prior['radar'])) {
                    $actual['radar'] = collect(array_merge($prior['radar'], $actual['radar'] ?? []))->unique('id')->values()->all();
                }
                if( ! empty($prior['session_id'])) {
                    $actual['session_id'] = $prior['session_id'];
                }
            }
            unset($actual);
            $progress = DailyPlanProgress::updateOrCreate(['plan_id' => $plan->id,'user_id' => $userId], [
                'alert_review'=>(isset($data['post_training']) && $data['post_training']!==($old?->post_training??[]) || isset($data['readiness']) && $data['readiness']!==($old?->readiness??[]))?null:$old?->alert_review,
                'version'=>($old?->version??0)+1,'actual_history'=>$history,'post_training'=>$data['post_training']??$old?->post_training??[],
                'readiness' => $data['readiness'] ?? $old?->readiness ?? [],'items' => $items,'reflection' => $data['reflection'] ?? $old?->reflection ?? [],
                'started_at' => $old?->started_at ?? $data['started_at'] ?? now(),'completed_at' => $old?->completed_at ?? $data['completed_at'] ?? null,
            ]);
            DB::afterCommit(function () use ($plan, $userId): void {foreach(['last_sessions_','performance_overview_','dashboard_graphics_'] as $prefix) {Cache::forget($prefix.$plan->team_id); }app(\App\Services\Development\PlayerDevelopmentDashboardCache::class)->forgetPlayer($userId);});
            return $progress;
        });
    }
}
