/**
 * ballFlight.js — projectile model for FMTRX Game Mode.
 *
 * Turns a batted ball's three measured inputs (exit velocity, launch angle,
 * spray angle) into a landing point + arc so we can plot it on the field image
 * and animate the ball from home plate to where it lands.
 *
 * Pure functions only (no React / RN) so they're easy to unit test and reuse
 * across Game Mode, Cage reports, and LiveAB.
 */

import { trajectoryFromLaunch } from './hitClassification';

// Re-export so callers can pull trajectory from the ball-flight module too;
// the single source of truth lives in hitClassification.js.
export { trajectoryFromLaunch };

const MPH_TO_FPS = 1.46667; // 1 mph = 1.46667 ft/s
const G = 32.174; // gravity, ft/s²

// Real baseballs travel a fraction of the vacuum range because of drag. 0.55
// is the FMTRX-tuned carry factor for the air portion (before rollout).
export const DEFAULT_CARRY_FACTOR = 0.55;

// Rollout (ground roll after the ball lands) — physics-based FMTRX model:
//   rollout = EV × 1.45 × rollAngleModifier(LA) × surfaceMultiplier
// A hard flat ball scoots a long way; a steep fly barely rolls.

// Surface multipliers for ground roll.
export const SURFACE_MULTIPLIERS = {
  wetGrass: 0.70,
  grass: 0.90, // normal grass (default)
  dryGrass: 1.0,
  turf: 1.2,
};
export const DEFAULT_SURFACE = 'turf';
export const ROLLOUT_EV_FACTOR = 1.45;
export const DEFAULT_CONTACT_HEIGHT_FT = 3;

// Fraction of rollout retained by launch angle. Flat balls roll; fly balls stop
// dead. Interpolated linearly between these breakpoints.
const ROLL_MODIFIER_TABLE = [
  [0, 1.0], [5, 0.85], [10, 0.7], [15, 0.55], [20, 0.4],
  [25, 0.25], [30, 0.15], [35, 0.08], [40, 0.05], [45, 0],
];

export function rollAngleModifier(launchAngleDeg) {
  const la = Number(launchAngleDeg);
  if (!Number.isFinite(la)) return 0;
  // A negative launch angle describes distance to first ground contact, not
  // predictable rollout. TrackMan/Statcast validation shows that adding a
  // generic turf roll here overstates these balls by more than 100 feet.
  if (la < 0) return 0;
  if (la >= 45) return 0; // fly balls don't roll
  for (let i = 0; i < ROLL_MODIFIER_TABLE.length - 1; i++) {
    const [x0, y0] = ROLL_MODIFIER_TABLE[i];
    const [x1, y1] = ROLL_MODIFIER_TABLE[i + 1];
    if (la >= x0 && la <= x1) return y0 + (y1 - y0) * ((la - x0) / (x1 - x0));
  }
  return 0;
}

export function computeRollout(exitVelocityMph, launchAngleDeg, surface = DEFAULT_SURFACE) {
  const ev = Number(exitVelocityMph);
  const la = Number(launchAngleDeg);
  if (!Number.isFinite(ev) || ev <= 0 || !Number.isFinite(la)) return 0;
  const surfaceMult = SURFACE_MULTIPLIERS[surface] ?? SURFACE_MULTIPLIERS[DEFAULT_SURFACE];
  return Math.max(0, ev * ROLLOUT_EV_FACTOR * rollAngleModifier(la) * surfaceMult);
}

const toRad = (deg) => (Number(deg) * Math.PI) / 180;
const clampNum = (v, min, max) => Math.max(min, Math.min(max, v));

/**
 * Compute the flight of a batted ball.
 *
 * @param {number} exitVelocityMph
 * @param {number} launchAngleDeg   0 = flat, 90 = straight up
 * @param {number} sprayAngleDeg    0 = dead center, - = pull/left, + = oppo/right
 * @param {object} [opts]
 * @param {number} [opts.carryFactor=DEFAULT_CARRY_FACTOR]
 * @returns {{
 *   distanceFt:number, carryFt:number, rolloutFt:number, apexFt:number,
 *   hangTimeS:number, x:number, y:number, carryX:number, carryY:number,
 *   exitVelocityMph:number, launchAngleDeg:number, sprayAngleDeg:number
 * }} distanceFt = carry + rollout (final resting spot). x/y = lateral/CF feet
 *    to that resting spot; carryX/carryY = where it first lands (air only).
 */
