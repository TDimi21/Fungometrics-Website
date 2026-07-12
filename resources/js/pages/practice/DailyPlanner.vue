<script setup>
import { ref, computed, nextTick, onMounted, watch } from 'vue'
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
const commandActionLoading = ref('')
const showReviewQueue = ref(false)
const selectedReviewTaskIds = ref([])
const correctionMessage = ref('')
const generatedPlanPreview = ref(null)
const reviewQueueRef = ref(null)
const completionSummary = ref(null)
const completionSummaryLoading = ref(false)
const completionSummaryError = ref('')
const weeklyRollup = ref(null)
const weeklyRollupLoading = ref(false)
const weeklyRollupError = ref('')
const nextWeekDraft = ref(null)
const nextWeekDraftLoading = ref(false)
const nextWeekDraftError = ref('')
const nextWeekDraftMessage = ref('')
const nextWeekPreviewDay = ref(null)
const savingNextWeekDay = ref('')
const savedNextWeekDailyPlan = ref(null)

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
    completionSummary.value = null
    weeklyRollup.value = null
    nextWeekDraft.value = null
    return
  }
  commandLoading.value = true
  commandError.value = ''
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/planner-command-center`, { days: 365 })
    commandCenter.value = res?.data?.data || null
    await loadCompletionSummary(commandCenter.value?.daily_plan_id)
  } catch {
    commandCenter.value = null
    completionSummary.value = null
    commandError.value = 'Could not load the planner command center.'
  } finally {
    commandLoading.value = false
  }
}
const loadNextWeekDraft = async () => {
  nextWeekDraftError.value = ''
  if (!activeTeamId.value) {
    nextWeekDraft.value = null
    return null
  }

  nextWeekDraftLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/next-week-plan-draft`, {
      days: 7,
      plan_days: 5,
      max_minutes_per_day: 90,
    })
    nextWeekDraft.value = res?.data?.data || null
    return nextWeekDraft.value
  } catch {
    nextWeekDraft.value = null
    nextWeekDraftError.value = 'Next week draft is not available yet.'
    return null
  } finally {
    nextWeekDraftLoading.value = false
  }
}
const loadWeeklyRollup = async () => {
  weeklyRollupError.value = ''
  if (!activeTeamId.value) {
    weeklyRollup.value = null
    return null
  }

  weeklyRollupLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-planner-rollup`, { days: 7, include_players: true })
    weeklyRollup.value = res?.data?.data || null
    return weeklyRollup.value
  } catch {
    weeklyRollup.value = null
    weeklyRollupError.value = 'Weekly planner rollup is not available yet.'
    return null
  } finally {
    weeklyRollupLoading.value = false
  }
}
const loadCompletionSummary = async (dailyPlanId = commandCenter.value?.daily_plan_id) => {
  completionSummaryError.value = ''
  if (!dailyPlanId) {
    completionSummary.value = null
    return null
  }

  completionSummaryLoading.value = true
  try {
    const res = await axiosGet(`coach/daily-plans/${dailyPlanId}/completion-summary`, { days: 365 })
    completionSummary.value = res?.data?.data || null
    return completionSummary.value
  } catch {
    completionSummary.value = null
    completionSummaryError.value = 'Completion summary is not available yet.'
    return null
  } finally {
    completionSummaryLoading.value = false
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
onMounted(() => { loadPlans(); loadGroups(); loadRoster(); loadCustomDrills(); loadCommandCenter(); loadWeeklyRollup(); loadNextWeekDraft() })
watch(activeTeamId, () => { loadRoster(); loadCommandCenter(); loadWeeklyRollup(); loadNextWeekDraft() })

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
const pendingReviewTasks = computed(() => Array.isArray(commandReview.value?.tasks_pending_review) ? commandReview.value.tasks_pending_review : [])
const commandHeader = computed(() => commandCenter.value?.operating_header || {})
const primaryAction = computed(() => commandCenter.value?.primary_next_action || commandActions.value[0] || null)
const commandStatusCards = computed(() => Array.isArray(commandCenter.value?.status_cards) ? commandCenter.value.status_cards : [])
const commandVisibility = computed(() => commandCenter.value?.section_visibility || {})
const completionRows = computed(() => Array.isArray(completionSummary.value?.player_summaries) ? completionSummary.value.player_summaries : [])
const completionActions = computed(() => Array.isArray(completionSummary.value?.coach_next_actions) ? completionSummary.value.coach_next_actions : [])
const weeklyPlanSummary = computed(() => weeklyRollup.value?.plan_execution_summary || {})
const weeklyPlayerSummary = computed(() => weeklyRollup.value?.player_completion_summary || {})
const weeklyBenchmarkSummary = computed(() => weeklyRollup.value?.benchmark_collection_summary || {})
const weeklyReviewSummary = computed(() => weeklyRollup.value?.review_summary || {})
const weeklyTrustedSummary = computed(() => weeklyRollup.value?.trusted_data_summary || {})
const weeklyRecommendations = computed(() => Array.isArray(weeklyRollup.value?.next_week_recommendations) ? weeklyRollup.value.next_week_recommendations : [])
const weeklyFollowUps = computed(() => Array.isArray(weeklyPlayerSummary.value?.players_needing_follow_up) ? weeklyPlayerSummary.value.players_needing_follow_up : [])
const weeklyMissingMetrics = computed(() => Array.isArray(weeklyBenchmarkSummary.value?.top_missing_metrics_remaining) ? weeklyBenchmarkSummary.value.top_missing_metrics_remaining : [])
const nextWeekPriorities = computed(() => Array.isArray(nextWeekDraft.value?.priority_focuses) ? nextWeekDraft.value.priority_focuses : [])
const nextWeekDays = computed(() => Array.isArray(nextWeekDraft.value?.suggested_plan_days) ? nextWeekDraft.value.suggested_plan_days : [])
const nextWeekAssignments = computed(() => Array.isArray(nextWeekDraft.value?.player_assignments) ? nextWeekDraft.value.player_assignments : [])
const nextWeekTargets = computed(() => Array.isArray(nextWeekDraft.value?.benchmark_collection_targets) ? nextWeekDraft.value.benchmark_collection_targets : [])
const nextWeekNotes = computed(() => Array.isArray(nextWeekDraft.value?.coach_notes) ? nextWeekDraft.value.coach_notes : [])
const weeklyCards = computed(() => [
  {
    label: 'Weekly Execution',
    value: `${oneDecimal(weeklyPlanSummary.value.average_completion_percentage)}%`,
    detail: `${weeklyPlanSummary.value.plans_published || 0} published · ${weeklyPlanSummary.value.total_completed_assignments || 0}/${weeklyPlanSummary.value.total_assigned_players || 0} completed`,
  },
  {
    label: 'Benchmark Collection',
    value: weeklyBenchmarkSummary.value.metric_values_submitted || 0,
    detail: `${weeklyBenchmarkSummary.value.metric_values_approved || 0} approved · ${weeklyReviewSummary.value.pending_review_count || 0} pending review`,
  },
  {
    label: 'Trusted Data',
    value: weeklyTrustedSummary.value.trusted_values_added || 0,
    detail: `${weeklyTrustedSummary.value.players_improved || 0} players updated`,
  },
  {
    label: 'Follow-Up',
    value: weeklyFollowUps.value.length || 0,
    detail: `${weeklyPlayerSummary.value.players_partially_completed || 0} partial · ${weeklyPlayerSummary.value.players_not_started || 0} not started`,
  },
])
const completionSummaryCards = computed(() => {
  const summary = completionSummary.value || {}
  return [
    { label: 'Team Complete', value: `${oneDecimal(summary.team_completion_percentage)}%`, detail: `${summary.completed_player_count || 0} completed` },
    { label: 'In Progress', value: summary.in_progress_player_count || 0, detail: `${summary.not_started_player_count || 0} not started` },
    { label: 'Review Needed', value: summary.pending_review_count || 0, detail: `${summary.benchmark_submissions_count || 0} submitted values` },
    { label: 'Coach Feedback', value: `${summary.approved_count || 0}/${summary.correction_requested_count || 0}`, detail: 'approved / correction' },
  ]
})
const priorityClass = (priority) => ({
  critical: 'dp-priority--critical',
  high: 'dp-priority--high',
  medium: 'dp-priority--medium',
  low: 'dp-priority--low',
}[priority] || 'dp-priority--medium')
const statusBadgeClass = (tone) => ({
  warning: 'dp-status--warning',
  good: 'dp-status--good',
  info: 'dp-status--info',
  complete: 'dp-status--complete',
  muted: 'dp-status--muted',
}[tone] || 'dp-status--neutral')
const statusCardClass = (tone) => ({
  warning: 'dp-status-card--warning',
  danger: 'dp-status-card--danger',
  good: 'dp-status-card--good',
  info: 'dp-status-card--info',
  complete: 'dp-status-card--complete',
}[tone] || 'dp-status-card--neutral')
const actionKey = (action) => action?.action_id || action?.action_type || ''
const openReviewQueue = async () => {
  showReviewQueue.value = true
  await nextTick()
  reviewQueueRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
}
const reviewTaskSelected = (taskId) => selectedReviewTaskIds.value.includes(String(taskId))
const toggleReviewTask = (taskId) => {
  const id = String(taskId || '')
  if (!id) return
  selectedReviewTaskIds.value = reviewTaskSelected(id)
    ? selectedReviewTaskIds.value.filter((value) => value !== id)
    : [...selectedReviewTaskIds.value, id]
}
const actionButtonDisabled = (action) => commandActionLoading.value !== '' || action?.enabled === false
const actionConfirmText = (action, payload = {}) => {
  const count = payload.task_ids?.length || action?.payload?.player_ids?.length || 0
  if (action?.action_type === 'send_reminder') return `Send reminder to ${count || commandSummary.value.not_acknowledged_count || 0} players who have not acknowledged?`
  if (action?.action_type === 'approve_values') return 'Approve selected benchmark submissions?'
  if (action?.action_type === 'request_corrections') return 'Request corrections for selected benchmark submissions?'
  if (action?.action_type === 'promote_trusted_data') return 'Promote approved values to trusted benchmark data?'
  if (action?.action_type === 'publish_plan') return 'Publish this Daily Plan?'
  return `Run "${action?.title || 'this action'}"?`
}
const runCommandAction = async (action) => {
  if (!action) return
  const actionType = action.action_type
  commandActionMessage.value = ''
  generatedPlanPreview.value = null

  if (action.enabled === false) {
    commandActionMessage.value = action.disabled_reason || 'This action is not available yet.'
    return
  }

  if (action.target_route && !action.api_endpoint) {
    if (actionType === 'open_daily_planner') newPlan()
    commandActionMessage.value = action.disabled_reason || 'Open the existing Daily Planner section to continue.'
    return
  }

  if (action.requires_confirmation && !confirm(actionConfirmText(action))) return

  try {
    commandActionLoading.value = actionKey(action) || actionType
    const payload = {
      ...(action.payload || {}),
      action_type: actionType,
      daily_plan_id: action.payload?.daily_plan_id || commandCenter.value?.daily_plan_id || null,
      days: 365,
    }
    const res = await axiosPost(action.api_endpoint || `coach/teams/${activeTeamId.value}/planner-command-center/action`, payload)
    await handleActionResult(res?.data, action)
  } catch {
    commandActionMessage.value = 'Could not complete that command center action.'
  } finally {
    commandActionLoading.value = ''
  }
}
const runSelectedReviewAction = async (actionType) => {
  commandActionMessage.value = ''
  generatedPlanPreview.value = null
  if (!selectedReviewTaskIds.value.length) {
    commandActionMessage.value = 'Select one or more review tasks first.'
    return
  }
  if (actionType === 'request_corrections' && !correctionMessage.value.trim()) {
    commandActionMessage.value = 'Add a correction message before sending.'
    return
  }
  const action = {
    action_id: actionType,
    action_type: actionType,
    title: actionType === 'approve_values' ? 'Approve Selected' : 'Request Correction',
    requires_confirmation: true,
  }
  if (!confirm(actionConfirmText(action, { task_ids: selectedReviewTaskIds.value }))) return
  try {
    commandActionLoading.value = actionType
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/planner-command-center/action`, {
      action_type: actionType,
      daily_plan_id: commandCenter.value?.daily_plan_id || null,
      task_ids: selectedReviewTaskIds.value,
      message: correctionMessage.value.trim() || null,
      days: 365,
    })
    await handleActionResult(res?.data, action)
    selectedReviewTaskIds.value = []
    correctionMessage.value = ''
  } catch {
    commandActionMessage.value = 'Could not complete review action. Try again.'
  } finally {
    commandActionLoading.value = ''
  }
}
const handleActionResult = async (result, action) => {
  if (result?.updated_command_center) {
    commandCenter.value = result.updated_command_center
    await loadCompletionSummary(commandCenter.value?.daily_plan_id)
  } else {
    await loadCommandCenter()
  }
  await loadWeeklyRollup()
  await loadNextWeekDraft()
  if (action?.action_type === 'review_submissions') await openReviewQueue()
  if (action?.action_type === 'generate_next_plan') {
    generatedPlanPreview.value = result?.result?.daily_plan_preview || null
  }
  commandActionMessage.value = result?.message || action?.success_message || 'Action completed.'
  if (Array.isArray(result?.warnings) && result.warnings.length) {
    commandActionMessage.value += ` ${result.warnings[0]}`
  }
  if (action?.action_type === 'publish_plan') await loadPlans()
}
const previewNextWeekDay = (day) => {
  nextWeekPreviewDay.value = day
}
const openSavedNextWeekPlan = () => {
  const plan = savedNextWeekDailyPlan.value?.daily_plan
  if (plan) editing.value = planFromApi(plan)
}
const saveNextWeekDayAsDraft = async (day) => {
  if (!day?.day_index || !activeTeamId.value) return
  nextWeekDraftMessage.value = ''
  savingNextWeekDay.value = String(day.day_index)
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/next-week-plan-draft/save-day`, {
      day_index: day.day_index,
      scheduled_for: day.scheduled_for,
      status: 'draft',
      assign_player_ids: [],
    })
    savedNextWeekDailyPlan.value = res?.data?.data || null
    nextWeekDraftMessage.value = `Saved ${day.title || 'suggested day'} as a Daily Planner draft.`
    await loadPlans()
    if (savedNextWeekDailyPlan.value?.daily_plan) {
      editing.value = planFromApi(savedNextWeekDailyPlan.value.daily_plan)
    }
  } catch {
    nextWeekDraftMessage.value = 'Could not save that suggested day as a draft.'
  } finally {
    savingNextWeekDay.value = ''
  }
}

// Buckets not yet on the plan (keep the app's ordering).
const availableBuckets = computed(() =>
  BUCKETS.filter((b) => !(editing.value?.buckets || []).some((x) => x.type === b.type)))
const bucketDef = (type) => BUCKET_BY_TYPE[type] || {}
const addBucket = (b) => {
  // Append in the order the coach selects them (matches the app's PlanBuilder).
  editing.value.buckets.push({ type: b.type, title: b.title, kind: b.kind, items: [], note: '' })
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
              <h2 class="text-xl font-black tracking-wide">Daily Planner Operations</h2>
              <p class="text-white/45 text-sm mt-1">Today’s plan, player progress, benchmark review, and the next best coach action.</p>
            </div>
            <button class="dp-btn" :disabled="commandLoading" @click="loadCommandCenter">{{ commandLoading ? 'Refreshing…' : 'Refresh' }}</button>
          </div>

          <div v-if="commandError" class="dp-command-alert">{{ commandError }}</div>
          <div v-else-if="commandLoading && !commandCenter" class="dp-command-loading">Loading planner command center…</div>
          <template v-else-if="commandCenter">
            <div class="dp-operating-header">
              <div class="min-w-0">
                <div class="dp-section mb-2">Today’s Plan</div>
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="dp-operating-title">{{ commandHeader.display_title || 'No active Daily Plan' }}</h3>
                  <span class="dp-status" :class="statusBadgeClass(commandHeader.status_tone)">{{ commandHeader.status_label || human(commandPlan.status) }}</span>
                </div>
                <p class="dp-operating-sub">
                  {{ commandHeader.scheduled_for || 'No scheduled date' }} · {{ commandHeader.published_state || 'Not saved' }}
                </p>
                <p v-if="commandHeader.empty_state" class="dp-empty-copy">{{ commandHeader.empty_state }}</p>
                <p v-else-if="commandVisibility.show_draft_notice" class="dp-empty-copy">This plan is saved as a draft. Players will not see it until you publish and assign it.</p>
                <p v-else-if="commandVisibility.show_revision_note" class="dp-empty-copy">
                  Revision {{ commandHeader.latest_revision_number }} — updated {{ prettyDateTime(commandHeader.latest_revision_at) }}. {{ commandHeader.revision_note }}
                </p>
                <div class="dp-command-mini">
                  <span>{{ commandHeader.assigned_count || 0 }} assigned</span>
                  <span>{{ commandHeader.acknowledged_count || 0 }} acknowledged</span>
                  <span>{{ commandHeader.completed_count || 0 }} completed</span>
                  <span>{{ commandHeader.pending_review_count || 0 }} review needed</span>
                  <span>{{ commandHeader.benchmark_generated ? 'FMTRX generated' : 'Manual plan' }}</span>
                </div>
              </div>
              <div class="dp-primary-action">
                <div class="dp-command-label">Next Best Action</div>
                <div class="dp-primary-action-title">{{ primaryAction?.title || 'Plan Looks Current' }}</div>
                <p>{{ primaryAction?.why || commandHeader.next_action_text || 'No urgent coach action is waiting.' }}</p>
                <div class="dp-primary-action-buttons">
                  <button
                    v-if="primaryAction?.button_label"
                    class="dp-btn dp-btn--primary"
                    :disabled="actionButtonDisabled(primaryAction)"
                    @click="runCommandAction(primaryAction)"
                  >
                    {{ commandActionLoading === actionKey(primaryAction) ? 'Working…' : primaryAction.button_label }}
                  </button>
                  <button v-if="commandVisibility.show_review_shortcut" class="dp-btn" @click="openReviewQueue">Review Submitted Values</button>
                  <button v-if="!commandVisibility.has_plan" class="dp-btn" @click="newPlan">Create Manually</button>
                </div>
              </div>
            </div>

            <div v-if="commandStatusCards.length" class="dp-status-grid">
              <div v-for="card in commandStatusCards" :key="card.key" class="dp-status-card" :class="statusCardClass(card.tone)">
                <div class="dp-command-label">{{ card.label }}</div>
                <div class="dp-command-value">{{ card.value }}</div>
                <div class="dp-command-sub">{{ card.detail }}</div>
              </div>
            </div>

            <div class="dp-command-block">
              <div class="dp-section mb-2">Workout Completion Summary</div>
              <div v-if="completionSummaryLoading && !completionSummary" class="dp-command-loading">Loading completion summary…</div>
              <div v-else-if="completionSummaryError" class="dp-empty dp-empty--sm">{{ completionSummaryError }}</div>
              <div v-else-if="!completionSummary" class="dp-empty dp-empty--sm">No workout progress yet.</div>
              <template v-else>
                <div class="dp-completion-grid">
                  <div v-for="card in completionSummaryCards" :key="card.label" class="dp-command-card">
                    <div class="dp-command-label">{{ card.label }}</div>
                    <div class="dp-command-value">{{ card.value }}</div>
                    <div class="dp-command-sub">{{ card.detail }}</div>
                  </div>
                </div>

                <div v-if="completionRows.length" class="dp-completion-list">
                  <div v-for="row in completionRows" :key="row.player_id" class="dp-completion-row">
                    <div class="min-w-0">
                      <div class="font-bold truncate">{{ row.player_name }}</div>
                      <div class="text-white/40 text-xs">{{ row.next_needed_action || 'No coach action needed.' }}</div>
                      <div v-if="row.last_activity_at" class="text-white/30 text-[11px] mt-0.5">Last activity {{ prettyDateTime(row.last_activity_at) }}</div>
                    </div>
                    <div class="dp-player-status-metrics">
                      <span>{{ oneDecimal(row.completion_percentage) }}%</span>
                      <span>{{ row.completed_items || 0 }}/{{ row.total_items || 0 }} items</span>
                      <span>{{ row.benchmark_values_submitted || 0 }} submitted</span>
                      <span>{{ row.pending_review_count || 0 }} review</span>
                      <span>{{ row.approved_count || 0 }} approved</span>
                      <span>{{ row.correction_requested_count || 0 }} correction</span>
                    </div>
                  </div>
                </div>
                <div v-else class="dp-empty dp-empty--sm mt-2">No assigned player progress has been recorded yet.</div>

                <div v-if="completionActions.length" class="dp-completion-actions">
                  <div class="dp-command-label">Coach Next Actions</div>
                  <ul>
                    <li v-for="action in completionActions.slice(0, 4)" :key="action">{{ action }}</li>
                  </ul>
                </div>
                <div v-else class="dp-empty dp-empty--sm mt-2">No coach review needed.</div>
              </template>
            </div>

            <div class="dp-command-block">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="dp-section mb-0">Weekly Planner Rollup</div>
                <button class="dp-link" :disabled="weeklyRollupLoading" @click="loadWeeklyRollup">{{ weeklyRollupLoading ? 'Refreshing…' : 'Refresh Week' }}</button>
              </div>
              <div v-if="weeklyRollupLoading && !weeklyRollup" class="dp-command-loading">Loading weekly rollup…</div>
              <div v-else-if="weeklyRollupError" class="dp-empty dp-empty--sm">{{ weeklyRollupError }}</div>
              <div v-else-if="!weeklyRollup" class="dp-empty dp-empty--sm">No weekly planner rollup is available yet.</div>
              <template v-else>
                <div class="dp-weekly-header">
                  <div>
                    <div class="dp-command-label">{{ weeklyRollup.week_label || 'Current Week' }}</div>
                    <p class="dp-empty-copy">{{ weeklyRollup.coach_summary || 'No daily plans were assigned this week.' }}</p>
                  </div>
                  <span class="dp-status" :class="statusBadgeClass(weeklyRollup.summary_status === 'complete' ? 'complete' : weeklyRollup.summary_status === 'partial' ? 'warning' : 'muted')">
                    {{ human(weeklyRollup.summary_status || 'empty') }}
                  </span>
                </div>
                <div class="dp-completion-grid mt-3">
                  <div v-for="card in weeklyCards" :key="card.label" class="dp-command-card">
                    <div class="dp-command-label">{{ card.label }}</div>
                    <div class="dp-command-value">{{ card.value }}</div>
                    <div class="dp-command-sub">{{ card.detail }}</div>
                  </div>
                </div>

                <div class="dp-weekly-columns">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Players Needing Follow-Up</div>
                    <div v-if="weeklyFollowUps.length" class="dp-weekly-list">
                      <div v-for="player in weeklyFollowUps.slice(0, 5)" :key="player.player_id || player.player_name" class="dp-gap-row">
                        <span>{{ player.player_name || 'Player' }}</span>
                        <span>{{ player.reason || 'Follow up' }}</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No player follow-up is needed from this week.</div>
                  </div>
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Remaining Benchmark Gaps</div>
                    <div v-if="weeklyMissingMetrics.length" class="dp-weekly-list">
                      <div v-for="metric in weeklyMissingMetrics.slice(0, 5)" :key="`${metric.metric_key}-${metric.category}`" class="dp-gap-row">
                        <span>{{ metric.display_name || human(metric.metric_key) }}</span>
                        <span>{{ metric.missing_count || 0 }} missing</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No benchmark gaps are surfaced for this week.</div>
                  </div>
                </div>

                <div class="dp-weekly-panel mt-3">
                  <div class="dp-command-label">Next Week Recommendations</div>
                  <div v-if="weeklyRecommendations.length" class="dp-weekly-recommendations">
                    <div v-for="recommendation in weeklyRecommendations.slice(0, 4)" :key="`${recommendation.title}-${recommendation.priority}`" class="dp-action-card">
                      <span class="dp-priority" :class="priorityClass(recommendation.priority)">{{ human(recommendation.priority) }}</span>
                      <div class="font-extrabold mt-2">{{ recommendation.title }}</div>
                      <p class="text-white/55 text-xs mt-2">{{ recommendation.why }}</p>
                      <p v-if="recommendation.recommended_plan_block" class="text-white/35 text-xs mt-1">{{ recommendation.recommended_plan_block }} · {{ recommendation.estimated_minutes || 0 }} min</p>
                    </div>
                  </div>
                  <div v-else class="dp-command-sub">No next-week recommendations are available yet.</div>
                </div>
              </template>
            </div>

            <div class="dp-command-block">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div class="dp-section mb-0">Next Week Plan Draft</div>
                <button class="dp-link" :disabled="nextWeekDraftLoading" @click="loadNextWeekDraft">{{ nextWeekDraftLoading ? 'Generating…' : 'Regenerate Draft' }}</button>
              </div>
              <div v-if="nextWeekDraftLoading && !nextWeekDraft" class="dp-command-loading">Generating next week draft from the weekly rollup…</div>
              <div v-else-if="nextWeekDraftError" class="dp-empty dp-empty--sm">{{ nextWeekDraftError }}</div>
              <div v-else-if="!nextWeekDraft" class="dp-empty dp-empty--sm">Next week draft is not available yet.</div>
              <template v-else>
                <div class="dp-weekly-header">
                  <div>
                    <div class="dp-command-label">Starts {{ nextWeekDraft.next_week_start_date || '—' }}</div>
                    <p class="dp-empty-copy">
                      <span v-if="nextWeekDraft.generation_status === 'empty'">Complete or assign plans this week to generate next week’s draft.</span>
                      <span v-else>FMTRX built this draft from weekly completion, benchmark collection, review status, trusted data, and current team intelligence.</span>
                    </p>
                  </div>
                  <span class="dp-status" :class="statusBadgeClass(nextWeekDraft.generation_status === 'complete' ? 'complete' : nextWeekDraft.generation_status === 'partial' ? 'warning' : 'muted')">
                    {{ human(nextWeekDraft.generation_status || 'empty') }}
                  </span>
                </div>

                <div v-if="nextWeekPriorities.length" class="dp-weekly-panel mt-3">
                  <div class="dp-command-label">Top Priorities</div>
                  <div class="dp-weekly-recommendations">
                    <div v-for="priority in nextWeekPriorities.slice(0, 4)" :key="`${priority.rank}-${priority.title}`" class="dp-action-card">
                      <span class="dp-priority" :class="priorityClass(priority.priority)">{{ human(priority.priority) }}</span>
                      <div class="font-extrabold mt-2">#{{ priority.rank }} {{ priority.title }}</div>
                      <p class="text-white/55 text-xs mt-2">{{ priority.why }}</p>
                      <p v-if="priority.metrics?.length" class="text-white/35 text-xs mt-1">Metrics: {{ priority.metrics.join(', ') }}</p>
                    </div>
                  </div>
                </div>

                <div v-if="nextWeekDays.length" class="dp-next-week-days">
                  <div v-for="day in nextWeekDays" :key="day.day_index" class="dp-next-week-day">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <div class="dp-command-label">Day {{ day.day_index }} · {{ day.day_label }} · {{ day.scheduled_for || 'Unscheduled' }}</div>
                        <div class="dp-command-value">{{ day.title }}</div>
                        <div class="dp-command-sub">{{ day.primary_focus }} · {{ day.estimated_total_minutes || 0 }} min</div>
                        <p class="dp-empty-copy">{{ day.why_this_day }}</p>
                      </div>
                      <div class="dp-next-week-actions">
                        <button class="dp-btn dp-btn--small" @click="previewNextWeekDay(day)">Preview Day</button>
                        <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="savingNextWeekDay === String(day.day_index)" @click="saveNextWeekDayAsDraft(day)">
                          {{ savingNextWeekDay === String(day.day_index) ? 'Saving…' : 'Save Day as Draft' }}
                        </button>
                      </div>
                    </div>
                    <div class="dp-next-week-blocks">
                      <div v-for="block in (day.blocks || [])" :key="`${day.day_index}-${block.title}-${block.category}`" class="dp-next-week-block">
                        <strong>{{ block.title }}</strong>
                        <span>{{ human(block.category) }} · {{ block.duration_minutes || 0 }} min</span>
                        <small>{{ block.why || block.description }}</small>
                        <small v-if="block.metrics_to_collect?.length">Collect: {{ block.metrics_to_collect.join(', ') }}</small>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="dp-empty dp-empty--sm mt-3">No suggested days yet. Complete or assign plans this week to generate next week’s draft.</div>

                <div v-if="nextWeekPreviewDay" class="dp-weekly-panel mt-3">
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <div class="dp-command-label">Preview Day</div>
                      <div class="font-extrabold">{{ nextWeekPreviewDay.title }}</div>
                      <p class="dp-command-sub">{{ nextWeekPreviewDay.why_this_day }}</p>
                    </div>
                    <button class="dp-link" @click="nextWeekPreviewDay = null">Close</button>
                  </div>
                  <ul class="dp-next-week-preview-list">
                    <li v-for="block in (nextWeekPreviewDay.blocks || [])" :key="`${nextWeekPreviewDay.day_index}-${block.title}`">
                      <strong>{{ block.title }}</strong>
                      <span>{{ block.description }}</span>
                    </li>
                  </ul>
                </div>

                <div class="dp-weekly-columns">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Benchmark Collection Targets</div>
                    <div v-if="nextWeekTargets.length" class="dp-weekly-list">
                      <div v-for="target in nextWeekTargets.slice(0, 5)" :key="`${target.title}-${target.priority}`" class="dp-gap-row">
                        <span>{{ target.title }}</span>
                        <span>{{ (target.metrics || []).length }} metrics</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No benchmark collection targets are currently carried forward.</div>
                  </div>
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Individual Assignments</div>
                    <div v-if="nextWeekAssignments.length" class="dp-weekly-list">
                      <div v-for="assignment in nextWeekAssignments.slice(0, 5)" :key="assignment.player_id || assignment.player_name" class="dp-gap-row">
                        <span>{{ assignment.player_name || 'Player' }}</span>
                        <span>{{ human(assignment.priority) }}</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No individual follow-up assignments are surfaced yet.</div>
                  </div>
                </div>

                <div v-if="nextWeekNotes.length" class="dp-completion-actions">
                  <div class="dp-command-label">Coach Notes</div>
                  <ul>
                    <li v-for="note in nextWeekNotes.slice(0, 4)" :key="note">{{ note }}</li>
                  </ul>
                </div>
                <p v-if="nextWeekDraftMessage" class="dp-command-message">
                  {{ nextWeekDraftMessage }}
                  <button v-if="savedNextWeekDailyPlan?.daily_plan" class="dp-link ml-2" @click="openSavedNextWeekPlan">Open Daily Planner to Edit</button>
                </p>
              </template>
            </div>

            <div v-if="commandActions.length" class="dp-command-block">
              <div class="dp-section mb-2">Coach Command Center</div>
              <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="action in commandActions" :key="action.action_id || `${action.action_type}-${action.title}`" class="dp-action-card" :class="{ 'dp-action-card--primary': actionKey(action) === actionKey(primaryAction) }">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <span class="dp-priority" :class="priorityClass(action.priority)">{{ human(action.priority) }}</span>
                      <div class="font-extrabold mt-2">{{ action.title }}</div>
                    </div>
                    <button
                      v-if="action.button_label"
                      class="dp-btn dp-btn--primary dp-btn--small"
                      :disabled="actionButtonDisabled(action)"
                      @click.stop="runCommandAction(action)"
                    >
                      {{ commandActionLoading === actionKey(action) ? 'Working…' : action.button_label }}
                    </button>
                  </div>
                  <p class="text-white/55 text-xs mt-2">{{ action.why }}</p>
                  <p class="text-white/35 text-xs mt-1">{{ action.action }}</p>
                  <p v-if="action.enabled === false && action.disabled_reason" class="text-red-200/70 text-xs mt-2">{{ action.disabled_reason }}</p>
                </div>
              </div>
            </div>

            <p v-if="commandActionMessage" class="dp-command-message">{{ commandActionMessage }}</p>

            <div v-if="generatedPlanPreview" class="dp-command-block">
              <div class="dp-section mb-2">Generated Plan Preview</div>
              <div class="dp-command-card">
                <div class="dp-command-label">Preview Only</div>
                <div class="dp-command-value">{{ generatedPlanPreview.name || 'Suggested Daily Plan' }}</div>
                <div class="dp-command-sub">{{ generatedPlanPreview.primary_goal || 'No primary goal' }} · {{ generatedPlanPreview.estimated_minutes || 0 }} min · {{ generatedPlanPreview.buckets?.length || 0 }} blocks</div>
                <p class="text-white/45 text-xs mt-3">Nothing was published. Open the Daily Planner to review, edit, assign, and publish this plan.</p>
              </div>
            </div>

            <div class="dp-command-block">
              <div class="dp-section mb-2">Player Plan Progress</div>
              <div v-if="!commandRows.length && commandVisibility.has_plan" class="dp-empty dp-empty--sm">No players are assigned to this plan yet.</div>
              <div v-else-if="!commandRows.length" class="dp-empty dp-empty--sm">No active Daily Plan is available for player progress yet.</div>
              <div v-else class="dp-player-status-list">
                <div v-for="row in commandRows" :key="row.player_id" class="dp-player-status-row">
                  <div class="min-w-0">
                    <div class="font-bold truncate">{{ row.player_name }}</div>
                    <div class="text-white/40 text-xs">{{ row.next_needed_action || 'No action needed' }}</div>
                  </div>
                  <div class="dp-player-status-metrics">
                    <span>{{ row.acknowledged ? 'Acknowledged' : 'Needs reminder' }}</span>
                    <span>{{ row.started ? 'Started' : 'Not started' }}</span>
                    <span>{{ row.completed_items || 0 }}/{{ row.total_items || 0 }} items</span>
                    <span>{{ oneDecimal(row.completion_percentage) }}%</span>
                    <span>{{ row.benchmark_values_submitted || 0 }} results</span>
                    <span>{{ row.pending_review_count || 0 }} review</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="dp-command-grid dp-command-grid--two" ref="reviewQueueRef">
              <div class="dp-command-card" :class="{ 'dp-command-card--attention': commandVisibility.show_review_shortcut }">
                <div class="dp-command-label">Review Queue</div>
                <div class="dp-command-value">{{ commandReview.pending_review_count || 0 }} pending</div>
                <div class="dp-command-sub" v-if="commandReview.pending_review_count">Oldest: {{ prettyDateTime(commandReview.oldest_pending_at) }}</div>
                <div class="dp-command-sub" v-else>No benchmark submissions are waiting for review.</div>
                <p v-if="commandVisibility.show_review_shortcut" class="dp-empty-copy">{{ commandReview.pending_review_count }} benchmark submission{{ Number(commandReview.pending_review_count) === 1 ? '' : 's' }} need review.</p>
                <button v-if="pendingReviewTasks.length && !showReviewQueue" class="dp-link mt-2" @click="showReviewQueue = true">Open review queue</button>
                <div v-if="pendingReviewTasks.length && showReviewQueue" class="dp-review-box">
                  <label v-for="task in pendingReviewTasks.slice(0, 8)" :key="task.task_id" class="dp-review-row">
                    <input type="checkbox" :checked="reviewTaskSelected(task.task_id)" @change="toggleReviewTask(task.task_id)" />
                    <span class="min-w-0">
                      <strong>{{ task.player_name }}</strong>
                      <small>{{ task.title }} · {{ prettyDateTime(task.submitted_at) }}</small>
                    </span>
                  </label>
                  <div class="dp-review-actions">
                    <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="commandActionLoading === 'approve_values'" @click="runSelectedReviewAction('approve_values')">
                      {{ commandActionLoading === 'approve_values' ? 'Approving…' : 'Approve Selected' }}
                    </button>
                    <button class="dp-btn dp-btn--small" :disabled="commandActionLoading === 'request_corrections'" @click="runSelectedReviewAction('request_corrections')">
                      {{ commandActionLoading === 'request_corrections' ? 'Sending…' : 'Request Correction' }}
                    </button>
                  </div>
                  <textarea v-model="correctionMessage" class="dp-input w-full mt-2" rows="2" placeholder="Correction message for selected submissions…"></textarea>
                </div>
              </div>
              <div class="dp-command-card">
                <div class="dp-command-label">Benchmark Intelligence Connection</div>
                <div class="dp-command-value">{{ commandGaps.length }} tracked</div>
                <div v-if="!commandVisibility.has_benchmark_blocks" class="dp-command-sub">This plan does not include FMTRX benchmark collection blocks.</div>
                <div v-else-if="!commandGaps.length" class="dp-command-sub">No missing benchmark baselines surfaced for this plan.</div>
                <div v-else class="mt-3 space-y-1">
                  <div v-for="gap in commandGaps.slice(0, 5)" :key="`${gap.display_name}-${gap.category}`" class="dp-gap-row">
                    <span>{{ gap.display_name }}</span>
                    <span>{{ gap.missing_count }}/{{ gap.eligible_count }} · {{ human(gap.priority) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
          <div v-else class="dp-command-loading">Planner command center is not available yet.</div>
        </section>
        <div v-if="loading" class="dp-empty">Loading…</div>
        <div v-else-if="plans.length === 0" class="dp-empty">No saved workout plans yet. Generate one from FMTRX Intelligence or create one manually.</div>
        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <div class="sm:col-span-2 xl:col-span-3">
            <div class="dp-section mb-2">Saved Plans</div>
          </div>
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
.dp-operating-header { display:grid; grid-template-columns:1fr; gap:14px; background:rgba(9,14,29,.72); border:1px solid rgba(255,255,255,.11); border-radius:16px; padding:16px; }
@media (min-width:900px){ .dp-operating-header { grid-template-columns:minmax(0,1.4fr) minmax(300px,.8fr); align-items:stretch; } }
.dp-operating-title { font-size:24px; line-height:1.1; font-weight:950; overflow-wrap:anywhere; }
.dp-operating-sub { color:rgba(255,255,255,.52); font-size:13px; margin-top:6px; }
.dp-empty-copy { color:rgba(255,255,255,.58); font-size:12.5px; line-height:1.45; margin-top:10px; }
.dp-primary-action { background:rgba(216,35,42,.1); border:1px solid rgba(216,35,42,.28); border-radius:14px; padding:14px; min-width:0; }
.dp-primary-action-title { font-size:18px; line-height:1.15; font-weight:950; margin-top:7px; overflow-wrap:anywhere; }
.dp-primary-action p { color:rgba(255,255,255,.64); font-size:12.5px; line-height:1.45; margin-top:7px; }
.dp-primary-action-buttons { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
.dp-status { display:inline-flex; align-items:center; border-radius:999px; border:1px solid rgba(255,255,255,.14); padding:5px 9px; font-size:10.5px; font-weight:950; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; }
.dp-status--warning { background:rgba(245,158,11,.16); border-color:rgba(245,158,11,.32); color:#fbbf24; }
.dp-status--good { background:rgba(52,211,153,.14); border-color:rgba(52,211,153,.28); color:#86efac; }
.dp-status--info { background:rgba(59,130,246,.15); border-color:rgba(59,130,246,.32); color:#93c5fd; }
.dp-status--complete { background:rgba(20,184,166,.16); border-color:rgba(20,184,166,.32); color:#5eead4; }
.dp-status--muted { background:rgba(148,163,184,.12); border-color:rgba(148,163,184,.2); color:#cbd5e1; }
.dp-status--neutral { background:rgba(148,163,184,.12); color:#cbd5e1; }
.dp-status-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px; margin-top:12px; }
@media (min-width:900px){ .dp-status-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }
.dp-status-card { background:rgba(9,14,29,.64); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:12px; min-width:0; }
.dp-status-card--warning { border-color:rgba(245,158,11,.28); background:rgba(245,158,11,.08); }
.dp-status-card--danger { border-color:rgba(216,35,42,.3); background:rgba(216,35,42,.09); }
.dp-status-card--good { border-color:rgba(52,211,153,.24); background:rgba(52,211,153,.08); }
.dp-status-card--info { border-color:rgba(59,130,246,.24); background:rgba(59,130,246,.08); }
.dp-status-card--complete { border-color:rgba(20,184,166,.24); background:rgba(20,184,166,.08); }
.dp-completion-grid { display:grid; grid-template-columns:repeat(1, minmax(0,1fr)); gap:10px; }
@media (min-width:700px){ .dp-completion-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }
.dp-completion-list { display:grid; gap:7px; margin-top:10px; }
.dp-completion-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
@media (min-width:800px){ .dp-completion-row { flex-direction:row; align-items:center; } }
.dp-completion-actions { margin-top:10px; border:1px solid rgba(59,130,246,.18); background:rgba(59,130,246,.07); border-radius:12px; padding:11px 12px; }
.dp-completion-actions ul { margin:7px 0 0; padding-left:17px; color:rgba(255,255,255,.68); font-size:12.5px; line-height:1.45; }
.dp-completion-actions li { margin-top:3px; }
.dp-weekly-header { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(9,14,29,.64); border:1px solid rgba(255,255,255,.09); border-radius:14px; padding:13px 14px; }
@media (min-width:720px){ .dp-weekly-header { flex-direction:row; align-items:center; } }
.dp-weekly-columns { display:grid; grid-template-columns:1fr; gap:10px; margin-top:10px; }
@media (min-width:820px){ .dp-weekly-columns { grid-template-columns:repeat(2, minmax(0,1fr)); } }
.dp-weekly-panel { background:rgba(9,14,29,.5); border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:12px; min-width:0; }
.dp-weekly-list { display:grid; gap:6px; margin-top:8px; }
.dp-weekly-recommendations { display:grid; gap:8px; margin-top:9px; }
@media (min-width:900px){ .dp-weekly-recommendations { grid-template-columns:repeat(2, minmax(0,1fr)); } }
.dp-next-week-days { display:grid; gap:12px; margin-top:12px; }
.dp-next-week-day { background:rgba(9,14,29,.62); border:1px solid rgba(255,255,255,.1); border-radius:15px; padding:14px; min-width:0; }
.dp-next-week-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-start; }
@media (min-width:760px){ .dp-next-week-actions { justify-content:flex-end; } }
.dp-next-week-blocks { display:grid; grid-template-columns:1fr; gap:8px; margin-top:12px; }
@media (min-width:860px){ .dp-next-week-blocks { grid-template-columns:repeat(2, minmax(0,1fr)); } }
.dp-next-week-block { background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px; display:grid; gap:4px; min-width:0; }
.dp-next-week-block strong { font-size:13px; overflow-wrap:anywhere; }
.dp-next-week-block span { color:rgba(255,255,255,.55); font-size:11.5px; font-weight:800; }
.dp-next-week-block small { color:rgba(255,255,255,.43); font-size:11.5px; line-height:1.35; overflow-wrap:anywhere; }
.dp-next-week-preview-list { margin:10px 0 0; padding:0; list-style:none; display:grid; gap:8px; }
.dp-next-week-preview-list li { background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:9px 10px; display:grid; gap:3px; }
.dp-next-week-preview-list span { color:rgba(255,255,255,.55); font-size:12px; line-height:1.4; }
.dp-command-grid { display:grid; grid-template-columns:repeat(1, minmax(0, 1fr)); gap:10px; }
.dp-command-grid--two { margin-top:12px; }
@media (min-width:768px){ .dp-command-grid { grid-template-columns:repeat(3, minmax(0, 1fr)); } .dp-command-grid--two { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
.dp-command-card { background:rgba(9,14,29,.76); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:14px; min-width:0; }
.dp-command-card--attention { border-color:rgba(216,35,42,.32); box-shadow:0 0 0 1px rgba(216,35,42,.14) inset; }
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
.dp-action-card--primary { border-color:rgba(216,35,42,.34); background:rgba(216,35,42,.075); }
.dp-priority { display:inline-flex; align-items:center; border-radius:999px; padding:3px 8px; font-size:10px; font-weight:950; text-transform:uppercase; letter-spacing:.06em; }
.dp-priority--critical { background:rgba(216,35,42,.22); color:#ff8d98; }
.dp-priority--high { background:rgba(245,158,11,.18); color:#fbbf24; }
.dp-priority--medium { background:rgba(59,130,246,.16); color:#93c5fd; }
.dp-priority--low { background:rgba(148,163,184,.14); color:#cbd5e1; }
.dp-player-status-list { display:grid; gap:7px; }
.dp-player-status-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
@media (min-width:700px){ .dp-player-status-row { flex-direction:row; align-items:center; } }
.dp-player-status-metrics { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:6px; color:rgba(255,255,255,.62); font-size:11px; font-weight:850; }
.dp-player-status-metrics span { background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.075); border-radius:999px; padding:3px 7px; }
.dp-gap-row { display:flex; align-items:center; justify-content:space-between; gap:10px; color:rgba(255,255,255,.62); font-size:12px; border-top:1px solid rgba(255,255,255,.06); padding-top:6px; }
.dp-review-box { display:grid; gap:8px; margin-top:12px; }
.dp-review-row { display:flex; align-items:flex-start; gap:8px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:8px; font-size:12px; cursor:pointer; }
.dp-review-row:hover { background:rgba(255,255,255,.065); }
.dp-review-row input { width:14px; height:14px; accent-color:#d8232a; margin-top:2px; flex:none; }
.dp-review-row small { display:block; color:rgba(255,255,255,.45); margin-top:2px; overflow-wrap:anywhere; }
.dp-review-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:4px; }
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
