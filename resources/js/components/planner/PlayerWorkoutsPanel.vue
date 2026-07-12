<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { planFromApi, bucketTitle } from '@/features/planner/dailyPlanner.js'

const { axiosGet, axiosPost } = useAxiosAuth()

const workouts = ref([])
const current = ref(null)   // { plan, items: { [id]: { done } }, startedAt }
const loading = ref(false)
const saving = ref(false)
const offline = ref(false)
const expandedInstructions = ref(new Set())

const metricLabels = {
  average_exit_velocity: 'Average EV',
  max_exit_velocity: 'Max EV',
  hard_hit_percentage: 'Hard-Hit %',
  line_drive_percentage: 'Line-Drive %',
  hitter_swing_miss_percentage: 'Swing/Miss %',
  average_fastball_velocity: 'Avg Fastball',
  max_fastball_velocity: 'Max Fastball',
  strike_percentage: 'Strike %',
  long_toss_max_distance: 'Long Toss Distance',
  weighted_ball_5oz_velocity: '5 oz Velocity',
  bench_press: 'Bench Press',
  squat: 'Squat',
  deadlift: 'Deadlift',
  pull_ups: 'Pull-Ups',
  pushups: 'Pushups',
  forty_yard_dash: '40-Yard Dash',
  sixty_yard_dash: '60-Yard Dash',
  broad_jump: 'Broad Jump',
  vertical_jump: 'Vertical Jump',
  mobility_score: 'Mobility Score',
  shoulder_mobility_score: 'Shoulder Mobility',
  hip_mobility_score: 'Hip Mobility',
  t_spine_mobility_score: 'T-Spine Mobility',
  player_context: 'Roster Profile',
}

