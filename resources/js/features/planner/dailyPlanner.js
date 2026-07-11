// ─────────────────────────────────────────────────────────────────────────────
// FMTRX Daily Planner (Workout) — web feature module.
//
// Pure data + shape mappers shared by the Workout tab. Mirrors the mobile app's
// plan shape so a plan authored on either client is compatible. API calls live in
// the component (they need the useAxiosAuth composable); this file stays pure.
// ─────────────────────────────────────────────────────────────────────────────

export const PLAN_BUCKETS = [
  { type: 'throwing', title: 'Throwing' },
  { type: 'hitting', title: 'Hitting' },
  { type: 'strength', title: 'Strength' },
  { type: 'arm_care', title: 'Arm Care' },
  { type: 'conditioning', title: 'Conditioning' },
  { type: 'recovery', title: 'Recovery' },
]

export const PLAN_PHASES = ['Foundation', 'Build', 'Peak', 'In-Season', 'Recovery']
export const WORKLOAD_LEVELS = ['Light', 'Moderate', 'High']

export const bucketTitle = (type) =>
  PLAN_BUCKETS.find((b) => b.type === type)?.title || type

export const uid = (p = 'dp') => `${p}_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`

export const todayISO = () => new Date().toISOString().slice(0, 10)

export function blankPlan() {
  return {
    id: uid('plan'),
    name: '',
    date: todayISO(),
    phase: 'Foundation',
    primaryGoal: '',
    workloadLevel: 'Moderate',
    buckets: [],            // [{ type, title, items: [{ id, name, sets, reps, note }] }]
    assignedPlayerIds: [],
    status: 'draft',        // draft | published
    publishedAt: null,
  }
}

export const blankItem = () => ({ id: uid('it'), name: '', sets: null, reps: null, note: '' })

// Estimate: ~4 min per item, floored so an empty plan reads 0.
export const estimateMinutes = (plan) =>
  (plan.buckets || []).reduce((n, b) => n + (b.items || []).length, 0) * 4

// ── shape mappers (web camelCase ↔ api snake_case) ───────────────────────────
export const planToApi = (p, teamId) => ({
  id: p.id,
  ...(teamId ? { team_id: String(teamId) } : {}),
  name: p.name ?? '',
  date: p.date ?? null,
  phase: p.phase ?? null,
  primary_goal: p.primaryGoal ?? null,
  estimated_minutes: estimateMinutes(p),
  workload_level: p.workloadLevel ?? null,
  status: p.status ?? 'draft',
  buckets: Array.isArray(p.buckets) ? p.buckets : [],
  assigned_player_ids: Array.isArray(p.assignedPlayerIds) ? p.assignedPlayerIds.map(String) : [],
  published_at: p.publishedAt ?? null,
})

export const planFromApi = (r = {}) => ({
  id: r.id,
  name: r.name ?? '',
  date: r.date ?? todayISO(),
  phase: r.phase ?? 'Foundation',
  primaryGoal: r.primary_goal ?? '',
  workloadLevel: r.workload_level ?? 'Moderate',
  buckets: Array.isArray(r.buckets) ? r.buckets : [],
  assignedPlayerIds: Array.isArray(r.assigned_player_ids) ? r.assigned_player_ids.map(String) : [],
  status: r.status ?? 'draft',
  publishedAt: r.published_at ?? null,
})

export const groupFromApi = (r = {}) => ({
  id: r.id,
  name: r.name ?? '',
  memberIds: Array.isArray(r.member_ids) ? r.member_ids.map(String) : [],
})
