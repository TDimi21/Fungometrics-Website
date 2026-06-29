import { describe, it, expect } from 'vitest';
import {
  extractScores, classifyPlayerType, buildStrengths, buildLimiters,
  buildPlayerInsights, buildTeamInsight, buildArmAdvisory,
} from '@/features/development/lib/assessmentInsights.js';

describe('extractScores', () => {
  it('never scores missing data as zero (null instead)', () => {
    const s = extractScores({});
    expect(s.hitting).toBeNull();
    expect(s.mobility).toBeNull();
    expect(s.overall).toBeNull();
  });
});

describe('classifyPlayerType', () => {
  it('flags a low-mobility athlete as Mobility Limited', () => {
    const r = { mobility_overall_score: 25, strength_overall_score: 70 };
    expect(classifyPlayerType(extractScores(r), r).type).toBe('Mobility Limited');
  });
  it('flags a high-hitting, mobile, strong athlete as Power Hitter', () => {
    const r = { hitting_score: 85, strength_overall_score: 80, mobility_overall_score: 75 };
    expect(classifyPlayerType(extractScores(r), r).type).toBe('Power Hitter');
  });
});

describe('strengths / limiters', () => {
  const scores = extractScores({
    arm_health_score: 82, hitting_score: 67, strength_overall_score: 66,
    mobility_overall_score: 25, strength_explosive_score: 47,
  });
  it('lists strengths from the highest sections', () => {
    const strengths = buildStrengths(scores).join(' ');
    expect(strengths).toMatch(/Arm Health/);
  });
  it('lists limiters from the lowest sections', () => {
    const limiters = buildLimiters(scores).join(' ');
    expect(limiters).toMatch(/Mobility/);
  });
  it('does not put the same section in both strengths and limiters', () => {
    const strengths = buildStrengths(scores).map((s) => s.split(':')[0]);
    const limiters = buildLimiters(scores).map((s) => s.split(':')[0]);
    expect(strengths.filter((x) => limiters.includes(x))).toHaveLength(0);
  });
});

describe('arm-pain advisory', () => {
  it('advises reducing workload + consulting a medical professional, no diagnosis', () => {
    const msg = buildArmAdvisory({ throwing_workload_data: { arm_pain: 'Yes' } });
    expect(msg).toMatch(/medical professional/i);
    expect(msg).toMatch(/reduce/i);
    expect(buildArmAdvisory({ throwing_workload_data: { arm_pain: 'No' } })).toBeNull();
  });
});

describe('buildPlayerInsights', () => {
  it('produces a 30-day plan with a retest date 30 days out', () => {
    const ins = buildPlayerInsights({ assessment_date: '2026-06-01', mobility_overall_score: 30, strength_overall_score: 55 });
    expect(ins.plan.retestDate).toBe('Jul 1, 2026');
    expect(Array.isArray(ins.plan.priorities)).toBe(true);
  });
});

describe('buildTeamInsight', () => {
  it('returns null for an empty roster', () => {
    expect(buildTeamInsight([])).toBeNull();
  });
  it('builds a practice sentence from the weakest tested category', () => {
    const insight = buildTeamInsight([
      { user_id: 'a', assessment_date: '2026-06-01', overall_score: 60, mobility_overall_score: 25, hip_mobility: 3, strength_overall_score: 70 },
      { user_id: 'b', assessment_date: '2026-06-01', overall_score: 64, mobility_overall_score: 30, strength_overall_score: 72 },
    ]);
    expect(insight.sentence).toMatch(/20 minutes/);
    expect(typeof insight.teamOverall).toBe('number');
  });
});
