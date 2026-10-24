/**
 * battedBallOutcome.js — FMTRX probability-based result engine.
 *
 * Given a batted ball (EV, launch angle, spray) and the field, returns the
 * chances of Out / Single / Double / Triple / Home Run rather than a single
 * deterministic result — because a 99 mph ball at short is usually trouble but
 * still sometimes an out.
 *
 * Model: a base row (launch-angle band × EV band, from the FMTRX table) + an EV
 * out% modifier + a fence-based HR override. Fouls (|spray| > 45°) short-circuit.
 */
import { fenceAt } from './ballFlight';

const P = (out, single = 0, double = 0, triple = 0, homeRun = 0) => ({ out, single, double, triple, homeRun });

/** Base outcome probabilities by launch-angle band and exit velocity. */
function baseOutcome(ev, la) {
  if (la >= 45) return P(95, 5);            // popup
  if (la < 5) {                             // grounder (IF zone)
    if (ev < 75) return P(85, 15);
    if (ev < 90) return P(65, 30, 5);
    if (ev < 100) return P(35, 55, 10);
    return P(20, 65, 15);
  }
  if (la < 12) {                            // line drive (defender zone)
    if (ev < 80) return P(70, 25, 5);
    if (ev < 95) return P(45, 40, 15);
    return P(25, 45, 25, 5);
  }
  if (la < 25) {                            // gap ball (between OF)
    if (ev >= 95) return P(10, 10, 45, 35); // deep gap
    if (ev >= 85) return P(15, 25, 50, 10); // gap
    return P(60, 25, 15);                    // soft / bloop
  }
  if (la < 45) {                            // fly ball (OF zone)
    if (ev < 85) return P(80, 5, 15);
    if (ev < 95) return P(65, 5, 25, 5);
    return P(45, 0, 30, 15, 10);            // hard deep fly (HR gated below)
  }
  return P(95, 5);
}

/** Exit-velocity out% modifier (harder = fewer outs). */
export function evOutModifier(ev) {
  if (ev < 75) return 20;
  if (ev < 85) return 10;
  if (ev < 95) return 0;
  if (ev < 105) return -15;
  return -25;
}

function normalize(p) {
  const total = p.out + p.single + p.double + p.triple + p.homeRun;
  if (total <= 0) return P(100);
  const f = 100 / total;
  const out = Math.round(p.out * f);
  const single = Math.round(p.single * f);
  const double = Math.round(p.double * f);
  const triple = Math.round(p.triple * f);
  // Give any rounding remainder to the biggest non-out bucket (or out).
  const homeRun = Math.max(0, 100 - out - single - double - triple);
  return { out, single, double, triple, homeRun };
}

/**
 * @param {object} flight  from computeBallFlight (exitVelocityMph, launchAngleDeg, sprayAngleDeg, carryFt)
 * @param {object} config  field config (needs .fences for the HR override)
 * @returns {{foul:boolean, expected:string, expectedPct:number, probabilities:object|null}}
 */
export function battedBallOutcome(flight, config) {
  const ev = Number(flight?.exitVelocityMph) || 0;
  const la = Number(flight?.launchAngleDeg) || 0;
  const sa = Number(flight?.sprayAngleDeg) || 0;

  if (Math.abs(sa) > 45) {
    return { foul: true, expected: 'Foul Ball', expectedPct: 0, probabilities: null };
  }

  let p = { ...baseOutcome(ev, la) };

  // EV modifier: shift out% and redistribute the delta across the hit types.
  const hitTotal = p.single + p.double + p.triple + p.homeRun;
  const newOut = Math.max(0, Math.min(100, p.out + evOutModifier(ev)));
  const delta = p.out - newOut; // + = removed from out → spread to hits
  p.out = newOut;
  if (hitTotal > 0 && delta !== 0) {
    ['single', 'double', 'triple', 'homeRun'].forEach((k) => {
      p[k] = Math.max(0, p[k] + delta * (p[k] / hitTotal));
    });
  }

  // Home-run override: only if the carry clears the fence at that spray angle.
  const fences = config && config.fences;
  const clearsFence = fences ? Number(flight.carryFt) >= fenceAt(sa, fences) : false;
  if (clearsFence) {
    p = P(10, 0, 5, 0, 85);
  } else if (p.homeRun > 0) {
    p.out += p.homeRun; // couldn't leave the yard → it's in play
    p.homeRun = 0;
  }

  const probabilities = normalize(p);
  const ranked = [
    ['Out', probabilities.out],
    ['Single', probabilities.single],
    ['Double', probabilities.double],
    ['Triple', probabilities.triple],
    ['Home Run', probabilities.homeRun],
  ].sort((a, b) => b[1] - a[1]);

  return { foul: false, expected: ranked[0][0], expectedPct: ranked[0][1], probabilities };
}

/** Weighted-random result from a probability object. */
export function simulateOutcome(probabilities, random = Math.random) {
  if (!probabilities) return 'Foul Ball';
  const r = random() * 100;
  let acc = 0;
  const order = [['out', 'Out'], ['single', 'Single'], ['double', 'Double'], ['triple', 'Triple'], ['homeRun', 'Home Run']];
  for (const [key, label] of order) {
    acc += probabilities[key] || 0;
    if (r <= acc) return label;
  }
  return 'Out';
}

export const OUTCOME_LABELS = [
  ['out', 'OUT'], ['single', '1B'], ['double', '2B'], ['triple', '3B'], ['homeRun', 'HR'],
];
