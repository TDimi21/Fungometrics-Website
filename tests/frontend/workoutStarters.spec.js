import {describe, it, expect} from 'vitest';
import {WORKOUT_STARTERS, preloadWorkoutSections, removeWorkoutSections} from '../../resources/js/features/planner/lib/workoutStarters';

describe('workout starter sections', () => {
  it.each(WORKOUT_STARTERS)('preloads $label with both surveys and no duplicate sections', starter => {
    const buckets = preloadWorkoutSections([], starter.type);
    expect(buckets.map(b => b.type)).toEqual(['daily_readiness', ...starter.sections, 'player_reflection']);
    expect(preloadWorkoutSections(buckets, starter.type)).toEqual(buckets);
  });
  it('combines types without losing existing prescriptions, notes, or survey data', () => {
    const original = [
      {type: 'daily_readiness', note: 'Check in first', items: []},
      {type: 'hitting', items: [{name: 'Tee', sets: 3}], note: 'Keep this'},
      {type: 'recovery', items: []},
      {type: 'player_reflection', note: 'Reflect', items: []},
    ];
    const result = preloadWorkoutSections(original, 'strength');
    expect(result.find(b => b.type === 'hitting')).toBe(original[1]);
    expect(result[0]).toBe(original[0]);
    expect(result.at(-1)).toBe(original[3]);
    expect(result.at(-2).type).toBe('recovery');
    expect(original).toHaveLength(4);
  });
});

describe('deselecting workout types', () => {
  it('removes empty starter sections while retaining both surveys', () => {
    const buckets = preloadWorkoutSections([], 'hitting');
    expect(removeWorkoutSections(buckets, 'hitting').map(b => b.type)).toEqual(['daily_readiness', 'player_reflection']);
  });
  it('keeps shared sections when another type is selected', () => {
    const buckets = preloadWorkoutSections(preloadWorkoutSections([], 'pitching'), 'defensive');
    expect(removeWorkoutSections(buckets, 'pitching', ['defensive']).map(b => b.type)).toEqual(['daily_readiness', 'movement_prep', 'throwing', 'defense', 'recovery', 'player_reflection']);
  });
  it('preserves drills, coach notes and section scheduling', () => {
    const buckets = preloadWorkoutSections([], 'hitting');
    buckets.find(b => b.type === 'hitting').items = [{name: 'Tee work'}];
    buckets.find(b => b.type === 'movement_prep').note = 'Custom warmup';
    buckets.find(b => b.type === 'recovery').durationMinutes = 5;
    expect(removeWorkoutSections(buckets, 'hitting')).toEqual(buckets);
  });
});
