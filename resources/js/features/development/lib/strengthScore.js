const safe = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

export function computeStrengthScore(input = {}) {
  const lowerBodyStrength = (
    safe(input.back_squat) * 0.35 +
    safe(input.front_squat) * 0.25 +
    safe(input.trap_bar_deadlift) * 0.40
  ) / 3;

  const upperBodyStrength = (
    safe(input.bench_press) * 0.55 +
    safe(input.pull_ups) * 10 * 0.45
  ) / 2;

  const explosivePower = (
    safe(input.vertical_jump) * 3 +
    safe(input.broad_jump) * 0.65
  ) / 2;

  const rotationalPower = (
    safe(input.med_ball_scoop_toss) * 2.2 +
    safe(input.med_ball_shotput_throw) * 2 +
    safe(input.med_ball_chest_throw) * 1.8
  ) / 3;

  const raw = (
    lowerBodyStrength * 0.35 +
    upperBodyStrength * 0.25 +
    explosivePower * 0.20 +
    rotationalPower * 0.20
  );

  const score = clamp(raw / 3);

  return {
    score: Math.round(score),
    parts: {
      lowerBodyStrength: Math.round(clamp(lowerBodyStrength / 3)),
      upperBodyStrength: Math.round(clamp(upperBodyStrength / 2.4)),
      explosivePower: Math.round(clamp(explosivePower)),
      rotationalPower: Math.round(clamp(rotationalPower)),
    },
  };
}

export default computeStrengthScore;
