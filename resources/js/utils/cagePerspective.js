/**
 * cagePerspective.js — map a tap on the perspective cage photo to real cage feet.
 *
 * The cage is a box viewed in one-point perspective. A tap lands on one of five
 * planar surfaces (back net, floor, ceiling, left wall, right wall). For each
 * surface we build a homography from its image quad to a unit square, so any tap
 * un-projects to (depth, lateral, height) in feet — then to spray / launch.
 *
 * Calibration = the 8 cage corners as fractions (0..1) of the image W×H:
 *   ftl ftr fbl fbr  (front "mouth" corners)
 *   btl btr bbl bbr  (back-wall corners)
 * Coordinate frame: image x→right, y→down. Physical: depth 0(front)..L(back),
 * lateral -W/2(left)..+W/2(right), height 0(floor)..H(ceiling).
 */

// Default calibration estimated from the FungoMetrics cage render. Tune these
// with the on-screen drag overlay; they are fractions of image width/height.
export const DEFAULT_CAGE_CALIBRATION = {
  ftl: { x: 0.0, y: 0.0 },
  ftr: { x: 1.0, y: 0.0 },
  fbl: { x: 0.0, y: 1.0 },
  fbr: { x: 1.0, y: 1.0 },
  btl: { x: 0.37, y: 0.33 },
  btr: { x: 0.63, y: 0.33 },
  bbl: { x: 0.37, y: 0.68 },
  bbr: { x: 0.63, y: 0.68 },
};

export const DEFAULT_CAGE_DIMS = { widthFt: 14, heightFt: 12, lengthFt: 70, contactHeightFt: 3 };

// Natural size of the cage render (src/assets/images/cage/fungo-cage.png).
export const CAGE_IMAGE_ASPECT = 1341 / 1173;

// Solve the 8x8 system for a homography mapping src[4] -> dst[4] (h33 = 1).
function solveHomography(src, dst) {
  const A = [];
  const b = [];
  for (let i = 0; i < 4; i++) {
    const { x, y } = src[i];
    const { x: X, y: Y } = dst[i];
    A.push([x, y, 1, 0, 0, 0, -x * X, -y * X]);
    b.push(X);
    A.push([0, 0, 0, x, y, 1, -x * Y, -y * Y]);
    b.push(Y);
  }
  const h = gaussianSolve(A, b);
  return [h[0], h[1], h[2], h[3], h[4], h[5], h[6], h[7], 1];
}

// Gaussian elimination with partial pivoting for an n×n system.
function gaussianSolve(A, b) {
  const n = b.length;
  const M = A.map((row, i) => [...row, b[i]]);
  for (let col = 0; col < n; col++) {
    let piv = col;
    for (let r = col + 1; r < n; r++) if (Math.abs(M[r][col]) > Math.abs(M[piv][col])) piv = r;
    [M[col], M[piv]] = [M[piv], M[col]];
    const d = M[col][col] || 1e-9;
    for (let r = 0; r < n; r++) {
      if (r === col) continue;
      const f = M[r][col] / d;
      for (let c = col; c <= n; c++) M[r][c] -= f * M[col][c];
    }
  }
  return M.map((row, i) => row[n] / (M[i][i] || 1e-9));
}

function applyH(h, x, y) {
  const w = h[6] * x + h[7] * y + h[8];
  return { x: (h[0] * x + h[1] * y + h[2]) / w, y: (h[3] * x + h[4] * y + h[5]) / w };
}

const UNIT = [{ x: 0, y: 0 }, { x: 1, y: 0 }, { x: 1, y: 1 }, { x: 0, y: 1 }];

/**
 * Build a mapper from a calibration + cage dims.
 * @returns {{ mapTap(u,v): null | {surface, depthFt, lateralFt, heightFt, sprayDeg, launchDeg} }}
 */
