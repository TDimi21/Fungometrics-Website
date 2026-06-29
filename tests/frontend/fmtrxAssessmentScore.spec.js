import { describe, it, expect } from 'vitest';
import {
  computeFmtrxAssessment, scoreLabel, scoreColor, workloadLevel,
} from '@/features/development/lib/fmtrxAssessmentScore.js';

describe('label / colour / workload mappings', () => {
  it('maps scores to labels', () => {
    expect(scoreLabel(95)).toBe('Elite');
    expect(scoreLabel(82)).toBe('Advanced');
    expect(scoreLabel(0)).toBe('—');
  });
  it('maps workload score to a level', () => {
    expect(workloadLevel(80).label).toBe('Very High');
    expect(workloadLevel(40).label).toBe('Moderate');
    expect(workloadLevel(10).label).toBe('Low');
  });
  it('returns a colour for any score', () => {
    expect(scoreColor(90)).toMatch(/^#/);
    expect(scoreColor(null)).toMatch(/^#/);
  });
});

describe('computeFmtrxAssessment', () => {
  it('returns a full breakdown with clamped 0-100 scores', () => {
    const r = computeFmtrxAssessment({ max_exit_velo: 100, fastball_velocity: 90, yd_60_dash_sec: 6.5 });
    for (const k of ['overall', 'athletic', 'hitting', 'pitching', 'armHealth']) {
      expect(r[k]).toBeGreaterThanOrEqual(0);
      expect(r[k]).toBeLessThanOrEqual(100);
    }
  });

  it('scores a high exit-velo hitter above a low one', () => {
    const hi = computeFmtrxAssessment({ max_exit_velo: 100 }).hitting;
    const lo = computeFmtrxAssessment({ max_exit_velo: 65 }).hitting;
    expect(hi).toBeGreaterThan(lo);
  });

  it('leaves mobility null when no mobility data is entered', () => {
    expect(computeFmtrxAssessment({}).mobility).toBeNull();
  });
});
