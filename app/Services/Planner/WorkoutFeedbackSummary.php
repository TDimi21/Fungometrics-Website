<?php
namespace App\Services\Planner;

use App\Models\{DailyPlan, DailyPlanProgress};

final class WorkoutFeedbackSummary
{
    public function build(DailyPlan $plan, DailyPlanProgress $progress): array
    {
        $items = collect($plan->bucketsFor((string)$progress->user_id))->reject(fn($b) => in_array($b['type'] ?? '', ['daily_readiness','player_reflection','coach_notes'], true) || in_array($b['kind'] ?? '', ['survey','note'], true))->flatMap(fn($b) => $b['items'] ?? []);
        $required = $items->filter(fn($i) => ($i['required'] ?? true) !== false);
        $counted = $required->isNotEmpty() ? $required : $items;
        $actual = $progress->items ?? [];
        $done = $counted->filter(fn($i) => !empty($actual[$i['id']]['done']))->count();
        $readiness = $progress->readiness ?? [];
        $reflection = $progress->reflection ?? [];
        $check = function(array $answers, array $keys): array {
            $answered = count(array_filter($keys, fn($k) => isset($answers[$k]) && $answers[$k] !== ''));
            return ['status' => $answered === count($keys) ? 'received' : ($answered ? 'partial' : 'missing'), 'answered' => $answered, 'expected' => count($keys)];
        };
        $checks = [
            'readiness' => $check($readiness, ['sleep_hours','sleep_quality','energy','overall_soreness','arm_soreness','shoulder_soreness','elbow_soreness','lower_body_soreness','stress','motivation','pain_flag']),
            'reflection' => $check($reflection, ['workout_rating','session_rpe']),
        ];
        $reasons = array_merge(app(PlannerWellnessService::class)->readiness($readiness)['alerts'], app(PlannerWellnessService::class)->post($progress->post_training ?? [])['alerts']);
        if (($reflection['pain_after'] ?? 0) >= 4) $reasons[] = 'Pain reported after training';
        if (isset($reflection['workout_rating']) && $reflection['workout_rating'] !== '' && $reflection['workout_rating'] <= 2) $reasons[] = 'Low workout rating';
        if (($reflection['session_rpe'] ?? 0) >= 9) $reasons[] = 'High reported effort';
        if (collect($actual)->contains(fn($i) => !empty($i['pain']))) $reasons[] = 'Discomfort reported during a drill';
        if ($progress->completed_at) {
            if ($done < $counted->count()) $reasons[] = 'Submitted with unfinished drills';
            foreach ($checks as $name => $check) if ($check['status'] !== 'received') $reasons[] = ucfirst($name).' '.$check['status'];
        }
        return [
            'strength_results' => $items->filter(fn($item) => isset($actual[$item['id']]['strength_summary']))->map(fn($item) => ['item_id'=>$item['id'], 'name'=>$item['name'] ?? 'Exercise', 'summary'=>$actual[$item['id']]['strength_summary']])->values()->all(),
            'skill_results' => $items->map(fn($item) => ['item_id'=>$item['id'], 'name'=>$item['name'] ?? 'Drill'] + app(WorkoutSkillResults::class)->summary($actual[$item['id']] ?? []))->values()->all(),
            'submission_status' => $progress->completed_at ? 'submitted' : ($progress->started_at || $done ? 'in_progress' : 'not_started'),
            'completed_drills' => $done, 'counted_drills' => $counted->count(),
            'completion_pct' => $counted->count() ? (int)round(100*$done/$counted->count()) : null,
            'checks' => $checks, 'attention_reasons' => array_values(array_unique($reasons)),
            'review_status' => ($progress->coach_review['reviewed'] ?? false) ? 'reviewed' : 'awaiting_review',
            'coach_feedback' => $progress->coach_review['feedback'] ?? '',
        ];
    }
}
