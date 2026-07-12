<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { storeToRefs } from 'pinia'
import {
  BUCKETS, BUCKET_BY_TYPE, INTENSITY_LEVELS, THROW_INTENTS, PHASES, WORKLOAD_LEVELS,
} from '@/features/planner/lib/plannerBuckets.js'
import {
  getCategoriesForBucket, searchDrills, drillCategory, itemFromDrill,
} from '@/features/planner/lib/plannerDrills.js'
import { PRESCRIPTION_TYPES, makeSet, renumber, setSummary } from '@/features/planner/lib/strengthLoad.js'
import {
  blankPlan, estimateMinutes, planToApi, planFromApi, groupFromApi, bucketTitle, uid,
} from '@/features/planner/dailyPlanner.js'

const { axiosGet, axiosPost, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

const plans = ref([])
const groups = ref([])
const teamPlayers = ref([])
const editing = ref(null)
const loading = ref(false)
const saving = ref(false)
const offline = ref(false)
const playerSearch = ref('')
const commandCenter = ref(null)
const commandLoading = ref(false)
const commandError = ref('')
const commandActionMessage = ref('')

// Drill picker
const picker = ref(null)          // the bucket object being added to
const pickerCategory = ref(null)
const pickerQuery = ref('')
const customName = ref('')
const customDrills = ref([])      // coach's saved custom drills/lifts (merge into library)

// ── data ─────────────────────────────────────────────────────────────────────
const loadPlans = async () => {
  loading.value = true
  try {
    const res = await axiosGet('coach/daily-plans')
    const rows = res?.data?.data
    if (!Array.isArray(rows)) throw new Error('bad response')
    plans.value = rows.map(planFromApi).filter((p) => p.status !== 'template')
    offline.value = false
  } catch { offline.value = true } finally { loading.value = false }
}
const loadCommandCenter = async () => {
  if (!activeTeamId.value) {
    commandCenter.value = null
    return
  }
  commandLoading.value = true
  commandError.value = ''
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/planner-command-center`, { days: 365 })
    commandCenter.value = res?.data?.data || null
  } catch {
    commandCenter.value = null
    commandError.value = 'Could not load the planner command center.'
  } finally {
    commandLoading.value = false
  }
}
const loadGroups = async () => {
  try {
    const res = await axiosGet('coach/player-groups')
    const rows = res?.data?.data
    groups.value = Array.isArray(rows) ? rows.map(groupFromApi) : []
  } catch { groups.value = [] }
}
const loadRoster = async () => {
  if (!activeTeamId.value) return
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}`)
    const raw = Array.isArray(res?.data?.data) ? res.data.data : []
    teamPlayers.value = raw.map((p) => ({
      id: String(p?.id ?? p?.user_id ?? ''),
      name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || `Player #${p?.id}`,
    })).filter((p) => p.id)
  } catch { teamPlayers.value = [] }
}
// Coach's saved custom drills/lifts — merged into the picker library (+ shared team ones).
const loadCustomDrills = async () => {
  try {
    const res = await axiosGet('coach/drills')
    const rows = res?.data?.data
    customDrills.value = Array.isArray(rows) ? rows : []
  } catch { customDrills.value = [] }
}
onMounted(() => { loadPlans(); loadGroups(); loadRoster(); loadCustomDrills(); loadCommandCenter() })
watch(activeTeamId, () => { loadRoster(); loadCommandCenter() })

// ── plan / builder ───────────────────────────────────────────────────────────
const newPlan = () => { editing.value = blankPlan() }
const editPlan = (p) => { editing.value = JSON.parse(JSON.stringify(p)) }
const cancelEdit = () => { editing.value = null }