const asArray = (value) => (Array.isArray(value) ? value : [])
const cleanText = (value) => String(value ?? '').trim()
const normalizeToken = (value) => cleanText(value).toLowerCase().replace(/\s+/g, '_')
const humanizeMetric = (value) => {
  const key = cleanText(value)
  if (!key) return ''
  return metricLabels[key] || key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

const itemMetrics = (item = {}) => {
  const values = [
    ...asArray(item.relatedMetrics),
    ...asArray(item.related_metrics),
    ...asArray(item.metrics_to_collect),
    ...asArray(item.metricsToCollect),
    ...asArray(item.metrics),
  ]

  return [...new Set(values
    .map((metric) => typeof metric === 'object' ? (metric.metric_key || metric.key || metric.display_name || metric.name) : metric)
    .map(humanizeMetric)
    .filter(Boolean))]
}

const isBenchmarkItem = (item = {}) => {
  const source = normalizeToken(item.source)
  const tags = asArray(item.tags).map(normalizeToken)
  const categoryGroup = normalizeToken(item.categoryGroup || item.category_group)

  return [
    'coach_action_practice_plan',
    'benchmark_collection_plan',
    'benchmark-generated',
    'benchmark_generated',
  ].includes(source)
    || tags.some((tag) => ['benchmark-generated', 'benchmark_generated', 'coach_action_practice_plan', 'benchmark_collection_plan'].includes(tag))
    || categoryGroup === 'fmtrx_benchmark'
    || itemMetrics(item).length > 0
}

const hasBenchmarkItems = (bucket = {}) => asArray(bucket.items).some((item) => isBenchmarkItem(item))

const benchmarkTypeLabel = (item = {}, bucket = {}) => {
  const name = `${cleanText(item.name)} ${bucket.type || ''}`.toLowerCase()
  if (name.includes('mobility')) return 'Mobility Screen'
  if (name.includes('strength') || bucket.type?.startsWith('strength')) return 'Strength Baseline'
  if (name.includes('bullpen') || name.includes('fastball') || bucket.type === 'pitching') return 'Throwing Baseline'
  if (name.includes('long toss') || name.includes('weighted') || bucket.type === 'throwing') return 'Throwing Baseline'
  if (name.includes('exit') || name.includes('power') || bucket.type === 'hitting') return 'Benchmark Baseline'
  if (name.includes('roster') || name.includes('profile')) return 'Data Collection'
  return 'FMTRX Benchmark'
}

const benchmarkWhy = (item = {}, bucket = {}) => {
  const explicit = [
    item.why,
    item.coachCue,
    item.coach_cue,
    item.description,
    item.note,
    bucket.why,
    bucket.note,
  ].map(cleanText).find(Boolean)
  if (explicit) return explicit

  const type = cleanText(bucket.type || item.bucket)
  const metrics = itemMetrics(item).map((metric) => metric.toLowerCase())
  if (type === 'hitting' || metrics.some((metric) => metric.includes('ev') || metric.includes('hit'))) {
    return 'This helps FMTRX understand your power and barrel profile.'
  }
  if (['pitching', 'throwing'].includes(type) || metrics.some((metric) => metric.includes('fastball') || metric.includes('strike') || metric.includes('toss'))) {
    return 'This helps FMTRX understand your throwing capacity and command profile.'
  }
  if (type.startsWith('strength') || metrics.some((metric) => ['bench press', 'squat', 'deadlift', 'pull-ups', 'pushups'].includes(metric))) {
    return 'This helps FMTRX connect strength to baseball output.'
  }
  if (type === 'speed_agility' || metrics.some((metric) => metric.includes('dash') || metric.includes('jump'))) {
    return 'This helps FMTRX understand speed and explosiveness.'
  }
  if (type === 'recovery' || metrics.some((metric) => metric.includes('mobility'))) {
    return 'This helps FMTRX identify movement limits that may affect performance.'
  }
  if (type === 'education' || metrics.some((metric) => metric.includes('roster') || metric.includes('profile'))) {
    return 'This helps FMTRX compare you to the correct age and peer group.'
  }

  return 'This helps FMTRX build a clearer development profile for you.'
}

const splitInstructions = (value) => {
  if (Array.isArray(value)) return value.map(cleanText).filter(Boolean)
  const text = cleanText(value)
  if (!text) return []

  return text
    .split(/\n|•|- |\.\s+/)
    .map((part) => part.replace(/^\d+\.\s*/, '').trim())
    .filter((part) => part.length > 2)
}

const fallbackInstructions = (item = {}, bucket = {}) => {
  const name = cleanText(item.name).toLowerCase()
  const type = cleanText(bucket.type || item.bucket)
  if (name.includes('exit velocity') || name.includes('power') || type === 'hitting') {
    return [
      'Complete a controlled barrel round.',
      'Complete a max-intent EV round.',
      'Record average EV and max EV.',
    ]
  }
  if (name.includes('bullpen') || name.includes('fastball') || type === 'pitching') {
    return [
      'Throw a tracked bullpen.',
      'Record average fastball, max fastball, and strike percentage.',
    ]
  }
  if (name.includes('long toss') || name.includes('weighted') || type === 'throwing') {
    return [
      'Complete the assigned throwing progression.',
      'Record the top distance or 5 oz velocity listed by your coach.',
    ]
  }
  if (name.includes('strength') || type.startsWith('strength')) {
    return [
      'Record assigned strength tests.',
      'Use coach-approved loads and safe technique.',
    ]
  }
  if (name.includes('mobility') || type === 'recovery') {
    return [
      'Complete shoulder, hip, and T-spine checks.',
      'Record the score or coach result.',
    ]
  }
  if (name.includes('roster') || name.includes('profile') || type === 'education') {
    return [
      'Review your profile information.',
      'Update missing context with your coach.',
    ]
  }

  return [
    'Complete the assigned block.',
    'Follow your coach cue and record any requested metric.',
  ]
}

const itemInstructions = (item = {}, bucket = {}) => {
  const explicit = [
    ...splitInstructions(item.instructions),
    ...splitInstructions(item.instruction),
    ...splitInstructions(item.coachInstructions),
    ...splitInstructions(item.coach_instructions),
  ]

  return (explicit.length ? explicit : fallbackInstructions(item, bucket)).slice(0, 8)
}

const instructionRows = (item = {}, bucket = {}) => {
  const rows = itemInstructions(item, bucket)
  const expanded = expandedInstructions.value.has(item.id)
  return expanded ? rows : rows.slice(0, 3)
}

const hasMoreInstructions = (item = {}, bucket = {}) => itemInstructions(item, bucket).length > 3
const toggleInstructions = (id) => {
  const next = new Set(expandedInstructions.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expandedInstructions.value = next
}

const itemMinutes = (item = {}) => {
  const seconds = Number(item.durationSec ?? item.duration_sec)
  if (Number.isFinite(seconds) && seconds > 0) return Math.max(1, Math.round(seconds / 60))

  const minutes = Number(item.duration_minutes ?? item.durationMinutes ?? item.estimated_minutes ?? item.minutes)
  return Number.isFinite(minutes) && minutes > 0 ? Math.round(minutes) : null
}

const itemMetaParts = (item = {}, bucket = {}) => {
  const parts = []
  const minutes = itemMinutes(item)
  if (minutes) parts.push(`${minutes} min`)
  if (item.sets) parts.push(`${item.sets} sets`)
  if (item.reps) parts.push(`${item.reps} reps`)
  if (item.throws) parts.push(`${item.throws} throws`)
  if (item.intent) parts.push(`${item.intent}% intent`)
  if (item.intensity) parts.push(cleanText(item.intensity))
  if (item.workloadType) parts.push(cleanText(item.workloadType))
  parts.push(bucketTitle(bucket.type || item.bucket))
  return [...new Set(parts.filter(Boolean))]
}

const itemRequiredLabel = (item = {}) => item.required === false ? 'Optional' : 'Required'
const coachCue = (item = {}) => cleanText(item.coachCue || item.coach_cue)

const load = async () => {
  loading.value = true
  try {
    const res = await axiosGet('player/daily-plans')
    const rows = res?.data?.data
    if (!Array.isArray(rows)) throw new Error('bad response')
    workouts.value = rows.map((r) => ({ ...planFromApi(r), progress: r.progress || null }))
    offline.value = false
  } catch {
    offline.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)

const itemCount = (w) => (w.buckets || []).reduce((n, b) => n + (b.items || []).length, 0)
const isDone = (w) => !!(w.progress && w.progress.completed_at)
const fmtDate = (iso) => {
  if (!iso) return ''
  try { return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) } catch { return iso }
}

const open = (w) => {
  const items = {}
  const prog = w.progress?.items || {}
  ;(w.buckets || []).forEach((b) => (b.items || []).forEach((it) => { items[it.id] = { done: !!prog[it.id]?.done } }))
  expandedInstructions.value = new Set()
  current.value = { plan: w, items, startedAt: w.progress?.started_at || new Date().toISOString() }
}
const back = () => { current.value = null }
const toggleItem = (id) => { current.value.items[id].done = !current.value.items[id].done }

const total = computed(() => current.value ? itemCount(current.value.plan) : 0)
const done = computed(() => current.value ? Object.values(current.value.items).filter((i) => i.done).length : 0)

const finish = async () => {
  saving.value = true
  try {
    await axiosPost(`player/daily-plans/${current.value.plan.id}/progress`, {
      items: current.value.items,
      started_at: current.value.startedAt,
      completed_at: new Date().toISOString(),
    })
    await load()
    current.value = null
  } catch {
    alert('Could not save — check your connection and try again.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <!-- ══ LIST ══ -->
    <template v-if="!current">
      <div v-if="loading" class="pw-empty">Loading…</div>
      <div v-else-if="offline" class="pw-empty">Couldn't load your workouts. Check your connection.</div>
      <div v-else-if="workouts.length === 0" class="pw-empty">No workouts assigned yet. Your coach's plans show up here.</div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <button v-for="w in workouts" :key="w.id" class="pw-card" @click="open(w)">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 text-left">
              <div class="font-extrabold text-white truncate">{{ w.name || 'Workout' }}</div>
              <div class="text-white/45 text-xs mt-0.5">{{ fmtDate(w.date) }} · {{ w.phase || '—' }}</div>
            </div>
            <span v-if="isDone(w)" class="pw-badge pw-badge--done">Completed</span>
            <span v-else class="pw-badge">{{ itemCount(w) }} items</span>
          </div>
          <div class="mt-3 flex flex-wrap gap-1.5">
            <span v-for="b in w.buckets" :key="b.type" class="pw-chip">{{ bucketTitle(b.type) }}</span>
          </div>
        </button>
      </div>
    </template>

    <!-- ══ DO A WORKOUT ══ -->
    <template v-else>
      <div class="flex items-center justify-between gap-3 mb-4">
        <button class="pw-link" @click="back">‹ All workouts</button>
        <div class="text-white/50 text-sm font-bold">{{ done }}/{{ total }} done</div>
      </div>

      <div class="mb-4">
        <div class="text-xl font-black text-white">{{ current.plan.name || 'Workout' }}</div>
        <div class="text-white/45 text-sm">{{ fmtDate(current.plan.date) }} · {{ current.plan.phase || '—' }}</div>
      </div>

      <div v-for="bucket in current.plan.buckets" :key="bucket.type" class="pw-bucket">
        <div class="pw-bucket-head">
          <div class="pw-bucket-title">{{ bucketTitle(bucket.type) }}</div>
          <span v-if="hasBenchmarkItems(bucket)" class="pw-benchmark-bucket">Benchmark</span>
        </div>
        <p v-if="bucket.note" class="pw-bucket-note">{{ bucket.note }}</p>

        <div
          v-for="it in bucket.items"
          :key="it.id"
          class="pw-item"
          :class="{ 'pw-item--done': current.items[it.id]?.done, 'pw-item--benchmark': isBenchmarkItem(it) }"
          @click="toggleItem(it.id)"
        >
          <input
            type="checkbox"
            :aria-label="`Mark ${it.name || 'item'} complete`"
            :checked="current.items[it.id]?.done"
            @click.stop
            @change="toggleItem(it.id)"
          />
          <div class="flex-1 min-w-0">
            <div class="pw-item-topline">
              <span v-if="isBenchmarkItem(it)" class="pw-benchmark-badge">{{ benchmarkTypeLabel(it, bucket) }}</span>
              <span class="pw-required" :class="{ 'pw-required--optional': it.required === false }">{{ itemRequiredLabel(it) }}</span>
            </div>
            <span class="pw-item-name">{{ it.name || 'Item' }}</span>
            <span v-if="itemMetaParts(it, bucket).length" class="pw-item-meta">{{ itemMetaParts(it, bucket).join(' · ') }}</span>

            <template v-if="isBenchmarkItem(it)">
              <p class="pw-why">
                <span>Why:</span> {{ benchmarkWhy(it, bucket) }}
              </p>
              <p v-if="coachCue(it)" class="pw-role-line">
                <span>Coach cue:</span> {{ coachCue(it) }}
              </p>
              <p class="pw-role-line">
                <span>Your job:</span> Complete the block and record the requested benchmark with your coach.
              </p>
              <div v-if="itemMetrics(it).length" class="pw-metrics">
                <span class="pw-metrics-label">Metrics:</span>
                <span v-for="metric in itemMetrics(it)" :key="`${it.id}-${metric}`" class="pw-metric-chip">{{ metric }}</span>
              </div>
              <ul class="pw-instructions">
                <li v-for="row in instructionRows(it, bucket)" :key="`${it.id}-${row}`">{{ row }}</li>
              </ul>
              <button
                v-if="hasMoreInstructions(it, bucket)"
                type="button"
                class="pw-more"
                @click.stop="toggleInstructions(it.id)"
              >
                {{ expandedInstructions.has(it.id) ? 'Show less' : 'Show more' }}
              </button>
              <p v-if="current.items[it.id]?.done" class="pw-complete-note">
                Completed — benchmark data may need coach review.
              </p>
            </template>

            <template v-else>
              <span v-if="it.note" class="pw-item-note">{{ it.note }}</span>
            </template>
          </div>
        </div>
      </div>

      <div v-if="!current.plan.buckets.length" class="pw-empty">This workout has no items yet.</div>

      <button class="pw-finish" :disabled="saving" @click="finish">{{ saving ? 'Saving…' : 'Finish Workout' }}</button>
    </template>
  </div>
</template>

<style scoped>
.pw-empty { border: 1px dashed rgba(255,255,255,.14); border-radius: 16px; padding: 30px 20px; text-align: center; color: rgba(255,255,255,.5); font-size: 14px; }
.pw-card { display: block; width: 100%; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; padding: 16px; cursor: pointer; transition: border-color .12s, background .12s; }
.pw-card:hover { border-color: rgba(255,255,255,.24); background: rgba(255,255,255,.06); }
.pw-badge { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; padding: 3px 8px; border-radius: 6px; white-space: nowrap; color: rgba(255,255,255,.6); background: rgba(255,255,255,.08); }
.pw-badge--done { color: #43d089; background: rgba(52,211,153,.16); }
.pw-chip { font-size: 12px; font-weight: 700; color: rgba(255,255,255,.7); background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1); padding: 3px 10px; border-radius: 999px; }
.pw-link { background: none; border: none; color: #7ca6f5; font-weight: 800; font-size: 14px; cursor: pointer; }
.pw-link:hover { text-decoration: underline; }
.pw-bucket { border: 1px solid rgba(255,255,255,.1); border-radius: 14px; padding: 14px; margin-bottom: 12px; background: rgba(255,255,255,.03); }
.pw-bucket-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
.pw-bucket-title { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; color: #fff; }
.pw-bucket-note { color: rgba(255,255,255,.58); font-size: 12.5px; line-height: 1.45; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.035); border-radius: 10px; padding: 9px 10px; margin: 0 0 10px; }
.pw-benchmark-bucket { font-size:10px; font-weight:900; letter-spacing:.05em; text-transform:uppercase; color:#93f5d2; background:rgba(16,185,129,.13); border:1px solid rgba(110,231,183,.22); border-radius:999px; padding:3px 8px; }
.pw-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 6px; border-radius: 10px; cursor: pointer; }
.pw-item:hover { background: rgba(255,255,255,.04); }
.pw-item--benchmark { border:1px solid rgba(110,231,183,.16); background: linear-gradient(135deg, rgba(16,185,129,.09), rgba(59,130,246,.045)); padding: 12px 10px; margin-bottom: 8px; }
.pw-item--benchmark:hover { background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(59,130,246,.06)); }
.pw-item input { width: 20px; height: 20px; margin-top: 1px; accent-color: #ff2d55; flex: none; }
.pw-item-topline { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:5px; }
.pw-benchmark-badge { display:inline-flex; align-items:center; color:#d1fae5; border:1px solid rgba(110,231,183,.28); background:rgba(16,185,129,.16); border-radius:999px; padding:3px 8px; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; }
.pw-required { display:inline-flex; align-items:center; color:rgba(255,255,255,.68); border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.06); border-radius:999px; padding:3px 8px; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; }
.pw-required--optional { color:#fde68a; border-color:rgba(251,191,36,.25); background:rgba(251,191,36,.1); }
.pw-item-name { display: block; color: #fff; font-size: 15px; font-weight: 700; }
.pw-item--done .pw-item-name { text-decoration: line-through; color: rgba(255,255,255,.45); }
.pw-item-meta { display: block; color: rgba(255,255,255,.55); font-size: 12.5px; margin-top: 1px; }
.pw-item-note { display: block; color: rgba(255,255,255,.4); font-size: 12.5px; font-style: italic; margin-top: 1px; }
.pw-why { color:rgba(255,255,255,.72); font-size:12.5px; line-height:1.45; margin:8px 0 0; }
.pw-why span, .pw-role-line span, .pw-metrics-label { color:#fff; font-weight:900; }
.pw-role-line { color:rgba(255,255,255,.6); font-size:12px; line-height:1.4; margin:5px 0 0; }
.pw-metrics { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:9px; }
.pw-metrics-label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:rgba(255,255,255,.72); }
.pw-metric-chip { display:inline-flex; align-items:center; border-radius:999px; border:1px solid rgba(56,189,248,.22); background:rgba(14,165,233,.12); color:#bae6fd; font-size:11px; font-weight:800; padding:3px 8px; }
.pw-instructions { margin:9px 0 0; padding-left:18px; color:rgba(255,255,255,.68); font-size:12.5px; line-height:1.45; }
.pw-instructions li { margin-top:3px; }
.pw-more { margin-top:7px; border:0; background:transparent; color:#7ca6f5; font-size:12px; font-weight:900; padding:0; cursor:pointer; }
.pw-more:hover { text-decoration:underline; }
.pw-complete-note { margin:9px 0 0; border:1px solid rgba(52,211,153,.22); background:rgba(52,211,153,.1); color:#bbf7d0; border-radius:8px; padding:7px 9px; font-size:12px; font-weight:800; }
.pw-finish { width: 100%; margin-top: 8px; background: #22c55e; border: none; color: #06210f; font-weight: 900; font-size: 15px; padding: 14px; border-radius: 12px; cursor: pointer; }
.pw-finish:hover { background: #2dd46a; }
.pw-finish:disabled { opacity: .6; cursor: default; }
</style>
