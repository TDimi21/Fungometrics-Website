import {describe, it, expect} from 'vitest';
import {trainingDayOffsets} from '../../resources/js/features/workouts/programSchedule';
describe('recurring training dates', () => {
  it('creates exactly three sessions per week starting on a Saturday', () => {
    expect(trainingDayOffsets('2026-10-10', 2, [1,3,5])).toEqual([2,4,6,9,11,13]);
  });
  it('handles year boundaries and Sunday starts', () => {
    expect(trainingDayOffsets('2026-12-27', 2, [0])).toEqual([0,7]);
  });
  it('rejects invalid ranges', () => {
    expect(trainingDayOffsets('', 4, [1])).toEqual([]);
    expect(trainingDayOffsets('2026-10-10', 0, [1])).toEqual([]);
  });
});
