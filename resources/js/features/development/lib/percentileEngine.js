import DEFAULT_AGE_PERCENTILE_BENCHMARKS from '../data/defaultAgePercentileBenchmarks';

const asNumber = (v) => (Number.isFinite(Number(v)) ? Number(v) : null);
const ageToNum = (ageGroup) => {
  if (ageGroup === '9U') return 9;
  const n = asNumber(ageGroup);
  return n ?? null;
};

export function getAgeGroup(player = {}) {
  const age = asNumber(player.age);
  if (!age) return '14';
  if (age <= 9) return '9U';
  if (age === 10) return '10';
  if (age === 11) return '11';
  if (age === 12) return '12';
  if (age === 13) return '13';
  if (age === 14) return '14';
  if (age === 15) return '15';
  if (age <= 17) return '16';
  return '18';
}

export function getPercentileLabel(percentile) {
  const p = asNumber(percentile) ?? 0;
  if (p >= 90) return 'Elite';
  if (p >= 75) return 'Above Average';
  if (p >= 50) return 'Average';
  return 'Below Average';
}

export function getBenchmarkRow(metricKey, ageGroup, level = 'travel', benchmarks = DEFAULT_AGE_PERCENTILE_BENCHMARKS) {
  const normalizedLevel = String(level || 'travel').toLowerCase();
  const levelCandidates = Array.from(new Set([normalizedLevel, 'travel', 'all']));

  for (const lvl of levelCandidates) {
    const exact = benchmarks.find((row) =>
      row.metric_key === metricKey &&
      String(row.age_group) === String(ageGroup) &&
      String(row.level || '').toLowerCase() === lvl &&
      row.active !== false,
    ) ?? null;

    if (exact) return exact;
  }

  // Interpolate missing age buckets (e.g., 10, 12, 15) from surrounding age rows.
  const targetAge = ageToNum(ageGroup);
  if (targetAge === null) return null;

  for (const lvl of levelCandidates) {
    const series = benchmarks
      .filter((row) =>
        row.metric_key === metricKey &&
        String(row.level || '').toLowerCase() === lvl &&
        row.active !== false &&
        ageToNum(row.age_group) !== null,
      )
      .map((row) => ({ row, age: ageToNum(row.age_group) }))
      .sort((a, b) => a.age - b.age);

    if (series.length < 2) continue;

    let lower = null;
    let upper = null;
    for (const point of series) {
      if (point.age <= targetAge) lower = point;
      if (point.age >= targetAge) {
        upper = point;
        break;
      }
    }

    if (!lower || !upper) continue;
    if (lower.age === upper.age) return lower.row;

    const t = (targetAge - lower.age) / (upper.age - lower.age);
    const lerp = (a, b) => {
      const av = asNumber(a);
      const bv = asNumber(b);
      if (av === null || bv === null) return null;
      return Number((av + (bv - av) * t).toFixed(2));
    };

    return {
      metric_key: metricKey,
      age_group: String(ageGroup),
      level: lvl,
      p10: lerp(lower.row.p10, upper.row.p10),
      p25: lerp(lower.row.p25, upper.row.p25),
      p50: lerp(lower.row.p50, upper.row.p50),
      p75: lerp(lower.row.p75, upper.row.p75),
      p90: lerp(lower.row.p90, upper.row.p90),
      p95: lerp(lower.row.p95, upper.row.p95),
      p99: lerp(lower.row.p99, upper.row.p99),
      source: 'FMTRX interpolated',
      active: true,
      lower_is_better: lower.row.lower_is_better === true || upper.row.lower_is_better === true,
    };
  }

  return null;
}

export function getMetricPercentile(metricKey, value, ageGroup, level = 'travel', benchmarks = DEFAULT_AGE_PERCENTILE_BENCHMARKS) {
  const row = getBenchmarkRow(metricKey, ageGroup, level, benchmarks);
  const v = asNumber(value);
  if (!row || v === null) return null;

  // Primary scale derives from admin P50 + P90 anchors.
  // This creates a continuous percentile curve where:
  // P90 = Elite, P75 = Above Average, P50 = Average, <P50 = Below Average.
  const p50 = asNumber(row.p50);
  const p90 = asNumber(row.p90);
  const lowerIsBetter = row.lower_is_better === true;

  if (p50 !== null && p90 !== null && p50 !== p90) {
    const span = p90 - p50;
    const raw = lowerIsBetter
      ? (50 + ((p50 - v) / span) * 40)
      : (50 + ((v - p50) / span) * 40);
    return Math.max(1, Math.min(99, Math.round(raw)));
  }

  // Fallback to explicit cut bands if P50/P90 are unavailable.
  const cuts = [
    { p: 10, key: 'p10' }, { p: 25, key: 'p25' }, { p: 50, key: 'p50' },
    { p: 75, key: 'p75' }, { p: 90, key: 'p90' }, { p: 95, key: 'p95' }, { p: 99, key: 'p99' },
  ].map((c) => ({ p: c.p, v: asNumber(row[c.key]) }));

  const ordered = lowerIsBetter ? [...cuts].reverse() : cuts;

  let percentile = 1;
  for (const c of ordered) {
    if (c.v === null) continue;
    if ((!lowerIsBetter && v >= c.v) || (lowerIsBetter && v <= c.v)) {
      percentile = c.p;
      break;
    }
  }

  return percentile;
}

export function getBenchmarkComparison(metricKey, value, ageGroup, level = 'travel') {
  const percentile = getMetricPercentile(metricKey, value, ageGroup, level);
  if (percentile === null) {
    return { percentile: null, label: 'No benchmark', status: 'missing' };
  }

  return {
    percentile,
    label: getPercentileLabel(percentile),
    status: percentile >= 75 ? 'strong' : percentile >= 50 ? 'ok' : 'attention',
  };
}

export default {
  getAgeGroup,
  getPercentileLabel,
  getMetricPercentile,
  getBenchmarkComparison,
};