const itemCount = (p) => (p.buckets || []).reduce((n, b) => n + (b.items || []).length, 0)
const fmtDate = (iso) => {
  if (!iso) return ''
  try { return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' }) } catch { return iso }
}
const prettyDateTime = (value) => {
  if (!value) return '—'
  try { return new Date(value).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) } catch { return value }
}
const oneDecimal = (value) => Number.isFinite(Number(value)) ? Number(value).toFixed(1) : '0.0'
const human = (value) => String(value || '—').replace(/[_-]/g, ' ').replace(/\b\w/g, (m) => m.toUpperCase())
const commandPlan = computed(() => commandCenter.value?.plan_status || {})
const commandSummary = computed(() => commandCenter.value?.player_status_summary || {})
const commandBenchmark = computed(() => commandCenter.value?.benchmark_workflow_summary || {})
const commandReview = computed(() => commandCenter.value?.review_queue_summary || {})
const commandTrusted = computed(() => commandCenter.value?.trusted_data_summary || {})
const commandRows = computed(() => Array.isArray(commandCenter.value?.player_rows) ? commandCenter.value.player_rows : [])
const commandActions = computed(() => Array.isArray(commandCenter.value?.next_actions) ? commandCenter.value.next_actions : [])
const commandGaps = computed(() => Array.isArray(commandCenter.value?.remaining_benchmark_gaps) ? commandCenter.value.remaining_benchmark_gaps : [])
const priorityClass = (priority) => ({
  critical: 'dp-priority--critical',
  high: 'dp-priority--high',
  medium: 'dp-priority--medium',
  low: 'dp-priority--low',
}[priority] || 'dp-priority--medium')
const canRunAction = (action) => ['publish_plan', 'send_reminder', 'review_submissions', 'refresh_intelligence'].includes(action?.action_type)
const runCommandAction = async (action) => {
  if (!action) return
  const actionType = action.action_type
  commandActionMessage.value = ''

  if (actionType === 'publish_plan' && !commandCenter.value?.daily_plan_id) {
    newPlan()
    return
  }

  if (!canRunAction(action)) return

  try {
    if (actionType === 'publish_plan') {
      const plan = plans.value.find((p) => p.id === commandCenter.value?.daily_plan_id)
      if (!plan) {
        commandActionMessage.value = 'Open the plan card to publish it.'
        return
      }
      const updated = { ...JSON.parse(JSON.stringify(plan)), status: 'published', publishedAt: plan.publishedAt || new Date().toISOString() }
      await axiosPost('coach/daily-plans', planToApi(updated, activeTeamId.value))
      commandActionMessage.value = 'Plan published.'
      await loadPlans()
    } else if (actionType === 'send_reminder') {
      await axiosPost(`coach/daily-plans/${commandCenter.value?.daily_plan_id}/send-reminder`, {})
      commandActionMessage.value = 'Reminder preview updated. Manual copy is used until push delivery is available.'
    } else if (actionType === 'review_submissions') {
      const res = await axiosGet(`intelligence/teams/${activeTeamId.value}/benchmark-task-reviews`)
      const count = res?.data?.pending_count ?? res?.data?.data?.pending_count ?? commandReview.value.pending_review_count ?? 0
      commandActionMessage.value = `${count} submission${Number(count) === 1 ? '' : 's'} pending review.`
    } else if (actionType === 'refresh_intelligence') {
      await axiosPost(`intelligence/teams/${activeTeamId.value}/refresh-benchmarks`, {})
      commandActionMessage.value = 'Benchmark intelligence refreshed.'
    }
    await loadCommandCenter()
  } catch {
    commandActionMessage.value = 'Could not complete that command center action.'
  }
}

// Buckets not yet on the plan (keep the app's ordering).
const availableBuckets = computed(() =>
  BUCKETS.filter((b) => !(editing.value?.buckets || []).some((x) => x.type === b.type)))
const bucketDef = (type) => BUCKET_BY_TYPE[type] || {}
const addBucket = (b) => {
  editing.value.buckets.push({ type: b.type, title: b.title, kind: b.kind, items: [], note: '' })
  editing.value.buckets = BUCKETS
    .filter((def) => editing.value.buckets.some((x) => x.type === def.type))
    .map((def) => editing.value.buckets.find((x) => x.type === def.type))
}
const removeBucket = (type) => { editing.value.buckets = editing.value.buckets.filter((b) => b.type !== type) }
const isStrengthItem = (it) => Array.isArray(it.setList)

// ── drill picker ─────────────────────────────────────────────────────────────
const openPicker = (bucket) => { picker.value = bucket; pickerCategory.value = null; pickerQuery.value = ''; customName.value = '' }
const closePicker = () => { picker.value = null }
const pickerCategories = computed(() => picker.value ? getCategoriesForBucket(picker.value.type, customDrills.value) : [])
const pickerDrills = computed(() => {
  if (!picker.value) return []
  let list = searchDrills(pickerQuery.value, picker.value.type, customDrills.value)
  if (pickerCategory.value) list = list.filter((d) => drillCategory(d) === pickerCategory.value)
  return list.slice(0, 200)
})
const addDrill = (drill) => { picker.value.items.push(itemFromDrill(drill)) }

const buildCustomDrill = (name, bucketType) => {
  const def = bucketDef(bucketType)
  const workloadType = def.throwing ? 'throwing' : def.strength ? 'strength' : 'none'
  return {
    id: uid('drill'), name, bucket: bucketType, workloadType, source: 'custom',
    categoryGroup: '', subcategory: '',
    defaultSets: 3, defaultReps: 5, defaultIntensity: 'Moderate',
    defaultPrescriptionType: def.strength ? 'percent_1rm' : null,
  }
}
const addCustomDrill = async () => {
  const name = customName.value.trim()
  if (!name || !picker.value) return
  const drill = buildCustomDrill(name, picker.value.type)
  picker.value.items.push(itemFromDrill(drill))     // drop it into the plan now
  customDrills.value = [drill, ...customDrills.value] // and into the library list
  customName.value = ''
  // Persist to the drill library so it syncs and reappears next time (best-effort).
  try {
    await axiosPost('coach/drills', {
      ...drill,
      category_group: drill.categoryGroup || '',
      equipment: '',
      visibility: 'private',
      source: 'custom',
      ...(activeTeamId.value ? { team_id: String(activeTeamId.value) } : {}),
    })
  } catch { /* stays local for this session; will sync on the next save */ }
}
const removeItem = (bucket, id) => { bucket.items = bucket.items.filter((it) => it.id !== id) }

