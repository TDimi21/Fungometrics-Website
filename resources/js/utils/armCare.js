/**
 * Arm Care scoring helpers for the web player app.
 * Mirrors the mobile app's armCareStore so scores match across platforms.
 */
import { countRequired } from '@/data/armCareRoutines'

export const EXERCISE_STATUS = {
  PENDING: 'pending',
  COMPLETE: 'complete',
  SKIP: 'skip',
}

/** Map a 0–100 score to its compliance grade + tone. */
export function gradeForScore(score) {
  if (score >= 90) return { label: 'Elite Prep', tone: 'elite' }
  if (score >= 80) return { label: 'Good Prep', tone: 'good' }
  if (score >= 70) return { label: 'Partial Prep', tone: 'partial' }
  if (score >= 60) return { label: 'Incomplete Prep', tone: 'incomplete' }
  return { label: 'At Risk', tone: 'risk' }
}

export const TONE_COLOR = {
  elite: '#16A34A',
  good: '#22C55E',
  partial: '#F59E0B',
  incomplete: '#F97316',
  risk: '#ff2d55',
}

/**
 * Compute the Arm Care Compliance Score from a statuses map.
 * Score = completed required drills / total required drills * 100.
 * - "choose one" phases count as a single required slot.
 * - exercises with sub-drills are scored per sub-drill.
 */
export function computeScore(routine, statuses) {
  const assigned = countRequired(routine)
  let completedRequired = 0
  let completedTotal = 0
  let skipped = 0

  routine.phases.forEach((phase) => {
    if (phase.selectOne) {
      const done = phase.exercises.filter(
        (ex) => statuses[ex.id] === EXERCISE_STATUS.COMPLETE,
      ).length
      completedTotal += done
      if (done > 0) completedRequired += 1
      return
    }
    phase.exercises.forEach((ex) => {
      if (ex.subItems?.length) {
        ex.subItems.forEach((si) => {
          const s = statuses[si.id] || EXERCISE_STATUS.PENDING
          if (s === EXERCISE_STATUS.COMPLETE) {
            completedTotal += 1
            completedRequired += 1
          } else if (s === EXERCISE_STATUS.SKIP) {
            skipped += 1
          }
        })
        return
      }
      const s = statuses[ex.id] || EXERCISE_STATUS.PENDING
      if (s === EXERCISE_STATUS.COMPLETE) {
        completedTotal += 1
        if (ex.required) completedRequired += 1
      } else if (s === EXERCISE_STATUS.SKIP) {
        skipped += 1
      }
    })
  })

  const score = assigned > 0 ? Math.round((completedRequired / assigned) * 100) : 0
  return {
    score: Math.max(0, Math.min(100, score)),
    assigned,
    completedRequired,
    completedTotal,
    skipped,
  }
}

/** Build the record saved to history + sent to the backend. */
export function buildSessionRecord(routine, statuses, notes = {}, meta = {}) {
  const { score, assigned, completedRequired, completedTotal, skipped } = computeScore(
    routine,
    statuses,
  )
  const grade = gradeForScore(score)

  const breakdown = routine.phases.map((phase) => ({
    id: phase.id,
    name: phase.name,
    exercises: phase.exercises.map((ex) => {
      const hasSub = !!ex.subItems?.length
      return {
        id: ex.id,
        name: ex.name,
        required: !!ex.required,
        status: hasSub
          ? ex.subItems.every((si) => statuses[si.id] === EXERCISE_STATUS.COMPLETE)
            ? EXERCISE_STATUS.COMPLETE
            : EXERCISE_STATUS.PENDING
          : statuses[ex.id] || EXERCISE_STATUS.PENDING,
        note: notes[ex.id] || '',
        subItems: hasSub
          ? ex.subItems.map((si) => ({
              id: si.id,
              name: si.name,
              status: statuses[si.id] || EXERCISE_STATUS.PENDING,
            }))
          : undefined,
      }
    }),
  }))

  return {
    localId: `ac_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
    routineKey: routine.key,
    routineLabel: routine.label,
    score,
    grade: grade.label,
    assigned,
    completedRequired,
    completedTotal,
    skipped,
    durationSeconds: meta.durationSeconds || 0,
    performedAt: new Date().toISOString(),
    breakdown,
    synced: false,
  }
}

// ── Local history (localStorage) — mirrors mobile so the day-by-day list
//    survives reloads and works even before the backend endpoint is live ──────
const HISTORY_KEY = 'armcare_history'
const HISTORY_LIMIT = 60

export function readLocalHistory() {
  try {
    const raw = localStorage.getItem(HISTORY_KEY)
    const list = raw ? JSON.parse(raw) : []
    return Array.isArray(list) ? list : []
  } catch {
    return []
  }
}

export function writeLocalHistory(list) {
  const trimmed = list.slice(0, HISTORY_LIMIT)
  try {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(trimmed))
  } catch {
    /* ignore quota errors */
  }
  return trimmed
}

export function toApiPayload(record, { userId, teamId } = {}) {
  return {
    user_id: userId || null,
    team_id: teamId || null,
    routine_key: record.routineKey,
    routine_label: record.routineLabel,
    score: record.score,
    grade: record.grade,
    assigned: record.assigned,
    completed: record.completedRequired,
    completed_total: record.completedTotal,
    skipped: record.skipped,
    duration_seconds: record.durationSeconds,
    performed_at: record.performedAt,
    breakdown: record.breakdown,
    client_id: record.localId,
  }
}
