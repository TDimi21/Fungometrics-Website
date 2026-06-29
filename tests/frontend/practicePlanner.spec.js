import { describe, it, expect } from 'vitest';
import {
  minutesToTimestamp, buildTimeline, scheduledMinutesOf, allBlocksOf, buildShareText,
} from '@/features/practice/practicePlanner.js';

const slots = [
  { id: 's1', blocks: [{ id: 'b1', name: 'Warmup', minutes: 10, group: 'Full Team', focus: 'Warmup' }] },
  { id: 's2', blocks: [
    { id: 'b2', name: 'BP', minutes: 20, group: 'Hitters', focus: 'Hitting' },
    { id: 'b3', name: 'Bullpen', minutes: 15, group: 'Pitchers', focus: 'Pitching' },
  ] },
];

describe('minutesToTimestamp', () => {
  it('formats minutes as h/m', () => {
    expect(minutesToTimestamp(90)).toBe('1h 30m');
    expect(minutesToTimestamp(45)).toBe('45m');
    expect(minutesToTimestamp(60)).toBe('1h 0m');
  });
});

describe('timeline + totals', () => {
  it('parallel blocks take the longest block as the slot duration', () => {
    const tl = buildTimeline(slots);
    expect(tl[0].startLabel).toBe('0:00');
    expect(tl[0].endLabel).toBe('0:10');
    expect(tl[1].duration).toBe(20); // max(20, 15)
    expect(tl[1].endLabel).toBe('0:30');
  });

  it('scheduled minutes sum the per-slot max', () => {
    expect(scheduledMinutesOf(slots)).toBe(30);
  });

  it('counts every block (including parallel stations)', () => {
    expect(allBlocksOf(slots)).toHaveLength(3);
  });
});

describe('buildShareText', () => {
  it('renders a readable plan with the drills', () => {
    const text = buildShareText({ title: 'Tuesday', date: '2026-06-30', focus: 'Mixed', totalDuration: '90', slots });
    expect(text).toMatch(/PRACTICE PLAN — TUESDAY/);
    expect(text).toMatch(/Warmup/);
    expect(text).toMatch(/Total Drills: 3/);
  });
});