export function computeBallFlight(exitVelocityMph, launchAngleDeg, sprayAngleDeg, opts = {}) {
  const carryFactor = opts.carryFactor ?? DEFAULT_CARRY_FACTOR;
  const measuredDistanceFt = Number(opts.distanceFt ?? opts.measuredDistanceFt);
  const contactHeightFt = clampNum(
    Number(opts.contactHeightFt ?? DEFAULT_CONTACT_HEIGHT_FT),
    2.25,
    4.5,
  );

  const ev = Number(exitVelocityMph);
  const la = clampNum(Number(launchAngleDeg), -90, 90);
  const sa = clampNum(Number(sprayAngleDeg), -60, 60);

  if (!Number.isFinite(ev) || ev <= 0 || !Number.isFinite(la)) {
    return {
      distanceFt: 0, carryFt: 0, rolloutFt: 0, apexFt: 0, hangTimeS: 0,
      x: 0, y: 0, carryX: 0, carryY: 0,
      exitVelocityMph: ev || 0, launchAngleDeg: la || 0, sprayAngleDeg: sa || 0,
    };
  }

  const v0 = ev * MPH_TO_FPS;
  const laRad = toRad(la);
  const saRad = toRad(sa);

  let carryFt;
  let apexFt;
  let hangTimeS;
  let rolloutFt;

  if (la < 0) {
    // Downward contact: solve from contact height to y=0 using the original
    // negative vertical velocity. Over this very short flight, a drag factor
    // is neither needed nor supported by the paired TrackMan/Statcast data.
    const verticalFps = v0 * Math.sin(laRad);
    hangTimeS =
      (verticalFps + Math.sqrt(verticalFps ** 2 + 2 * G * contactHeightFt)) / G;
    carryFt = Math.max(0, v0 * Math.cos(laRad) * hangTimeS);
    apexFt = 0;
    rolloutFt = 0;
  } else {
    // Airborne contact: vacuum range reduced by the fitted carry factor.
    carryFt = Math.max(0, ((v0 * v0 * Math.sin(2 * laRad)) / G) * carryFactor);
    apexFt = Math.max(0, ((v0 * v0 * Math.pow(Math.sin(laRad), 2)) / (2 * G)) * carryFactor);
    hangTimeS = (2 * v0 * Math.sin(laRad)) / G;

    // Ground roll after landing is retained only for non-negative launch.
    rolloutFt = computeRollout(ev, la, opts.surface);
  }

  const hasMeasuredDistance = Number.isFinite(measuredDistanceFt) && measuredDistanceFt > 0;
  const measuredRolloutFt = hasMeasuredDistance
    ? Math.min(rolloutFt, measuredDistanceFt)
    : rolloutFt;
  const distanceFt = hasMeasuredDistance ? measuredDistanceFt : carryFt + rolloutFt;
  const plottedCarryFt = hasMeasuredDistance
    ? Math.max(0, measuredDistanceFt - measuredRolloutFt)
    : carryFt;
  const sinSa = Math.sin(saRad);
  const cosSa = Math.cos(saRad);

  return {
    distanceFt,
    carryFt: plottedCarryFt,
    rolloutFt: measuredRolloutFt,
    apexFt,
    hangTimeS,
    x: distanceFt * sinSa,
    y: distanceFt * cosSa,
    carryX: plottedCarryFt * sinSa,
    carryY: plottedCarryFt * cosSa,
    exitVelocityMph: ev,
    launchAngleDeg: la,
    sprayAngleDeg: sa,
  };
}

export const FIELD_ASPECT = 1.5; // wide landscape for the fair-territory wedge

/**
 * Base coordinates (feet) for a given base-path length. Home at origin, +y to
 * center field, +x to right field. 1B/3B sit on the ±45° foul lines; 2B is
 * base·√2 straight out (127.28 ft at 90 ft base paths).
 */
export function baseCoords(basePathFt = 90) {
  const half = basePathFt / Math.SQRT2;
  return {
    home: [0, 0],
    first: [half, half],
    second: [0, basePathFt * Math.SQRT2],
    third: [-half, half],
  };
}

/**
 * FMTRX default field profiles — base paths, pitching distance, and outfield
 * fences (LF/RF line, LC/RC gap, center). These are the average, most common
 * sizes per level, not any single sanctioning body; coaches edit from here.
 */
