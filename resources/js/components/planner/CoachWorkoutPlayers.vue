<script setup>
// Coach "View Players" — every assigned player's progress for one workout, with a
// per-player review detail (readiness, prescribed-vs-actual, coach feedback, Mark
// Reviewed). Mirrors the mobile CoachWorkoutPlayers + CoachPlayerWorkoutDetail flow.
import { ref, computed, watch, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { BUCKET_BY_TYPE, SURVEY_FIELDS } from '@/features/planner/lib/plannerBuckets.js'
import { setSummary } from '@/features/planner/lib/strengthLoad.js'
import { readinessScore, readinessStatus } from '@/features/planner/lib/readiness.js'
import {
  buildWorkoutCompletionSummary, getWorkoutRating, getPlayerWorkoutNote,
  needsAttention, isReviewed, contentItems,
} from '@/features/planner/lib/workoutProgress.js'
import { progressFromApi, bucketTitle } from '@/features/planner/dailyPlanner.js'

const props = defineProps({ plan: { type: Object, required: true } })
const emit = defineEmits(['back'])
const { axiosGet, axiosPost } = useAxiosAuth()

const rows = ref([])
const loading = ref(false)
const progressUnavailable = ref(false)
const filter = ref('All')
const selected = ref(null)   // { player, progress } when a player detail is open
const feedback = ref('')
const saving = ref(false)
const savedNotice = ref('')

const FILTERS = ['All', 'Completed', 'In Progress', 'Not Started', 'Needs Attention', 'Reviewed']
const STATUS = {
  not_started: { label: 'Not Started', color: '#64748B' },
  in_progress: { label: 'In Progress', color: '#f59e0b' },
  completed: { label: 'Completed', color: '#22c55e' },
  reviewed: { label: 'Reviewed', color: '#2160C4' },
}
const statusMeta = (s) => STATUS[s] || STATUS.not_started

const load = async () => {
  if (!props.plan?.id) return
  loading.value = true
  try {
    const res = await axiosGet(`coach/daily-plans/${props.plan.id}/progress`)
    const data = res?.data?.data
    const players = Array.isArray(data?.players) ? data.players : []
    rows.value = players.map((row) => ({
      player: row.player || {},
      progress: progressFromApi(row.progress, props.plan.id),
    }))
    progressUnavailable.value = false
  } catch {
    rows.value = []
    progressUnavailable.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)
watch(() => props.plan?.id, () => { selected.value = null; load() })

const enriched = computed(() => rows.value.map((r) => {
  const summary = buildWorkoutCompletionSummary(props.plan || {}, r.progress || {})
  return {
    ...r,
    summary,
    rating: getWorkoutRating(r.progress),
    rpe: r.progress?.reflection?.session_rpe ?? null,
    pain: r.progress?.reflection?.pain_after ?? null,
    note: getPlayerWorkoutNote(r.progress),
    reviewed: isReviewed(r.progress),
    attention: needsAttention(props.plan || {}, r.progress || {}),
  }
}))

const stats = computed(() => {
  const assigned = enriched.value.length
  const completions = enriched.value.map((e) => e.summary.completionPct)
  const teamCompletion = assigned ? Math.round(completions.reduce((a, b) => a + b, 0) / assigned) : 0
  const ratings = enriched.value.map((e) => e.rating).filter((r) => r != null)
  const avgRating = ratings.length ? (ratings.reduce((a, b) => a + b, 0) / ratings.length).toFixed(1) : null
  return { assigned, teamCompletion, avgRating }
})

const filtered = computed(() => enriched.value.filter((e) => {
  switch (filter.value) {
    case 'Completed': return e.summary.status === 'completed' || e.summary.status === 'reviewed'
    case 'In Progress': return e.summary.status === 'in_progress'
    case 'Not Started': return e.summary.status === 'not_started'
    case 'Needs Attention': return e.attention
    case 'Reviewed': return e.reviewed
    default: return true
  }
}))

const playerName = (p) => `${p?.first_name || ''} ${p?.last_name || ''}`.trim() || 'Player'
const initial = (p) => (playerName(p)[0] || '?').toUpperCase()
const fmtDate = (iso) => {
  if (!iso) return ''
  try { return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) } catch { return iso }
}
const fmtTime = (iso) => {
  if (!iso) return '—'
  try { return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) } catch { return '—' }
}
const fmtVal = (v) => {
  if (v === true) return 'Yes'
  if (v === false) return 'No'
  if (v == null || v === '') return '—'
  return String(v)
}