// ── strength sets ("type of reps") ───────────────────────────────────────────
const addSet = (item) => { item.setList = renumber([...(item.setList || []), makeSet((item.setList?.length || 0) + 1, { prescriptionType: item.defaultPrescriptionType || 'percent_1rm' })]) }
const removeSet = (item, sid) => { item.setList = renumber((item.setList || []).filter((s) => s.id !== sid)) }
const summary = (s) => setSummary(s)

// ── assign ───────────────────────────────────────────────────────────────────
const selected = computed(() => new Set((editing.value?.assignedPlayerIds || []).map(String)))
const togglePlayer = (id) => {
  const s = new Set(editing.value.assignedPlayerIds.map(String))
  s.has(String(id)) ? s.delete(String(id)) : s.add(String(id))
  editing.value.assignedPlayerIds = [...s]
}
const applyGroup = (g) => {
  const ids = (g.memberIds || []).filter((id) => teamPlayers.value.some((p) => p.id === String(id)))
  editing.value.assignedPlayerIds = [...new Set(ids.map(String))]
}
const assignWholeTeam = () => { editing.value.assignedPlayerIds = teamPlayers.value.map((p) => p.id) }
const clearAssign = () => { editing.value.assignedPlayerIds = [] }
const filteredPlayers = computed(() => {
  const q = playerSearch.value.trim().toLowerCase()
  return q ? teamPlayers.value.filter((p) => p.name.toLowerCase().includes(q)) : teamPlayers.value
})

// ── save / delete ────────────────────────────────────────────────────────────
const save = async (status) => {
  if (!String(editing.value.name || '').trim()) { alert('Name your plan first.'); return }
  editing.value.status = status
  if (status === 'published' && !editing.value.publishedAt) editing.value.publishedAt = new Date().toISOString()
  saving.value = true
  try {
    await axiosPost('coach/daily-plans', planToApi(editing.value, activeTeamId.value))
    await loadPlans()
    await loadCommandCenter()
    editing.value = null
  } catch { alert('Could not reach the server — check your connection and try again.') } finally { saving.value = false }
}
const del = async (p) => {
  if (!confirm(`Delete "${p.name || 'Untitled'}"?`)) return
  plans.value = plans.value.filter((x) => x.id !== p.id)
  try { await axiosDelete('coach/daily-plans/', p.id); await loadCommandCenter() } catch { /* server reconciles */ }
}
</script>

