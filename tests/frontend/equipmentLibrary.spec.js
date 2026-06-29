import { describe, it, expect } from 'vitest';
import { drillRunnable, EQUIPMENT_ALL } from '@/features/practice/equipmentLibrary.js';

describe('drillRunnable', () => {
  it('is always runnable when the drill needs no special equipment', () => {
    expect(drillRunnable({ equipmentTags: [] }, [])).toBe(true);
    expect(drillRunnable({}, [])).toBe(true);
  });

  it('requires every tag to be available', () => {
    const drill = { equipmentTags: ['Machine', 'Radar'] };
    expect(drillRunnable(drill, ['Machine'])).toBe(false);
    expect(drillRunnable(drill, ['Machine', 'Radar'])).toBe(true);
  });

  it('hides a machine drill when no machine is available', () => {
    expect(drillRunnable({ equipmentTags: ['Machine'] }, ['Tee', 'Front Toss'])).toBe(false);
  });

  it('accepts a Set or an array for availability', () => {
    expect(drillRunnable({ equipmentTags: ['Tee'] }, new Set(['Tee']))).toBe(true);
  });

  it('everything is runnable when all equipment is available', () => {
    expect(drillRunnable({ equipmentTags: ['Machine', 'Radar', 'Tee'] }, EQUIPMENT_ALL)).toBe(true);
  });
});
