import {describe, it, expect} from 'vitest';
import {workoutTiming, sectionMinutes} from '@/features/planner/lib/workoutTiming';
import {planToApi, planFromApi} from '@/features/planner/dailyPlanner';

describe('workout timing', () => {
  const makePlan = () => ({startTime: '15:00', settings: {post_check_required: true}, buckets: [
    {type: 'movement_prep', items: [{plannedMinutes: 5}, {durationSec: 120}]},
    {type: 'throwing', durationMinutes: 20, items: [{plannedMinutes: 8}]},
    {type: 'recovery', items: [{plannedMinutes: 3}]},
  ]});
  it('cascades drill totals and section overrides from a single start time', () => {
    const timing = workoutTiming(makePlan());
    expect(timing.minutes).toBe(30);
    expect(timing.sections.map(s => [s.startTime, s.endTime])).toEqual([['15:00','15:07'],['15:07','15:27'],['15:27','15:30']]);
    const plan = makePlan();
    plan.buckets[0].items[0].plannedMinutes = 10;
    expect(workoutTiming(plan).endTime).toBe('15:35');
    plan.startTime = '16:00';
    expect(workoutTiming(plan).endTime).toBe('16:35');
    plan.buckets.splice(1, 1);
    expect(workoutTiming(plan).endTime).toBe('16:15');
  });
  it('preserves automatic calculation and unrelated settings through API save/reload', () => {
    const saved = planToApi(makePlan(), 'team');
    expect(saved.settings).toEqual({post_check_required: true, workout_start_time: '15:00'});
    expect(saved.estimated_minutes).toBe(30);
    expect(saved.buckets[1].startTime).toBe('15:07');
    const restored = planFromApi(saved);
    restored.buckets[0].items[0].plannedMinutes = 10;
    expect(workoutTiming(restored).endTime).toBe('15:35');
    expect(restored.buckets[0].durationMinutes).toBeUndefined();
  });
  it('handles midnight and clearing the start time without retaining old times', () => {
    const plan = makePlan(); plan.startTime = '23:45';
    expect(workoutTiming(plan)).toMatchObject({endTime: '00:15', endDayOffset: 1});
    plan.startTime = '';
    expect(planToApi(plan).buckets.every(b => b.startTime === '' && b.endTime === '')).toBe(true);
    expect(workoutTiming(plan).minutes).toBe(30);
  });
  it('uses existing drill durations, explicit zero, and four minutes only for untimed drills', () => {
    expect(sectionMinutes({items: [{durationSec: 90}, {plannedMinutes: 0}, {}]})).toBe(6);
    expect(sectionMinutes({durationMinutes: 0, items: [{}]})).toBe(0);
    expect(sectionMinutes({durationMinutes: '', items: [{}]})).toBe(4);
    expect(sectionMinutes({items: []})).toBe(0);
  });
  it('retains the duration of legacy manually timed sections', () => {
    const restored = planFromApi({buckets: [{startTime: '10:00', endTime: '10:15', items: []}]});
    expect(restored.startTime).toBe('10:00');
    expect(restored.buckets[0].durationMinutes).toBe(15);
  });
});
