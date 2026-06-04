/**
 * Strength Assessment Score — FMTRX
 *
 * Inputs are raw roster metrics (lbs / reps / seconds / scores).
 * Each metric is mapped to a normalized 0–100 score using anchor ranges,
 * then combined into section and overall scores.
 *
 * Formula:
 *   Strength Base (50%): front squat 25% · back squat 25% · deadlift 30% · bench 20%
 *   Power (20%): power clean 70% · hand strength 30%
 *   Speed (20%): 40 time 45% · 60 time 55%
 *   Recovery/Mobility (10%): sleep hours 20% · sleep quality 20% · recovery 30% · mobility 30%
 */

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

export function getStrengthLabel(score) {
  if (score >= 90) return 'Elite';
  if (score >= 80) return 'Advanced';
  if (score >= 70) return 'Strong';
  if (score >= 60) return 'Developing';
  if (score >= 50) return 'Needs Work';
  return 'Foundation Needed';
}

export function computeStrengthAssessmentScore(input = {}) {
  const pick = (...keys) => {
    for (const k of keys) {
      const n = toNum(input?.[k]);
      if (n !== null) return n;
    }
    return null;
  };

  const bw = pick('body_weight_lbs', 'body_weight', 'weight');

  const front = pick('front_squat_lbs', 'front_squat');
  const back = pick('back_squat_lbs', 'back_squat');
  const dead = pick('dead_lift_lbs', 'dead_lift', 'deadlift');
  const bench = pick('bench_press_lbs', 'bench_press');
  const clean = pick('power_clean_lbs', 'power_clean');
  const hand = pick('hand_strength_lbs', 'hand_strength');

  const t40 = pick('yd_40_dash_sec', 'yd_40_dash', 'dash_40');
  const t60 = pick('yd_60_dash_sec', 'yd_60_dash', 'dash_60');

  const sleepHours = pick('sleep_hours');
  const sleepQuality = pick('sleep_quality_1_to_5', 'sleep_quality');
  const recovery = pick('recovery_score');
  const mobility = pick('mobility_score');

  const frontRatio = bw && bw > 0 && front ? front / bw : null;
  const backRatio = bw && bw > 0 && back ? back / bw : null;
  const deadRatio = bw && bw > 0 && dead ? dead / bw : null;
  const benchRatio = bw && bw > 0 && bench ? bench / bw : null;
  const cleanRatio = bw && bw > 0 && clean ? clean / bw : null;

  const frontScore = mapHigherBetter(frontRatio, [[0.8, 25], [1.0, 45], [1.2, 62], [1.4, 78], [1.6, 92], [1.9, 100]]);
  const backScore = mapHigherBetter(backRatio, [[1.0, 25], [1.2, 45], [1.5, 62], [1.8, 78], [2.1, 92], [2.4, 100]]);
  const deadScore = mapHigherBetter(deadRatio, [[1.2, 25], [1.5, 45], [1.8, 62], [2.1, 78], [2.5, 92], [2.9, 100]]);
  const benchScore = mapHigherBetter(benchRatio, [[0.6, 25], [0.8, 45], [1.0, 62], [1.2, 78], [1.4, 92], [1.6, 100]]);
  const cleanScore = mapHigherBetter(cleanRatio, [[0.5, 25], [0.7, 45], [0.9, 62], [1.1, 78], [1.3, 92], [1.5, 100]]);

  const handScore = mapHigherBetter(hand, [[45, 25], [60, 45], [75, 62], [90, 78], [105, 92], [120, 100]]);

  const dash40Score = mapLowerBetter(t40, [[4.4, 100], [4.6, 92], [4.8, 78], [5.0, 62], [5.2, 45], [5.5, 25]]);
  const dash60Score = mapLowerBetter(t60, [[6.3, 100], [6.5, 92], [6.7, 78], [6.9, 62], [7.2, 45], [7.6, 25]]);

  const sleepHoursScore = mapHigherBetter(sleepHours, [[5.0, 30], [6.0, 50], [7.0, 72], [8.0, 90], [9.0, 100]]);
  const sleepQualityScore = mapHigherBetter(sleepQuality, [[1, 20], [2, 40], [3, 65], [4, 85], [5, 100]]);
  const recoveryScoreNorm = mapHigherBetter(recovery, [[40, 30], [55, 50], [70, 70], [85, 90], [95, 100]]);
  const mobilityScoreNorm = mapHigherBetter(mobility, [[40, 30], [55, 50], [70, 70], [85, 90], [95, 100]]);

  const lowerBody = clamp(frontScore * 0.25 + backScore * 0.25 + deadScore * 0.30 + benchScore * 0.20);
  const upperBody = clamp(benchScore * 0.70 + handScore * 0.30);
  const explosivePower = clamp(cleanScore * 0.70 + handScore * 0.30);
  const rotationalPower = clamp(dash40Score * 0.45 + dash60Score * 0.55);
  const readinessScore = clamp(sleepHoursScore * 0.20 + sleepQualityScore * 0.20 + recoveryScoreNorm * 0.30 + mobilityScoreNorm * 0.30);

  const overall = clamp(
    lowerBody      * 0.50 +
    explosivePower * 0.20 +
    rotationalPower * 0.20 +
    readinessScore * 0.10,
  );

  const hasData = [
    input.body_weight_lbs,
    input.front_squat_lbs,
    input.back_squat_lbs,
    input.dead_lift_lbs,
    input.bench_press_lbs,
    input.power_clean_lbs,
    input.hand_strength_lbs,
    input.yd_40_dash_sec,
    input.yd_60_dash_sec,
    input.sleep_hours,
    input.sleep_quality_1_to_5,
    input.recovery_score,
    input.mobility_score,
  ].some((v) => toNum(v) !== null && toNum(v) > 0);

  return {
    score:  Math.round(overall),
    hasData,
    parts: {
      lowerBody:      Math.round(lowerBody),
      upperBody:      Math.round(upperBody),
      explosivePower: Math.round(explosivePower),
      rotationalPower: Math.round(rotationalPower),
      readiness: Math.round(readinessScore),
    },
    labels: {
      lowerBody:      getStrengthLabel(lowerBody),
      upperBody:      getStrengthLabel(upperBody),
      explosivePower: getStrengthLabel(explosivePower),
      rotationalPower: getStrengthLabel(rotationalPower),
      readiness: getStrengthLabel(readinessScore),
      overall:        getStrengthLabel(overall),
    },
  };
}

export default computeStrengthAssessmentScore;
