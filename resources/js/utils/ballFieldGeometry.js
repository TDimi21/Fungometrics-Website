/**
 * ballFieldGeometry.js — top-down baseball field geometry (ported from the app's
 * ballFlight.js). Turns distance (ft) + spray angle (deg) into field pixels so the
 * velocity spray chart can draw fences, the diamond, and per-zone heat polygons.
 * Pure functions, no framework.
 */

const clampNum = (v, min, max) => Math.max(min, Math.min(max, v));

export const FIELD_ASPECT = 1.5; // wide landscape for the fair-territory wedge

// FMTRX default field profiles (base paths + outfield fences per level).
export const FIELD_LEVELS = [
  { id: 'tball', basePathFt: 50, fence: { line: 100, gap: 110, center: 120 } },
  { id: 'coach', basePathFt: 60, fence: { line: 150, gap: 165, center: 175 } },
  { id: '8u', basePathFt: 60, fence: { line: 165, gap: 180, center: 190 } },
  { id: '10u', basePathFt: 65, fence: { line: 200, gap: 215, center: 225 } },
  { id: '12u', basePathFt: 70, fence: { line: 225, gap: 245, center: 260 } },
  { id: '13u', basePathFt: 80, fence: { line: 275, gap: 305, center: 325 } },
  { id: '14u', basePathFt: 90, fence: { line: 300, gap: 335, center: 360 } },
  { id: 'hs', basePathFt: 90, fence: { line: 320, gap: 365, center: 390 } },
  { id: 'college', basePathFt: 90, fence: { line: 330, gap: 375, center: 400 } },
  { id: 'pro', basePathFt: 90, fence: { line: 330, gap: 380, center: 400 } },
];

export const expandFence = (f) => ({ lineL: f.line, gapL: f.gap, center: f.center, gapR: f.gap, lineR: f.line });

export function configFromLevel(levelId) {
  const lvl = FIELD_LEVELS.find((l) => l.id === levelId) || FIELD_LEVELS.find((l) => l.id === 'hs');
  return {
    levelId: lvl.id,
    basePathFt: lvl.basePathFt,
    fences: expandFence(lvl.fence),
    homeXFrac: 0.5,
    homeYFrac: 0.95,
    topMarginFrac: 0.06,
    sideMarginFrac: 0.03,
  };
}

export const DEFAULT_FIELD_CONFIG = configFromLevel('hs');

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

/** Base coordinates (feet). Home at origin, +y to center field, +x to right. */
export function baseCoords(basePathFt = 90) {
  const half = basePathFt / Math.SQRT2;
  return { home: [0, 0], first: [half, half], second: [0, basePathFt * Math.SQRT2], third: [-half, half] };
}

/** Linear top-down mapper: field feet → pixels, fit so the deepest fence shows. */
export function createFieldMapper(width, height, config = DEFAULT_FIELD_CONFIG) {
  const cfg = { ...DEFAULT_FIELD_CONFIG, ...(config || {}) };
  const fences = cfg.fences || DEFAULT_FIELD_CONFIG.fences;
  const maxFence = Math.max(fences.lineL, fences.gapL, fences.center, fences.gapR, fences.lineR);
  const maxDistanceFt = maxFence * 1.06; // room past the wall

  const homeX = cfg.homeXFrac * width;
  const homeY = cfg.homeYFrac * height;
  const vert = homeY - cfg.topMarginFrac * height;
  const horiz = width / 2 - cfg.sideMarginFrac * width;
  const ppfV = vert / maxDistanceFt;
  const ppfH = horiz / (maxDistanceFt * Math.sin(Math.PI / 4));
  const ppf = Math.max(1e-6, Math.min(ppfV, ppfH));

  const mapFeet = (x, y) => ({ px: homeX + (Number(x) || 0) * ppf, py: homeY - (Number(y) || 0) * ppf });
  const mapBall = (distanceFt, sprayDeg) => {
    const r = ((Number(sprayDeg) || 0) * Math.PI) / 180;
    const dFt = Number(distanceFt) || 0;
    return mapFeet(dFt * Math.sin(r), dFt * Math.cos(r));
  };
  return { mapFeet, mapBall, home: { px: homeX, py: homeY }, pixelsPerFoot: ppf, config: cfg, maxDistanceFt };
}
