/**
 * Strength Assessment Score — FMTRX
 *
 * Inputs are 0-100 percentile values entered by the coach.
 * No raw measurements required; the coach maps each test result
 * to a 0–100 percentile using team/age-group norms.
 *
 * Formula (simplified weight-room edition):
 *   Lower Body  (30%): squat 60% · deadlift 25% · lunge 15%
 *   Upper Body  (20%): bench 50% · pull-up 25% · push-up 25%
 *   Explosive   (25%): broad jump 40% · vertical jump 40% · 10-yd sprint 20%
 *   Rotational  (25%): med ball rotational throw 60% · exit velo 25% · bat speed 15%
 */

const safe = (v) => {
  const n = Number(v);
  return Number.isFinite(n) && n >= 0 ? Math.min(100, n) : 0;
};

const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

export function getStrengthLabel(score) {
  if (score >= 90) return 'Elite';
  if (score >= 80) return 'Advanced';
  if (score >= 70) return 'Strong';
  if (score >= 60) return 'Developing';
  if (score >= 50) return 'Needs Work';
  return 'Foundation Needed';
}

export function computeStrengthAssessmentScore(input = {}) {
  const sq  = safe(input.squat_percentile);
  const dl  = safe(input.deadlift_percentile);
  const lng = safe(input.lunge_percentile);

  const bp  = safe(input.bench_press_percentile);
  const pu  = safe(input.pull_up_percentile);
  const psh = safe(input.push_up_percentile);

  const bj  = safe(input.broad_jump_percentile);
  const vj  = safe(input.vertical_jump_percentile);
  const sp  = safe(input.sprint_10yd_percentile);

  const mb  = safe(input.med_ball_rotational_percentile);
  const ev  = safe(input.exit_velocity_percentile);
  const bs  = safe(input.bat_speed_percentile);

  const lowerBody      = clamp(sq * 0.60 + dl * 0.25 + lng * 0.15);
  const upperBody      = clamp(bp * 0.50 + pu * 0.25 + psh * 0.25);
  const explosivePower = clamp(bj * 0.40 + vj * 0.40 + sp * 0.20);
  const rotationalPower = clamp(mb * 0.60 + ev * 0.25 + bs * 0.15);

  const overall = clamp(
    lowerBody      * 0.30 +
    upperBody      * 0.20 +
    explosivePower * 0.25 +
    rotationalPower * 0.25,
  );

  const hasData = sq > 0 || bp > 0 || bj > 0 || mb > 0;

  return {
    score:  Math.round(overall),
    hasData,
    parts: {
      lowerBody:      Math.round(lowerBody),
      upperBody:      Math.round(upperBody),
      explosivePower: Math.round(explosivePower),
      rotationalPower: Math.round(rotationalPower),
    },
    labels: {
      lowerBody:      getStrengthLabel(lowerBody),
      upperBody:      getStrengthLabel(upperBody),
      explosivePower: getStrengthLabel(explosivePower),
      rotationalPower: getStrengthLabel(rotationalPower),
      overall:        getStrengthLabel(overall),
    },
  };
}

export default computeStrengthAssessmentScore;
