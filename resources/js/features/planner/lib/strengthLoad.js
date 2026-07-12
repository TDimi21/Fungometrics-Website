// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — strength prescription + load math.
//
// A strength exercise holds a `sets` ARRAY; each set can use its own
// prescription method (percent of 1RM, fixed weight, athlete-selected, bodyweight,
// max reps, timed, distance, velocity, RPE, custom). The coach builds sets
// individually; the player later enters actual results against each target.
//
// 1RM sourcing (for percent_1rm) prefers, in order: verified assessment →
// verified metric log → training max → estimated 1RM. Loads round to the gym's
// available increment. This util is UI-agnostic so the player view + backend
// can reuse it unchanged.
// ─────────────────────────────────────────────────────────────────────────────

export const PRESCRIPTION_TYPES = [
  { type: 'percent_1rm', label: 'Percentage of 1RM', short: '% 1RM' },
  { type: 'fixed_weight', label: 'Fixed weight', short: 'Fixed' },
  { type: 'athlete_weight', label: 'Athlete-selected weight', short: 'Athlete' },
  { type: 'bodyweight', label: 'Bodyweight reps', short: 'Bodyweight' },
  { type: 'max_reps', label: 'Max reps', short: 'Max reps' },
  { type: 'timed', label: 'Timed', short: 'Timed' },
  { type: 'distance', label: 'Distance', short: 'Distance' },
  { type: 'velocity', label: 'Velocity', short: 'Velocity' },
  { type: 'rpe', label: 'RPE', short: 'RPE' },
  { type: 'custom', label: 'Custom', short: 'Custom' },
];

export const PRESCRIPTION_BY_TYPE = PRESCRIPTION_TYPES.reduce((a, p) => { a[p.type] = p; return a; }, {});
export const prescriptionShort = (type) => PRESCRIPTION_BY_TYPE[type]?.short || 'Set';
export const prescriptionLabel = (type) => PRESCRIPTION_BY_TYPE[type]?.label || 'Set';

export const MAX_SOURCE_PRIORITY = [
  'verified_assessment', 'verified_metric_log', 'training_max', 'estimated_1rm',
];

const MAX_SOURCE_LABEL = {
  verified_assessment: 'Assessment',
  verified_metric_log: 'Metric log',
  training_max: 'Training max',
  estimated_1rm: 'Estimated 1RM',
};
export const maxSourceLabel = (k) => MAX_SOURCE_LABEL[k] || k;

const n = (v) => {
  const x = Number(v);
  return Number.isFinite(x) ? x : null;
};

export const roundToIncrement = (w, inc = 5) => {
  if (!(w > 0)) return null;
  const step = inc > 0 ? inc : 5;
  return Math.round(w / step) * step;
};

// Target load for a percent-of-1RM set. Returns null when no valid max.
// Rounds to 2.5 lb (smallest common plate pair) so e.g. 75% of 250 → 187.5 lb.
export const targetFromPercent = (oneRM, pct, inc = 2.5) => {
  const rm = n(oneRM);
  const p = n(pct);
  if (!(rm > 0) || !(p > 0)) return null;
  return roundToIncrement(rm * (p / 100), inc);
};

// The lift maxes stored on the player_fitnesses record that we can resolve
// percent-of-1RM targets against.
export const ONE_RM_FIELDS = ['back_squat', 'front_squat', 'bench_press', 'dead_lift', 'power_clean'];

// Collapse a player's fitness rows (newest-first) into their latest non-zero max
// per lift. This is the "verified metric log" 1RM source.
export function coalesceMaxes(rows) {
  const list = Array.isArray(rows) ? rows : (rows ? [rows] : []);
  const out = {};
  ONE_RM_FIELDS.forEach((field) => {
    for (const r of list) {
      const v = Number(r?.[field]);
      if (Number.isFinite(v) && v > 0) { out[field] = v; break; }
    }
  });
  return out;
}

