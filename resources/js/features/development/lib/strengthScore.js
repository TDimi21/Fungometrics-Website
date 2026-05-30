const toNum = (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
};

const clamp = (v, min = 0, max = 100) => Math.max(min, Math.min(max, v));

const lerp = (x, x0, x1, y0, y1) => {
  if (x1 === x0) return y0;
  return y0 + ((x - x0) / (x1 - x0)) * (y1 - y0);
};

const mapHigherBetter = (value, anchors = []) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return null;
  const pts = anchors
    .filter((p) => Array.isArray(p) && p.length === 2)
    .sort((a, b) => a[0] - b[0]);
  if (!pts.length) return null;
  if (value <= pts[0][0]) return clamp(pts[0][1], 0, 100);
  for (let i = 1; i < pts.length; i += 1) {
    const [x1, y1] = pts[i];
    const [x0, y0] = pts[i - 1];
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1), 0, 100);
  }
  return 100;
};

const mapLowerBetter = (value, anchors = []) => {
  if (value === null || value === undefined || !Number.isFinite(value) || value <= 0) return null;
  const pts = anchors
    .filter((p) => Array.isArray(p) && p.length === 2)
    .sort((a, b) => a[0] - b[0]);
  if (!pts.length) return null;
  if (value <= pts[0][0]) return clamp(pts[0][1], 0, 100);
  for (let i = 1; i < pts.length; i += 1) {
    const [x1, y1] = pts[i];
    const [x0, y0] = pts[i - 1];
    if (value <= x1) return clamp(lerp(value, x0, x1, y0, y1), 0, 100);
  }
  return clamp(pts[pts.length - 1][1], 0, 100);
};

const weightedAverage = (items = [], fallback = 0) => {
  const valid = items.filter((x) => x && Number.isFinite(x.value));
  if (!valid.length) return fallback;
  const wSum = valid.reduce((s, x) => s + (x.weight || 0), 0);
  if (!wSum) return fallback;
  return valid.reduce((s, x) => s + (x.value * (x.weight || 0)), 0) / wSum;
};

export function computeStrengthScore(input = {}) {
  const bodyWeight = toNum(input.body_weight ?? input.weight ?? input.bw);
  if (!bodyWeight || bodyWeight <= 0) {
    return {
      score: 0,
      parts: {
        power: 0,
        strength: 0,
        speed: 0,
        pwo: 0,
      },
    };
  }

  const bench = toNum(input.bench_press) || 0;
  const dead = toNum(input.dead_lift ?? input.trap_bar_deadlift) || 0;
  const backSq = toNum(input.back_squat) || 0;
  const frontSq = toNum(input.front_squat) || 0;
  const clean = toNum(input.power_clean) || 0;
  const dash40 = toNum(input.yd_40_dash ?? input.sprint_40_time);
  const dash60 = toNum(input.yd_60_dash ?? input.sprint_time);

  const cleanRatio = clean > 0 ? clean / bodyWeight : null;
  const deadRatio = dead > 0 ? dead / bodyWeight : null;
  const backRatio = backSq > 0 ? backSq / bodyWeight : null;
  const frontRatio = frontSq > 0 ? frontSq / bodyWeight : null;
  const benchRatio = bench > 0 ? bench / bodyWeight : null;

  const cleanScore = mapHigherBetter(cleanRatio, [[0.8, 30], [1.0, 55], [1.2, 78], [1.35, 90], [1.5, 100]]);
  const deadScore = mapHigherBetter(deadRatio, [[1.5, 35], [2.0, 60], [2.5, 85], [3.0, 100]]);
  const backScore = mapHigherBetter(backRatio, [[1.2, 35], [1.6, 60], [2.0, 82], [2.5, 100]]);
  const frontScore = mapHigherBetter(frontRatio, [[1.0, 40], [1.3, 62], [1.5, 78], [2.0, 100]]);
  const benchScore = mapHigherBetter(benchRatio, [[0.9, 40], [1.1, 58], [1.3, 76], [1.5, 90], [1.7, 100]]);
  const dash60Score = mapLowerBetter(dash60, [[6.3, 100], [6.5, 92], [6.6, 84], [6.8, 70], [7.4, 30]]);
  const dash40Score = mapLowerBetter(dash40, [[4.3, 100], [4.5, 94], [4.7, 84], [4.9, 68], [5.3, 30]]);

  const powerScore = weightedAverage([{ value: cleanScore, weight: 1 }], 0);
  const strengthScore = weightedAverage([
    { value: deadScore, weight: 0.35 },
    { value: backScore, weight: 0.30 },
    { value: frontScore, weight: 0.20 },
    { value: benchScore, weight: 0.15 },
  ], 0);
  const speedScore = weightedAverage([
    { value: dash60Score, weight: 0.7 },
    { value: dash40Score, weight: 0.3 },
  ], 0);
  const relativeStrengthScore = weightedAverage([
    { value: cleanScore, weight: 0.6 },
    { value: deadScore, weight: 0.4 },
  ], 0);

  const score = clamp(
    (powerScore * 0.45) +
    (strengthScore * 0.30) +
    (speedScore * 0.20) +
    (relativeStrengthScore * 0.05),
    0,
    100
  );

  return {
    score: Math.round(score),
    parts: {
      power: Math.round(powerScore),
      strength: Math.round(strengthScore),
      speed: Math.round(speedScore),
      pwo: Math.round(relativeStrengthScore),
    },
  };
}

export default computeStrengthScore;