// ── detail ───────────────────────────────────────────────────────────────────
const openPlayer = (e) => {
  selected.value = e
  feedback.value = e.progress?.coachReview?.feedback || ''
  savedNotice.value = ''
}
const closePlayer = () => { selected.value = null }

const contentBuckets = computed(() =>
  (props.plan?.buckets || []).filter((b) => (BUCKET_BY_TYPE[b.type]?.kind || 'content') === 'content'))
const hasExercises = computed(() => contentItems(props.plan || {}).length > 0)
const selSummary = computed(() => selected.value ? buildWorkoutCompletionSummary(props.plan || {}, selected.value.progress || {}) : null)
const selReflection = computed(() => selected.value?.progress?.reflection || {})
const selReadiness = computed(() => selected.value?.progress?.readiness || {})
const selReviewed = computed(() => !!selected.value?.progress?.coachReview?.reviewed)
const reflectionFields = computed(() => (SURVEY_FIELDS.player_reflection || []).filter((f) => f.key !== 'workout_rating'))
const readinessFields = computed(() => SURVEY_FIELDS.daily_readiness || [])
const rScore = computed(() => selected.value ? readinessScore(selReadiness.value) : null)
const rStatus = computed(() => readinessStatus(rScore.value))

const itemProgress = (id) => selected.value?.progress?.items?.[id] || {}
const isStrength = (it) => Array.isArray(it.setList)
const actualText = (ip, idx) => {
  const a = ip.actualSets?.[idx] || {}
  return (a.weight != null || a.reps != null)
    ? `${a.weight != null ? a.weight : '—'} × ${a.reps != null ? a.reps : '—'}`
    : '—'
}
const nonStrengthText = (it) =>
  [it.sets ? `${it.sets} sets` : '', it.reps ? `${it.reps} reps` : '', it.intensity || ''].filter(Boolean).join(' · ') || '—'

const markReviewed = async () => {
  if (saving.value || !selected.value) return
  saving.value = true
  const playerId = selected.value.player.id
  const reviewedAt = new Date().toISOString()
  const nextReview = { reviewed: true, reviewedAt, reviewedBy: null, feedback: feedback.value }
  let ok = false
  try {
    await axiosPost(`coach/daily-plans/${props.plan.id}/players/${playerId}/review`, {
      reviewed: true, feedback: feedback.value, reviewed_at: reviewedAt,
    })
    ok = true
  } catch { ok = false }
  // Reflect the review locally in both the detail and the list row.
  selected.value.progress = { ...(selected.value.progress || {}), coachReview: nextReview }
  const row = rows.value.find((r) => String(r.player.id) === String(playerId))
  if (row) row.progress = { ...(row.progress || {}), coachReview: nextReview }
  saving.value = false
  savedNotice.value = ok
    ? 'Reviewed — your feedback is shared with the player.'
    : 'Saved on this device — it will sync when the connection returns.'
}
</script>

