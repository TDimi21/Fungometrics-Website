/**
 * FMTRX Baseline Assessment Score — full parity with the mobile app.
 *
 * Ports the mobile app's AssessmentScreen scoring so the web computes the exact
 * same sections and overall (athletic, strength/power/speed/baseball, mobility,
 * hitting, pitching, throwing workload, arm health, and the FMTRX Baseline).
 *
 * Inputs are accepted under both the app's form keys (e.g. `vertical_jump`) and
 * the web's suffixed keys (e.g. `vertical_jump_inches`) via the pick() helper.
 */

import { computeStrengthAssessmentScore } from './strengthAssessmentScore.js';

const toNum = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

const lerp = (x, x0, x1, y0, y1) => {
  if (x1 === x0) return y0;
  return y0 + ((x - x0) / (x1 - x0)) * (y1 - y0);
};

const mapHigherBetter = (value, anchors) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return 0;
  const pts = (anchors || []).filter((p) => Array.isArray(p) && p.length === 2).sort((a, b) => a[0] - b[0]);
  if (!pts.length) return 0;
  if (value <= pts[0][0]) return clamp(pts[0][1]);
  for (let i = 1; i < pts.length; i++) {
    const [x1, y1] = pts[i];
    const [x0, y0] = pts[i - 1];
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1));
  }
  return clamp(pts[pts.length - 1][1]);
};

const mapLowerBetter = (value, anchors) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return 0;
  const pts = (anchors || []).filter((p) => Array.isArray(p) && p.length === 2).sort((a, b) => a[0] - b[0]);
  if (!pts.length) return 0;
  if (value <= pts[0][0]) return clamp(pts[0][1]);
  for (let i = 1; i < pts.length; i++) {
    const [x1, y1] = pts[i];
    const [x0, y0] = pts[i - 1];
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1));
  }
  return clamp(pts[pts.length - 1][1]);
};

// ── Option scales (must match the app's ChoicePillRow option order) ──────────
export const throwsPerDayOptions = ['Less than 25', '25-50', '51-75', '76-100', '101-125', '126-150', '150+'];
export const pitchCountOptions = ['0', '1-25', '26-50', '51-75', '76-100', '101-125', '126-150', '150+'];
export const intensityOptions = ['Recovery Catch', 'Light Catch', 'Moderate', 'High Intent', 'Max Effort'];

const optionIndexScore = (value, options) => {
  const idx = options.indexOf(value);
  if (idx < 0 || options.length <= 1) return 0;
  return (idx / (options.length - 1)) * 100;
};

export function workloadLevel(score) {
  if (score >= 78) return { label: 'Very High', color: '#EF4444' };
  if (score >= 58) return { label: 'High', color: '#F97316' };
  if (score >= 35) return { label: 'Moderate', color: '#FACC15' };
  return { label: 'Low', color: '#22C55E' };
}

export function scoreLabel(s) {
  if (!s) return '—';
  if (s >= 90) return 'Elite';
  if (s >= 80) return 'Advanced';
  if (s >= 70) return 'Strong';
  if (s >= 60) return 'Developing';
  if (s >= 40) return 'Needs Work';
  return 'Foundation Needed';
}

export function scoreColor(s) {
  if (s === null || s === undefined) return '#64748B';
  if (s >= 85) return '#2ECC71';
  if (s >= 70) return '#27AE60';
  if (s >= 55) return '#F39C12';
  if (s >= 40) return '#E67E22';
  return '#E74C3C';
}

function computeMobilityScore(pick) {
  const keys = [
    'shoulder_mobility', 'hip_mobility', 'ankle_mobility',
    'hamstring_mobility', 't_spine_rotation', 'overhead_squat', 'single_leg_balance',
  ];
  const vals = keys.map((k) => pick(k)).filter((v) => v !== null && v !== undefined && v !== '');
  if (!vals.length) return null;
  return Math.round((vals.reduce((a, b) => a + Number(b), 0) / vals.length) * 20);
}

/**
 * @param {object} form  Raw assessment inputs (app or web key names).
 * @returns full breakdown matching the app's AssessmentScreen.
 */