export function createCageMapper(calibration = DEFAULT_CAGE_CALIBRATION, dims = DEFAULT_CAGE_DIMS) {
  const c = calibration;
  const W = dims.widthFt, H = dims.heightFt, L = dims.lengthFt;
  const hc = dims.contactHeightFt ?? 3;

  // Each surface: image quad (corner order matches UNIT) + a param→feet resolver.
  // s = UNIT.x (0..1), t = UNIT.y (0..1).
  const surfaces = [
    { // BACK NET (plane depth = L): s=left→right, t=top→bottom
      name: 'back', quad: [c.btl, c.btr, c.bbr, c.bbl],
      toFeet: (s, t) => ({ depthFt: L, lateralFt: (s - 0.5) * W, heightFt: (1 - t) * H }),
    },
    { // FLOOR (height = 0): s=left→right, t=far→near
      name: 'floor', quad: [c.bbl, c.bbr, c.fbr, c.fbl],
      toFeet: (s, t) => ({ depthFt: (1 - t) * L, lateralFt: (s - 0.5) * W, heightFt: 0 }),
    },
    { // CEILING (height = H): s=left→right, t=far→near
      name: 'ceiling', quad: [c.btl, c.btr, c.ftr, c.ftl],
      toFeet: (s, t) => ({ depthFt: (1 - t) * L, lateralFt: (s - 0.5) * W, heightFt: H }),
    },
    { // LEFT WALL (lateral = -W/2): s=near→far, t=top→bottom
      name: 'left', quad: [c.ftl, c.btl, c.bbl, c.fbl],
      toFeet: (s, t) => ({ depthFt: s * L, lateralFt: -W / 2, heightFt: (1 - t) * H }),
    },
    { // RIGHT WALL (lateral = +W/2): s=near→far, t=top→bottom
      name: 'right', quad: [c.ftr, c.btr, c.bbr, c.fbr],
      toFeet: (s, t) => ({ depthFt: s * L, lateralFt: W / 2, heightFt: (1 - t) * H }),
    },
  ].map((surf) => ({ ...surf, H: solveHomography(surf.quad, UNIT) }));

  const mapTap = (u, v) => {
    // u,v are normalized tap coords in [0,1]. Find the surface whose homography
    // lands inside the unit square (with a small tolerance for shared edges).
    let best = null;
    let bestScore = Infinity;
    for (const surf of surfaces) {
      const { x: s, y: t } = applyH(surf.H, u, v);
      const inside = s >= -0.02 && s <= 1.02 && t >= -0.02 && t <= 1.02;
      if (!inside) continue;
      // Prefer the surface where the point sits most centrally (avoids edge ties).
      const score = Math.max(Math.abs(s - 0.5), Math.abs(t - 0.5));
      if (score < bestScore) { bestScore = score; best = { surf, s, t }; }
    }
    if (!best) return null;

    const pt = best.surf.toFeet(clamp01(best.s), clamp01(best.t));
    const horiz = Math.hypot(pt.depthFt, pt.lateralFt);
    const sprayDeg = horiz > 0 ? (Math.atan2(pt.lateralFt, pt.depthFt) * 180) / Math.PI : 0;
    const launchDeg = horiz > 0 ? (Math.atan2(pt.heightFt - hc, horiz) * 180) / Math.PI : 0;
    return {
      surface: best.surf.name,
      depthFt: pt.depthFt,
      lateralFt: pt.lateralFt,
      heightFt: pt.heightFt,
      sprayDeg: Math.round(sprayDeg * 10) / 10,
      launchDeg: Math.round(launchDeg * 10) / 10,
    };
  };

  return { mapTap, surfaces };
}

function clamp01(v) { return Math.max(0, Math.min(1, v)); }

/**
 * Wireframe of the calibrated cage box in normalized image coords (0..1).
 * Rendering this over the photo shows whether the calibration lines up with the
 * real cage edges. Returns arrays of {x,y} polylines.
 */
export function cageWireframe(calibration = DEFAULT_CAGE_CALIBRATION) {
  const c = calibration;
  return {
    back: [c.btl, c.btr, c.bbr, c.bbl, c.btl],
    front: [c.ftl, c.ftr, c.fbr, c.fbl, c.ftl],
    edges: [
      [c.ftl, c.btl], [c.ftr, c.btr], [c.fbl, c.bbl], [c.fbr, c.bbr],
    ],
  };
}

// The 8 draggable calibration corners, in a stable order for the overlay UI.
export const CAGE_CORNER_KEYS = ['ftl', 'ftr', 'fbl', 'fbr', 'btl', 'btr', 'bbl', 'bbr'];
