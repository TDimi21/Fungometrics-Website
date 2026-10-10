import {describe, it, expect} from 'vitest';
import {workoutFeedback} from '../../resources/js/features/planner/lib/workoutProgress';
const plan = {buckets: [{type:'hitting',items:[{id:'required'},{id:'optional',required:false}]}]};
describe('workout feedback fallback', () => {
  it('separates submission from completed drills and reports missing check-ins', () => {
    const result = workoutFeedback(plan, {completedAt:'2026-10-10',items:{optional:{done:true}}});
    expect(result.submission_status).toBe('submitted');
    expect(result.completion_pct).toBe(0);
    expect(result.counted_drills).toBe(1);
    expect(result.attention_reasons).toContain('Submitted with unfinished drills');
    expect(result.checks.reflection.status).toBe('missing');
  });
  it('distinguishes partial check-ins from missing ones and accepts zero/false answers', () => {
    const result = workoutFeedback(plan, {readiness:{arm_soreness:0,pain_flag:false},reflection:{workout_rating:4}});
    expect(result.checks.readiness.answered).toBe(2);
    expect(result.checks.readiness.status).toBe('partial');
    expect(result.checks.reflection.status).toBe('partial');
  });
  it('uses the server summary except while local changes await sync', () => {
    const summary = {submission_status:'submitted'};
    expect(workoutFeedback(plan,{feedbackSummary:summary})).toBe(summary);
    expect(workoutFeedback(plan,{feedbackSummary:summary,pendingSync:true}).submission_status).toBe('not_started');
  });
});
