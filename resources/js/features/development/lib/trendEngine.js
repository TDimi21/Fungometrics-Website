const asNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : null);

const avg = (items, key) => {
  const vals = items.map((x) => asNum(x?.[key])).filter((x) => x !== null);
  if (!vals.length) return null;
  return vals.reduce((a, b) => a + b, 0) / vals.length;
};

const pctChange = (curr, prev) => {
  if (curr === null || prev === null || prev === 0) return null;
  return ((curr - prev) / Math.abs(prev)) * 100;
};

export function splitBy30DayWindows(history = [], nowDate = new Date()) {
  const now = new Date(nowDate);
  const d30 = new Date(now); d30.setDate(now.getDate() - 30);
  const d60 = new Date(now); d60.setDate(now.getDate() - 60);

  const curr = history.filter((x) => new Date(x.date) >= d30);
  const prev = history.filter((x) => {
    const d = new Date(x.date);
    return d >= d60 && d < d30;
  });

  return { curr, prev };
}

export function buildTrendSummary(history = []) {
  const { curr, prev } = splitBy30DayWindows(history);

  const keys = [
    'avg_exit_velocity', 'avg_pitch_velocity', 'hard_contact_percentage',
    'command_score', 'bp_score', 'bullpen_score', 'rotational_power_score',
    'mobility_score', 'recovery_score', 'sleep_hours',
  ];

  const changes = keys.reduce((acc, key) => {
    const c = avg(curr, key);
    const p = avg(prev, key);
    acc[key] = {
      current: c,
      previous: p,
      delta: c !== null && p !== null ? c - p : null,
      changePct: pctChange(c, p),
      direction: c === null || p === null ? 'flat' : c > p ? 'up' : c < p ? 'down' : 'flat',
    };
    return acc;
  }, {});

  const directional = Object.values(changes).filter((x) => x.direction !== 'flat');
  const up = directional.filter((x) => x.direction === 'up').length;
  const down = directional.filter((x) => x.direction === 'down').length;

  const status = up === 0 && down === 0
    ? 'no_recent_data'
    : up - down >= 3
      ? 'improving'
      : down - up >= 3
        ? 'declining'
        : 'steady';

  return { status, windows: { curr, prev }, changes };
}

export default buildTrendSummary;