export const FIELD_LEVELS = [
  { id: 'tball', label: 'T-Ball', basePathFt: 50, pitchingFt: 35, fence: { line: 100, gap: 110, center: 120 } },
  { id: 'coach', label: 'Coach Pitch', basePathFt: 60, pitchingFt: 40, fence: { line: 150, gap: 165, center: 175 } },
  { id: '8u', label: '8U', basePathFt: 60, pitchingFt: 46, fence: { line: 165, gap: 180, center: 190 } },
  { id: '10u', label: '9U–10U', basePathFt: 65, pitchingFt: 46, fence: { line: 200, gap: 215, center: 225 } },
  { id: '12u', label: '11U–12U', basePathFt: 70, pitchingFt: 50, fence: { line: 225, gap: 245, center: 260 } },
  { id: '13u', label: '13U', basePathFt: 80, pitchingFt: 54, fence: { line: 275, gap: 305, center: 325 } },
  { id: '14u', label: '14U', basePathFt: 90, pitchingFt: 60.5, fence: { line: 300, gap: 335, center: 360 } },
  { id: 'hs', label: 'High School', basePathFt: 90, pitchingFt: 60.5, fence: { line: 320, gap: 365, center: 390 } },
  { id: 'college', label: 'College', basePathFt: 90, pitchingFt: 60.5, fence: { line: 330, gap: 375, center: 400 } },
  { id: 'pro', label: 'Professional', basePathFt: 90, pitchingFt: 60.5, fence: { line: 330, gap: 380, center: 400 } },
];

/** Expand a symmetric {line,gap,center} into the 5-point fence profile. */
export function expandFence(f) {
  return { lineL: f.line, gapL: f.gap, center: f.center, gapR: f.gap, lineR: f.line };
}

/** Full field config from a level id (base paths + pitching + fences). */
export function configFromLevel(levelId) {
  const lvl = FIELD_LEVELS.find((l) => l.id === levelId) || FIELD_LEVELS.find((l) => l.id === 'hs');
  return {
    levelId: lvl.id,
    levelLabel: lvl.label,
    basePathFt: lvl.basePathFt,
    pitchingFt: lvl.pitchingFt,
    moundRadiusFt: 9,
    fences: expandFence(lvl.fence),
    homeXFrac: 0.5,
    homeYFrac: 0.95,
    topMarginFrac: 0.06,
    sideMarginFrac: 0.03,
  };
}

/** Fence distance (feet) at a given spray angle, interpolating the 5 anchors. */
export function fenceAt(sprayDeg, fences) {
  const a = clampNum(Number(sprayDeg) || 0, -45, 45);
  const anchors = [
    [-45, fences.lineL], [-22.5, fences.gapL], [0, fences.center], [22.5, fences.gapR], [45, fences.lineR],
  ];
  for (let i = 1; i < anchors.length; i++) {
    if (a <= anchors[i][0]) {
      const [x0, y0] = anchors[i - 1];
      const [x1, y1] = anchors[i];
      return y0 + (y1 - y0) * ((a - x0) / (x1 - x0));
    }
  }
  return fences.lineR;
}

/**
 * Classify a batted ball against the field's fences.
 * HR requires the CARRY (air distance) to clear the fence; foul is |spray|>45.
 * @returns {{result:string, code:string, fenceDist:number, trajectory:string}}
 */
export function classifyBattedBall(flight, config = DEFAULT_FIELD_CONFIG) {
  const fences = config.fences || DEFAULT_FIELD_CONFIG.fences;
  const sa = flight.sprayAngleDeg;
  const traj = trajectoryFromLaunch(flight.launchAngleDeg);
  if (Math.abs(sa) > 45) return { result: 'Foul Ball', code: 'foul', fenceDist: 0, trajectory: traj };

  const fd = fenceAt(sa, fences);
  const carry = flight.carryFt;
  if (traj === 'Ground Ball') return { result: 'Ground Ball', code: 'gb', fenceDist: fd, trajectory: traj };
  if (carry >= fd) return { result: 'Home Run', code: 'hr', fenceDist: fd, trajectory: traj };
  if (carry >= fd - 8) return { result: 'Off the Wall', code: 'wall', fenceDist: fd, trajectory: traj };
  if (carry >= fd - 25) return { result: 'Warning Track', code: 'track', fenceDist: fd, trajectory: traj };
  return { result: traj, code: 'inplay', fenceDist: fd, trajectory: traj };
}