// Which player_fitnesses column stores this exercise's max (or null if none).
// Never guesses across lifts — an unmapped exercise returns null ("Max needed").
export function oneRMFieldForExercise(nameOrDrill) {
  const name = String(nameOrDrill?.name ?? nameOrDrill ?? '').toLowerCase();
  if (name.includes('front squat')) return 'front_squat';
  if (name.includes('squat')) return 'back_squat';          // back squat / barbell squat / squat
  if (name.includes('bench')) return 'bench_press';         // bench press / db bench / incline bench
  if (name.includes('dead')) return 'dead_lift';            // deadlift / RDL / sumo dead
  if (name.includes('clean')) return 'power_clean';         // power clean / hang clean
  return null;
}

let __setSeq = 0;
export function makeSet(setNumber, overrides = {}) {
  __setSeq += 1;
  return {
    id: `set_${Date.now()}_${__setSeq}`,
    setNumber,
    targetReps: 5,
    repRangeMax: null,       // set for rep ranges (e.g. 6–10)
    prescriptionType: 'percent_1rm',
    percentage: null,        // percent_1rm
    weight: null,            // fixed_weight / added weight for bodyweight
    rangeMin: null,          // athlete_weight range
    rangeMax: null,
    timeSec: null,           // timed
    distance: null,          // distance
    velocity: null,          // velocity target (mph)
    rpe: null,               // rpe / reps-in-reserve target
    reps: null,              // free reps value if not using targetReps
    isMaxReps: false,        // max_reps
    isMaxTime: false,        // timed → max hold
    customText: '',          // custom
    restSeconds: null,
    tempo: '',
    notes: '',
    ...overrides,
  };
}

// Build a starting set list from a drill's simple defaults.
export function initialSets(defaultSets, defaultReps, prescriptionType = 'percent_1rm') {
  const count = Math.max(1, Number(defaultSets) || 3);
  const reps = Number(defaultReps) || 5;
  return Array.from({ length: count }, (_, i) =>
    makeSet(i + 1, { targetReps: reps, prescriptionType }));
}

export function renumber(sets = []) {
  return sets.map((s, i) => ({ ...s, setNumber: i + 1 }));
}

const repsText = (s) => {
  if (s.isMaxReps || s.prescriptionType === 'max_reps') return 'Max';
  if (s.repRangeMax != null && s.targetReps != null) return `${s.targetReps}–${s.repRangeMax}`;
  if (s.targetReps != null) return `${s.targetReps}`;
  return '—';
};

// One-line description of a set for the coach view. `oneRM` optional → shows load.
export function setSummary(s, oneRM) {
  switch (s.prescriptionType) {
    case 'percent_1rm': {
      const t = targetFromPercent(oneRM, s.percentage);
      return `${repsText(s)} × ${s.percentage != null ? `${s.percentage}%` : '—'}${t != null ? ` → ${t} lb` : ''}`;
    }
    case 'fixed_weight':
      return `${repsText(s)} × ${s.weight != null ? `${s.weight} lb` : '— lb'}`;
    case 'athlete_weight':
      return `${repsText(s)} × athlete${(s.rangeMin != null && s.rangeMax != null) ? ` (${s.rangeMin}–${s.rangeMax} lb)` : ''}`;
    case 'bodyweight':
      return `${repsText(s)} reps${s.weight ? ` +${s.weight} lb` : ' (BW)'}`;
    case 'max_reps':
      return 'Max reps';
    case 'timed':
      return s.isMaxTime ? 'Max time' : `${s.timeSec != null ? `${s.timeSec} sec` : '— sec'}`;
    case 'distance':
      return `${s.distance != null ? `${s.distance} ft` : '— ft'}`;
    case 'velocity':
      return `${repsText(s)} × ${s.velocity != null ? `${s.velocity} mph` : '— mph'}`;
    case 'rpe':
      return `${repsText(s)} × @RPE ${s.rpe != null ? s.rpe : '—'}`;
    case 'custom':
      return s.customText || 'Custom';
    default:
      return repsText(s);
  }
}

// Whether a set is a percent-of-1RM set that needs a max to resolve a load.
export const needsOneRM = (s) => s?.prescriptionType === 'percent_1rm';
