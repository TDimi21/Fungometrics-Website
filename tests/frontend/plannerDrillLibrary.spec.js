import {describe, it, expect} from 'vitest';
import {SEED_DRILLS, searchDrills, getCategoriesForBucket, addLibraryDrill} from '@/features/planner/lib/plannerDrills';

describe('workout editor full drill library', () => {
  const saved = {id: 'saved', name: 'Coach custom drill', bucket: 'throwing', categoryGroup: 'Team drills', description: 'Follow the chalk target', equipment: 'Blue training ball', defaultThrows: 12};
  it('searches built-in and saved drills across buckets without a 200-result limit', () => {
    const extras = Array.from({length: 250}, (_, i) => ({...saved, id: `saved-${i}`, name: `Saved drill ${i}`}));
    expect(searchDrills('', '', extras)).toHaveLength(SEED_DRILLS.length + 250);
    expect(searchDrills('Saved drill 249', '', extras)[0].id).toBe('saved-249');
    expect(searchDrills('chalk target', '', [saved])).toContain(saved);
    expect(searchDrills('Blue training ball', '', [saved])).toContain(saved);
    expect(searchDrills('Coach custom drill', 'hitting', [saved])).toEqual([]);
    expect(getCategoriesForBucket('', [saved])).toContainEqual({label: 'Team drills', count: 1});
  });
  it('creates the appropriate bucket once and preserves each drill prescription', () => {
    const plan = {buckets: []};
    const bucket = addLibraryDrill(plan, saved);
    addLibraryDrill(plan, saved);
    expect(plan.buckets).toHaveLength(1);
    expect(bucket.type).toBe('throwing');
    expect(bucket.items).toHaveLength(2);
    expect(bucket.items[0]).toMatchObject({drillId: 'saved', throws: 12, instructions: saved.description});
    expect(bucket.items[0].id).not.toBe(bucket.items[1].id);
    const hitting = SEED_DRILLS.find(d => d.bucket === 'hitting');
    addLibraryDrill(plan, hitting);
    expect(plan.buckets.map(b => b.type)).toEqual(['throwing', 'hitting']);
  });
  it('lets a coach add a drill from another category to an explicitly chosen bucket', () => {
    const target = {type: 'movement_prep', items: []};
    const plan = {buckets: [target]};
    expect(addLibraryDrill(plan, saved, target)).toBe(target);
    expect(target.items[0].drillId).toBe('saved');
    expect(plan.buckets).toHaveLength(1);
  });
  it('maps legacy strength drills to primary strength and rejects unknown sections', () => {
    const plan = {buckets: []};
    expect(addLibraryDrill(plan, {...saved, bucket: 'strength'}).type).toBe('strength_primary');
    expect(addLibraryDrill(plan, {...saved, bucket: 'unknown'})).toBeNull();
    expect(plan.buckets).toHaveLength(1);
  });
});
