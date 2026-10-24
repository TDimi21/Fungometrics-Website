/**
 * hitClassification.js — single source of truth for classifying a batted ball
 * from its measured inputs, so a coach never has to hand-pick the trajectory or
 * contact quality. Cage Mode and Game Mode both derive from here.
 *
 * Trajectory thresholds mirror Cage Mode's original classifyLaunch:
 *   Ground Ball < 8°, Line Drive 8–24°, Fly Ball 25–44°, Pop Fly ≥ 45°.
 */

export const TRAJECTORY = {
  GROUND_BALL: 'Ground Ball',
  LINE_DRIVE: 'Line Drive',
  FLY_BALL: 'Fly Ball',
  POP_FLY: 'Pop Fly',
};

export const TRAJECTORY_COLORS = {
  'Line Drive': '#22c55e',
  'Fly Ball': '#3b82f6',
  'Pop Fly': '#a855f7',
  'Ground Ball': '#f59e0b',
};

/**
 * Trajectory (type of hit) from launch angle. Returns null for invalid input.
 * @param {number} launchAngleDeg
 * @returns {string|null}
 */
export function trajectoryFromLaunch(launchAngleDeg) {
  const la = Number(launchAngleDeg);
  if (!Number.isFinite(la)) return null;
  if (la >= 45) return TRAJECTORY.POP_FLY;
  if (la >= 25) return TRAJECTORY.FLY_BALL;
  if (la >= 8) return TRAJECTORY.LINE_DRIVE;
  return TRAJECTORY.GROUND_BALL;
}

// Exit-velocity thresholds (mph) for contact quality. Tunable — defaults lean
// toward HS/travel-ball levels.
export const CONTACT_QUALITY_THRESHOLDS = {
  barrelEv: 95,     // barrel requires high EV in the productive launch window
  hardHitEv: 90,    // hard-hit floor
  solidEv: 75,      // solid contact floor
};

export const CONTACT_QUALITY = {
  BARREL: 'Barrel',
  HARD_HIT: 'Hard Hit',
  SOLID: 'Solid',
  WEAK: 'Weak',
};

/**
 * Contact quality from exit velocity + launch angle, so the coach doesn't tag
 * weak/solid/hard-hit/barrel by hand. A barrel is high EV struck in the
 * productive launch window (~8–35°); otherwise it grades on EV alone.
 * @param {number} exitVelocityMph
 * @param {number} launchAngleDeg
 * @param {object} [thresholds=CONTACT_QUALITY_THRESHOLDS]
 * @returns {string|null}
 */
export function contactQuality(exitVelocityMph, launchAngleDeg, thresholds = CONTACT_QUALITY_THRESHOLDS) {
  const ev = Number(exitVelocityMph);
  const la = Number(launchAngleDeg);
  if (!Number.isFinite(ev) || ev <= 0) return null;

  const inBarrelWindow = Number.isFinite(la) && la >= 8 && la <= 35;
  if (ev >= thresholds.barrelEv && inBarrelWindow) return CONTACT_QUALITY.BARREL;
  if (ev >= thresholds.hardHitEv) return CONTACT_QUALITY.HARD_HIT;
  if (ev >= thresholds.solidEv) return CONTACT_QUALITY.SOLID;
  return CONTACT_QUALITY.WEAK;
}

/**
 * Full auto-classification for one batted ball.
 * @returns {{trajectory:string|null, quality:string|null}}
 */
export function classifyHit(exitVelocityMph, launchAngleDeg) {
  return {
    trajectory: trajectoryFromLaunch(launchAngleDeg),
    quality: contactQuality(exitVelocityMph, launchAngleDeg),
  };
}

/* ------------------------------------------------------------------ *
 * FMTRX Barrel + 5-tier Contact Quality (age-appropriate)
 * ------------------------------------------------------------------ */

// Barrel exit-velocity floor (mph) by field-level id. Youth → Pro ladder, so a
// "barrel" means something age-appropriate instead of one fixed MLB number.
export const FMTRX_BARREL_EV = {
  tball: 60, coach: 60, '8u': 60, '10u': 60, // Youth (8U–10U)
  '12u': 68,                                  // 11U–12U
  '13u': 78, '14u': 78,                       // 13U–14U
  hs: 88,                                     // High School
  college: 98, pro: 98,                       // College / Pro
};

const DEFAULT_BARREL_EV = 88; // fall back to High School when level is unknown

// College/Pro use a dynamic Statcast-style barrel window that widens with EV.
const DYNAMIC_BARREL_LEVELS = new Set(['college', 'pro']);

/** Barrel exit-velocity floor for a field level. */
export function barrelEvForLevel(levelId) {
  return FMTRX_BARREL_EV[levelId] ?? DEFAULT_BARREL_EV;
}

/**
 * Launch-angle window (deg) that counts as a barrel for a level. Fixed 10°–30°
 * for Youth → High School; for College/Pro it widens with exit velocity,
 * mirroring the Statcast barrel model (98 mph → 26°–30°, each mph above widens
 * the band ~1° down / ~3° up, capped at 8°–50°).
 */
export function barrelWindowForLevel(levelId, exitVelocityMph) {
  if (DYNAMIC_BARREL_LEVELS.has(levelId)) {
    const over = Math.max(0, (Number(exitVelocityMph) || 0) - 98);
    const lo = Math.max(8, 26 - over * 1);
    const hi = Math.min(50, 30 + over * 3);
    return [lo, hi];
  }
  return [10, 30];
}

