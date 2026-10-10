// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner (Workout) — web feature module.
//
// API shape mappers + a couple of helpers. The real bucket / drill / prescription
// data lives in ./lib (ported verbatim from the app so web and app build identical
// plans). Keep this file pure — API calls stay in the component.
// ─────────────────────────────────────────────────────────────────────────────

import { workoutTiming, restoreSectionTiming } from './lib/workoutTiming'
import { BUCKET_BY_TYPE } from './lib/plannerBuckets'

export const bucketTitle = (type) => BUCKET_BY_TYPE[type]?.title || type

export const uid = (p = 'dp') => `${p}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`
export const todayISO = () => new Date().toISOString().slice(0, 10)

// Explicit section durations override drill totals; untimed drills default to 4 minutes.
export const estimateMinutes = (plan) => workoutTiming(plan).minutes

export function blankPlan() {
  return {
    id: uid('plan'),
    name: '',
    startTime: '',
    date: todayISO(),
    phase: 'Foundation',
    primaryGoal: '',
    workloadLevel: 'Moderate',
    buckets: [{type:'daily_readiness', title:'Daily Readiness', kind:'survey', items:[], note:'Complete before starting your workout.'}, {type:'player_reflection', title:'Player Reflection', kind:'survey', items:[], note:'Complete after finishing your workout.'}],
    assignedPlayerIds: [],
    status: 'draft',
    publishedAt: null,
  }
}

const timedBuckets = plan => {
  if (plan.startTime === undefined) return plan.buckets || []
  const timing = workoutTiming(plan)
  return (plan.buckets || []).map((bucket, index) => ({...bucket, ...timing.sections[index], timingMode: 'automatic'}))
}

// ── shape mappers (web camelCase ↔ api snake_case) ───────────────────────────
export const planToApi = (p, teamId) => ({
  id: p.id,
  version: p.version,
  settings: {...p.settings, ...(p.startTime !== undefined ? {workout_start_time: p.startTime || ''} : {})},
  ...(teamId ? { team_id: String(teamId) } : {}),
  name: p.name ?? '',
  date: p.date ?? null,
  phase: p.phase ?? null,
  primary_goal: p.primaryGoal ?? null,
  estimated_minutes: estimateMinutes(p),
  workload_level: p.workloadLevel ?? null,
  status: p.status ?? 'draft',
  buckets: timedBuckets(p),
  assigned_player_ids: Array.isArray(p.assignedPlayerIds) ? p.assignedPlayerIds.map(String) : [],
  published_at: p.publishedAt ?? null,
})

export const planFromApi = (r = {}) => ({
  id: r.id,
  version: r.version,
  settings: r.settings,
  startTime: r.settings?.workout_start_time ?? r.buckets?.find(b => b.startTime)?.startTime ?? '',
  name: r.name ?? '',
  date: r.date ?? todayISO(),
  phase: r.phase ?? 'Foundation',
  primaryGoal: r.primary_goal ?? '',
  workloadLevel: r.workload_level ?? 'Moderate',
  buckets: Array.isArray(r.buckets) ? r.buckets.map(restoreSectionTiming) : [],
  assignedPlayerIds: Array.isArray(r.assigned_player_ids) ? r.assigned_player_ids.map(String) : [],
  status: r.status ?? 'draft',
  publishedAt: r.published_at ?? null,
})

export const groupFromApi = (r = {}) => ({
  id: r.id,
  version: r.version,
  settings: r.settings,
  name: r.name ?? '',
  memberIds: Array.isArray(r.member_ids) ? r.member_ids.map(String) : [],
})

// Map a player's progress record (snake-case backend model) to the camelCase shape
// the workoutProgress helpers expect. Null-safe: a null/absent record → null.
export const progressFromApi = (pr, planId) => {
  if (!pr || typeof pr !== 'object') return null
  return {
    planId: pr.plan_id ?? planId,
    readiness: pr.readiness || {},
    items: pr.items || {},
    reflection: pr.reflection || {},
    completionSummary: pr.completion_summary || pr.completionSummary || undefined,
    coachReview: pr.coach_review || pr.coachReview || undefined,
    startedAt: pr.started_at ?? pr.startedAt ?? null,
    completedAt: pr.completed_at ?? pr.completedAt ?? null,
  }
}
