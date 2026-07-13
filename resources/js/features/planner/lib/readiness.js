// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — readiness score. (Ported from the mobile app, identical.)
//
// The player fills the Daily Readiness survey; this turns it into a 0–100 score.
//   25% Sleep · 20% Energy · 20% Soreness · 15% Arm health · 10% Stress · 10% Motivation
// Higher = more ready. Soreness / stress are inverted (more soreness → lower).
// ─────────────────────────────────────────────────────────────────────────────

const num = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};
const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

// scale a 1..5 rating to 0..100 (optionally inverted so low rating = high score)
const from5 = (v, invert = false) => {
  const n = num(v);
  if (n == null) return null;
  const pct = ((clamp(n, 1, 5) - 1) / 4) * 100;
  return invert ? 100 - pct : pct;
};
// scale a 0..10 soreness/pain (0 = none) to 0..100 where 0 soreness → 100
const fromSoreness10 = (v) => {
  const n = num(v);
  if (n == null) return null;
  return 100 - (clamp(n, 0, 10) / 10) * 100;
};
const fromSleepHours = (v) => {
  const n = num(v);
  if (n == null) return null;
  // 8h ideal; falls off either side.
  return clamp(100 - Math.abs(n - 8) * 18);
};

const avg = (arr) => {
  const vals = arr.filter((x) => x != null);
  return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
};

export function readinessScore(entry = {}) {
  const sleep = avg([fromSleepHours(entry.sleep_hours), from5(entry.sleep_quality)]);
  const energy = from5(entry.energy);
  const soreness = avg([from5(entry.overall_soreness, true), fromSoreness10(entry.lower_body_soreness)]);
  const armHealth = avg([
    fromSoreness10(entry.arm_soreness),
    fromSoreness10(entry.shoulder_soreness),
    fromSoreness10(entry.elbow_soreness),
  ]);
  const stress = from5(entry.stress, true);
  const motivation = from5(entry.motivation);

  const parts = [
    { v: sleep, w: 0.25 },
    { v: energy, w: 0.20 },
    { v: soreness, w: 0.20 },
    { v: armHealth, w: 0.15 },
    { v: stress, w: 0.10 },
    { v: motivation, w: 0.10 },
  ].filter((p) => p.v != null);

  if (!parts.length) return null;
  const wSum = parts.reduce((a, p) => a + p.w, 0);
  const score = parts.reduce((a, p) => a + p.v * p.w, 0) / wSum;
  return Math.round(clamp(score));
}

export function readinessStatus(score) {
  if (score == null) return { label: '—', color: '#64748B' };
  if (score >= 85) return { label: 'Ready', color: '#22c55e' };
  if (score >= 70) return { label: 'Monitor', color: '#f59e0b' };
  if (score >= 55) return { label: 'Modify', color: '#f97316' };
  return { label: 'Recovery recommended', color: '#ef4444' };
}

// Coach-alert flags (coaching, not medical) per the spec thresholds.
export function readinessFlags(entry = {}, score) {
  const flags = [];
  if (num(entry.arm_soreness) > 3) flags.push('Arm soreness above 3');
  if (num(entry.elbow_soreness) > 2) flags.push('Elbow soreness above 2');
  if (num(entry.shoulder_soreness) > 3) flags.push('Shoulder soreness above 3');
  if (score != null && score < 60) flags.push('Readiness below 60');
  if (entry.pain_flag === true || String(entry.pain_flag).toLowerCase() === 'yes') flags.push('Pain reported');
  return flags;
}
