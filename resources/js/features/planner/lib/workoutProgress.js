// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner — workout completion helpers. (Ported from the mobile app.)
//
// The plan says what the coach assigned; the progress record says what the player
// actually did. These pure helpers read both to derive completion %, status, and
// the coach-facing summary — without mutating either. Everything is null-safe so
// old progress records (missing the newer fields) never crash.
// ─────────────────────────────────────────────────────────────────────────────

import { BUCKET_BY_TYPE } from './plannerBuckets.js';

// Only "content" buckets hold workout exercises. Survey (readiness/reflection) and
// note (coach_notes) buckets are NOT workout tasks.
const bucketKind = (bucket) => BUCKET_BY_TYPE[bucket?.type]?.kind || bucket?.kind || 'content';

export const contentItems = (plan) => {
  const buckets = Array.isArray(plan?.buckets) ? plan.buckets : [];
  const out = [];
  buckets.forEach((b) => {
    if (bucketKind(b) !== 'content') return;
    (Array.isArray(b?.items) ? b.items : []).forEach((it) => { if (it) out.push(it); });
  });
  return out;
};

const isRequired = (item) => item?.required !== false; // default: required
const isDone = (progress, itemId) => !!(progress?.items?.[itemId]?.done);

const pct = (done, total) => (total > 0 ? Math.round((done / total) * 100) : 0);

/**
 * completionPct is based on REQUIRED content items when any exist; otherwise on all
 * content items. Optional items count toward totals but never block completion.
 * @returns {number} 0–100
 */
export function calculateWorkoutCompletion(plan, progress) {
  const items = contentItems(plan);
  if (items.length === 0) return 0;
  const required = items.filter(isRequired);
  if (required.length > 0) {
    const doneReq = required.filter((it) => isDone(progress, it.id)).length;
    return pct(doneReq, required.length);
  }
  const done = items.filter((it) => isDone(progress, it.id)).length;
  return pct(done, items.length);
}

/**
 * @returns {'not_started'|'in_progress'|'completed'|'reviewed'}
 */
export function getWorkoutStatus(plan, progress) {
  const p = progress || {};
  if (p.completedAt) {
    return p.coachReview && p.coachReview.reviewed ? 'reviewed' : 'completed';
  }
  const items = contentItems(plan);
  const anyDone = items.some((it) => isDone(p, it.id));
  if (p.startedAt || anyDone) return 'in_progress';
  return 'not_started';
}

/**
 * Full coach-facing summary. Safe with null/partial data.
 */
export function buildWorkoutCompletionSummary(plan, progress) {
  const items = contentItems(plan);
  const required = items.filter(isRequired);
  const completedItems = items.filter((it) => isDone(progress, it.id)).length;
  const completedRequiredItems = required.filter((it) => isDone(progress, it.id)).length;
  return {
    totalItems: items.length,
    completedItems,
    requiredItems: required.length,
    completedRequiredItems,
    completionPct: calculateWorkoutCompletion(plan, progress),
    status: getWorkoutStatus(plan, progress),
  };
}

// The player's written note to the coach (reflection.comments is the canonical field).
export function getPlayerWorkoutNote(progress) {
  const c = progress?.reflection?.comments;
  return typeof c === 'string' ? c.trim() : '';
}

// 1–5 overall workout rating, or null.
export function getWorkoutRating(progress) {
  const r = progress?.reflection?.workout_rating;
  return r === 0 || r ? Number(r) : null;
}

// Coach-review helpers.
export const isReviewed = (progress) => !!(progress?.coachReview && progress.coachReview.reviewed);

// "Review recommended" flags — coaching signals, never medical conclusions.
export function needsAttention(plan, progress) {
  const p = progress || {};
  if (!p.completedAt) return false;
  const rating = getWorkoutRating(p);
  const rpe = p.reflection?.session_rpe;
  const pain = p.reflection?.pain_after;
  const summary = buildWorkoutCompletionSummary(plan, p);
  if (pain != null && Number(pain) >= 4) return true;
  if (rating != null && rating <= 2) return true;
  if (rpe != null && Number(rpe) >= 9) return true;
  if (summary.completionPct < 70) return true;
  return false;
}
