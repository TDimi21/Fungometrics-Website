import {describe, it, expect} from 'vitest';
import {buildWorkoutCompletionSummary} from '@/features/planner/lib/workoutProgress';
import {progressFromApi} from '@/features/planner/dailyPlanner';
const plan = {id:'workout', buckets:[{type:'throwing', items:Array.from({length:18}, (_,i) => ({id:String(i), required:true}))}]};
describe('coach workout check-in summaries', () => {
  it('does not label an assigned player as started without progress', () => {
    expect(buildWorkoutCompletionSummary(plan, progressFromApi(null,plan.id))).toMatchObject({status:'not_started',completionPct:0,completedItems:0,totalItems:18});
  });
  it('recognizes a started workout even before any task is completed', () => {
    expect(buildWorkoutCompletionSummary(plan, progressFromApi({started_at:'2026-10-10T08:00:00Z'},plan.id))).toMatchObject({status:'in_progress',completionPct:0});
  });
  it('keeps actual task completion separate from submitting the workout', () => {
    const progress = progressFromApi({started_at:'2026-10-10T08:00:00Z',completed_at:'2026-10-10T09:00:00Z',items:{0:{done:true},1:{done:true}}},plan.id);
    expect(buildWorkoutCompletionSummary(plan,progress)).toMatchObject({status:'completed',completionPct:11,completedItems:2,totalItems:18});
  });
});
