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

// Prefer the shared API summary; compute a fallback for offline/older clients.
export function workoutFeedback(plan, progress = {}) {
  const p = progress || {};
  if (p.feedbackSummary && !p.pendingSync) return p.feedbackSummary;
  const summary = buildWorkoutCompletionSummary(plan, p);
  const check = (answers, keys) => {
    const answered = keys.filter(k => answers?.[k] != null && answers[k] !== '').length;
    return {status: answered === keys.length ? 'received' : answered ? 'partial' : 'missing', answered, expected: keys.length};
  };
  const checks = {
    readiness: check(p.readiness, ['sleep_hours','sleep_quality','energy','overall_soreness','arm_soreness','shoulder_soreness','elbow_soreness','lower_body_soreness','stress','motivation','pain_flag']),
    reflection: check(p.reflection, ['workout_rating','session_rpe']),
  };
  const count = summary.requiredItems || summary.totalItems;
  const done = summary.requiredItems ? summary.completedRequiredItems : summary.completedItems;
  const reasons = [];
  if (Number(p.reflection?.pain_after) >= 4) reasons.push('Pain reported after training');
  if (getWorkoutRating(p) != null && getWorkoutRating(p) <= 2) reasons.push('Low workout rating');
  if (Number(p.reflection?.session_rpe) >= 9) reasons.push('High reported effort');
  if (Object.values(p.items || {}).some(i => i.pain)) reasons.push('Discomfort reported during a drill');
  if (p.completedAt) {
    if (done < count) reasons.push('Submitted with unfinished drills');
    for (const [name, c] of Object.entries(checks)) if (c.status !== 'received') reasons.push(`${name[0].toUpperCase()}${name.slice(1)} ${c.status}`);
  }
  return {submission_status: p.completedAt ? 'submitted' : p.startedAt || done ? 'in_progress' : 'not_started', completed_drills: done, counted_drills: count, completion_pct: count ? summary.completionPct : null, checks, attention_reasons: reasons, review_status: isReviewed(p) ? 'reviewed' : 'awaiting_review', coach_feedback: p.coachReview?.feedback || ''};
}

export function needsAttention(plan, progress) {
  return workoutFeedback(plan, progress).attention_reasons.length > 0;
}