export const FMTRX_QUALITY = {
  BARREL: 'Barrel',
  HARD_HIT: 'Hard Hit',
  SOLID: 'Solid Contact',
  WEAK: 'Weak Contact',
  MISHIT: 'Mishit',
};

// 🔴 Barrel · 🟠 Hard Hit · 🟢 Solid · 🔵 Weak · ⚫ Mishit
export const FMTRX_QUALITY_COLORS = {
  [FMTRX_QUALITY.BARREL]: '#ef4444',
  [FMTRX_QUALITY.HARD_HIT]: '#f59e0b',
  [FMTRX_QUALITY.SOLID]: '#22c55e',
  [FMTRX_QUALITY.WEAK]: '#3b82f6',
  [FMTRX_QUALITY.MISHIT]: '#6b7280',
};

export const FMTRX_QUALITY_ORDER = [
  FMTRX_QUALITY.BARREL,
  FMTRX_QUALITY.HARD_HIT,
  FMTRX_QUALITY.SOLID,
  FMTRX_QUALITY.WEAK,
  FMTRX_QUALITY.MISHIT,
];

/**
 * FMTRX 5-tier contact quality for one batted ball, age-scaled by field level:
 *   Barrel    — ideal EV for level struck inside the barrel LA window
 *   Hard Hit  — barrel-level EV but LA outside the window
 *   Solid     — average EV
 *   Weak      — low EV
 *   Mishit    — very low EV, or an extreme pop-up / topped ball
 * @returns {string|null}
 */
export function fmtrxContactQuality(exitVelocityMph, launchAngleDeg, levelId) {
  const ev = Number(exitVelocityMph);
  const la = Number(launchAngleDeg);
  if (!Number.isFinite(ev) || ev <= 0) return null;

  const barrelEv = barrelEvForLevel(levelId);
  const solidEv = Math.max(20, barrelEv - 15);
  const weakEv = Math.max(10, barrelEv - 30);
  const [loLA, hiLA] = barrelWindowForLevel(levelId, ev);
  const laValid = Number.isFinite(la);

  // Extreme pop-up / topped ball that wasn't crushed → Mishit.
  if (laValid && (la >= 50 || la < -10) && ev < barrelEv) return FMTRX_QUALITY.MISHIT;

  if (ev >= barrelEv) {
    if (laValid && la >= loLA && la <= hiLA) return FMTRX_QUALITY.BARREL;
    return FMTRX_QUALITY.HARD_HIT;
  }
  if (ev >= solidEv) return FMTRX_QUALITY.SOLID;
  if (ev >= weakEv) return FMTRX_QUALITY.WEAK;
  return FMTRX_QUALITY.MISHIT;
}

/** Convenience: FMTRX contact-quality color for a batted ball. */
export function fmtrxQualityColor(exitVelocityMph, launchAngleDeg, levelId) {
  const q = fmtrxContactQuality(exitVelocityMph, launchAngleDeg, levelId);
  return FMTRX_QUALITY_COLORS[q] || '#e5e7eb';
}

/**
 * Aggregate a list of swings ({ ev, launchAngle }) into Game Mode session
 * stats: totals, barrels, the 5-tier quality breakdown, trajectory % (GB/LD/
 * Fly, where Fly = Fly Ball + Pop Fly) and average / best exit velocity —
 * all age-scaled by field level.
 */
export function summarizeSwings(swings = [], levelId) {
  const list = (swings || []).filter(
    (s) => Number.isFinite(Number(s?.ev)) && Number(s.ev) > 0,
  );
  const total = list.length;
  const quality = {
    [FMTRX_QUALITY.BARREL]: 0,
    [FMTRX_QUALITY.HARD_HIT]: 0,
    [FMTRX_QUALITY.SOLID]: 0,
    [FMTRX_QUALITY.WEAK]: 0,
    [FMTRX_QUALITY.MISHIT]: 0,
  };
  const traj = { gb: 0, ld: 0, fly: 0 };
  let evSum = 0;
  let evBest = 0;
  let laSum = 0;
  let laCount = 0;

  list.forEach((s) => {
    const ev = Number(s.ev);
    const la = Number(s.launchAngle);
    evSum += ev;
    if (ev > evBest) evBest = ev;
    if (Number.isFinite(la)) {
      laSum += la;
      laCount += 1;
    }

    const q = fmtrxContactQuality(ev, la, levelId);
    if (q && quality[q] != null) quality[q] += 1;

    const t = trajectoryFromLaunch(la);
    if (t === TRAJECTORY.GROUND_BALL) traj.gb += 1;
    else if (t === TRAJECTORY.LINE_DRIVE) traj.ld += 1;
    else traj.fly += 1; // Fly Ball + Pop Fly
  });

  const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);
  return {
    total,
    barrels: quality[FMTRX_QUALITY.BARREL],
    quality,
    avgEv: total > 0 ? evSum / total : 0,
    avgLa: laCount > 0 ? laSum / laCount : 0,
    bestEv: evBest,
    trajectoryPct: { gb: pct(traj.gb), ld: pct(traj.ld), fly: pct(traj.fly) },
  };
}
