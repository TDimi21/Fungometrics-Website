import DEFAULT_AGE_PERCENTILE_BENCHMARKS from '../data/defaultAgePercentileBenchmarks';

const asNumber = (v) => (Number.isFinite(Number(v)) ? Number(v) : null);

export function getAgeGroup(player = {}) {
  const age = asNumber(player.age);
  if (!age) return '14';
  if (age <= 9) return '9U';
  if (age <= 11) return '11';
  if (age <= 13) return '13';
  if (age <= 15) return '14';
  if (age <= 17) return '16';
  return '18';
}

export function getPercentileLabel(percentile) {
  const p = asNumber(percentile) ?? 0;
  if (p >= 90) return 'Elite';
  if (p >= 75) return 'Above Average';
  if (p >= 50) return 'Average';
  if (p >= 25) return 'Below Average';
  return 'Needs Development';
}

export function getBenchmarkRow(metricKey, ageGroup, level = 'travel', benchmarks = DEFAULT_AGE_PERCENTILE_BENCHMARKS) {
  return benchmarks.find((row) =>
    row.metric_key === metricKey &&
    String(row.age_group) === String(ageGroup) &&
    (row.level === level || row.level === 'all') &&
    row.active !== false
  ) ?? null;
}

export function getMetricPercentile(metricKey, value, ageGroup, level = 'travel', benchmarks = DEFAULT_AGE_PERCENTILE_BENCHMARKS) {
  const row = getBenchmarkRow(metricKey, ageGroup, level, benchmarks);
  const v = asNumber(value);
  if (!row || v === null) return null;

  const cuts = [
    { p: 10, key: 'p10' }, { p: 25, key: 'p25' }, { p: 50, key: 'p50' },
    { p: 75, key: 'p75' }, { p: 90, key: 'p90' }, { p: 95, key: 'p95' }, { p: 99, key: 'p99' },
  ].map((c) => ({ p: c.p, v: asNumber(row[c.key]) }));

  const lowerIsBetter = row.lower_is_better === true;
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
