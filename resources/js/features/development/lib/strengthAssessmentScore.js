/**
 * Strength Assessment Score — FMTRX
 *
 * Inputs are raw roster metrics (lbs / reps / seconds / scores).
 * Each metric is mapped to a normalized 0–100 score using anchor ranges,
 * then combined into section and overall scores.
 *
 * Formula:
 *   Strength (30%): front squat · back squat · deadlift · bench · pull-ups · push-ups
 *   Power (25%): power clean · vertical jump · broad jump · med-ball rotational throw · hand strength
 *   Speed (20%): 10-yd · 40-yd · 60-yd sprint (lower is better)
 *   Baseball Metrics (25%): exit velo · bat speed · throwing velo · pitch velo
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

  const pushUps = pick('push_ups', 'pushups');
  const pullUps = pick('pull_ups', 'pullups');

  const verticalJump = pick('vertical_jump_inches', 'vertical_jump');
  const broadJump = pick('broad_jump_inches', 'broad_jump');
  const medBall = pick('med_ball_rotational_throw_ft', 'med_ball_rotational_throw', 'med_ball_rotational_ft');

  const t10 = pick('sprint_10yd_sec', 'yd_10_dash_sec', 'yd_10_dash', 'ten_yard');

  const exitVelo = pick('exit_velocity_mph', 'exit_velo', 'exit_velocity');
  const batSpeed = pick('bat_speed_mph', 'bat_speed');
  const throwingVelo = pick('throwing_velo_mph', 'throwing_velo', 'throwing_velocity');
  const pitchVelo = pick('pitch_velo_mph', 'pitch_velo', 'pitch_velocity');

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

  const pushUpsScore = mapHigherBetter(pushUps, [[10, 20], [20, 40], [30, 60], [45, 78], [60, 92], [75, 100]]);
  const pullUpsScore = mapHigherBetter(pullUps, [[1, 20], [4, 40], [7, 60], [11, 78], [16, 92], [22, 100]]);

  const verticalJumpScore = mapHigherBetter(verticalJump, [[12, 20], [16, 40], [20, 60], [24, 78], [28, 92], [32, 100]]);
  const broadJumpScore = mapHigherBetter(broadJump, [[55, 20], [70, 40], [80, 60], [90, 78], [100, 92], [110, 100]]);
  const medBallScore = mapHigherBetter(medBall, [[14, 20], [18, 40], [22, 60], [26, 78], [30, 92], [35, 100]]);

  const dash10Score = mapLowerBetter(t10, [[1.45, 100], [1.55, 92], [1.65, 78], [1.75, 62], [1.90, 45], [2.05, 25]]);
  const dash40Score = mapLowerBetter(t40, [[4.4, 100], [4.6, 92], [4.8, 78], [5.0, 62], [5.2, 45], [5.5, 25]]);
  const dash60Score = mapLowerBetter(t60, [[6.3, 100], [6.5, 92], [6.7, 78], [6.9, 62], [7.2, 45], [7.6, 25]]);

  const exitVeloScore = mapHigherBetter(exitVelo, [[60, 20], [70, 40], [80, 60], [90, 78], [98, 92], [105, 100]]);
  const batSpeedScore = mapHigherBetter(batSpeed, [[50, 20], [60, 40], [68, 60], [75, 78], [82, 92], [88, 100]]);
  const throwingVeloScore = mapHigherBetter(throwingVelo, [[55, 20], [65, 40], [75, 60], [82, 78], [88, 92], [94, 100]]);
  const pitchVeloScore = mapHigherBetter(pitchVelo, [[55, 20], [65, 40], [75, 60], [82, 78], [88, 92], [94, 100]]);

  const strengthScore = clamp(frontScore * 0.20 + backScore * 0.20 + deadScore * 0.22 + benchScore * 0.18 + pullUpsScore * 0.10 + pushUpsScore * 0.10);
  const powerScore = clamp(cleanScore * 0.30 + verticalJumpScore * 0.20 + broadJumpScore * 0.20 + medBallScore * 0.20 + handScore * 0.10);
  const speedScore = clamp(dash10Score * 0.45 + dash40Score * 0.30 + dash60Score * 0.25);
  const baseballScore = clamp(exitVeloScore * 0.30 + batSpeedScore * 0.25 + throwingVeloScore * 0.20 + pitchVeloScore * 0.25);

  const overall = clamp(
    strengthScore * 0.30 +
    powerScore * 0.25 +
    speedScore * 0.20 +
    baseballScore * 0.25,
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
    input.push_ups,
    input.pull_ups,
    input.vertical_jump_inches,
    input.broad_jump_inches,
    input.med_ball_rotational_throw_ft,
    input.sprint_10yd_sec,
    input.exit_velocity_mph,
    input.bat_speed_mph,
    input.throwing_velo_mph,
    input.pitch_velo_mph,
  ].some((v) => toNum(v) !== null && toNum(v) > 0);

  return {
    score:  Math.round(overall),
    hasData,
    parts: {
      strength: Math.round(strengthScore),
      power: Math.round(powerScore),
      speed: Math.round(speedScore),
      baseball: Math.round(baseballScore),
    },
    labels: {
      strength: getStrengthLabel(strengthScore),
      power: getStrengthLabel(powerScore),
      speed: getStrengthLabel(speedScore),
      baseball: getStrengthLabel(baseballScore),
      overall:        getStrengthLabel(overall),
    },
  };
}

export default computeStrengthAssessmentScore;
