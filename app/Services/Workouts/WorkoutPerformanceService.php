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
            abort_unless($assignment && 'published' === $plan->status, 403, 'Only assigned published workouts can be submitted.');
            $prescribed = collect($plan->buckets)->flatMap(fn ($b) => $b['items'] ?? [])->keyBy('id');
            $old = DailyPlanProgress::where('plan_id', $plan->id)->where('user_id', $userId)->first();
            $items = array_replace($old?->items ?? [], $data['items'] ?? []);
            foreach($items as $itemId => &$actual) {
                abort_unless($prescribed->has($itemId), 422, 'An exercise does not belong to this workout.');
                $exercise = $prescribed[$itemId];
                if(empty($exercise['template_id'])) {
                    continue;
                }
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
                    abort_unless(('bullpen' === $expected && 'P' === $session->type) || ('weighted_ball' === $expected && 'T' === $session->type && 'WB' === $session->modes), 422, 'Choose a session matching this exercise.');
                    $existing = DB::table('workout_session_links')->where(['plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId])->first();
                    abort_if($existing && $existing->practice_id !== $session->id, 409, 'This exercise already links to another session.');
                    if( ! $existing) {
                        DB::table('workout_session_links')->insert(['id' => (string)Str::uuid(),'plan_id' => $plan->id,'user_id' => $userId,'item_id' => $itemId,'practice_id' => $session->id,'type' => $expected,'created_at' => now(),'updated_at' => now()]);
                    }
                }
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
                'readiness' => $data['readiness'] ?? $old?->readiness ?? [],'items' => $items,'reflection' => $data['reflection'] ?? $old?->reflection ?? [],
                'started_at' => $old?->started_at ?? $data['started_at'] ?? now(),'completed_at' => $old?->completed_at ?? $data['completed_at'] ?? null,
            ]);
            DB::afterCommit(function () use ($plan, $userId): void {foreach(['last_sessions_','performance_overview_','dashboard_graphics_'] as $prefix) {Cache::forget($prefix.$plan->team_id); }app(\App\Services\Development\PlayerDevelopmentDashboardCache::class)->forgetPlayer($userId);});
            return $progress;
        });
    }
}
