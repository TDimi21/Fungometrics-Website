// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — Phase-1 workload summary.
//
// Phase 1 only needs *basic labels* + simple totals for the review card (the
// full throwing-load / strength-load engines come in Phase 3). Everything here
// is a rough estimate a coach can eyeball, not a sports-science measurement.
// ─────────────────────────────────────────────────────────────────────────────

import { INTENSITY_LEVELS } from './plannerBuckets';

const intensityValue = (label) =>
  (INTENSITY_LEVELS.find((l) => l.label === label)?.value) || 3;

const num = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
};

const setCount = (item) =>
  (Array.isArray(item.setList) ? item.setList.length : Math.max(1, num(item.sets)));

// Rough minutes for one item when the coach didn't set an explicit duration.
function itemMinutes(item) {
  if (num(item.durationSec) > 0) return num(item.durationSec) / 60;
  if (item.workloadType === 'strength') return setCount(item) * 1.2; // ~72s / set incl. rest
  const sets = Math.max(1, num(item.sets));
  if (num(item.throws) > 0) return (num(item.throws) * 0.25); // ~15s / throw
  if (num(item.reps) > 0) return sets * 0.75; // ~45s / set
  return 2; // generic task
}

export function summarizePlan(plan) {
  const buckets = plan?.buckets || [];
  let minutes = 0;
  let throws = 0;
  let highIntentThrows = 0;
  let bullpenPitches = 0;
  let strengthSets = 0;
  let items = 0;
  let maxIntensity = 0;

  buckets.forEach((b) => {
    (b.items || []).forEach((it) => {
      items += 1;
      minutes += itemMinutes(it);
      maxIntensity = Math.max(maxIntensity, intensityValue(it.intensity));
      if (it.workloadType === 'throwing') {
        const t = num(it.throws);
        throws += t;
        if (num(it.intent) >= 90) highIntentThrows += t;
        if (b.type === 'pitching') bullpenPitches += t;
      }
      if (it.workloadType === 'strength') {
        strengthSets += setCount(it);
      }
    });
  });

  minutes = Math.round(minutes);

  // Basic label heuristic: driven by throws, high-intent volume, minutes, and
  // the hardest thing on the day.
  let score = 0;
  score += Math.min(40, throws * 0.4);
  score += Math.min(25, highIntentThrows * 2.5);
  score += Math.min(20, minutes * 0.18);
  score += maxIntensity * 3; // up to 15
  let label = 'Low';
  if (score >= 70) label = 'Very High';
  else if (score >= 52) label = 'High';
  else if (score >= 32) label = 'Moderate';
  else if (score >= 14) label = 'Low';
  else label = 'Recovery';

  return {
    minutes,
    throws,
    highIntentThrows,
    bullpenPitches,
    strengthSets,
    items,
    workloadLabel: label,
  };
}