<template>
  <div class="min-h-screen bg-[#060b14] text-white">
    <div class="w-full px-4 py-6 lg:px-8 lg:py-8 pb-28 md:pb-12">

      <!-- ══ LIST ══ -->
      <template v-if="!editing">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <div>
            <h1 class="text-2xl font-black tracking-wide flex items-center gap-2"><span>💪</span> Workout Plans</h1>
            <p class="text-white/40 text-sm mt-0.5">Build a player's day from buckets and the drill library, then publish it to their app.</p>
          </div>
          <button class="dp-btn dp-btn--primary" @click="newPlan">+ New Plan</button>
        </div>
        <p v-if="offline" class="dp-hint mb-4">Couldn't reach the server. Published plans and new saves need a connection.</p>
        <section class="dp-command mb-5">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <div class="dp-command-eyebrow">Coach Planner Command Center</div>
              <h2 class="text-xl font-black tracking-wide">Today’s Planner Status</h2>
              <p class="text-white/45 text-sm mt-1">Daily Plan, player acknowledgement, benchmark workflow, and next coach actions.</p>
            </div>
            <button class="dp-btn" :disabled="commandLoading" @click="loadCommandCenter">{{ commandLoading ? 'Refreshing…' : 'Refresh' }}</button>
          </div>

          <div v-if="commandError" class="dp-command-alert">{{ commandError }}</div>
          <div v-else-if="commandLoading && !commandCenter" class="dp-command-loading">Loading planner command center…</div>
          <template v-else-if="commandCenter">
            <div class="dp-command-grid">
              <div class="dp-command-card">
                <div class="dp-command-label">Plan Status</div>
                <div class="dp-command-value">{{ commandPlan.title || 'No active plan' }}</div>
                <div class="dp-command-sub">{{ human(commandPlan.status) }} · {{ commandPlan.scheduled_for || 'No date' }}</div>
                <div class="dp-command-mini">
                  <span>{{ commandPlan.block_count || 0 }} blocks</span>
                  <span>{{ commandPlan.estimated_total_minutes ?? '—' }} min</span>
                  <span>{{ commandPlan.benchmark_generated ? 'Benchmark generated' : 'Manual plan' }}</span>
                </div>
              </div>
              <div class="dp-command-card">
                <div class="dp-command-label">Player Status</div>
                <div class="dp-command-value">{{ commandSummary.completed_count || 0 }} / {{ commandSummary.assigned_count || 0 }} complete</div>
                <div class="dp-command-sub">{{ oneDecimal(commandSummary.completion_percentage) }}% completion · {{ oneDecimal(commandSummary.acknowledgement_percentage) }}% acknowledged</div>
                <div class="dp-command-mini">
                  <span>{{ commandSummary.not_acknowledged_count || 0 }} not acknowledged</span>
                  <span>{{ commandSummary.not_started_count || 0 }} not started</span>
                  <span>{{ commandSummary.pending_review_count || 0 }} pending review</span>
                </div>
              </div>
              <div class="dp-command-card">
                <div class="dp-command-label">Benchmark Workflow</div>
                <div class="dp-command-value">{{ commandBenchmark.benchmark_items_completed || 0 }} / {{ commandBenchmark.benchmark_items_total || 0 }} items</div>
                <div class="dp-command-sub">{{ commandBenchmark.submitted_metric_count || 0 }} submitted metrics · {{ commandBenchmark.promoted_count || 0 }} promoted</div>
                <div class="dp-command-mini">
                  <span>{{ commandReview.pending_review_count || 0 }} review queue</span>
                  <span>{{ commandTrusted.trusted_values_added || 0 }} trusted values</span>
                  <span>{{ commandBenchmark.refresh_status || 'No refresh status' }}</span>
                </div>
              </div>
            </div>

            <div v-if="commandActions.length" class="dp-command-block">
              <div class="dp-section mb-2">Next Actions</div>
              <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="action in commandActions" :key="`${action.action_type}-${action.title}`" class="dp-action-card">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <span class="dp-priority" :class="priorityClass(action.priority)">{{ human(action.priority) }}</span>
                      <div class="font-extrabold mt-2">{{ action.title }}</div>
                    </div>
                    <button
                      v-if="action.button_label && (canRunAction(action) || action.action_type === 'publish_plan')"
                      class="dp-btn dp-btn--primary dp-btn--small"
                      @click.stop="runCommandAction(action)"
                    >
                      {{ action.button_label }}
                    </button>
                  </div>
                  <p class="text-white/55 text-xs mt-2">{{ action.why }}</p>
                  <p class="text-white/35 text-xs mt-1">{{ action.action }}</p>
                </div>
              </div>
            </div>

            <p v-if="commandActionMessage" class="dp-command-message">{{ commandActionMessage }}</p>

            <div class="dp-command-block">
              <div class="dp-section mb-2">Player Rows</div>
              <div v-if="!commandRows.length" class="dp-empty dp-empty--sm">No players are assigned to the active Daily Plan yet.</div>
              <div v-else class="dp-player-status-list">
                <div v-for="row in commandRows" :key="row.player_id" class="dp-player-status-row">
                  <div class="min-w-0">
                    <div class="font-bold truncate">{{ row.player_name }}</div>
                    <div class="text-white/40 text-xs">{{ row.next_needed_action || 'No action needed' }}</div>
                  </div>
                  <div class="dp-player-status-metrics">
                    <span>{{ row.acknowledged ? 'Ack' : 'Needs ack' }}</span>
                    <span>{{ row.completed_items || 0 }}/{{ row.total_items || 0 }} items</span>
                    <span>{{ oneDecimal(row.completion_percentage) }}%</span>
                    <span>{{ row.pending_review_count || 0 }} review</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="dp-command-grid dp-command-grid--two">
              <div class="dp-command-card">
                <div class="dp-command-label">Review Queue</div>
                <div class="dp-command-value">{{ commandReview.pending_review_count || 0 }} pending</div>
                <div class="dp-command-sub">Oldest: {{ prettyDateTime(commandReview.oldest_pending_at) }}</div>
                <div v-if="commandReview.tasks_pending_review?.length" class="mt-3 space-y-1">
                  <div v-for="task in commandReview.tasks_pending_review.slice(0, 4)" :key="task.task_id" class="text-xs text-white/55">
                    {{ task.player_name }} · {{ task.title }}
                  </div>
                </div>
              </div>
              <div class="dp-command-card">
                <div class="dp-command-label">Remaining Benchmark Gaps</div>
                <div class="dp-command-value">{{ commandGaps.length }} tracked</div>
                <div v-if="!commandGaps.length" class="dp-command-sub">No benchmark gaps surfaced for this plan.</div>
                <div v-else class="mt-3 space-y-1">
                  <div v-for="gap in commandGaps.slice(0, 5)" :key="`${gap.display_name}-${gap.category}`" class="dp-gap-row">
                    <span>{{ gap.display_name }}</span>
                    <span>{{ gap.missing_count }}/{{ gap.eligible_count }} · {{ human(gap.priority) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
          <div v-else class="dp-command-loading">Select a team to load the planner command center.</div>
        </section>
        <div v-if="loading" class="dp-empty">Loading…</div>
        <div v-else-if="plans.length === 0" class="dp-empty">No workout plans yet. Click <strong>New Plan</strong> to build one.</div>
        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div v-for="p in plans" :key="p.id" class="dp-card" @click="editPlan(p)">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="font-extrabold truncate">{{ p.name || 'Untitled plan' }}</div>
                <div class="text-white/40 text-xs mt-0.5">{{ fmtDate(p.date) }} · {{ p.phase || '—' }}</div>
              </div>
              <span class="dp-badge" :class="p.status === 'published' ? 'dp-badge--pub' : 'dp-badge--draft'">{{ p.status === 'published' ? 'Published' : 'Draft' }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-xs text-white/50">
              <span>{{ p.buckets.length }} bucket{{ p.buckets.length === 1 ? '' : 's' }}</span>
              <span>{{ itemCount(p) }} item{{ itemCount(p) === 1 ? '' : 's' }}</span>
              <span>{{ estimateMinutes(p) }} min</span>
              <span>{{ p.assignedPlayerIds.length }} assigned</span>
            </div>
            <div class="mt-3 pt-3 border-t border-white/10 flex justify-end">
              <button class="dp-link dp-link--danger" @click.stop="del(p)">Delete</button>
            </div>
          </div>
        </div>
      </template>

      <!-- ══ BUILDER ══ -->
      <template v-else>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <button class="dp-link" @click="cancelEdit">‹ Back to plans</button>
          <div class="flex gap-2">
            <button class="dp-btn" :disabled="saving" @click="save('draft')">Save Draft</button>
            <button class="dp-btn dp-btn--primary" :disabled="saving" @click="save('published')">{{ saving ? 'Saving…' : 'Publish' }}</button>
          </div>
        </div>

        <!-- Plan info -->
        <div class="dp-panel grid gap-3 sm:grid-cols-2 mb-4">
          <label class="dp-field sm:col-span-2"><span class="dp-label">Plan name</span>
            <input v-model="editing.name" class="dp-input" placeholder="e.g. Tuesday Lift + Throw" /></label>
          <label class="dp-field"><span class="dp-label">Date</span><input v-model="editing.date" type="date" class="dp-input" /></label>
          <label class="dp-field"><span class="dp-label">Phase</span>
            <select v-model="editing.phase" class="dp-input"><option v-for="ph in PHASES" :key="ph" :value="ph">{{ ph }}</option></select></label>
          <label class="dp-field"><span class="dp-label">Workload</span>
            <select v-model="editing.workloadLevel" class="dp-input"><option v-for="w in WORKLOAD_LEVELS" :key="w.label" :value="w.label">{{ w.label }}</option></select></label>
          <label class="dp-field"><span class="dp-label">Primary goal</span><input v-model="editing.primaryGoal" class="dp-input" placeholder="Optional" /></label>
        </div>

        <!-- Add bucket -->
        <div class="dp-panel mb-4">
          <div class="dp-section">Add a bucket</div>
          <div class="flex flex-wrap gap-1.5">
            <button v-for="b in availableBuckets" :key="b.type" class="dp-chip" @click="addBucket(b)">
              <span class="dp-dot" :style="{ background: b.color }"></span>{{ b.title }}
            </button>
            <span v-if="!availableBuckets.length" class="text-white/40 text-sm">All buckets added.</span>
          </div>
        </div>

        <!-- Buckets -->
        <div v-for="bucket in editing.buckets" :key="bucket.type" class="dp-bucket">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 font-bold">
              <span class="dp-dot" :style="{ background: bucketDef(bucket.type).color }"></span>{{ bucketTitle(bucket.type) }}
            </div>
            <button class="dp-link dp-link--danger" @click="removeBucket(bucket.type)">Remove</button>
          </div>
          <p class="text-white/35 text-xs -mt-1 mb-3">{{ bucketDef(bucket.type).hint }}</p>

          <!-- survey buckets -->
          <div v-if="bucketDef(bucket.type).kind === 'survey'" class="dp-note-box">
            The player completes this survey {{ bucket.type === 'daily_readiness' ? 'before' : 'after' }} the session.
          </div>

          <!-- note buckets -->
          <textarea v-else-if="bucketDef(bucket.type).kind === 'note'" v-model="bucket.note" class="dp-input w-full" rows="3" placeholder="Message to the player…"></textarea>

          <!-- content buckets: items + drill picker -->
          <template v-else>
            <div v-for="it in bucket.items" :key="it.id" class="dp-item-card">
              <div class="flex items-start justify-between gap-2">
                <div class="font-bold">{{ it.name }}</div>
                <button class="dp-x" title="Remove" @click="removeItem(bucket, it.id)">×</button>
              </div>

              <!-- STRENGTH: per-set prescription (type of reps) -->
              <div v-if="isStrengthItem(it)" class="mt-2">
                <div v-for="s in it.setList" :key="s.id" class="dp-set">
                  <span class="dp-set-n">{{ s.setNumber }}</span>
                  <select v-model="s.prescriptionType" class="dp-input dp-input--sm">
                    <option v-for="pt in PRESCRIPTION_TYPES" :key="pt.type" :value="pt.type">{{ pt.short }}</option>
                  </select>
                  <input v-model.number="s.targetReps" type="number" min="0" class="dp-input dp-input--num" placeholder="Reps" />
                  <input v-if="s.prescriptionType === 'percent_1rm'" v-model.number="s.percentage" type="number" min="0" max="150" class="dp-input dp-input--num" placeholder="% 1RM" />
                  <input v-else-if="s.prescriptionType === 'fixed_weight' || s.prescriptionType === 'bodyweight'" v-model.number="s.weight" type="number" min="0" class="dp-input dp-input--num" placeholder="lb" />
                  <input v-else-if="s.prescriptionType === 'timed'" v-model.number="s.timeSec" type="number" min="0" class="dp-input dp-input--num" placeholder="sec" />
                  <input v-else-if="s.prescriptionType === 'distance'" v-model.number="s.distance" type="number" min="0" class="dp-input dp-input--num" placeholder="ft" />
                  <input v-else-if="s.prescriptionType === 'velocity'" v-model.number="s.velocity" type="number" min="0" class="dp-input dp-input--num" placeholder="mph" />
                  <input v-else-if="s.prescriptionType === 'rpe'" v-model.number="s.rpe" type="number" min="0" max="10" class="dp-input dp-input--num" placeholder="RPE" />
                  <input v-else-if="s.prescriptionType === 'custom'" v-model="s.customText" class="dp-input flex-1 min-w-0" placeholder="Custom…" />
                  <button class="dp-x" title="Remove set" @click="removeSet(it, s.id)">×</button>
                  <span class="dp-set-sum">{{ summary(s) }}</span>
                </div>
                <button class="dp-link mt-1" @click="addSet(it)">+ Add set</button>
              </div>

              <!-- CONTENT: sets / reps / intensity (+ throwing intent) -->
              <div v-else class="mt-2 flex flex-wrap items-center gap-2">
                <label class="dp-mini"><span>Sets</span><input v-model.number="it.sets" type="number" min="0" class="dp-input dp-input--num" /></label>
                <label class="dp-mini"><span>Reps</span><input v-model.number="it.reps" type="number" min="0" class="dp-input dp-input--num" /></label>
                <label class="dp-mini"><span>Intensity</span>
                  <select v-model="it.intensity" class="dp-input dp-input--sm"><option v-for="lv in INTENSITY_LEVELS" :key="lv.label" :value="lv.label">{{ lv.label }}</option></select>
                </label>
                <label v-if="bucketDef(bucket.type).throwing" class="dp-mini"><span>Intent</span>
                  <select v-model.number="it.intent" class="dp-input dp-input--sm"><option :value="null">—</option><option v-for="pct in THROW_INTENTS" :key="pct" :value="pct">{{ pct }}%</option></select>
                </label>
                <label v-if="bucketDef(bucket.type).throwing" class="dp-mini"><span>Throws</span><input v-model.number="it.throws" type="number" min="0" class="dp-input dp-input--num" /></label>
              </div>
            </div>

            <button class="dp-add-drill" @click="openPicker(bucket)">+ Add drill / lift</button>
          </template>
        </div>

        <!-- Assign -->
        <div class="dp-panel mt-4">
          <div class="dp-section flex items-center justify-between">
            <span>Assign to</span>
            <span class="text-white/40 text-xs font-normal normal-case">{{ editing.assignedPlayerIds.length }} selected</span>
          </div>
          <div class="flex flex-wrap gap-1.5 mb-3">
            <button class="dp-chip" @click="assignWholeTeam">Whole team</button>
            <button v-for="g in groups" :key="g.id" class="dp-chip" @click="applyGroup(g)">{{ g.name }}</button>
            <button class="dp-chip dp-chip--ghost" @click="clearAssign">Clear</button>
          </div>
          <input v-model="playerSearch" class="dp-input mb-2" placeholder="Search players…" />
          <div v-if="teamPlayers.length === 0" class="dp-empty dp-empty--sm">No roster found for this team.</div>
          <div v-else class="dp-players">
            <label v-for="p in filteredPlayers" :key="p.id" class="dp-player">
              <input type="checkbox" :checked="selected.has(p.id)" @change="togglePlayer(p.id)" />
              <span>{{ p.name }}</span>
            </label>
          </div>
        </div>
      </template>
    </div>

    <!-- ══ DRILL PICKER MODAL ══ -->
    <div v-if="picker" class="dp-modal" @click.self="closePicker">
      <div class="dp-modal-card">
        <div class="flex items-center justify-between mb-3">
          <div class="font-black text-lg">Add to {{ bucketTitle(picker.type) }}</div>
          <button class="dp-x" @click="closePicker">×</button>
        </div>
        <div v-if="pickerCategories.length" class="flex flex-wrap gap-1.5 mb-3">
          <button class="dp-cat" :class="{ 'dp-cat--on': !pickerCategory }" @click="pickerCategory = null">All</button>
          <button v-for="c in pickerCategories" :key="c.label" class="dp-cat" :class="{ 'dp-cat--on': pickerCategory === c.label }" @click="pickerCategory = c.label">{{ c.label }} ({{ c.count }})</button>
        </div>
        <div class="flex gap-2 mb-2">
          <input v-model="customName" class="dp-input flex-1" placeholder="Custom drill / lift name…" @keyup.enter="addCustomDrill" />
          <button class="dp-btn dp-btn--primary" @click="addCustomDrill">+</button>
        </div>
        <input v-model="pickerQuery" class="dp-input mb-2" placeholder="Search library…" />
        <div class="dp-modal-list">
          <button v-for="d in pickerDrills" :key="d.id" class="dp-drill-row" @click="addDrill(d)">
            <div class="min-w-0 text-left">
              <div class="font-bold truncate">{{ d.name }}</div>
              <div class="text-white/40 text-xs truncate">{{ drillCategory(d) || d.subcategory || '' }}</div>
            </div>
            <span class="dp-plus">+</span>
          </button>
          <div v-if="!pickerDrills.length" class="dp-empty dp-empty--sm">No drills here — clear the filter or add a custom one above.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dp-btn { background:#141d31; border:1px solid rgba(255,255,255,.12); color:#fff; font-weight:800; font-size:13px; padding:9px 16px; border-radius:10px; cursor:pointer; }
.dp-btn:hover { background:#1b2742; }
.dp-btn:disabled { opacity:.55; cursor:default; }
.dp-btn--primary { background:#d8232a; border-color:#d8232a; }
.dp-btn--primary:hover { background:#e5484d; }
.dp-btn--small { padding:7px 11px; font-size:11px; border-radius:8px; white-space:nowrap; }
.dp-panel { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px; padding:16px; }
.dp-command { background:linear-gradient(135deg, rgba(255,255,255,.055), rgba(255,255,255,.025)); border:1px solid rgba(255,255,255,.11); border-radius:18px; padding:16px; box-shadow:0 16px 50px rgba(0,0,0,.22); }
.dp-command-eyebrow { color:#ff4a5f; text-transform:uppercase; letter-spacing:.08em; font-size:11px; font-weight:900; margin-bottom:3px; }
.dp-command-grid { display:grid; grid-template-columns:repeat(1, minmax(0, 1fr)); gap:10px; }
.dp-command-grid--two { margin-top:12px; }
@media (min-width:768px){ .dp-command-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); } .dp-command-grid--two { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
.dp-command-card { background:rgba(9,14,29,.76); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:14px; min-width:0; }
.dp-command-label { color:rgba(255,255,255,.48); text-transform:uppercase; letter-spacing:.08em; font-size:10.5px; font-weight:900; }
.dp-command-value { font-size:20px; line-height:1.15; font-weight:950; margin-top:7px; overflow-wrap:anywhere; }
.dp-command-sub { color:rgba(255,255,255,.48); font-size:12px; margin-top:5px; }
.dp-command-mini { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
.dp-command-mini span { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); border-radius:999px; padding:3px 7px; font-size:10.5px; color:rgba(255,255,255,.58); font-weight:800; }
.dp-command-block { margin-top:14px; }
.dp-command-alert { border:1px solid rgba(248,113,113,.35); background:rgba(127,29,29,.28); color:#fecaca; border-radius:12px; padding:13px; font-size:13px; }
.dp-command-loading { border:1px dashed rgba(255,255,255,.14); color:rgba(255,255,255,.48); border-radius:12px; padding:18px; text-align:center; font-size:13px; }
.dp-command-message { background:rgba(52,211,153,.12); border:1px solid rgba(52,211,153,.28); color:#a7f3d0; border-radius:12px; padding:10px 12px; margin-top:12px; font-size:13px; font-weight:800; }
.dp-action-card { background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.09); border-radius:12px; padding:13px; }
.dp-priority { display:inline-flex; align-items:center; border-radius:999px; padding:3px 8px; font-size:10px; font-weight:950; text-transform:uppercase; letter-spacing:.06em; }
.dp-priority--critical { background:rgba(216,35,42,.22); color:#ff8d98; }
.dp-priority--high { background:rgba(245,158,11,.18); color:#fbbf24; }
.dp-priority--medium { background:rgba(59,130,246,.16); color:#93c5fd; }
.dp-priority--low { background:rgba(148,163,184,.14); color:#cbd5e1; }
.dp-player-status-list { display:grid; gap:7px; }
.dp-player-status-row { display:flex; align-items:center; justify-content:space-between; gap:12px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
.dp-player-status-metrics { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; color:rgba(255,255,255,.62); font-size:11px; font-weight:850; }
.dp-player-status-metrics span { background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.075); border-radius:999px; padding:3px 7px; }
.dp-gap-row { display:flex; align-items:center; justify-content:space-between; gap:10px; color:rgba(255,255,255,.62); font-size:12px; border-top:1px solid rgba(255,255,255,.06); padding-top:6px; }
.dp-section { font-size:12px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; color:#fff; margin-bottom:12px; }
.dp-field { display:flex; flex-direction:column; gap:5px; }
.dp-label { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.45); font-weight:700; }
.dp-input { background:#0b1322; border:1px solid rgba(255,255,255,.12); color:#fff; border-radius:9px; padding:9px 11px; font-size:14px; outline:none; }
.dp-input:focus { border-color:#3a6df0; }
.dp-input--num { width:74px; flex:none; text-align:center; }
.dp-input--sm { padding:7px 9px; font-size:13px; }
.dp-chip { display:inline-flex; align-items:center; gap:7px; background:#1b2742; border:1px solid rgba(255,255,255,.14); color:#fff; font-size:13px; font-weight:700; padding:6px 12px; border-radius:999px; cursor:pointer; }
.dp-chip:hover { background:#243357; }
.dp-chip--ghost { background:transparent; color:rgba(255,255,255,.6); }
.dp-dot { width:9px; height:9px; border-radius:50%; flex:none; }
.dp-bucket { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:16px; margin-bottom:12px; }
.dp-note-box { background:rgba(255,255,255,.04); border:1px dashed rgba(255,255,255,.14); border-radius:10px; padding:12px; color:rgba(255,255,255,.55); font-size:13px; }
.dp-item-card { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:12px; margin-bottom:10px; }
.dp-set { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:6px; }
.dp-set-n { width:22px; height:22px; border-radius:50%; background:rgba(255,255,255,.08); color:rgba(255,255,255,.7); font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex:none; }
.dp-set-sum { color:rgba(255,255,255,.4); font-size:12px; margin-left:auto; }
.dp-mini { display:flex; flex-direction:column; gap:3px; }
.dp-mini > span { font-size:10px; text-transform:uppercase; letter-spacing:.05em; color:rgba(255,255,255,.4); font-weight:700; }
.dp-x { width:28px; height:28px; border-radius:8px; border:1px solid rgba(255,255,255,.12); background:transparent; color:rgba(255,255,255,.6); font-size:17px; line-height:1; cursor:pointer; flex:none; }
.dp-x:hover { color:#f0787e; border-color:#f0787e; }
.dp-link { background:none; border:none; color:#7ca6f5; font-weight:700; font-size:13px; cursor:pointer; padding:2px 0; }
.dp-link:hover { text-decoration:underline; }
.dp-link--danger { color:#f0787e; }
.dp-add-drill { width:100%; margin-top:4px; background:rgba(33,96,196,.14); border:1px dashed rgba(124,166,245,.5); color:#9fc0ff; font-weight:800; font-size:13px; padding:11px; border-radius:10px; cursor:pointer; }
.dp-add-drill:hover { background:rgba(33,96,196,.22); }
.dp-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:14px; padding:16px; cursor:pointer; }
.dp-card:hover { border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.06); }
.dp-badge { font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; padding:3px 8px; border-radius:6px; white-space:nowrap; }
.dp-badge--pub { color:#43d089; background:rgba(52,211,153,.15); }
.dp-badge--draft { color:#9aa6c0; background:rgba(148,163,184,.15); }
.dp-empty { border:1px dashed rgba(255,255,255,.14); border-radius:14px; padding:34px 20px; text-align:center; color:rgba(255,255,255,.5); font-size:14px; }
.dp-empty--sm { padding:16px; font-size:13px; }
.dp-hint { color:#f5a524; font-size:13px; }
.dp-players { max-height:320px; overflow-y:auto; display:grid; gap:2px; }
.dp-player { display:flex; align-items:center; gap:10px; padding:8px 6px; border-radius:8px; font-size:14px; cursor:pointer; }
.dp-player:hover { background:rgba(255,255,255,.05); }
.dp-player input { width:16px; height:16px; accent-color:#d8232a; }
/* drill picker modal */
.dp-modal { position:fixed; inset:0; background:rgba(3,7,18,.72); display:flex; align-items:flex-end; justify-content:center; z-index:60; padding:0; }
.dp-modal-card { width:100%; max-width:620px; background:#0f1830; border:1px solid rgba(255,255,255,.12); border-radius:18px 18px 0 0; padding:18px; max-height:85vh; display:flex; flex-direction:column; }
@media (min-width:640px){ .dp-modal { align-items:center; padding:20px; } .dp-modal-card { border-radius:18px; } }
.dp-cat { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.7); font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; cursor:pointer; }
.dp-cat--on { background:#d8232a; border-color:#d8232a; color:#fff; }
.dp-modal-list { overflow-y:auto; margin-top:4px; }
.dp-drill-row { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:11px 6px; border-bottom:1px solid rgba(255,255,255,.07); cursor:pointer; }
.dp-drill-row:hover { background:rgba(255,255,255,.04); }
.dp-plus { color:#7ca6f5; font-size:20px; font-weight:800; flex:none; }
</style>
