<?php

namespace App\Services\Planner;

use App\Models\{DailyPlanAssignment, DailyPlanProgress};
use Carbon\Carbon;

final class WorkoutWeeklySummary
{
    public function build(string $playerId, Carbon $week, ?array $teamIds = null): array
    {
        $start = $week->copy()->startOfWeek(Carbon::MONDAY);
        $from = $start->copy()->subWeek()->toDateString();
        $to = $start->copy()->endOfWeek()->toDateString();
        $assignments = DailyPlanAssignment::with('plan.assignments')->where('user_id', $playerId)
            ->where('schedule_status', 'active')
            ->whereHas('plan', function ($q) use ($teamIds) {
                $q->where('status', 'published');
                if ($teamIds !== null) $q->whereIn('team_id', $teamIds);
            })
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('scheduled_date', [$from, $to])->orWhere(function ($q) use ($from, $to) {
                    $q->whereNull('scheduled_date')->whereHas('plan', fn($p) => $p->whereBetween('date', [$from, $to]));
                });
            })->get()->unique('plan_id');
        $progress = DailyPlanProgress::where('user_id', $playerId)->whereIn('plan_id', $assignments->pluck('plan_id'))->get()->keyBy('plan_id');
        $summarize = function ($rows) use ($playerId, $progress) {
            $totals = ['assigned'=>0, 'submitted'=>0, 'fully_completed'=>0, 'completed_drills'=>0, 'counted_drills'=>0, 'readiness_received'=>0, 'reflection_received'=>0, 'reviewed'=>0];
            $values = []; $pairs = []; $sessions = [];
            foreach ($rows as $assignment) {
                $plan = $assignment->plan;
                $pr = $progress->get($plan->id) ?? new DailyPlanProgress(['user_id'=>$playerId]);
                $summary = app(WorkoutFeedbackSummary::class)->build($plan, $pr);
                $totals['assigned']++;
                $totals['submitted'] += $summary['submission_status'] === 'submitted' ? 1 : 0;
                $totals['fully_completed'] += $summary['counted_drills'] > 0 && $summary['completed_drills'] === $summary['counted_drills'] ? 1 : 0;
                foreach (['completed_drills','counted_drills'] as $key) $totals[$key] += $summary[$key];
                foreach (['readiness','reflection'] as $key) $totals[$key.'_received'] += $summary['checks'][$key]['status'] === 'received' ? 1 : 0;
                $totals['reviewed'] += $summary['review_status'] === 'reviewed' ? 1 : 0;
                foreach ($summary['skill_results'] as $result) {
                    if ($result['session_id']) { $sessions[$result['session_id']] = true; continue; }
                    $p = $result['recorded'];
                    foreach (['swings','contacts','hard_contacts','throws','pitches','strikes','attempts','successful_reps','errors'] as $key) {
                        if (isset($p[$key]) && $p[$key] !== '') $values[$key] = ($values[$key] ?? 0) + $p[$key];
                    }
                    foreach (['contact_pct'=>['contacts','swings'], 'strike_pct'=>['strikes','pitches'], 'defensive_success_pct'=>['successful_reps','attempts']] as $key => [$part,$total]) {
                        if (isset($p[$part], $p[$total]) && $p[$part] !== '' && $p[$total] > 0) {
                            $pairs[$key][0] = ($pairs[$key][0] ?? 0) + $p[$part];
                            $pairs[$key][1] = ($pairs[$key][1] ?? 0) + $p[$total];
                        }
                    }
                }
                foreach ($summary['strength_results'] as $result) {
                    foreach (['completed_sets','volume_lb'] as $key) $values[$key] = ($values[$key] ?? 0) + ($result['summary'][$key] ?? 0);
                }
            }
            foreach ($pairs as $key => [$part,$total]) $values[$key] = round(100 * $part / $total, 1);
            return $totals + ['completion_pct'=>$totals['counted_drills'] ? round(100*$totals['completed_drills']/$totals['counted_drills'],1) : null, 'results'=>$values, 'rate_samples'=>$pairs, 'linked_sessions'=>count($sessions)];
        };
        $date = fn($a) => ($a->scheduled_date ?? $a->plan->date)->format('Y-m-d');
        $current = $summarize($assignments->filter(fn($a) => $date($a) >= $start->toDateString()));
        $previous = $summarize($assignments->filter(fn($a) => $date($a) < $start->toDateString()));
        $labels = ['swings'=>'Hitting swings', 'contacts'=>'Contacts', 'hard_contacts'=>'Hard contacts', 'contact_pct'=>'Contact %', 'throws'=>'Throws', 'pitches'=>'Pitches', 'strikes'=>'Strikes', 'strike_pct'=>'Strike %', 'attempts'=>'Defensive attempts', 'successful_reps'=>'Successful defensive reps', 'errors'=>'Defensive errors', 'defensive_success_pct'=>'Defensive success %', 'completed_sets'=>'Completed strength sets', 'volume_lb'=>'Strength volume (lb × reps)'];
        $metrics = [];
        foreach ($labels as $key=>$label) $metrics[] = ['key'=>$key, 'label'=>$label, 'current'=>$current['results'][$key] ?? null, 'previous'=>$previous['results'][$key] ?? null, 'current_sample'=>$current['rate_samples'][$key] ?? null, 'previous_sample'=>$previous['rate_samples'][$key] ?? null];
        return ['week_start'=>$start->toDateString(), 'week_end'=>$to, 'current'=>$current, 'previous'=>$previous, 'metrics'=>$metrics,
            'note'=>'Based on saved results for active, published assignments scheduled in each week. Linked sessions are counted separately; their measurements are not added to manual totals. Percentages use only drills with both counts recorded. Changes in workload are not a development score.'];
    }
}
