import {describe, it, expect, vi} from 'vitest';
import {createAssignmentSchedule} from '../../resources/js/features/planner/lib/assignmentFlow';

describe('multiple-date assignments', () => {
  it('creates independent plans per unique date', async () => {
    let id = 0;
    const schedule = createAssignmentSchedule(() => String(++id));
    const save = vi.fn().mockResolvedValue(undefined);
    await schedule.publish(['2026-11-12', '2026-11-10', '2026-11-12'], save);
    const rows = save.mock.calls.map(([row]) => row);
    expect(rows.map(row => row.date)).toEqual(['2026-11-10', '2026-11-12']);
    expect(new Set(rows.map(row => row.id)).size).toBe(2);
    expect(new Set(rows.map(row => row.itemId)).size).toBe(2);
  });
  it('retries failed dates with the same IDs without repeating successful dates', async () => {
    let id = 0;
    const schedule = createAssignmentSchedule(() => String(++id));
    const save = vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(new Error('Server Error')).mockResolvedValue(undefined);
    const dates = ['2026-11-10', '2026-11-12', '2026-11-14'];
    await expect(schedule.publish(dates, save)).rejects.toThrow('2026-11-12');
    const failed = save.mock.calls[1][0];
    await schedule.publish(dates, save);
    expect(save.mock.calls.map(([row]) => row.date)).toEqual(['2026-11-10', '2026-11-12', '2026-11-12', '2026-11-14']);
    expect(save.mock.calls[2][0]).toEqual(failed);
  });
});
