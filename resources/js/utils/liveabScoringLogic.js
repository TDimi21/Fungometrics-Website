// Canonical LiveAB scoring logic helpers for FMTRX.
// Keep this file framework-agnostic and side-effect free.

export const PLAY_RESULTS = {
  SAFE: [
    'SINGLE',
    'DOUBLE',
    'TRIPLE',
    'HR',
    'WALK',
    'IBB',
    'HBP',
    'ERROR',
    'FC',
    'CI',
    'DROPPED_THIRD_STRIKE_SAFE',
    'BUNT_SINGLE',
    'BUNT_SAFE_ERROR',
    'BUNT_SAFE_FC',
    'SAFE_MANUAL',
  ],
  OUT: [
    'STRIKEOUT_SWINGING',
    'STRIKEOUT_LOOKING',
    'GROUND_OUT',
    'FLY_OUT',
    'LINE_OUT',
    'POP_OUT',
    'FOUL_OUT',
    'BUNT_FOUL_STRIKE_THREE',
    'SAC_BUNT',
    'SAC_FLY',
    'FC_OUT',
    'FORCE_OUT',
    'TAG_OUT',
    'DOUBLE_PLAY',
    'TRIPLE_PLAY',
    'BATTER_INTERFERENCE',
    'RUNNER_INTERFERENCE',
    'INFIELD_FLY',
    'DROPPED_THIRD_STRIKE_OUT',
    'OUT',
  ],
};

export const ADVANCE_REASONS = [
  'batter_single',
  'batter_double',
  'batter_triple',
  'batter_home_run',
  'walk',
  'hit_by_pitch',
  'catcher_interference',
  'error',
  'fielders_choice',
  'sacrifice_bunt',
  'sacrifice_fly',
  'ground_ball',
  'fly_ball_tag',
  'line_drive',
  'wild_pitch',
  'passed_ball',
  'stolen_base',
  'balk',
  'defensive_indifference',
  'pickoff_error',
  'throwing_error',
  'obstruction',
  'manual_advance',
];

const ALIAS = {
  BB: 'WALK',
  K: 'STRIKEOUT_SWINGING',
  HOME_RUN: 'HR',
  HOME_RUNS: 'HR',
  FIELDERS_CHOICE: 'FC',
  "FIELDER'S_CHOICE": 'FC',
  ROE: 'ERROR',
  "CATCHER'S_INTERFERENCE": 'CI',
};

export function normalizePlayResult(result) {
  const key = String(result || '')
    .trim()
    .toUpperCase()
    .replace(/\s+/g, '_');
  return ALIAS[key] || key;
}

export function isStrikeout(result) {
  const r = normalizePlayResult(result);
  return [
    'STRIKEOUT_SWINGING',
    'STRIKEOUT_LOOKING',
    'BUNT_FOUL_STRIKE_THREE',
    'DROPPED_THIRD_STRIKE_SAFE',
    'DROPPED_THIRD_STRIKE_OUT',
  ].includes(r);
}

export function isHit(result) {
  const r = normalizePlayResult(result);
  return ['SINGLE', 'DOUBLE', 'TRIPLE', 'HR', 'BUNT_SINGLE'].includes(r);
}

export function isOfficialAtBat(result) {
  const r = normalizePlayResult(result);
  const nonAB = new Set(['WALK', 'IBB', 'HBP', 'SAC_BUNT', 'SAC_FLY', 'CI']);
  return !nonAB.has(r);
}

export function isPlateAppearance(result, opts = {}) {
  if (opts?.endedByRunnerThirdOutBeforeCompletion) {
    return false;
  }
  return !!normalizePlayResult(result);
}

export function isRbiBlockedByReason(reason) {
  return [
    'error',
    'passed_ball',
    'wild_pitch',
    'balk',
    'stolen_base',
    'defensive_indifference',
    'pickoff_error',
    'throwing_error',
  ].includes(String(reason || '').toLowerCase());
}

export function doesThirdOutCancelRun({
  thirdOutType, // force | batter_before_first | appeal_preceding | appeal_missed_base | tag | none
}) {
  return [
    'force',
    'batter_before_first',
    'appeal_preceding',
    'appeal_missed_base',
  ].includes(String(thirdOutType || '').toLowerCase());
}

export function computeRbiFromRunnerEvents(playResult, runnerEvents = []) {
  const r = normalizePlayResult(playResult);
  if (!runnerEvents.length) {
    return 0;
  }

  // Global hard blocks
  if (['ERROR', 'DOUBLE_PLAY', 'TRIPLE_PLAY'].includes(r)) {
    return 0;
  }

  return runnerEvents.reduce((sum, ev) => {
    const scored = !!ev?.run_scored;
    if (!scored) {
      return sum;
    }
    if (ev?.rbi_credit_to_batter === false) {
      return sum;
    }
    if (isRbiBlockedByReason(ev?.advance_reason)) {
      return sum;
    }
    return sum + 1;
  }, 0);
}

export function deriveBatterOutcomeFlags(playResult, context = {}) {
  const r = normalizePlayResult(playResult);
  const hit = isHit(r);
  const ab = isOfficialAtBat(r);
  const pa = isPlateAppearance(r, context);
  const so = isStrikeout(r);
  const bb = ['WALK', 'IBB'].includes(r);
  const hbp = r === 'HBP';
  const sf = r === 'SAC_FLY';
  const sh = r === 'SAC_BUNT';

  return {
    playResult: r,
    PA: pa ? 1 : 0,
    AB: ab ? 1 : 0,
    H: hit ? 1 : 0,
    single: r === 'SINGLE' || r === 'BUNT_SINGLE' ? 1 : 0,
    double: r === 'DOUBLE' ? 1 : 0,
    triple: r === 'TRIPLE' ? 1 : 0,
    homeRun: r === 'HR' ? 1 : 0,
    SO: so ? 1 : 0,
    BB: bb ? 1 : 0,
    HBP: hbp ? 1 : 0,
    SF: sf ? 1 : 0,
    SH: sh ? 1 : 0,
  };
}

export function forceAdvanceOnAwardFirst(runners = [false, false, false]) {
  // runners: [1B, 2B, 3B]
  const next = [...runners];
  let runs = 0;

  if (next[2] && next[1] && next[0]) {
    runs += 1;
  }
  next[2] = next[2] || next[1];
  next[1] = next[1] || next[0];
  next[0] = true;

  return {runners: next, runs};
}

export function computeRateStats(line) {
  const ab = Number(line?.AB || 0);
  const h = Number(line?.H || 0);
  const bb = Number(line?.BB || 0);
  const hbp = Number(line?.HBP || 0);
  const sf = Number(line?.SF || 0);
  const ci = Number(line?.CI || 0);

  const oneB = Number(line?.['1B'] || line?.single || 0);
  const twoB = Number(line?.['2B'] || line?.double || 0);
  const threeB = Number(line?.['3B'] || line?.triple || 0);
  const hr = Number(line?.HR || line?.homeRun || 0);

  const tb = oneB + twoB * 2 + threeB * 3 + hr * 4;

  const avg = ab > 0 ? h / ab : 0;
  const obpDen = ab + bb + hbp + sf + ci;
  const obp = obpDen > 0 ? (h + bb + hbp + ci) / obpDen : 0;
  const slg = ab > 0 ? tb / ab : 0;
  const ops = obp + slg;

  return {AVG: avg, OBP: obp, SLG: slg, OPS: ops, TB: tb};
}