/**
 * Top-down field geometry (orthographic — distance scales LINEARLY, exact with
 * no calibration). Home at bottom center; +y up toward center field. The scale
 * auto-fits the deepest fence.
 */
export const DEFAULT_FIELD_CONFIG = configFromLevel('hs');

/**
 * Build a linear top-down mapper for the rendered size. mapFeet/mapBall convert
 * field feet → pixels; the scale fits the deepest fence into the available space.
 */
export function createFieldMapper(width, height, config = DEFAULT_FIELD_CONFIG) {
  const cfg = { ...DEFAULT_FIELD_CONFIG, ...(config || {}) };
  const fences = cfg.fences || DEFAULT_FIELD_CONFIG.fences;
  const maxFence = Math.max(fences.lineL, fences.gapL, fences.center, fences.gapR, fences.lineR);
  const maxDistanceFt = (cfg.maxDistanceFt || maxFence) * 1.06; // room past the wall so HRs / foul balls plot outside the fence

  const homeX = cfg.homeXFrac * width;
  const homeY = cfg.homeYFrac * height;
  const vert = homeY - cfg.topMarginFrac * height;
  const horiz = width / 2 - cfg.sideMarginFrac * width;
  const ppfV = vert / maxDistanceFt;
  const ppfH = horiz / (maxDistanceFt * Math.sin(Math.PI / 4)); // widest at foul pole
  const ppf = Math.max(1e-6, Math.min(ppfV, ppfH));

  const mapFeet = (x, y) => ({ px: homeX + (Number(x) || 0) * ppf, py: homeY - (Number(y) || 0) * ppf });
  const mapBall = (distanceFt, sprayDeg) => {
    const r = ((Number(sprayDeg) || 0) * Math.PI) / 180;
    const dFt = Number(distanceFt) || 0;
    return mapFeet(dFt * Math.sin(r), dFt * Math.cos(r));
  };
  return { mapFeet, mapBall, home: { px: homeX, py: homeY }, pixelsPerFoot: ppf, config: cfg, maxDistanceFt };
}

/** Map a batted ball (distance + spray) to field-image pixels. */
export function mapBallToField(distanceFt, sprayAngleDeg, width, height, config = DEFAULT_FIELD_CONFIG) {
  return createFieldMapper(width, height, config).mapBall(distanceFt, sprayAngleDeg);
}

/** Map cartesian field feet {x,y} to pixels. */
export function mapFeetToField(point, width, height, config = DEFAULT_FIELD_CONFIG) {
  const m = createFieldMapper(width, height, config);
  return { ...m.mapFeet(point.x, point.y), pixelsPerFoot: m.pixelsPerFoot, home: m.home };
}

/**
 * Convenience: full pixel-space plot for one batted ball, including the
 * per-frame position along the arc (used by the animation).
 *
 * @returns {{
 *   landing:{px:number,py:number}, home:{px:number,py:number},
 *   apexPx:number, flight: object, pixelsPerFoot:number
 * }}
 */
export function plotBallFlight(exitVelocityMph, launchAngleDeg, sprayAngleDeg, width, height, config, opts) {
  const flight = computeBallFlight(exitVelocityMph, launchAngleDeg, sprayAngleDeg, opts);
  const m = createFieldMapper(width, height, config);

  return {
    landing: m.mapFeet(flight.x, flight.y),        // final resting spot (carry + rollout)
    carryLanding: m.mapFeet(flight.carryX, flight.carryY), // first touchdown (air only)
    home: m.home,
    apexPx: flight.apexFt * m.pixelsPerFoot,
    pixelsPerFoot: m.pixelsPerFoot,
    flight,
  };
}

/**
 * Ground + height position at normalized flight progress t∈[0,1].
 * Ground path is a straight line home→landing; height is a parabola peaking at
 * mid-flight. Returns the on-screen ball position (ground lifted by height) and
 * the shadow (ground only).
 *
 * @param {number} t 0 = contact at home plate, 1 = landing
 */
export function ballPositionAtProgress(t, home, landing, apexPx) {
  const clampedT = clampNum(t, 0, 1);
  const groundX = home.px + (landing.px - home.px) * clampedT;
  const groundY = home.py + (landing.py - home.py) * clampedT;
  const heightPx = 4 * apexPx * clampedT * (1 - clampedT); // parabola, peak at t=0.5
  return {
    shadow: { px: groundX, py: groundY },
    ball: { px: groundX, py: groundY - heightPx },
    heightPx,
  };
}