<template>
  <div class="cwp">
    <!-- ══ PLAYER LIST ══ -->
    <template v-if="!selected">
      <div class="cwp-top">
        <button class="cwp-back" @click="emit('back')">‹ Back to plans</button>
        <div class="cwp-title">{{ plan?.name || 'Workout' }}</div>
        <span />
      </div>

      <div class="cwp-stats">
        <div class="cwp-stat"><div class="cwp-stat-n">{{ stats.assigned }}</div><div class="cwp-stat-l">Assigned</div></div>
        <div class="cwp-stat"><div class="cwp-stat-n">{{ stats.teamCompletion }}%</div><div class="cwp-stat-l">Team Completion</div></div>
        <div class="cwp-stat"><div class="cwp-stat-n">{{ stats.avgRating || '—' }}</div><div class="cwp-stat-l">Avg Rating</div></div>
      </div>
      <div class="cwp-date">{{ fmtDate(plan?.date) }}</div>

      <div class="cwp-filters">
        <button
          v-for="f in FILTERS" :key="f"
          class="cwp-chip" :class="{ 'cwp-chip--on': filter === f }"
          @click="filter = f"
        >{{ f }}</button>
      </div>

      <p v-if="progressUnavailable && rows.length" class="cwp-warn">Live player progress needs the server — showing assignments only.</p>

      <div v-if="loading" class="cwp-empty">Loading…</div>
      <div v-else-if="!filtered.length" class="cwp-empty">
        {{ rows.length === 0 ? 'No players assigned to this workout.' : 'No players in this filter.' }}
      </div>
      <div v-else class="cwp-list">
        <button v-for="e in filtered" :key="e.player.id" class="cwp-card" @click="openPlayer(e)">
          <div class="cwp-card-top">
            <div class="cwp-avatar">{{ initial(e.player) }}</div>
            <div class="cwp-card-main">
              <div class="cwp-name">{{ playerName(e.player) }}</div>
              <div class="cwp-sub">
                <span v-if="e.player.position" class="cwp-pos">{{ e.player.position }}</span>
                <span class="cwp-pill" :style="{ color: statusMeta(e.summary.status).color, background: statusMeta(e.summary.status).color + '22' }">
                  {{ statusMeta(e.summary.status).label }}
                </span>
                <span v-if="e.attention" class="cwp-pill" style="color:#f59e0b;background:rgba(245,158,11,.18)">Review recommended</span>
                <span v-if="e.reviewed" class="cwp-check">✔ Reviewed</span>
              </div>
            </div>
            <div class="cwp-pct">
              <div class="cwp-pct-n">{{ e.summary.completionPct }}%</div>
              <div class="cwp-pct-s">{{ e.summary.completedItems }}/{{ e.summary.totalItems }}</div>
            </div>
          </div>
          <div v-if="e.rating != null || e.rpe != null || e.pain != null" class="cwp-metrics">
            <span v-if="e.rating != null">★ {{ e.rating }}/5</span>
            <span v-if="e.rpe != null">🔥 RPE {{ e.rpe }}</span>
            <span v-if="e.pain != null" :style="Number(e.pain) >= 4 ? 'color:#ef4444' : ''">⚠ Pain {{ e.pain }}</span>
          </div>
          <div v-if="e.note" class="cwp-note">“{{ e.note }}”</div>
        </button>
      </div>
    </template>

    <!-- ══ PLAYER DETAIL / REVIEW ══ -->
    <template v-else>
      <div class="cwp-top">
        <button class="cwp-back" @click="closePlayer">‹ Back</button>
        <div class="cwp-title">{{ playerName(selected.player) }}</div>
        <span />
      </div>

      <div class="cwp-panel">
        <div class="cwp-workout-name">{{ plan.name || 'Workout' }}</div>
        <div class="cwp-head-row">
          <span class="cwp-pill" :style="{ color: statusMeta(selSummary.status).color, background: statusMeta(selSummary.status).color + '22' }">
            {{ statusMeta(selSummary.status).label }}
          </span>
          <span class="cwp-head-meta">{{ selSummary.completionPct }}% · {{ selSummary.completedItems }}/{{ selSummary.totalItems }} tasks</span>
        </div>
        <div class="cwp-time-row">
          <span>Started {{ fmtTime(selected.progress?.startedAt) }}</span>
          <span>Completed {{ fmtTime(selected.progress?.completedAt) }}</span>
        </div>
      </div>

      <div class="cwp-section">Player Reflection</div>
      <div class="cwp-panel">
        <div class="cwp-rating-row">
          <span class="cwp-rating-l">Workout rating</span>
          <span class="cwp-rating-v">{{ selReflection.workout_rating != null ? `${selReflection.workout_rating}/5` : '—' }}</span>
        </div>
        <div v-for="f in reflectionFields" :key="f.key" class="cwp-row">
          <span class="cwp-row-l">{{ f.label }}</span>
          <span class="cwp-row-v">{{ fmtVal(selReflection[f.key]) }}</span>
        </div>
      </div>

      <div class="cwp-section">Readiness<span v-if="rScore != null"> · {{ rScore }} ({{ rStatus.label }})</span></div>
      <div class="cwp-panel">
        <div v-for="f in readinessFields" :key="f.key" class="cwp-row">
          <span class="cwp-row-l">{{ f.label }}</span>
          <span class="cwp-row-v">{{ fmtVal(selReadiness[f.key]) }}</span>
        </div>
      </div>

      <div class="cwp-section">Workout Breakdown</div>
      <div v-if="!hasExercises" class="cwp-panel cwp-dim">No exercises in this workout.</div>
      <template v-else>
        <div v-for="bucket in contentBuckets" :key="bucket.type" class="cwp-panel">
        <div class="cwp-bucket-title">{{ bucketTitle(bucket.type) }}</div>
        <div v-for="it in (bucket.items || [])" :key="it.id" class="cwp-item">
          <div class="cwp-item-head">
            <span class="cwp-item-dot" :class="{ 'cwp-item-dot--done': itemProgress(it.id).done }">{{ itemProgress(it.id).done ? '✓' : '○' }}</span>
            <span class="cwp-item-name">{{ it.name }}</span>
            <span v-if="it.required === false" class="cwp-opt">optional</span>
            <span v-if="itemProgress(it.id).pain" class="cwp-pain">⚠</span>
          </div>
          <div v-if="isStrength(it)" class="cwp-sets">
            <div v-for="(s, idx) in it.setList" :key="s.id || idx" class="cwp-set-row">
              <span class="cwp-set-n">{{ idx + 1 }}</span>
              <span class="cwp-set-target">Target {{ setSummary(s) }}</span>
              <span class="cwp-set-actual">Actual {{ actualText(itemProgress(it.id), idx) }}</span>
            </div>
          </div>
          <div v-else class="cwp-item-sub">{{ nonStrengthText(it) }}</div>
          <div v-if="itemProgress(it.id).note" class="cwp-item-note">Player note: {{ itemProgress(it.id).note }}</div>
        </div>
        </div>
      </template>

      <div class="cwp-section">Coach Feedback</div>
      <div class="cwp-panel">
        <textarea v-model="feedback" class="cwp-feedback" placeholder="Add feedback the player will see…" rows="3"></textarea>
        <p v-if="selReviewed" class="cwp-reviewed-note">Reviewed {{ fmtTime(selected.progress?.coachReview?.reviewedAt) }}</p>
        <p v-if="savedNotice" class="cwp-saved">{{ savedNotice }}</p>
        <button class="cwp-review-btn" :disabled="saving" @click="markReviewed">
          <span>✔</span> {{ saving ? 'Saving…' : selReviewed ? 'Update Review' : 'Mark Reviewed' }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.cwp { color: #f1f5f9; }
.cwp-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.cwp-back { color: #60a5fa; font-weight: 700; font-size: 14px; background: none; border: 0; cursor: pointer; padding: 4px 0; }
.cwp-title { font-size: 17px; font-weight: 900; text-align: center; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cwp-top > span { width: 90px; }

.cwp-stats { display: flex; gap: 8px; }
.cwp-stat { flex: 1; text-align: center; }
.cwp-stat-n { font-size: 22px; font-weight: 900; }
.cwp-stat-l { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
.cwp-date { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 6px; }

.cwp-filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0; }
.cwp-chip { height: 34px; padding: 0 14px; border-radius: 17px; background: #0f172a; border: 1px solid rgba(255,255,255,.09); color: #94a3b8; font-size: 12.5px; font-weight: 700; cursor: pointer; }
.cwp-chip--on { background: #D8232A; border-color: #D8232A; color: #fff; }

.cwp-warn { color: #f59e0b; font-size: 12.5px; margin-bottom: 12px; }
.cwp-empty { text-align: center; color: #64748b; font-size: 13px; padding: 40px 20px; background: #0f172a; border: 1px solid rgba(255,255,255,.08); border-radius: 14px; }

.cwp-list { display: flex; flex-direction: column; gap: 10px; }
.cwp-card { text-align: left; width: 100%; background: #161c33; border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 14px; cursor: pointer; }
.cwp-card:hover { border-color: rgba(255,255,255,.22); }
.cwp-card-top { display: flex; align-items: center; gap: 12px; }
.cwp-avatar { width: 40px; height: 40px; border-radius: 20px; background: rgba(33,96,196,.25); display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 16px; flex: none; }
.cwp-card-main { flex: 1; min-width: 0; }
.cwp-name { font-size: 15px; font-weight: 800; }
.cwp-sub { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.cwp-pos { color: #94a3b8; font-size: 12px; font-weight: 700; }
.cwp-pill { padding: 2px 8px; border-radius: 5px; font-size: 10px; font-weight: 800; }
.cwp-check { color: #2160C4; font-size: 11px; font-weight: 800; }
.cwp-pct { text-align: right; flex: none; }
.cwp-pct-n { font-size: 18px; font-weight: 900; }
.cwp-pct-s { font-size: 11px; color: #64748b; }
.cwp-metrics { display: flex; gap: 16px; margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,.08); color: #94a3b8; font-size: 12px; font-weight: 700; }
.cwp-note { color: #64748b; font-size: 12.5px; font-style: italic; margin-top: 8px; }

.cwp-panel { background: #161c33; border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 14px; margin-bottom: 12px; }
.cwp-dim { color: #94a3b8; font-size: 14px; }
.cwp-workout-name { font-size: 18px; font-weight: 900; }
.cwp-head-row { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
.cwp-head-meta { color: #94a3b8; font-size: 13px; font-weight: 700; }
.cwp-time-row { display: flex; justify-content: space-between; margin-top: 10px; color: #64748b; font-size: 12px; }
.cwp-section { color: #64748b; font-size: 11px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; margin: 4px 0 8px; }
.cwp-rating-row { display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; margin-bottom: 6px; border-bottom: 1px solid rgba(255,255,255,.08); }
.cwp-rating-l { font-size: 14px; font-weight: 800; }
.cwp-rating-v { color: #22c55e; font-size: 18px; font-weight: 900; }
.cwp-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 7px 0; }
.cwp-row-l { color: #94a3b8; font-size: 13px; flex: none; max-width: 45%; }
.cwp-row-v { color: #f1f5f9; font-size: 13px; font-weight: 700; text-align: right; flex: 1; }
.cwp-bucket-title { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 8px; }
.cwp-item { padding: 8px 0; border-top: 1px solid rgba(255,255,255,.08); }
.cwp-item-head { display: flex; align-items: center; gap: 8px; }
.cwp-item-dot { color: #64748b; font-weight: 900; }
.cwp-item-dot--done { color: #22c55e; }
.cwp-item-name { font-size: 14px; font-weight: 700; }
.cwp-opt { color: #64748b; font-size: 10px; font-weight: 700; }
.cwp-pain { color: #D8232A; }
.cwp-sets { margin-top: 6px; margin-left: 24px; }
.cwp-set-row { display: flex; align-items: center; gap: 8px; padding: 3px 0; font-variant-numeric: tabular-nums; }
.cwp-set-n { width: 20px; color: #64748b; font-size: 12px; font-weight: 800; }
.cwp-set-target { flex: 1; color: #94a3b8; font-size: 12px; }
.cwp-set-actual { color: #f1f5f9; font-size: 12px; font-weight: 700; }
.cwp-item-sub { color: #94a3b8; font-size: 12px; margin-top: 4px; margin-left: 24px; }
.cwp-item-note { color: #64748b; font-size: 12px; font-style: italic; margin-top: 4px; margin-left: 24px; }
.cwp-feedback { width: 100%; background: #0f172a; color: #f1f5f9; border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 12px; font-size: 14px; resize: vertical; }
.cwp-reviewed-note { color: #2160C4; font-size: 12px; font-weight: 700; margin-top: 8px; }
.cwp-saved { color: #22c55e; font-size: 12.5px; margin-top: 8px; }
.cwp-review-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; background: #D8232A; color: #fff; font-weight: 800; font-size: 14px; padding: 13px; border: 0; border-radius: 11px; margin-top: 12px; cursor: pointer; }
.cwp-review-btn:disabled { opacity: .6; cursor: default; }
</style>
