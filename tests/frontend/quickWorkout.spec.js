import {describe, it, expect} from 'vitest';
import {preloadQuickWorkout, quickWorkoutKind} from '../../resources/js/features/planner/lib/quickWorkout';
const template = {
  id: 'hitting-a', version: 1, name: 'Hybrid A — Power', is_premade: true,
  category: 'hitting', description: 'Power work',
  sections: [{section_type: 'hitting', name: 'Hitting', instructions: 'Full reset', exercises: [
    {id: 'tee', exercise_name: 'Tee', sets_min: 3, sets_max: 3, prescription_text: '6 swings per set', metadata: {track_metric: 'exit_velocity_mph'}},
  ]}],
};
describe('quick loading a premade into the selected day', () => {
  it('preserves day, assignments and start time while loading prescriptions and surveys', () => {
    const plan = {id: 'draft', name: '', date: '2026-11-14', startTime: '14:30', assignedPlayerIds: ['player'], buckets: []};
    const loaded = preloadQuickWorkout(plan, template, () => 'item');
    expect(loaded).toMatchObject({id: 'draft', date: plan.date, startTime: plan.startTime, assignedPlayerIds: ['player'], name: template.name});
    expect(loaded.buckets.map(b => b.type)).toEqual(['daily_readiness', 'hitting', 'player_reflection']);
    expect(loaded.buckets[1].items[0]).toMatchObject({sets: 3, prescription_text: '6 swings per set', metadata: {track_metric: 'exit_velocity_mph'}, template_exercise_id: 'tee'});
    expect(plan.buckets).toEqual([]);
  });
  it('keeps coach edits and does not duplicate drills when loaded again', () => {
    const plan = {name: 'My day', buckets: [{type: 'hitting', items: [{id: 'custom', name: 'Custom drill'}], note: 'Coach note'}]};
    const first = preloadQuickWorkout(plan, template, () => 'item');
    first.buckets[1].items[1].sets = 4;
    const second = preloadQuickWorkout(first, template, () => 'unused');
    expect(second).toEqual(first);
    expect(second.name).toBe('My day');
    expect(second.buckets[1].items).toHaveLength(2);
    expect(second.buckets[1].note).toBe('Coach note\n\nFull reset');
  });
  it('groups premades by discipline and saved workouts under custom', () => {
    expect(quickWorkoutKind(template)).toBe('hitting');
    expect(quickWorkoutKind({is_premade: true, category: 'Pitching / Velocity'})).toBe('pitching');
    expect(quickWorkoutKind({...template, is_premade: false})).toBe('custom');
    expect(quickWorkoutKind({is_premade: false, category: 'Strength'})).toBe('custom');
  });
});