export function computeFmtrxAssessment(form = {}) {
  const pick = (...keys) => {
    for (const k of keys) {
      const n = toNum(form?.[k]);
      if (n !== null) return n;
    }
    return null;
  };
  const raw = (...keys) => {
    for (const k of keys) {
      const v = form?.[k];
      if (v !== null && v !== undefined && v !== '') return v;
    }
    return null;
  };

  // Strength / Power / Speed / Baseball — reuse the aligned strength lib.
  const strength = computeStrengthAssessmentScore(form);

  // Athletic (overall athleticism: speed + explosiveness + arm)
  const athleticScore = Math.round(clamp(
    mapLowerBetter(pick('yd_60_dash_sec', 'yd_60_dash'), [[6.3, 100], [6.5, 92], [6.7, 78], [6.9, 62], [7.2, 45], [7.6, 25]]) * 0.35 +
    mapLowerBetter(pick('sprint_10yd_sec', 'sprint_10yd'), [[1.45, 100], [1.55, 92], [1.65, 78], [1.75, 62], [1.90, 45], [2.05, 25]]) * 0.25 +
    mapHigherBetter(pick('broad_jump_inches', 'broad_jump'), [[55, 20], [70, 40], [80, 60], [90, 78], [100, 92], [110, 100]]) * 0.20 +
    mapHigherBetter(pick('throwing_velo_mph', 'throwing_velo'), [[55, 20], [65, 40], [75, 60], [82, 78], [88, 92], [94, 100]]) * 0.20,
  ));

  // Hitting
  const hittingScore = Math.round(clamp(
    mapHigherBetter(pick('max_exit_velo', 'exit_velocity_mph', 'exit_velo'), [[60, 20], [70, 40], [80, 60], [90, 78], [98, 92], [105, 100]]) * 0.35 +
    mapHigherBetter(pick('avg_exit_velo'), [[50, 20], [60, 40], [70, 60], [80, 78], [88, 92], [95, 100]]) * 0.20 +
    clamp(pick('contact_percentage') || 0) * 0.15 +
    clamp(pick('hard_hit_percentage') || 0) * 0.15 +
    (100 - clamp(pick('whiff_percentage') || 0)) * 0.15,
  ));

  // Pitching
  const pitchingScore = Math.round(clamp(
    mapHigherBetter(pick('fastball_velocity', 'pitch_velo_mph', 'pitch_velo'), [[55, 20], [65, 40], [75, 60], [82, 78], [88, 92], [94, 100]]) * 0.45 +
    clamp(pick('strike_percentage') || 0) * 0.30 +
    clamp(pick('command_percentage') || 0) * 0.25,
  ));

  // Throwing workload
  const workloadScore = Math.round(clamp(
    mapHigherBetter(pick('throwing_days_per_week'), [[0, 0], [1, 15], [2, 30], [3, 45], [4, 60], [5, 75], [6, 90], [7, 100]]) * 0.18 +
    optionIndexScore(raw('throws_per_day_range'), throwsPerDayOptions) * 0.16 +
    optionIndexScore(raw('weekly_pitch_count_range'), pitchCountOptions) * 0.18 +
    mapHigherBetter(pick('bullpens_per_week'), [[0, 0], [1, 35], [2, 60], [3, 80], [4, 100]]) * 0.12 +
    mapHigherBetter(pick('long_toss_sessions_per_week'), [[0, 0], [1, 25], [2, 45], [3, 60], [4, 75], [5, 90], [6, 96], [7, 100]]) * 0.10 +
    mapHigherBetter(pick('weighted_ball_sessions_per_week'), [[0, 0], [1, 35], [2, 55], [3, 72], [4, 84], [5, 92], [6, 96], [7, 100]]) * 0.10 +
    optionIndexScore(raw('throwing_intensity'), intensityOptions) * 0.16,
  ));
  const workload = workloadLevel(workloadScore);

  // Arm health
  const armPain = raw('arm_pain');
  const armHealthScore = Math.round(clamp(
    (armPain === 'Yes' ? 35 : armPain === 'No' ? 100 : 70) * 0.22 +
    clamp(pick('recovery_score') || 0) * 0.20 +
    clamp(pick('arm_care_completion') || 0) * 0.18 +
    (100 - clamp(workloadScore)) * 0.25 +
    (100 - mapHigherBetter(pick('arm_soreness'), [[0, 0], [2, 20], [4, 45], [6, 70], [8, 88], [10, 100]])) * 0.15,
  ));

  const mobilityScore = computeMobilityScore(pick);

  // FMTRX Baseline composite
  const overallFMTRXScore = Math.round(clamp(
    athleticScore * 0.18 +
    strength.parts.strength * 0.17 +
    (mobilityScore || 0) * 0.14 +
    hittingScore * 0.17 +
    pitchingScore * 0.17 +
    armHealthScore * 0.17,
  ));

  return {
    overall: overallFMTRXScore,
    athletic: athleticScore,
    strength: strength.parts.strength,
    power: strength.parts.power,
    speed: strength.parts.speed,
    baseball: strength.parts.baseball,
    mobility: mobilityScore,
    hitting: hittingScore,
    pitching: pitchingScore,
    workload: workloadScore,
    workloadLabel: workload.label,
    workloadColor: workload.color,
    armHealth: armHealthScore,
    strengthOverall: strength.score,
  };
}

export default computeFmtrxAssessment;
