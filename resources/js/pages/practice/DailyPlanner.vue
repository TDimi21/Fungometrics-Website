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
import CoachWorkoutPlayers from '@/components/planner/CoachWorkoutPlayers.vue'

const { axiosGet, axiosPost, axiosPut, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)

const plans = ref([])
const groups = ref([])
const teamPlayers = ref([])
const editing = ref(null)
const viewingPlayers = ref(null)   // a published plan being reviewed in the View Players flow
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
const weeklyTeamReport = ref(null)
const weeklyTeamReportLoading = ref(false)
const weeklyTeamReportError = ref('')
const weeklyReportExportAudience = ref('coach')
const weeklyReportExportFormat = ref('text')
const weeklyReportTemplateKey = ref('detailed_coach_report')
const weeklyReportTemplates = ref([])
const weeklyReportTemplatesLoading = ref(false)
const weeklyReportTemplatesError = ref('')
const weeklyReportExportLoading = ref('')
const weeklyReportExportMessage = ref('')
const weeklyReportPreviewHtml = ref('')
const weeklyReportPreviewOpen = ref(false)
const weeklyReportDeliveryChannel = ref('copy')
const weeklyReportDeliveryFormat = ref('text')
const weeklyReportDeliveryPreview = ref(null)
const weeklyReportDeliveryLoading = ref('')
const weeklyReportDeliveryMessage = ref('')
const weeklyReportDeliveryReview = ref(null)
const weeklyReportDraftSubject = ref('')
const weeklyReportDraftMessage = ref('')
const weeklyReportConfirmSend = ref(false)
const weeklyReportSendResult = ref(null)
const weeklyReportDeliveryHistory = ref(null)
const weeklyReportDeliveryHistoryLoading = ref(false)
const weeklyReportDeliveryHistoryMessage = ref('')
const weeklyReportDeliveryAnalytics = ref(null)
const weeklyReportDeliveryAnalyticsLoading = ref(false)
const weeklyReportDeliveryAnalyticsMessage = ref('')
const communicationRhythm = ref(null)
const communicationRhythmLoading = ref(false)
const communicationRhythmMessage = ref('')
const seasonDevelopmentArchive = ref(null)
const seasonDevelopmentArchiveLoading = ref(false)
const seasonDevelopmentArchiveMessage = ref('')
const seasonArchiveExportAudience = ref('staff')
const seasonArchiveExportFormat = ref('text')
const seasonArchiveIncludeTimeline = ref(true)
const seasonArchiveIncludeBenchmark = ref(true)
const seasonArchiveIncludePlanner = ref(true)
const seasonArchiveIncludeCommunication = ref(true)
const seasonArchiveIncludePlayerRows = ref(true)
const seasonArchiveIncludeNextSteps = ref(true)
const seasonArchiveExportLoading = ref('')
const seasonArchiveExportMessage = ref('')
const seasonArchiveExportPayload = ref(null)
const seasonArchivePreviewHtml = ref('')
const seasonArchivePreviewOpen = ref(false)
const selectedWeeklyReportDelivery = ref(null)
const weeklyReportNotes = ref([])
const weeklyReportNotesLoading = ref(false)
const weeklyReportNotesMessage = ref('')
const weeklyReportNoteEditingId = ref('')
const weeklyReportNoteForm = ref({
  note_type: 'coach_comment',
  audience: 'coach',
  visibility: 'staff',
  title: '',
  body: '',
  player_id: '',
})
const nextWeekDraft = ref(null)
const nextWeekDraftLoading = ref(false)
const nextWeekDraftError = ref('')
const nextWeekDraftMessage = ref('')
const nextWeekPreviewDay = ref(null)
const savingNextWeekDay = ref('')
const savedNextWeekDailyPlan = ref(null)
const nextWeekCalendarDraft = ref(null)
const nextWeekCalendarLoading = ref(false)
const nextWeekCalendarError = ref('')
const nextWeekCalendarMessage = ref('')
const nextWeekCalendarStart = ref('')
const selectedCalendarDayIndexes = ref([])
const previewCalendarDay = ref(null)
const savingCalendarDays = ref(false)
const savedCalendarPlans = ref([])
const weeklyDraftPlans = ref(null)
const weeklyDraftPlansLoading = ref(false)
const weeklyDraftPlansError = ref('')
const weeklyPublishMessage = ref('')
const selectedWeeklyDraftPlanIds = ref([])
const weeklyPublishLoading = ref('')

// Drill picker
const picker = ref(null)          // the bucket object being added to
const pickerCategory = ref(null)
const pickerQuery = ref('')
const customName = ref('')
const customDrills = ref([])      // coach's saved custom drills/lifts (merge into library)

const weeklyReportNoteTypes = [
  { value: 'staff_note', label: 'Staff Note', hint: 'Coach and staff exports only.' },
  { value: 'coach_comment', label: 'Coach Comment', hint: 'Coach and staff exports only.' },
  { value: 'parent_summary', label: 'Parent Summary', hint: 'Parent-safe export note.' },
  { value: 'player_message', label: 'Player Message', hint: 'Player-safe export note.' },
  { value: 'next_week_emphasis', label: 'Next Week Emphasis', hint: 'Safe when visibility matches the audience.' },
  { value: 'player_follow_up', label: 'Player Follow-Up', hint: 'Coach and staff exports only.' },
  { value: 'internal_context', label: 'Internal Context', hint: 'Coach and staff exports only.' },
]
const weeklyReportVisibilityOptions = [
  { value: 'staff', label: 'Staff' },
  { value: 'coach', label: 'Coach' },
  { value: 'parents', label: 'Parents' },
  { value: 'players', label: 'Players' },
  { value: 'private', label: 'Private' },
]
const weeklyReportAudienceOptions = [
  { value: 'coach', label: 'Coach' },
  { value: 'staff', label: 'Staff' },
  { value: 'players', label: 'Players' },
  { value: 'parents', label: 'Parents' },
]
const seasonArchiveAudienceOptions = [
  { value: 'coach', label: 'Coach' },
  { value: 'staff', label: 'Staff' },
  { value: 'director', label: 'Director' },
  { value: 'players', label: 'Players' },
  { value: 'parents', label: 'Parents' },
]
const seasonArchiveFormatOptions = [
  { value: 'summary', label: 'Summary' },
  { value: 'text', label: 'Copy Text' },
  { value: 'html', label: 'Printable HTML' },
  { value: 'pdf', label: 'PDF' },
]
const weeklyReportDeliveryChannelOptions = [
  { value: 'copy', label: 'Copy', supported: true },
  { value: 'email', label: 'Email Draft', supported: false },
  { value: 'message', label: 'Message Draft', supported: false },
  { value: 'announcement', label: 'Announcement Draft', supported: false },
]
const weeklyReportDeliveryFormatOptions = [
  { value: 'text', label: 'Text' },
  { value: 'html', label: 'HTML' },
]
const resetWeeklyReportDeliveryReview = () => {
  weeklyReportDeliveryReview.value = null
  weeklyReportDraftSubject.value = ''
  weeklyReportDraftMessage.value = ''
  weeklyReportConfirmSend.value = false
  weeklyReportSendResult.value = null
}
const weeklyReportDeliveryBasePayload = () => ({
  days: 7,
  audience: weeklyReportExportAudience.value,
  template: weeklyReportTemplateKey.value,
  channel: weeklyReportDeliveryChannel.value,
  format: weeklyReportDeliveryFormat.value,
})

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
    weeklyTeamReport.value = null
    weeklyReportPreviewHtml.value = ''
    weeklyReportPreviewOpen.value = false
    weeklyReportDeliveryPreview.value = null
    weeklyReportDeliveryMessage.value = ''
    resetWeeklyReportDeliveryReview()
    weeklyReportDeliveryHistory.value = null
    weeklyReportDeliveryAnalytics.value = null
    communicationRhythm.value = null
    seasonDevelopmentArchive.value = null
    seasonArchiveExportPayload.value = null
    seasonArchiveExportMessage.value = ''
    seasonArchivePreviewHtml.value = ''
    seasonArchivePreviewOpen.value = false
    selectedWeeklyReportDelivery.value = null
    weeklyReportNotes.value = []
    resetWeeklyReportNoteForm()
    nextWeekDraft.value = null
    nextWeekCalendarDraft.value = null
    weeklyDraftPlans.value = null
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
const loadNextWeekCalendarDraft = async () => {
  nextWeekCalendarError.value = ''
  if (!activeTeamId.value) {
    nextWeekCalendarDraft.value = null
    return null
  }

  nextWeekCalendarLoading.value = true
  try {
    const params = {
      days: 7,
      plan_days: 5,
      max_minutes_per_day: 90,
    }
    if (nextWeekCalendarStart.value) params.next_week_start_date = nextWeekCalendarStart.value
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/next-week-calendar-draft`, params)
    nextWeekCalendarDraft.value = res?.data?.data || null
    nextWeekCalendarStart.value = nextWeekCalendarDraft.value?.week_start_date || nextWeekCalendarStart.value
    selectedCalendarDayIndexes.value = []
    previewCalendarDay.value = null
    return nextWeekCalendarDraft.value
  } catch {
    nextWeekCalendarDraft.value = null
    nextWeekCalendarError.value = 'Could not generate weekly calendar draft.'
    return null
  } finally {
    nextWeekCalendarLoading.value = false
  }
}
const loadWeeklyDraftPlans = async () => {
  weeklyDraftPlansError.value = ''
  if (!activeTeamId.value) {
    weeklyDraftPlans.value = null
    return null
  }

  weeklyDraftPlansLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-draft-plans`)
    weeklyDraftPlans.value = res?.data?.data || null
    return weeklyDraftPlans.value
  } catch {
    weeklyDraftPlans.value = null
    weeklyDraftPlansError.value = 'Could not load saved weekly draft plans.'
    return null
  } finally {
    weeklyDraftPlansLoading.value = false
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
const loadWeeklyTeamReport = async () => {
  weeklyTeamReportError.value = ''
  if (!activeTeamId.value) {
    weeklyTeamReport.value = null
    return null
  }

  weeklyTeamReportLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-team-report`, {
      days: 7,
      include_player_rows: true,
      include_benchmark_details: true,
      include_next_week_priorities: true,
    })
    weeklyTeamReport.value = res?.data?.data || null
    return weeklyTeamReport.value
  } catch {
    weeklyTeamReport.value = null
    weeklyTeamReportError.value = 'Weekly team report is not available yet.'
    return null
  } finally {
    weeklyTeamReportLoading.value = false
  }
}
const loadWeeklyReportNotes = async () => {
  weeklyReportNotesMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportNotes.value = []
    return []
  }

  weeklyReportNotesLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-report-notes`, { days: 7 })
    const rows = res?.data?.data?.notes
    weeklyReportNotes.value = Array.isArray(rows) ? rows : []
    return weeklyReportNotes.value
  } catch {
    weeklyReportNotes.value = []
    weeklyReportNotesMessage.value = 'Report notes are not available right now.'
    return []
  } finally {
    weeklyReportNotesLoading.value = false
  }
}
const loadWeeklyReportTemplates = async () => {
  weeklyReportTemplatesError.value = ''
  weeklyReportTemplatesLoading.value = true
  try {
    const res = await axiosGet('coach/weekly-report-templates')
    const rows = res?.data?.data?.templates
    weeklyReportTemplates.value = Array.isArray(rows) ? rows : []
    if (!weeklyReportTemplates.value.some((template) => template.template_key === weeklyReportTemplateKey.value) && weeklyReportTemplates.value[0]?.template_key) {
      weeklyReportTemplateKey.value = weeklyReportTemplates.value[0].template_key
    }
    return weeklyReportTemplates.value
  } catch {
    weeklyReportTemplates.value = []
    weeklyReportTemplatesError.value = 'No report templates available.'
    return []
  } finally {
    weeklyReportTemplatesLoading.value = false
  }
}
const loadWeeklyReportExport = async (format = weeklyReportExportFormat.value) => {
  weeklyReportExportMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportExportMessage.value = 'No team selected.'
    return null
  }

  weeklyReportExportLoading.value = format
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-report/export`, {
      days: 7,
      format,
      audience: weeklyReportExportAudience.value,
      template: weeklyReportTemplateKey.value,
      include_player_rows: true,
      include_benchmark_details: true,
      include_pending_reviews: true,
      include_next_week_priorities: true,
    })
    return res?.data?.data || null
  } catch {
    weeklyReportExportMessage.value = 'Export is not available right now.'
    return null
  } finally {
    weeklyReportExportLoading.value = ''
  }
}
const loadWeeklyReportDeliveryPreview = async () => {
  weeklyReportDeliveryMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportDeliveryMessage.value = 'No team selected.'
    return null
  }
  if (!weeklyTeamReport.value) {
    weeklyReportDeliveryMessage.value = 'Generate a weekly report before preparing delivery.'
    return null
  }

  weeklyReportDeliveryLoading.value = 'preview'
  resetWeeklyReportDeliveryReview()
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-report/delivery-preview`, weeklyReportDeliveryBasePayload())
    weeklyReportDeliveryPreview.value = res?.data?.data || null
    const warnings = [
      ...(weeklyReportDeliveryPreview.value?.privacy_warnings || []),
      ...(weeklyReportDeliveryPreview.value?.delivery_warnings || []),
    ]
    weeklyReportDeliveryMessage.value = warnings[0] || 'Delivery preview is ready.'
    return weeklyReportDeliveryPreview.value
  } catch {
    weeklyReportDeliveryPreview.value = null
    weeklyReportDeliveryMessage.value = 'Delivery prep is not available right now.'
    return null
  } finally {
    weeklyReportDeliveryLoading.value = ''
  }
}
const loadWeeklyReportDeliveryHistory = async () => {
  weeklyReportDeliveryHistoryMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportDeliveryHistory.value = null
    return null
  }

  weeklyReportDeliveryHistoryLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-report/deliveries`, { days: 30 })
    weeklyReportDeliveryHistory.value = res?.data?.data || null
    return weeklyReportDeliveryHistory.value
  } catch {
    weeklyReportDeliveryHistory.value = null
    weeklyReportDeliveryHistoryMessage.value = 'Delivery history is not available yet.'
    return null
  } finally {
    weeklyReportDeliveryHistoryLoading.value = false
  }
}
const loadWeeklyReportDeliveryAnalytics = async () => {
  weeklyReportDeliveryAnalyticsMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportDeliveryAnalytics.value = null
    return null
  }

  weeklyReportDeliveryAnalyticsLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/weekly-report/delivery-analytics`, { days: 30 })
    weeklyReportDeliveryAnalytics.value = res?.data?.data || null
    return weeklyReportDeliveryAnalytics.value
  } catch {
    weeklyReportDeliveryAnalytics.value = null
    weeklyReportDeliveryAnalyticsMessage.value = 'Delivery analytics are not available yet.'
    return null
  } finally {
    weeklyReportDeliveryAnalyticsLoading.value = false
  }
}
const loadCommunicationRhythm = async () => {
  communicationRhythmMessage.value = ''
  if (!activeTeamId.value) {
    communicationRhythm.value = null
    return null
  }

  communicationRhythmLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/communication-rhythm`, { weeks: 8 })
    communicationRhythm.value = res?.data?.data || null
    return communicationRhythm.value
  } catch {
    communicationRhythm.value = null
    communicationRhythmMessage.value = 'Communication rhythm is not available yet.'
    return null
  } finally {
    communicationRhythmLoading.value = false
  }
}
const loadSeasonDevelopmentArchive = async () => {
  seasonDevelopmentArchiveMessage.value = ''
  if (!activeTeamId.value) {
    seasonDevelopmentArchive.value = null
    return null
  }

  seasonDevelopmentArchiveLoading.value = true
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/season-development-archive`, {
      weeks: 12,
      include_player_rows: true,
      include_benchmark_progress: true,
      include_report_delivery: true,
      include_communication_rhythm: true,
      include_weekly_reports: true,
    })
    seasonDevelopmentArchive.value = res?.data?.data || null
    return seasonDevelopmentArchive.value
  } catch {
    seasonDevelopmentArchive.value = null
    seasonDevelopmentArchiveMessage.value = 'Season development archive is not available yet.'
    return null
  } finally {
    seasonDevelopmentArchiveLoading.value = false
  }
}
const seasonArchiveExportParams = (format = seasonArchiveExportFormat.value) => {
  const publicAudience = ['parents', 'players'].includes(seasonArchiveExportAudience.value)
  return {
    weeks: 12,
    format,
    audience: seasonArchiveExportAudience.value,
    include_weekly_timeline: seasonArchiveIncludeTimeline.value,
    include_benchmark_progress: seasonArchiveIncludeBenchmark.value,
    include_planner_progress: seasonArchiveIncludePlanner.value,
    include_communication_summary: seasonArchiveIncludeCommunication.value,
    include_player_rows: publicAudience ? false : seasonArchiveIncludePlayerRows.value,
    include_next_steps: seasonArchiveIncludeNextSteps.value,
    include_private_notes: false,
    include_internal_qa: false,
  }
}
const loadSeasonArchiveExport = async (format = seasonArchiveExportFormat.value) => {
  seasonArchiveExportMessage.value = ''
  if (!activeTeamId.value) {
    seasonArchiveExportMessage.value = 'No team selected.'
    return null
  }

  seasonArchiveExportLoading.value = format
  try {
    const res = await axiosGet(`coach/teams/${activeTeamId.value}/season-archive/export`, seasonArchiveExportParams(format))
    seasonArchiveExportPayload.value = res?.data?.data || null
    return seasonArchiveExportPayload.value
  } catch {
    seasonArchiveExportPayload.value = null
    seasonArchiveExportMessage.value = 'Season review packet export is not available right now.'
    return null
  } finally {
    seasonArchiveExportLoading.value = ''
  }
}
const refreshWeeklyReportDeliveryInsights = async () => {
  await Promise.all([
    loadWeeklyReportDeliveryHistory(),
    loadWeeklyReportDeliveryAnalytics(),
    loadCommunicationRhythm(),
  ])
}
const openWeeklyReportDeliveryDetail = async (delivery) => {
  selectedWeeklyReportDelivery.value = delivery || null
  const id = delivery?.delivery_id
  if (!id) return
  try {
    const res = await axiosGet(`coach/weekly-report-deliveries/${id}`)
    selectedWeeklyReportDelivery.value = res?.data?.data?.delivery || delivery
  } catch {
    selectedWeeklyReportDelivery.value = delivery
  }
}
const recordWeeklyReportCopyAction = async () => {
  const deliveryId = weeklyReportDeliveryReview.value?.delivery_history?.delivery_id
  if (!deliveryId) return null
  try {
    const res = await axiosPost(`coach/weekly-report-deliveries/${deliveryId}/record-copy`, {})
    const delivery = res?.data?.data?.delivery || null
    if (delivery) {
      weeklyReportDeliveryReview.value = {
        ...weeklyReportDeliveryReview.value,
        delivery_history: {
          delivery_id: delivery.delivery_id,
          delivery_status: delivery.delivery_status,
          recorded: true,
        },
      }
    }
    await refreshWeeklyReportDeliveryInsights()
    return delivery
  } catch {
    weeklyReportDeliveryHistoryMessage.value = 'Copied, but the copy action could not be recorded.'
    return null
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
onMounted(() => { loadPlans(); loadGroups(); loadRoster(); loadCustomDrills(); loadCommandCenter(); loadWeeklyRollup(); loadWeeklyTeamReport(); loadWeeklyReportNotes(); loadWeeklyReportTemplates(); refreshWeeklyReportDeliveryInsights(); loadSeasonDevelopmentArchive(); loadNextWeekDraft(); loadNextWeekCalendarDraft(); loadWeeklyDraftPlans() })
watch(activeTeamId, () => { weeklyReportDeliveryPreview.value = null; weeklyReportDeliveryMessage.value = ''; resetWeeklyReportDeliveryReview(); selectedWeeklyReportDelivery.value = null; loadRoster(); loadCommandCenter(); loadWeeklyRollup(); loadWeeklyTeamReport(); loadWeeklyReportNotes(); refreshWeeklyReportDeliveryInsights(); loadSeasonDevelopmentArchive(); loadNextWeekDraft(); loadNextWeekCalendarDraft(); loadWeeklyDraftPlans() })

// ── plan / builder ───────────────────────────────────────────────────────────
const newPlan = () => { editing.value = blankPlan() }
const editPlan = (p) => { editing.value = JSON.parse(JSON.stringify(p)) }
// Open the app-style "View Players" review flow for a published plan.
const viewPlayers = (p) => { viewingPlayers.value = JSON.parse(JSON.stringify(p)) }
// Clone a plan into the builder as a fresh draft.
const duplicatePlan = (p) => {
  const copy = JSON.parse(JSON.stringify(p))
  copy.id = undefined
  copy.name = `${p.name || 'Workout'} (copy)`
  copy.status = 'draft'
  copy.publishedAt = null
  editing.value = copy
}
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
const metricLabels = {
  average_exit_velocity: 'Average EV',
  max_exit_velocity: 'Max EV',
  hard_hit_percentage: 'Hard Hit %',
  line_drive_percentage: 'Line-Drive %',
  hitter_swing_miss_percentage: 'Swing & Miss %',
  average_fastball_velocity: 'Average Fastball Velocity',
  max_fastball_velocity: 'Max Fastball Velocity',
  strike_percentage: 'Strike %',
  long_toss_max_distance: 'Long Toss Max',
  weighted_ball_5oz_velocity: '5 oz Weighted Ball Velocity',
  bench_press: 'Bench Press',
  squat: 'Squat',
  deadlift: 'Deadlift',
  mobility_score: 'Mobility Score',
}
const metricLabel = (value) => metricLabels[value] || human(value)
const workloadLabel = (value) => ({
  light: 'Light Day',
  moderate: 'Moderate Day',
  heavy: 'Heavy Day',
  too_heavy: 'Too Heavy',
}[value] || human(value))
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
const weeklyTeamExecutive = computed(() => weeklyTeamReport.value?.executive_summary || {})
const weeklyTeamCompletion = computed(() => weeklyTeamReport.value?.team_completion || {})
const weeklyTeamBenchmark = computed(() => weeklyTeamReport.value?.benchmark_submission_summary || {})
const weeklyTeamReview = computed(() => weeklyTeamReport.value?.review_summary || {})
const weeklyTeamTrusted = computed(() => weeklyTeamReport.value?.trusted_data_summary || {})
const weeklyTeamMissed = computed(() => weeklyTeamReport.value?.missed_work_summary || {})
const weeklyTeamPlayers = computed(() => Array.isArray(weeklyTeamReport.value?.player_rows) ? weeklyTeamReport.value.player_rows : [])
const weeklyTeamFollowUps = computed(() => Array.isArray(weeklyTeamReport.value?.coach_follow_ups) ? weeklyTeamReport.value.coach_follow_ups : [])
const weeklyTeamPriorities = computed(() => Array.isArray(weeklyTeamReport.value?.next_week_priorities) ? weeklyTeamReport.value.next_week_priorities : [])
const weeklyTeamCollectedMetrics = computed(() => Array.isArray(weeklyTeamBenchmark.value?.top_collected_metrics) ? weeklyTeamBenchmark.value.top_collected_metrics : [])
const weeklyTeamRemainingMetrics = computed(() => Array.isArray(weeklyTeamBenchmark.value?.top_remaining_missing_metrics) ? weeklyTeamBenchmark.value.top_remaining_missing_metrics : [])
const selectedWeeklyReportTemplate = computed(() => weeklyReportTemplates.value.find((template) => template.template_key === weeklyReportTemplateKey.value) || null)
const weeklyReportTemplateSections = computed(() => Array.isArray(selectedWeeklyReportTemplate.value?.sections) ? selectedWeeklyReportTemplate.value.sections : [])
const weeklyReportTemplateRules = computed(() => Array.isArray(selectedWeeklyReportTemplate.value?.copy_rules) ? selectedWeeklyReportTemplate.value.copy_rules : [])
const selectedWeeklyReportDeliveryChannel = computed(() => weeklyReportDeliveryChannelOptions.find((channel) => channel.value === weeklyReportDeliveryChannel.value) || weeklyReportDeliveryChannelOptions[0])
const weeklyReportDeliveryDraftSupported = computed(() => Boolean(selectedWeeklyReportDeliveryChannel.value?.supported && weeklyReportDeliveryChannel.value !== 'copy'))
const weeklyReportDeliveryWarnings = computed(() => [
  ...(weeklyReportDeliveryPreview.value?.privacy_warnings || []),
  ...(weeklyReportDeliveryPreview.value?.delivery_warnings || []),
])
const weeklyReportDeliveryRecipients = computed(() => Array.isArray(weeklyReportDeliveryPreview.value?.recipients) ? weeklyReportDeliveryPreview.value.recipients : [])
const weeklyReportDeliverySummary = computed(() => weeklyReportDeliveryPreview.value?.recipient_summary || {
  total_recipients: 0,
  safe_recipients: 0,
  missing_contact_count: 0,
  unsafe_recipient_count: 0,
  recipient_types: {},
})
const weeklyReportReviewWarnings = computed(() => [
  ...(weeklyReportDeliveryReview.value?.privacy_warnings || []),
  ...(weeklyReportDeliveryReview.value?.delivery_warnings || []),
])
const weeklyReportReviewBlockers = computed(() => Array.isArray(weeklyReportDeliveryReview.value?.send_blockers) ? weeklyReportDeliveryReview.value.send_blockers : [])
const weeklyReportReviewRecipients = computed(() => Array.isArray(weeklyReportDeliveryReview.value?.recipients) ? weeklyReportDeliveryReview.value.recipients : [])
const weeklyReportReviewSummary = computed(() => weeklyReportDeliveryReview.value?.recipient_summary || {
  total_recipients: 0,
  safe_recipients: 0,
  missing_contact_count: 0,
  unsafe_recipient_count: 0,
  recipient_types: {},
})
const weeklyReportCanSend = computed(() => Boolean(weeklyReportDeliveryReview.value?.can_send && weeklyReportConfirmSend.value && !weeklyReportReviewBlockers.value.length))
const weeklyReportDeliveryHistorySummary = computed(() => weeklyReportDeliveryHistory.value?.summary || {})
const weeklyReportDeliveries = computed(() => Array.isArray(weeklyReportDeliveryHistory.value?.deliveries) ? weeklyReportDeliveryHistory.value.deliveries : [])
const weeklyReportDeliveryAnalyticsSummary = computed(() => weeklyReportDeliveryAnalytics.value?.summary || {})
const weeklyReportDeliveryHealth = computed(() => weeklyReportDeliveryAnalytics.value?.delivery_health || {})
const weeklyReportDeliveryTemplateUsage = computed(() => Array.isArray(weeklyReportDeliveryAnalytics.value?.template_usage) ? weeklyReportDeliveryAnalytics.value.template_usage : [])
const weeklyReportDeliveryAudienceUsage = computed(() => Array.isArray(weeklyReportDeliveryAnalytics.value?.audience_usage) ? weeklyReportDeliveryAnalytics.value.audience_usage : [])
const weeklyReportDeliveryChannelUsage = computed(() => Array.isArray(weeklyReportDeliveryAnalytics.value?.channel_usage) ? weeklyReportDeliveryAnalytics.value.channel_usage : [])
const weeklyReportDeliveryActions = computed(() => Array.isArray(weeklyReportDeliveryAnalytics.value?.recommended_actions) ? weeklyReportDeliveryAnalytics.value.recommended_actions : [])
const weeklyReportDeliverySafety = computed(() => weeklyReportDeliveryAnalytics.value?.privacy_safety_summary || {})
const communicationRhythmScore = computed(() => communicationRhythm.value?.rhythm_score || {})
const communicationRhythmRows = computed(() => Array.isArray(communicationRhythm.value?.weekly_rows) ? communicationRhythm.value.weekly_rows : [])
const communicationRhythmAudienceSummary = computed(() => communicationRhythm.value?.audience_summary || {})
const communicationRhythmTemplateSummary = computed(() => Array.isArray(communicationRhythm.value?.template_summary) ? communicationRhythm.value.template_summary : [])
const communicationRhythmHealth = computed(() => communicationRhythm.value?.delivery_health_summary || {})
const communicationRhythmMissedWeeks = computed(() => Array.isArray(communicationRhythm.value?.missed_weeks) ? communicationRhythm.value.missed_weeks : [])
const communicationRhythmStreaks = computed(() => communicationRhythm.value?.streaks || {})
const communicationRhythmActions = computed(() => Array.isArray(communicationRhythm.value?.recommended_actions) ? communicationRhythm.value.recommended_actions : [])
const communicationAudienceRows = computed(() => [
  { key: 'parents', label: 'Parent Updates', data: communicationRhythmAudienceSummary.value.parents || {} },
  { key: 'staff', label: 'Staff Reports', data: communicationRhythmAudienceSummary.value.staff || {} },
  { key: 'players', label: 'Player Summaries', data: communicationRhythmAudienceSummary.value.players || {} },
  { key: 'coach', label: 'Coach/Internal', data: communicationRhythmAudienceSummary.value.coach || {} },
])
const seasonArchiveSummary = computed(() => seasonDevelopmentArchive.value?.executive_summary || {})
const seasonArchiveTotals = computed(() => seasonDevelopmentArchive.value?.season_totals || {})
const seasonArchiveTimeline = computed(() => Array.isArray(seasonDevelopmentArchive.value?.weekly_timeline) ? seasonDevelopmentArchive.value.weekly_timeline : [])
const seasonArchiveBenchmark = computed(() => seasonDevelopmentArchive.value?.benchmark_progress || {})
const seasonArchivePlanner = computed(() => seasonDevelopmentArchive.value?.planner_progress || {})
const seasonArchiveCommunication = computed(() => seasonDevelopmentArchive.value?.communication_summary || {})
const seasonArchivePlayers = computed(() => Array.isArray(seasonDevelopmentArchive.value?.player_development_summary) ? seasonDevelopmentArchive.value.player_development_summary : [])
const seasonArchiveHighlights = computed(() => Array.isArray(seasonDevelopmentArchive.value?.season_highlights) ? seasonDevelopmentArchive.value.season_highlights : [])
const seasonArchiveConcerns = computed(() => Array.isArray(seasonDevelopmentArchive.value?.season_concerns) ? seasonDevelopmentArchive.value.season_concerns : [])
const seasonArchiveNextSteps = computed(() => Array.isArray(seasonDevelopmentArchive.value?.recommended_next_steps) ? seasonDevelopmentArchive.value.recommended_next_steps : [])
const seasonArchiveCards = computed(() => [
  {
    label: 'Plans Published',
    value: seasonArchiveTotals.value.daily_plans_published || 0,
    detail: `${seasonArchiveTotals.value.daily_plans_created || 0} created`,
  },
  {
    label: 'Completion',
    value: `${oneDecimal(seasonArchiveTotals.value.average_completion_percentage)}%`,
    detail: `${seasonArchiveTotals.value.completed_workouts || 0}/${seasonArchiveTotals.value.assigned_workouts || 0} workouts complete`,
  },
  {
    label: 'Trusted Values',
    value: seasonArchiveTotals.value.trusted_values_promoted || 0,
    detail: `${seasonArchiveTotals.value.benchmark_values_approved || 0} approved`,
  },
  {
    label: 'Reports Shared',
    value: seasonArchiveTotals.value.reports_sent_or_copied || 0,
    detail: `${seasonArchiveTotals.value.reports_created || 0} created`,
  },
  {
    label: 'Rhythm Score',
    value: seasonArchiveTotals.value.communication_rhythm_score == null ? '—' : oneDecimal(seasonArchiveTotals.value.communication_rhythm_score),
    detail: human(seasonArchiveCommunication.value.communication_rhythm_label),
  },
])
const seasonArchiveMetricNames = (rows) => (Array.isArray(rows) ? rows : [])
  .slice(0, 3)
  .map((row) => row.display_name || metricLabels[row.metric_key] || human(row.metric_key))
  .filter(Boolean)
  .join(', ')
const weeklyTeamReportCards = computed(() => [
  {
    label: 'Team Completion',
    value: `${oneDecimal(weeklyTeamCompletion.value.team_completion_percentage)}%`,
    detail: `${weeklyTeamCompletion.value.completed_assignments || 0}/${weeklyTeamCompletion.value.total_assignments || 0} assignments complete`,
  },
  {
    label: 'Benchmark Review',
    value: weeklyTeamBenchmark.value.submitted_metric_count || 0,
    detail: `${weeklyTeamBenchmark.value.approved_metric_count || 0} approved · ${weeklyTeamReview.value.pending_review_count || 0} pending`,
  },
  {
    label: 'Trusted Values',
    value: weeklyTeamTrusted.value.trusted_values_added || 0,
    detail: `${weeklyTeamTrusted.value.players_improved || 0} players updated`,
  },
  {
    label: 'Missed Work',
    value: weeklyTeamMissed.value.players_with_missed_work || 0,
    detail: `${weeklyTeamMissed.value.missed_items_count || 0} missed items`,
  },
])
const weeklyReportAudienceWarning = computed(() => {
  const warnings = {
    parents: 'Parent version hides private player review details.',
    players: "Player version hides other players' private details.",
  }
  if (selectedWeeklyReportTemplate.value?.template_key === 'internal_benchmark_qa') return 'Internal QA is coach/staff only.'
  return warnings[weeklyReportExportAudience.value] || ''
})
const seasonArchivePublicAudience = computed(() => ['parents', 'players'].includes(seasonArchiveExportAudience.value))
const seasonArchiveAudienceWarning = computed(() => {
  if (seasonArchiveExportFormat.value === 'pdf') return 'PDF export is not configured yet. Use Copy Text or Printable HTML.'
  if (seasonArchiveExportAudience.value === 'parents') return 'Parent version hides private player details, staff notes, internal QA, and raw benchmark payloads.'
  if (seasonArchiveExportAudience.value === 'players') return "Player version hides other players' private details, staff notes, internal QA, and raw benchmark payloads."
  if (seasonArchiveExportAudience.value === 'director') return 'Director version hides raw payloads and private system identifiers.'
  return ''
})
const weeklyReportVisibleNotes = computed(() => weeklyReportNotes.value.filter((note) => {
  if (weeklyReportExportAudience.value === 'coach') return true
  if (weeklyReportExportAudience.value === 'staff') return !['private'].includes(note.visibility)
  if (weeklyReportExportAudience.value === 'parents') return ['parent_summary', 'next_week_emphasis'].includes(note.note_type) && note.visibility === 'parents'
  if (weeklyReportExportAudience.value === 'players') return ['player_message', 'next_week_emphasis'].includes(note.note_type) && note.visibility === 'players'
  return false
}))
const weeklyReportNotesByType = computed(() => {
  const groups = {}
  weeklyReportNotes.value.forEach((note) => {
    const key = note.note_type || 'coach_comment'
    groups[key] = groups[key] || []
    groups[key].push(note)
  })
  return groups
})
const nextWeekPriorities = computed(() => Array.isArray(nextWeekDraft.value?.priority_focuses) ? nextWeekDraft.value.priority_focuses : [])
const nextWeekDays = computed(() => Array.isArray(nextWeekDraft.value?.suggested_plan_days) ? nextWeekDraft.value.suggested_plan_days : [])
const nextWeekAssignments = computed(() => Array.isArray(nextWeekDraft.value?.player_assignments) ? nextWeekDraft.value.player_assignments : [])
const nextWeekTargets = computed(() => Array.isArray(nextWeekDraft.value?.benchmark_collection_targets) ? nextWeekDraft.value.benchmark_collection_targets : [])
const nextWeekNotes = computed(() => Array.isArray(nextWeekDraft.value?.coach_notes) ? nextWeekDraft.value.coach_notes : [])
const calendarDays = computed(() => Array.isArray(nextWeekCalendarDraft.value?.calendar_days) ? nextWeekCalendarDraft.value.calendar_days : [])
const calendarSummary = computed(() => nextWeekCalendarDraft.value?.weekly_workload_summary || {})
const calendarTargets = computed(() => Array.isArray(nextWeekCalendarDraft.value?.benchmark_collection_targets) ? nextWeekCalendarDraft.value.benchmark_collection_targets : [])
const calendarNotes = computed(() => Array.isArray(nextWeekCalendarDraft.value?.coach_notes) ? nextWeekCalendarDraft.value.coach_notes : [])
const selectedCalendarDays = computed(() => calendarDays.value.filter((day) => selectedCalendarDayIndexes.value.includes(Number(day.day_index))))
const savedWeeklyDraftPlans = computed(() => Array.isArray(weeklyDraftPlans.value?.plans) ? weeklyDraftPlans.value.plans : [])
const weeklyDraftSummary = computed(() => weeklyDraftPlans.value || {})
const selectedWeeklyDraftPlans = computed(() => savedWeeklyDraftPlans.value.filter((plan) => selectedWeeklyDraftPlanIds.value.includes(String(plan.daily_plan_id))))
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
const deliveryStatusTone = (status) => ({
  sent: 'good',
  partial: 'warning',
  copy_only: 'info',
  draft_created: 'info',
  prepared: 'muted',
  blocked: 'warning',
  unsupported: 'warning',
  failed: 'warning',
}[status] || 'muted')
const communicationStatusTone = (status) => ({
  strong: 'good',
  solid: 'info',
  complete: 'good',
  partial: 'info',
  incomplete: 'warning',
  missed: 'warning',
  needs_review: 'warning',
  blocked: 'warning',
  copy_only: 'info',
  excellent: 'good',
  good: 'good',
  inconsistent: 'warning',
  needs_attention: 'warning',
  no_activity: 'muted',
  consistent: 'good',
  not_reached: 'muted',
}[status] || 'muted')
const noteTypeLabel = (value) => weeklyReportNoteTypes.find((type) => type.value === value)?.label || human(value)
const noteTypeHint = (value) => weeklyReportNoteTypes.find((type) => type.value === value)?.hint || ''
const applyWeeklyReportTemplate = () => {
  const template = selectedWeeklyReportTemplate.value
  if (!template) return
  if (template.template_key === 'short_text_summary') {
    weeklyReportExportFormat.value = 'text'
    weeklyReportDeliveryFormat.value = 'text'
    return
  }
  const audience = template.audience === 'internal' ? 'coach' : template.audience
  if (['coach', 'staff', 'players', 'parents'].includes(audience)) {
    weeklyReportExportAudience.value = audience
  }
}
watch(weeklyReportTemplateKey, () => {
  applyWeeklyReportTemplate()
  weeklyReportDeliveryPreview.value = null
  weeklyReportDeliveryMessage.value = ''
  resetWeeklyReportDeliveryReview()
})
watch([weeklyReportExportAudience, weeklyReportDeliveryChannel, weeklyReportDeliveryFormat], () => {
  weeklyReportDeliveryPreview.value = null
  weeklyReportDeliveryMessage.value = ''
  resetWeeklyReportDeliveryReview()
})
watch(seasonArchiveExportAudience, () => {
  if (seasonArchivePublicAudience.value) {
    seasonArchiveIncludePlayerRows.value = false
  }
  seasonArchiveExportPayload.value = null
  seasonArchiveExportMessage.value = ''
})
const resetWeeklyReportNoteForm = () => {
  weeklyReportNoteEditingId.value = ''
  weeklyReportNoteForm.value = {
    note_type: 'coach_comment',
    audience: 'coach',
    visibility: 'staff',
    title: '',
    body: '',
    player_id: '',
  }
}
const editWeeklyReportNote = (note) => {
  weeklyReportNoteEditingId.value = String(note?.id || '')
  weeklyReportNoteForm.value = {
    note_type: note?.note_type || 'coach_comment',
    audience: note?.audience || 'coach',
    visibility: note?.visibility || 'staff',
    title: note?.title || '',
    body: note?.body || '',
    player_id: note?.player_id || '',
  }
}
const saveWeeklyReportNote = async () => {
  weeklyReportNotesMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportNotesMessage.value = 'No team selected.'
    return
  }
  if (!weeklyReportNoteForm.value.body.trim()) {
    weeklyReportNotesMessage.value = 'Add note text before saving.'
    return
  }

  const payload = {
    ...weeklyReportNoteForm.value,
    player_id: weeklyReportNoteForm.value.player_id || null,
    days: 7,
  }

  try {
    if (weeklyReportNoteEditingId.value) {
      await axiosPut(`coach/weekly-report-notes/${weeklyReportNoteEditingId.value}`, payload)
      weeklyReportNotesMessage.value = 'Report note updated.'
    } else {
      await axiosPost(`coach/teams/${activeTeamId.value}/weekly-report-notes`, payload)
      weeklyReportNotesMessage.value = 'Report note saved.'
    }
    resetWeeklyReportNoteForm()
    await loadWeeklyReportNotes()
  } catch {
    weeklyReportNotesMessage.value = 'Could not save that report note.'
  }
}
const deleteWeeklyReportNote = async (note) => {
  const id = String(note?.id || '')
  if (!id || !confirm('Delete this report note?')) return
  weeklyReportNotesMessage.value = ''
  try {
    await axiosDelete('coach/weekly-report-notes/', id)
    weeklyReportNotesMessage.value = 'Report note deleted.'
    if (weeklyReportNoteEditingId.value === id) resetWeeklyReportNoteForm()
    await loadWeeklyReportNotes()
  } catch {
    weeklyReportNotesMessage.value = 'Could not delete that report note.'
  }
}
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
  await loadWeeklyTeamReport()
  await loadNextWeekDraft()
  await loadNextWeekCalendarDraft()
  await loadWeeklyDraftPlans()
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
const fallbackCopyText = (text) => {
  const area = document.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', 'readonly')
  area.style.position = 'fixed'
  area.style.left = '-9999px'
  document.body.appendChild(area)
  area.select()
  document.execCommand('copy')
  area.remove()
}
const copyWeeklyReportSummary = async () => {
  const exportPayload = await loadWeeklyReportExport('text')
  const text = exportPayload?.share_text || ''
  if (!text) {
    weeklyReportExportMessage.value = 'No report data to export.'
    return
  }

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
    } else {
      fallbackCopyText(text)
    }
    weeklyReportExportMessage.value = exportPayload?.warnings?.[0] || 'Copied weekly report.'
  } catch {
    fallbackCopyText(text)
    weeklyReportExportMessage.value = 'Copied weekly report.'
  }
}
const copyTextToClipboard = async (text, successMessage = 'Copied.') => {
  if (!text) return false
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
    } else {
      fallbackCopyText(text)
    }
    weeklyReportDeliveryMessage.value = successMessage
    return true
  } catch {
    fallbackCopyText(text)
    weeklyReportDeliveryMessage.value = successMessage
    return true
  }
}
const copyWeeklyReportDeliverySubject = async () => {
  const payload = weeklyReportDeliveryPreview.value || await loadWeeklyReportDeliveryPreview()
  const subject = weeklyReportDraftSubject.value || payload?.subject || ''
  if (!subject) {
    weeklyReportDeliveryMessage.value = 'No delivery subject to copy.'
    return
  }
  await copyTextToClipboard(subject, 'Copied delivery subject.')
  if (!weeklyReportDeliveryReview.value?.delivery_history?.delivery_id) await loadWeeklyReportDeliveryReview()
  await recordWeeklyReportCopyAction()
  weeklyReportDeliveryMessage.value = 'Copied delivery subject. Copy action recorded.'
}
const copyWeeklyReportDeliveryMessage = async () => {
  const payload = weeklyReportDeliveryPreview.value || await loadWeeklyReportDeliveryPreview()
  const text = weeklyReportDraftMessage.value || payload?.message_text || ''
  if (!text) {
    weeklyReportDeliveryMessage.value = 'No delivery message to copy.'
    return
  }
  await copyTextToClipboard(text, 'Copied delivery message.')
  if (!weeklyReportDeliveryReview.value?.delivery_history?.delivery_id) await loadWeeklyReportDeliveryReview()
  await recordWeeklyReportCopyAction()
  weeklyReportDeliveryMessage.value = 'Copied delivery message. Copy action recorded.'
}
const applyWeeklyReportDeliveryReview = (payload, message = 'Delivery draft is ready for review.') => {
  weeklyReportDeliveryReview.value = payload || null
  weeklyReportDraftSubject.value = payload?.subject || ''
  weeklyReportDraftMessage.value = payload?.message_text || ''
  weeklyReportConfirmSend.value = false
  weeklyReportSendResult.value = null
  weeklyReportDeliveryMessage.value = payload?.send_blockers?.[0] || payload?.delivery_warnings?.[0] || message
}
const loadWeeklyReportDeliveryReview = async () => {
  weeklyReportDeliveryMessage.value = ''
  weeklyReportSendResult.value = null
  if (!activeTeamId.value) {
    weeklyReportDeliveryMessage.value = 'No team selected.'
    return null
  }
  if (!weeklyTeamReport.value) {
    weeklyReportDeliveryMessage.value = 'Generate a weekly report before reviewing delivery.'
    return null
  }

  weeklyReportDeliveryLoading.value = 'review'
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/weekly-report/delivery-review`, weeklyReportDeliveryBasePayload())
    const payload = res?.data?.data || null
    applyWeeklyReportDeliveryReview(payload)
    await refreshWeeklyReportDeliveryInsights()
    return payload
  } catch {
    resetWeeklyReportDeliveryReview()
    weeklyReportDeliveryMessage.value = 'Could not review the delivery draft.'
    return null
  } finally {
    weeklyReportDeliveryLoading.value = ''
  }
}
const recheckWeeklyReportDeliveryDraft = async () => {
  weeklyReportDeliveryMessage.value = ''
  weeklyReportSendResult.value = null
  if (!activeTeamId.value) {
    weeklyReportDeliveryMessage.value = 'No team selected.'
    return null
  }

  weeklyReportDeliveryLoading.value = 'recheck'
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/weekly-report/update-delivery-draft`, {
      ...weeklyReportDeliveryBasePayload(),
      subject: weeklyReportDraftSubject.value,
      message_text: weeklyReportDraftMessage.value,
    })
    const payload = res?.data?.data || null
    applyWeeklyReportDeliveryReview(payload, 'Delivery draft rechecked.')
    return payload
  } catch {
    weeklyReportDeliveryMessage.value = 'Could not recheck the delivery draft.'
    return null
  } finally {
    weeklyReportDeliveryLoading.value = ''
  }
}
const sendWeeklyReportDeliveryDraft = async () => {
  weeklyReportDeliveryMessage.value = ''
  weeklyReportSendResult.value = null
  if (!weeklyReportCanSend.value) {
    weeklyReportDeliveryMessage.value = weeklyReportReviewBlockers.value[0] || 'Review the draft and confirm before sending.'
    return null
  }

  weeklyReportDeliveryLoading.value = 'send'
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/weekly-report/send-delivery-draft`, {
      ...weeklyReportDeliveryBasePayload(),
      subject: weeklyReportDraftSubject.value,
      message_text: weeklyReportDraftMessage.value,
      confirm_send: true,
    })
    weeklyReportSendResult.value = res?.data?.data || null
    weeklyReportDeliveryMessage.value = weeklyReportSendResult.value?.warnings?.[0] || `Delivery ${human(weeklyReportSendResult.value?.send_status || 'checked')}.`
    await refreshWeeklyReportDeliveryInsights()
    return weeklyReportSendResult.value
  } catch {
    weeklyReportDeliveryMessage.value = 'Could not complete the send check.'
    return null
  } finally {
    weeklyReportDeliveryLoading.value = ''
  }
}
const applyCommunicationRhythmAction = async (action) => {
  if (action?.template_key) weeklyReportTemplateKey.value = action.template_key
  if (action?.audience) weeklyReportExportAudience.value = action.audience
  weeklyReportDeliveryMessage.value = action?.action || 'Communication rhythm action selected.'
  if (weeklyTeamReport.value && action?.template_key) {
    await loadWeeklyReportDeliveryPreview()
  }
}
const createWeeklyReportDeliveryDraft = async () => {
  weeklyReportDeliveryMessage.value = ''
  if (!activeTeamId.value) {
    weeklyReportDeliveryMessage.value = 'No team selected.'
    return
  }
  weeklyReportDeliveryLoading.value = 'draft'
  resetWeeklyReportDeliveryReview()
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/weekly-report/create-delivery-draft`, {
      ...weeklyReportDeliveryBasePayload(),
    })
    weeklyReportDeliveryPreview.value = res?.data?.data || null
    weeklyReportDeliveryMessage.value = weeklyReportDeliveryPreview.value?.draft?.message || 'Draft payload prepared.'
    await refreshWeeklyReportDeliveryInsights()
  } catch {
    weeklyReportDeliveryMessage.value = 'Could not create a delivery draft.'
  } finally {
    weeklyReportDeliveryLoading.value = ''
  }
}
const previewWeeklyReportHtml = async () => {
  const exportPayload = await loadWeeklyReportExport('html')
  if (!exportPayload?.html) {
    weeklyReportExportMessage.value = 'No report data to export.'
    return
  }
  weeklyReportPreviewHtml.value = exportPayload.html
  weeklyReportPreviewOpen.value = true
  weeklyReportExportMessage.value = exportPayload?.warnings?.[0] || 'Printable report preview is ready.'
}
const requestWeeklyReportPdf = async () => {
  const exportPayload = await loadWeeklyReportExport('pdf')
  weeklyReportExportMessage.value = exportPayload?.warnings?.[0] || exportPayload?.pdf?.warnings?.[0] || 'PDF export is not configured yet. Use Copy Summary.'
}
const runWeeklyReportSelectedExport = async () => {
  if (weeklyReportExportFormat.value === 'html') {
    await previewWeeklyReportHtml()
    return
  }
  if (weeklyReportExportFormat.value === 'pdf') {
    await requestWeeklyReportPdf()
    return
  }
  await copyWeeklyReportSummary()
}
const printWeeklyReportPreview = () => {
  if (!weeklyReportPreviewHtml.value) return
  const win = window.open('', '_blank', 'noopener,noreferrer')
  if (!win) {
    weeklyReportExportMessage.value = 'Popup blocked. Use the browser print option from the preview.'
    return
  }
  win.document.open()
  win.document.write(weeklyReportPreviewHtml.value)
  win.document.close()
  setTimeout(() => {
    win.focus()
    win.print()
  }, 250)
}
const copySeasonArchiveText = async () => {
  const exportPayload = await loadSeasonArchiveExport('text')
  const text = exportPayload?.share_text || ''
  if (!text) {
    seasonArchiveExportMessage.value = 'No season packet data to export.'
    return
  }

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
    } else {
      fallbackCopyText(text)
    }
    seasonArchiveExportMessage.value = exportPayload?.warnings?.[0] || 'Copied season review packet.'
  } catch {
    fallbackCopyText(text)
    seasonArchiveExportMessage.value = 'Copied season review packet.'
  }
}
const previewSeasonArchiveHtml = async () => {
  const exportPayload = await loadSeasonArchiveExport('html')
  if (!exportPayload?.html) {
    seasonArchiveExportMessage.value = 'No season packet data to export.'
    return
  }
  seasonArchivePreviewHtml.value = exportPayload.html
  seasonArchivePreviewOpen.value = true
  seasonArchiveExportMessage.value = exportPayload?.warnings?.[0] || 'Printable season packet preview is ready.'
}
const requestSeasonArchivePdf = async () => {
  const exportPayload = await loadSeasonArchiveExport('pdf')
  seasonArchiveExportMessage.value = exportPayload?.warnings?.[0] || exportPayload?.pdf?.warnings?.[0] || 'PDF export is not configured yet. Use Copy Text or Printable HTML.'
}
const runSeasonArchiveSelectedExport = async () => {
  if (seasonArchiveExportFormat.value === 'html') {
    await previewSeasonArchiveHtml()
    return
  }
  if (seasonArchiveExportFormat.value === 'pdf') {
    await requestSeasonArchivePdf()
    return
  }
  await copySeasonArchiveText()
}
const printSeasonArchivePreview = () => {
  if (!seasonArchivePreviewHtml.value) return
  const win = window.open('', '_blank', 'noopener,noreferrer')
  if (!win) {
    seasonArchiveExportMessage.value = 'Popup blocked. Use the browser print option from the preview.'
    return
  }
  win.document.open()
  win.document.write(seasonArchivePreviewHtml.value)
  win.document.close()
  setTimeout(() => {
    win.focus()
    win.print()
  }, 250)
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
    await loadWeeklyDraftPlans()
    await loadWeeklyTeamReport()
    if (savedNextWeekDailyPlan.value?.daily_plan) {
      editing.value = planFromApi(savedNextWeekDailyPlan.value.daily_plan)
    }
  } catch {
    nextWeekDraftMessage.value = 'Could not save that suggested day as a draft.'
  } finally {
    savingNextWeekDay.value = ''
  }
}
const calendarDaySelected = (day) => selectedCalendarDayIndexes.value.includes(Number(day?.day_index))
const toggleCalendarDay = (day) => {
  const dayIndex = Number(day?.day_index)
  if (!dayIndex) return
  const selectedSet = new Set(selectedCalendarDayIndexes.value.map(Number))
  selectedSet.has(dayIndex) ? selectedSet.delete(dayIndex) : selectedSet.add(dayIndex)
  selectedCalendarDayIndexes.value = [...selectedSet].sort((a, b) => a - b)
}
const previewCalendarDraftDay = (day) => {
  previewCalendarDay.value = day
}
const openCalendarDailyPlan = async (dayOrSaved) => {
  const dailyPlan = dayOrSaved?.daily_plan
  if (dailyPlan) {
    editing.value = planFromApi(dailyPlan)
    return
  }
  const planId = dayOrSaved?.saved_daily_plan_id || dayOrSaved?.save_status?.existing_daily_plan_id || dayOrSaved?.existing_daily_plan_id
  if (!planId) {
    nextWeekCalendarMessage.value = 'No saved Daily Planner draft is connected to this day yet.'
    return
  }
  let plan = plans.value.find((row) => String(row.id) === String(planId))
  if (!plan) {
    await loadPlans()
    plan = plans.value.find((row) => String(row.id) === String(planId))
  }
  if (plan) {
    editing.value = JSON.parse(JSON.stringify(plan))
  } else {
    nextWeekCalendarMessage.value = 'The saved draft exists, but it is not loaded in this browser yet. Refresh the plan list and try again.'
  }
}
const saveCalendarDraftDays = async (daysToSave = selectedCalendarDays.value) => {
  nextWeekCalendarMessage.value = ''
  if (!activeTeamId.value) return
  if (!daysToSave.length) {
    nextWeekCalendarMessage.value = 'No days selected.'
    return
  }

  savingCalendarDays.value = true
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/next-week-calendar-draft/save-days`, {
      days: daysToSave.map((day) => ({
        day_index: day.day_index,
        scheduled_for: day.scheduled_for,
        status: 'draft',
        assign_player_ids: [],
        overwrite_existing: false,
      })),
      next_week_start_date: nextWeekCalendarDraft.value?.week_start_date || nextWeekCalendarStart.value || null,
      days_to_review: 7,
      plan_days: 5,
      max_minutes_per_day: 90,
    })
    const data = res?.data?.data || {}
    savedCalendarPlans.value = Array.isArray(data.saved_daily_plans) ? data.saved_daily_plans : []
    const savedCount = Number(data.saved_count || 0)
    const skippedCount = Number(data.skipped_count || 0)
    if (savedCount > 0) {
      nextWeekCalendarMessage.value = `${savedCount} selected day${savedCount === 1 ? '' : 's'} saved as Daily Planner draft${savedCount === 1 ? '' : 's'}.`
    } else if (skippedCount > 0) {
      nextWeekCalendarMessage.value = data.skipped_days?.[0]?.reason || 'A plan already exists for this date.'
    } else {
      nextWeekCalendarMessage.value = 'No days were saved.'
    }
    await loadPlans()
    await loadNextWeekCalendarDraft()
    await loadWeeklyDraftPlans()
    await loadWeeklyTeamReport()
  } catch {
    nextWeekCalendarMessage.value = 'Could not save selected days.'
  } finally {
    savingCalendarDays.value = false
  }
}
const saveOneCalendarDraftDay = (day) => saveCalendarDraftDays(day ? [day] : [])
const weeklyDraftSelected = (plan) => selectedWeeklyDraftPlanIds.value.includes(String(plan?.daily_plan_id))
const toggleWeeklyDraftPlan = (plan) => {
  const id = String(plan?.daily_plan_id || '')
  if (!id) return
  const selectedSet = new Set(selectedWeeklyDraftPlanIds.value.map(String))
  selectedSet.has(id) ? selectedSet.delete(id) : selectedSet.add(id)
  selectedWeeklyDraftPlanIds.value = [...selectedSet]
}
const openWeeklyDraftPlan = async (plan) => {
  const dailyPlan = plan?.daily_plan
  if (dailyPlan) {
    editing.value = planFromApi(dailyPlan)
    return
  }
  const planId = plan?.daily_plan_id
  if (!planId) return
  let local = plans.value.find((row) => String(row.id) === String(planId))
  if (!local) {
    await loadPlans()
    local = plans.value.find((row) => String(row.id) === String(planId))
  }
  if (local) editing.value = JSON.parse(JSON.stringify(local))
}
const publishWeeklyDraftPlan = async (plan, assignAll = false) => {
  const planId = String(plan?.daily_plan_id || '')
  if (!planId) return
  weeklyPublishMessage.value = ''
  weeklyPublishLoading.value = `${planId}:${assignAll ? 'assign' : 'publish'}`
  try {
    const endpoint = assignAll ? `coach/daily-plans/${planId}/publish-and-assign` : `coach/daily-plans/${planId}/publish`
    const res = await axiosPost(endpoint, {
      assign_all: assignAll,
      player_ids: [],
      notify_players: false,
    })
    const data = res?.data?.data || {}
    weeklyPublishMessage.value = data.status === 'skipped'
      ? (data.skipped_plans?.[0]?.reason || data.warnings?.[0] || 'This plan was skipped.')
      : assignAll
        ? 'Plan published and assigned to available players.'
        : 'Plan published. Assign players when you are ready.'
    await loadPlans()
    await loadWeeklyDraftPlans()
    await loadNextWeekCalendarDraft()
    await loadWeeklyTeamReport()
  } catch {
    weeklyPublishMessage.value = 'Could not publish that weekly draft plan.'
  } finally {
    weeklyPublishLoading.value = ''
  }
}
const publishSelectedWeeklyDraftPlans = async (assignAll = false) => {
  weeklyPublishMessage.value = ''
  if (!activeTeamId.value) return
  if (!selectedWeeklyDraftPlanIds.value.length) {
    weeklyPublishMessage.value = 'Select one or more weekly draft plans first.'
    return
  }

  weeklyPublishLoading.value = assignAll ? 'bulk-assign' : 'bulk-publish'
  try {
    const res = await axiosPost(`coach/teams/${activeTeamId.value}/weekly-draft-plans/publish`, {
      daily_plan_ids: selectedWeeklyDraftPlanIds.value,
      assign_all: assignAll,
      player_ids: [],
      notify_players: false,
    })
    const data = res?.data?.data || {}
    weeklyPublishMessage.value = `${data.published_count || 0} selected plan${Number(data.published_count || 0) === 1 ? '' : 's'} published. ${data.assigned_count || 0} new assignment${Number(data.assigned_count || 0) === 1 ? '' : 's'} created.`
    if (Number(data.skipped_count || 0) > 0 && data.skipped_plans?.[0]?.reason) {
      weeklyPublishMessage.value += ` ${data.skipped_plans[0].reason}`
    }
    selectedWeeklyDraftPlanIds.value = []
    await loadPlans()
    await loadWeeklyDraftPlans()
    await loadNextWeekCalendarDraft()
    await loadWeeklyTeamReport()
  } catch {
    weeklyPublishMessage.value = 'Could not publish selected weekly draft plans.'
  } finally {
    weeklyPublishLoading.value = ''
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

      <!-- ══ VIEW PLAYERS (per-player progress + review) ══ -->
      <CoachWorkoutPlayers v-if="viewingPlayers" :plan="viewingPlayers" @back="viewingPlayers = null" />

      <!-- ══ LIST ══ -->
      <template v-else-if="!editing">
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
                <div>
                  <div class="dp-section mb-0">Coach Weekly Team Report</div>
                  <p class="dp-command-sub">Completion, benchmark submissions, coach review, trusted data, and next-week priorities.</p>
                </div>
                <div class="dp-calendar-controls">
                  <button v-if="weeklyTeamReview.pending_review_count" class="dp-btn dp-btn--small" @click="openReviewQueue">Review Pending Submissions</button>
                  <button class="dp-btn dp-btn--small" @click="loadNextWeekDraft">Generate Next Week Draft</button>
                  <button class="dp-btn dp-btn--small" @click="loadNextWeekCalendarDraft">Open Weekly Calendar Draft</button>
                  <button class="dp-link" :disabled="weeklyTeamReportLoading" @click="loadWeeklyTeamReport">{{ weeklyTeamReportLoading ? 'Refreshing…' : 'Refresh Report' }}</button>
                </div>
              </div>

              <div v-if="weeklyTeamReportLoading && !weeklyTeamReport" class="dp-command-loading">Building weekly team report…</div>
              <div v-else-if="weeklyTeamReportError" class="dp-empty dp-empty--sm">{{ weeklyTeamReportError }}</div>
              <div v-else-if="!weeklyTeamReport" class="dp-empty dp-empty--sm">Weekly team report is not available yet.</div>
              <template v-else>
                <div class="dp-weekly-header">
                  <div>
                    <div class="dp-command-label">{{ weeklyTeamReport.week_label || 'Current Week' }}</div>
                    <div class="dp-report-headline">{{ weeklyTeamExecutive.headline || 'No daily plans were assigned this week.' }}</div>
                    <p class="dp-empty-copy">{{ weeklyTeamExecutive.summary_text || 'No player completion data is available yet.' }}</p>
                  </div>
                  <span class="dp-status" :class="statusBadgeClass(weeklyTeamReport.report_status === 'complete' ? 'complete' : weeklyTeamReport.report_status === 'partial' ? 'warning' : weeklyTeamReport.report_status === 'failed' ? 'warning' : 'muted')">
                    {{ human(weeklyTeamReport.report_status || 'empty') }}
                  </span>
                </div>

                <div class="dp-report-summary-grid">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Wins</div>
                    <ul v-if="weeklyTeamExecutive.wins?.length" class="dp-report-list">
                      <li v-for="win in weeklyTeamExecutive.wins.slice(0, 4)" :key="`win-${win}`">{{ win }}</li>
                    </ul>
                    <p v-else class="dp-command-sub">No weekly wins are available yet.</p>
                  </div>
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Concerns</div>
                    <ul v-if="weeklyTeamExecutive.concerns?.length" class="dp-report-list">
                      <li v-for="concern in weeklyTeamExecutive.concerns.slice(0, 4)" :key="`concern-${concern}`">{{ concern }}</li>
                    </ul>
                    <p v-else class="dp-command-sub">No urgent weekly blockers are surfaced.</p>
                  </div>
                  <div class="dp-weekly-panel dp-weekly-panel--highlight">
                    <div class="dp-command-label">Next Best Action</div>
                    <div class="dp-report-next-action">{{ weeklyTeamExecutive.next_best_action || 'Generate the next week plan when ready.' }}</div>
                  </div>
                </div>

                <div class="dp-completion-grid mt-3">
                  <div v-for="card in weeklyTeamReportCards" :key="`team-report-${card.label}`" class="dp-command-card">
                    <div class="dp-command-label">{{ card.label }}</div>
                    <div class="dp-command-value">{{ card.value }}</div>
                    <div class="dp-command-sub">{{ card.detail }}</div>
                  </div>
                </div>

                <div class="dp-weekly-panel mt-3">
                  <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <div class="dp-command-label">Export / Share</div>
                      <p class="dp-command-sub">Create a safe weekly summary for coaches, staff, players, or parents.</p>
                    </div>
                    <div class="dp-export-controls">
                      <label class="dp-export-field dp-export-field--wide">
                        <span>Template</span>
                        <select v-model="weeklyReportTemplateKey" class="dp-input dp-input--compact">
                          <option v-if="!weeklyReportTemplates.length" value="detailed_coach_report">Detailed Coach Report</option>
                          <option v-for="template in weeklyReportTemplates" :key="template.template_key" :value="template.template_key">{{ template.display_name }}</option>
                        </select>
                      </label>
                      <label class="dp-export-field">
                        <span>Audience</span>
                        <select v-model="weeklyReportExportAudience" class="dp-input dp-input--compact">
                          <option value="coach">Coach</option>
                          <option value="staff">Staff</option>
                          <option value="players">Players</option>
                          <option value="parents">Parents</option>
                        </select>
                      </label>
                      <label class="dp-export-field">
                        <span>Format</span>
                        <select v-model="weeklyReportExportFormat" class="dp-input dp-input--compact">
                          <option value="summary">Summary</option>
                          <option value="text">Copy Text</option>
                          <option value="html">Printable HTML</option>
                          <option value="pdf">PDF</option>
                        </select>
                      </label>
                    </div>
                  </div>
                  <div class="dp-report-template-preview">
                    <div v-if="weeklyReportTemplatesLoading" class="dp-command-sub">Loading report templates…</div>
                    <div v-else-if="weeklyReportTemplatesError" class="dp-calendar-warning">{{ weeklyReportTemplatesError }}</div>
                    <template v-else-if="selectedWeeklyReportTemplate">
                      <div class="dp-report-template-main">
                        <div>
                          <div class="dp-command-label">{{ selectedWeeklyReportTemplate.display_name }}</div>
                          <p class="dp-command-sub">{{ selectedWeeklyReportTemplate.description }}</p>
                        </div>
                        <span class="dp-status" :class="statusBadgeClass(selectedWeeklyReportTemplate.template_key === 'internal_benchmark_qa' ? 'warning' : selectedWeeklyReportTemplate.audience === 'parents' || selectedWeeklyReportTemplate.audience === 'players' ? 'info' : 'neutral')">
                          {{ human(selectedWeeklyReportTemplate.tone) }}
                        </span>
                      </div>
                      <div class="dp-command-mini">
                        <span v-for="section in weeklyReportTemplateSections.slice(0, 7)" :key="`template-section-${section}`">{{ human(section) }}</span>
                        <span v-if="weeklyReportTemplateSections.length > 7">+{{ weeklyReportTemplateSections.length - 7 }} sections</span>
                      </div>
                      <ul v-if="weeklyReportTemplateRules.length" class="dp-report-list dp-report-list--compact">
                        <li v-for="rule in weeklyReportTemplateRules.slice(0, 3)" :key="`template-rule-${rule}`">{{ rule }}</li>
                      </ul>
                    </template>
                    <div v-else class="dp-empty dp-empty--sm">No report templates available.</div>
                  </div>
                  <div v-if="weeklyReportAudienceWarning" class="dp-calendar-warning mt-3">{{ weeklyReportAudienceWarning }}</div>
                  <div class="dp-export-actions">
                    <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="Boolean(weeklyReportExportLoading)" @click="copyWeeklyReportSummary">
                      {{ weeklyReportExportLoading === 'text' ? 'Copying…' : 'Copy Summary' }}
                    </button>
                    <button class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportExportLoading)" @click="previewWeeklyReportHtml">
                      {{ weeklyReportExportLoading === 'html' ? 'Loading…' : 'Preview Printable Report' }}
                    </button>
                    <button class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportExportLoading)" @click="runWeeklyReportSelectedExport">
                      {{ weeklyReportExportLoading ? 'Exporting…' : 'Run Selected Export' }}
                    </button>
                  </div>
                  <p class="dp-command-sub mt-2">PDF export is not configured yet. Use Copy Summary or Printable HTML.</p>
                  <p v-if="weeklyReportExportMessage" class="dp-command-message">{{ weeklyReportExportMessage }}</p>

                  <div class="dp-report-delivery mt-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div class="dp-command-label">Delivery Prep</div>
                        <p class="dp-command-sub">Prepare a copy-ready message. Nothing is sent automatically.</p>
                      </div>
                      <div class="dp-export-controls">
                        <label class="dp-export-field">
                          <span>Channel</span>
                          <select v-model="weeklyReportDeliveryChannel" class="dp-input dp-input--compact">
                            <option v-for="channel in weeklyReportDeliveryChannelOptions" :key="channel.value" :value="channel.value" :disabled="!channel.supported">
                              {{ channel.label }}{{ channel.supported ? '' : ' (not configured)' }}
                            </option>
                          </select>
                        </label>
                        <label class="dp-export-field">
                          <span>Delivery Format</span>
                          <select v-model="weeklyReportDeliveryFormat" class="dp-input dp-input--compact">
                            <option v-for="option in weeklyReportDeliveryFormatOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                          </select>
                        </label>
                      </div>
                    </div>

                    <div v-if="!weeklyTeamReport" class="dp-empty dp-empty--sm mt-3">Generate a weekly report before preparing delivery.</div>
                    <template v-else>
                      <div class="dp-export-actions">
                        <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="Boolean(weeklyReportDeliveryLoading)" @click="loadWeeklyReportDeliveryPreview">
                          {{ weeklyReportDeliveryLoading === 'preview' ? 'Preparing…' : 'Preview Delivery' }}
                        </button>
                        <button class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportDeliveryLoading)" @click="loadWeeklyReportDeliveryReview">
                          {{ weeklyReportDeliveryLoading === 'review' ? 'Reviewing…' : 'Review Draft' }}
                        </button>
                        <button class="dp-btn dp-btn--small" :disabled="!weeklyReportDeliveryPreview?.subject && !weeklyReportDraftSubject" @click="copyWeeklyReportDeliverySubject">Copy Subject</button>
                        <button class="dp-btn dp-btn--small" :disabled="!weeklyReportDeliveryPreview?.message_text && !weeklyReportDraftMessage" @click="copyWeeklyReportDeliveryMessage">Copy Message</button>
                        <button v-if="weeklyReportDeliveryDraftSupported" class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportDeliveryLoading)" @click="createWeeklyReportDeliveryDraft">
                          {{ weeklyReportDeliveryLoading === 'draft' ? 'Preparing Draft…' : 'Create Draft' }}
                        </button>
                        <span v-else class="dp-command-sub">Draft delivery is not configured yet. Use copy mode.</span>
                      </div>

                      <div v-if="weeklyReportDeliveryPreview" class="dp-delivery-preview">
                        <div class="dp-completion-grid">
                          <div class="dp-command-card">
                            <div class="dp-command-label">Recipients</div>
                            <div class="dp-command-value">{{ weeklyReportDeliverySummary.total_recipients || 0 }}</div>
                            <div class="dp-command-sub">{{ weeklyReportDeliverySummary.safe_recipients || 0 }} safe · {{ weeklyReportDeliverySummary.missing_contact_count || 0 }} missing contact</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Unsafe</div>
                            <div class="dp-command-value">{{ weeklyReportDeliverySummary.unsafe_recipient_count || 0 }}</div>
                            <div class="dp-command-sub">Blocked or missing contact</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Status</div>
                            <div class="dp-command-value">{{ human(weeklyReportDeliveryPreview.delivery_status || 'prepared') }}</div>
                            <div class="dp-command-sub">Coach approval required</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Channel</div>
                            <div class="dp-command-value">{{ human(weeklyReportDeliveryPreview.channel || weeklyReportDeliveryChannel) }}</div>
                            <div class="dp-command-sub">{{ weeklyReportDeliveryPreview.format?.toUpperCase?.() || weeklyReportDeliveryFormat.toUpperCase() }}</div>
                          </div>
                        </div>

                        <div v-if="weeklyReportDeliveryWarnings.length" class="dp-delivery-warnings">
                          <div v-for="warning in weeklyReportDeliveryWarnings" :key="`delivery-warning-${warning}`" class="dp-calendar-warning">{{ warning }}</div>
                        </div>

                        <div class="dp-delivery-message-grid">
                          <div class="dp-weekly-panel">
                            <div class="dp-command-label">Subject</div>
                            <div class="dp-delivery-subject">{{ weeklyReportDeliveryPreview.subject || 'No subject generated yet.' }}</div>
                          </div>
                          <div class="dp-weekly-panel">
                            <div class="dp-command-label">Recipient Preview</div>
                            <div v-if="weeklyReportDeliveryRecipients.length" class="dp-delivery-recipient-list">
                              <div v-for="recipient in weeklyReportDeliveryRecipients.slice(0, 8)" :key="`${recipient.recipient_type}-${recipient.recipient_id || recipient.email || recipient.name}`" class="dp-delivery-recipient">
                                <div>
                                  <strong>{{ recipient.name || recipient.email || human(recipient.recipient_type) }}</strong>
                                  <span>{{ human(recipient.recipient_type) }} · {{ recipient.email || 'missing contact' }}</span>
                                </div>
                                <span class="dp-status" :class="statusBadgeClass(recipient.safe_to_send ? 'good' : 'warning')">{{ recipient.safe_to_send ? 'Safe' : 'Review' }}</span>
                              </div>
                              <div v-if="weeklyReportDeliveryRecipients.length > 8" class="dp-command-sub">+{{ weeklyReportDeliveryRecipients.length - 8 }} more recipients</div>
                            </div>
                            <div v-else class="dp-empty dp-empty--sm">No recipients found for this audience.</div>
                          </div>
                        </div>

                        <div class="dp-weekly-panel mt-3">
                          <div class="dp-command-label">Message Preview</div>
                          <pre class="dp-delivery-message">{{ weeklyReportDeliveryPreview.message_text || 'No message returned for this delivery request.' }}</pre>
                        </div>
                      </div>

                      <div class="dp-delivery-review">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <div class="dp-command-label">Delivery Draft Review</div>
                            <p class="dp-command-sub">Review, edit, and confirm before sending. FMTRX will not send unless this draft passes every safety check.</p>
                          </div>
                          <span class="dp-status" :class="statusBadgeClass(weeklyReportDeliveryReview?.can_send ? 'good' : 'warning')">
                            {{ weeklyReportDeliveryReview?.can_send ? 'Send Ready' : 'Needs Review' }}
                          </span>
                        </div>

                        <div v-if="weeklyReportDeliveryReview" class="dp-delivery-review-body">
                          <div class="dp-completion-grid">
                            <div class="dp-command-card">
                              <div class="dp-command-label">Recipients</div>
                              <div class="dp-command-value">{{ weeklyReportReviewSummary.total_recipients || 0 }}</div>
                              <div class="dp-command-sub">{{ weeklyReportReviewSummary.safe_recipients || 0 }} safe · {{ weeklyReportReviewSummary.missing_contact_count || 0 }} missing contact</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Channel</div>
                              <div class="dp-command-value">{{ human(weeklyReportDeliveryReview.channel || weeklyReportDeliveryChannel) }}</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliveryReview.format?.toUpperCase?.() || weeklyReportDeliveryFormat.toUpperCase() }}</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Status</div>
                              <div class="dp-command-value">{{ human(weeklyReportDeliveryReview.delivery_status || 'review_ready') }}</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliveryReview.requires_confirmation ? 'Confirmation required' : 'No confirmation required' }}</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Unsafe</div>
                              <div class="dp-command-value">{{ weeklyReportReviewSummary.unsafe_recipient_count || 0 }}</div>
                              <div class="dp-command-sub">Blocked or missing contact</div>
                            </div>
                          </div>

                          <div v-if="weeklyReportReviewWarnings.length || weeklyReportReviewBlockers.length" class="dp-delivery-warnings">
                            <div v-for="blocker in weeklyReportReviewBlockers" :key="`review-blocker-${blocker}`" class="dp-calendar-warning dp-calendar-warning--danger">{{ blocker }}</div>
                            <div v-for="warning in weeklyReportReviewWarnings" :key="`review-warning-${warning}`" class="dp-calendar-warning">{{ warning }}</div>
                          </div>

                          <div class="dp-delivery-editor">
                            <label class="dp-export-field dp-export-field--full">
                              <span>Subject</span>
                              <input v-model="weeklyReportDraftSubject" class="dp-input" type="text" placeholder="Delivery subject" />
                            </label>
                            <label class="dp-export-field dp-export-field--full">
                              <span>Message</span>
                              <textarea v-model="weeklyReportDraftMessage" class="dp-input dp-delivery-textarea" rows="10" placeholder="Delivery message"></textarea>
                            </label>
                          </div>

                          <div class="dp-delivery-message-grid">
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Recipient Check</div>
                              <div v-if="weeklyReportReviewRecipients.length" class="dp-delivery-recipient-list">
                                <div v-for="recipient in weeklyReportReviewRecipients.slice(0, 8)" :key="`review-${recipient.recipient_type}-${recipient.recipient_id || recipient.email || recipient.name}`" class="dp-delivery-recipient">
                                  <div>
                                    <strong>{{ recipient.name || recipient.email || human(recipient.recipient_type) }}</strong>
                                    <span>{{ human(recipient.recipient_type) }} · {{ recipient.email || 'missing contact' }}</span>
                                  </div>
                                  <span class="dp-status" :class="statusBadgeClass(recipient.safe_to_send ? 'good' : 'warning')">{{ recipient.safe_to_send ? 'Safe' : 'Review' }}</span>
                                </div>
                                <div v-if="weeklyReportReviewRecipients.length > 8" class="dp-command-sub">+{{ weeklyReportReviewRecipients.length - 8 }} more recipients</div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm">No recipients found for this audience.</div>
                            </div>
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Send Readiness</div>
                              <p class="dp-command-sub" v-if="weeklyReportDeliveryReview.copy_only || weeklyReportDeliveryReview.delivery_status === 'copy_only'">Copy-only delivery. Nothing will be sent by FMTRX.</p>
                              <p class="dp-command-sub" v-else-if="weeklyReportDeliveryReview.preview?.channel_supported === false">Sending is not configured for this channel. Copy the message instead.</p>
                              <p class="dp-command-sub" v-else-if="weeklyReportReviewBlockers.length">Resolve every blocker before sending.</p>
                              <p class="dp-command-sub" v-else>Draft passed current safety checks.</p>

                              <label class="dp-confirm-row">
                                <input v-model="weeklyReportConfirmSend" type="checkbox" :disabled="!weeklyReportDeliveryReview?.can_send" />
                                <span>I reviewed this report and confirm it is safe to send to the selected audience.</span>
                              </label>

                              <div class="dp-export-actions">
                                <button class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportDeliveryLoading)" @click="recheckWeeklyReportDeliveryDraft">
                                  {{ weeklyReportDeliveryLoading === 'recheck' ? 'Checking…' : 'Recheck Draft' }}
                                </button>
                                <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="!weeklyReportCanSend || Boolean(weeklyReportDeliveryLoading)" @click="sendWeeklyReportDeliveryDraft">
                                  {{ weeklyReportDeliveryLoading === 'send' ? 'Sending…' : 'Send Confirmed Draft' }}
                                </button>
                              </div>
                            </div>
                          </div>

                          <div v-if="weeklyReportSendResult" class="dp-send-result">
                            <div class="dp-command-label">Send Result</div>
                            <div class="dp-command-value">{{ human(weeklyReportSendResult.send_status || 'checked') }}</div>
                            <p class="dp-command-sub">{{ weeklyReportSendResult.sent_count || 0 }} sent · {{ weeklyReportSendResult.skipped_count || 0 }} skipped · {{ weeklyReportSendResult.failed_count || 0 }} failed</p>
                            <ul v-if="weeklyReportSendResult.warnings?.length" class="dp-report-list dp-report-list--compact">
                              <li v-for="warning in weeklyReportSendResult.warnings" :key="`send-warning-${warning}`">{{ warning }}</li>
                            </ul>
                          </div>
                        </div>
                        <div v-else class="dp-empty dp-empty--sm mt-3">Prepare a delivery draft review before sending.</div>
                      </div>

                      <div class="dp-delivery-history">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <div class="dp-command-label">Delivery History</div>
                            <p class="dp-command-sub">Recent weekly report delivery attempts, copy actions, blocked sends, and unsupported channels.</p>
                          </div>
                          <button class="dp-btn dp-btn--small" :disabled="weeklyReportDeliveryHistoryLoading || weeklyReportDeliveryAnalyticsLoading" @click="refreshWeeklyReportDeliveryInsights">
                            {{ weeklyReportDeliveryHistoryLoading || weeklyReportDeliveryAnalyticsLoading ? 'Loading…' : 'Refresh History' }}
                          </button>
                        </div>

                        <div class="dp-completion-grid mt-3">
                          <div class="dp-command-card">
                            <div class="dp-command-label">Total</div>
                            <div class="dp-command-value">{{ weeklyReportDeliveryHistorySummary.total_deliveries || 0 }}</div>
                            <div class="dp-command-sub">Last 30 days</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Sent</div>
                            <div class="dp-command-value">{{ weeklyReportDeliveryHistorySummary.sent_count || 0 }}</div>
                            <div class="dp-command-sub">{{ weeklyReportDeliveryHistorySummary.partial_count || 0 }} partial</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Copy Only</div>
                            <div class="dp-command-value">{{ weeklyReportDeliveryHistorySummary.copy_only_count || 0 }}</div>
                            <div class="dp-command-sub">Manual share</div>
                          </div>
                          <div class="dp-command-card">
                            <div class="dp-command-label">Blocked</div>
                            <div class="dp-command-value">{{ weeklyReportDeliveryHistorySummary.blocked_count || 0 }}</div>
                            <div class="dp-command-sub">{{ weeklyReportDeliveryHistorySummary.unsupported_count || 0 }} unsupported</div>
                          </div>
                        </div>

                        <div class="dp-delivery-analytics">
                          <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                              <div class="dp-command-label">Delivery Analytics</div>
                              <p class="dp-command-sub">Read-only delivery health, template usage, audience usage, and recommended next actions.</p>
                            </div>
                            <span class="dp-status" :class="statusBadgeClass(weeklyReportDeliveryHealth.privacy_block_count ? 'warning' : 'info')">
                              {{ weeklyReportDeliveryAnalytics ? 'Last 30 Days' : 'No Data Yet' }}
                            </span>
                          </div>

                          <div v-if="weeklyReportDeliveryAnalytics" class="dp-completion-grid mt-3">
                            <div class="dp-command-card">
                              <div class="dp-command-label">Success Rate</div>
                              <div class="dp-command-value">{{ oneDecimal(weeklyReportDeliveryHealth.delivery_success_rate) }}%</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliveryAnalyticsSummary.sent_or_partial_count || 0 }} sent or partial</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Recipients</div>
                              <div class="dp-command-value">{{ weeklyReportDeliveryAnalyticsSummary.recipients_targeted || 0 }}</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliveryAnalyticsSummary.recipients_sent || 0 }} confirmed sent</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Privacy Blocks</div>
                              <div class="dp-command-value">{{ weeklyReportDeliveryHealth.privacy_block_count || 0 }}</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliverySafety.private_note_leak_prevented_count || 0 }} protected</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Contact Warnings</div>
                              <div class="dp-command-value">{{ weeklyReportDeliveryHealth.missing_contact_warning_count || 0 }}</div>
                              <div class="dp-command-sub">{{ weeklyReportDeliveryHealth.unsafe_recipient_count || 0 }} unsafe recipients</div>
                            </div>
                          </div>

                          <div v-if="weeklyReportDeliveryAnalytics" class="dp-delivery-analytics-grid">
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Template Usage</div>
                              <div v-if="weeklyReportDeliveryTemplateUsage.length" class="dp-delivery-analytics-list">
                                <div v-for="row in weeklyReportDeliveryTemplateUsage.slice(0, 5)" :key="`template-analytics-${row.template_key}`" class="dp-delivery-analytics-row">
                                  <span>{{ row.display_name || human(row.template_key) }}</span>
                                  <strong>{{ row.count || 0 }} · {{ oneDecimal(row.percent) }}%</strong>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No template usage yet.</div>
                            </div>
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Audience Usage</div>
                              <div v-if="weeklyReportDeliveryAudienceUsage.length" class="dp-delivery-analytics-list">
                                <div v-for="row in weeklyReportDeliveryAudienceUsage.slice(0, 5)" :key="`audience-analytics-${row.audience}`" class="dp-delivery-analytics-row">
                                  <span>{{ row.display_name || human(row.audience) }}</span>
                                  <strong>{{ row.count || 0 }} · {{ row.recipient_count || 0 }} recipients</strong>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No audience usage yet.</div>
                            </div>
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Channel Usage</div>
                              <div v-if="weeklyReportDeliveryChannelUsage.length" class="dp-delivery-analytics-list">
                                <div v-for="row in weeklyReportDeliveryChannelUsage.slice(0, 5)" :key="`channel-analytics-${row.channel}`" class="dp-delivery-analytics-row">
                                  <span>{{ row.display_name || human(row.channel) }}</span>
                                  <strong>{{ row.count || 0 }} · {{ oneDecimal(row.percent) }}%</strong>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No channel usage yet.</div>
                            </div>
                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Recommended Actions</div>
                              <div v-if="weeklyReportDeliveryActions.length" class="dp-delivery-analytics-list">
                                <div v-for="action in weeklyReportDeliveryActions.slice(0, 4)" :key="`delivery-action-${action.id}`" class="dp-delivery-analytics-action">
                                  <span class="dp-status" :class="statusBadgeClass(action.priority === 'high' ? 'warning' : action.priority === 'medium' ? 'info' : 'muted')">{{ human(action.priority) }}</span>
                                  <strong>{{ action.title }}</strong>
                                  <small>{{ action.action }}</small>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No delivery actions are recommended yet.</div>
                            </div>
                          </div>
                          <div v-else class="dp-empty dp-empty--sm mt-3">
                            Delivery analytics will appear after a weekly report is prepared, copied, or sent.
                          </div>
                          <p v-if="weeklyReportDeliveryAnalyticsMessage" class="dp-command-message">{{ weeklyReportDeliveryAnalyticsMessage }}</p>
                        </div>

                        <div class="dp-communication-rhythm">
                          <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                              <div class="dp-command-label">Communication Rhythm</div>
                              <p class="dp-command-sub">Weekly update consistency by audience, template, and delivery status.</p>
                            </div>
                            <span class="dp-status" :class="statusBadgeClass(communicationStatusTone(communicationRhythmScore.label))">
                              {{ communicationRhythm ? human(communicationRhythmScore.label) : 'No Data Yet' }}
                            </span>
                          </div>

                          <div v-if="communicationRhythm" class="dp-completion-grid mt-3">
                            <div class="dp-command-card">
                              <div class="dp-command-label">Rhythm Score</div>
                              <div class="dp-command-value">{{ oneDecimal(communicationRhythmScore.score_0_100) }}</div>
                              <div class="dp-command-sub">{{ communicationRhythmScore.weeks_with_any_report || 0 }} of {{ communicationRhythm.weeks_analyzed || 0 }} weeks had updates</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Consistency</div>
                              <div class="dp-command-value">{{ oneDecimal(communicationRhythmScore.consistency_percentage) }}%</div>
                              <div class="dp-command-sub">{{ communicationRhythmStreaks.current_any_report_streak || 0 }} week current streak</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Parent Updates</div>
                              <div class="dp-command-value">{{ communicationRhythmScore.weeks_with_parent_update || 0 }}/{{ communicationRhythm.weeks_analyzed || 0 }}</div>
                              <div class="dp-command-sub">{{ oneDecimal(communicationRhythmScore.parent_update_percentage) }}% of weeks</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Delivery Health</div>
                              <div class="dp-command-value">{{ communicationRhythmHealth.sent_count || 0 }}</div>
                              <div class="dp-command-sub">{{ communicationRhythmHealth.copy_only_count || 0 }} copy-only · {{ communicationRhythmHealth.blocked_count || 0 }} blocked</div>
                            </div>
                          </div>

                          <div v-if="communicationRhythm" class="dp-communication-grid">
                            <div class="dp-weekly-panel dp-communication-panel--wide">
                              <div class="dp-command-label">Weekly Rhythm Timeline</div>
                              <div v-if="communicationRhythmRows.length" class="dp-rhythm-timeline">
                                <div v-for="row in communicationRhythmRows" :key="`rhythm-week-${row.week_start_date}`" class="dp-rhythm-row">
                                  <div>
                                    <strong>{{ row.week_label }}</strong>
                                    <span>{{ row.sent_count || 0 }} sent · {{ row.copy_only_count || 0 }} copy-only · {{ row.blocked_count || 0 }} blocked</span>
                                    <small v-if="row.recommended_action">{{ row.recommended_action }}</small>
                                  </div>
                                  <div class="dp-rhythm-pills">
                                    <span class="dp-status" :class="statusBadgeClass(communicationStatusTone(row.status_label))">{{ human(row.status_label) }}</span>
                                    <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': row.has_parent_update }">Parents</span>
                                    <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': row.has_staff_report }">Staff</span>
                                    <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': row.has_player_summary }">Players</span>
                                  </div>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No reports found for this date range.</div>
                            </div>

                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Audience Summary</div>
                              <div class="dp-delivery-analytics-list">
                                <div v-for="row in communicationAudienceRows" :key="`rhythm-audience-${row.key}`" class="dp-delivery-analytics-row">
                                  <span>{{ row.label }}<small> · {{ human(row.data.status) }}</small></span>
                                  <strong>{{ row.data.weeks_reached || 0 }} weeks</strong>
                                </div>
                              </div>
                            </div>

                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Template Usage</div>
                              <div v-if="communicationRhythmTemplateSummary.length" class="dp-delivery-analytics-list">
                                <div v-for="row in communicationRhythmTemplateSummary.slice(0, 5)" :key="`rhythm-template-${row.template_key}`" class="dp-delivery-analytics-row">
                                  <span>{{ row.display_name || human(row.template_key) }}<small> · {{ row.weeks_used || 0 }} weeks</small></span>
                                  <strong>{{ row.total_uses || 0 }} uses</strong>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No template usage yet.</div>
                            </div>

                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Missed Weeks</div>
                              <div v-if="communicationRhythmMissedWeeks.length" class="dp-delivery-analytics-list">
                                <div v-for="row in communicationRhythmMissedWeeks.slice(0, 5)" :key="`missed-rhythm-${row.week_start_date}`" class="dp-delivery-analytics-action">
                                  <strong>{{ row.week_label }}</strong>
                                  <small>Missing: {{ (row.missed_audiences || []).map(human).join(', ') || 'Weekly Update' }}</small>
                                  <small>{{ row.recommended_action }}</small>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No missed weeks in this window.</div>
                            </div>

                            <div class="dp-weekly-panel">
                              <div class="dp-command-label">Recommended Actions</div>
                              <div v-if="communicationRhythmActions.length" class="dp-delivery-analytics-list">
                                <div v-for="action in communicationRhythmActions.slice(0, 5)" :key="`rhythm-action-${action.id}`" class="dp-delivery-analytics-action">
                                  <span class="dp-status" :class="statusBadgeClass(action.priority === 'high' || action.priority === 'critical' ? 'warning' : action.priority === 'medium' ? 'info' : 'muted')">{{ human(action.priority) }}</span>
                                  <strong>{{ action.title }}</strong>
                                  <small>{{ action.action }}</small>
                                  <button v-if="action.template_key" class="dp-link mt-1" @click="applyCommunicationRhythmAction(action)">Use This Template</button>
                                </div>
                              </div>
                              <div v-else class="dp-empty dp-empty--sm mt-2">No communication actions are recommended yet.</div>
                            </div>
                          </div>
                          <div v-else class="dp-empty dp-empty--sm mt-3">
                            No communication history yet. Create and share a weekly report to start building communication rhythm.
                          </div>
                          <p v-if="communicationRhythmMessage" class="dp-command-message">{{ communicationRhythmMessage }}</p>
                        </div>

                        <div class="dp-season-archive">
                          <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                              <div class="dp-command-label">Season Development Archive</div>
                              <p class="dp-command-sub">Season-long timeline of planner execution, benchmark progress, trusted data, and communication rhythm.</p>
                            </div>
                            <div class="dp-season-archive-actions">
                              <span class="dp-status" :class="statusBadgeClass(communicationStatusTone(seasonDevelopmentArchive?.archive_status))">
                                {{ seasonDevelopmentArchive ? human(seasonDevelopmentArchive.archive_status) : 'No Data Yet' }}
                              </span>
                              <button class="dp-link" :disabled="seasonDevelopmentArchiveLoading" @click="loadSeasonDevelopmentArchive">
                                {{ seasonDevelopmentArchiveLoading ? 'Refreshing…' : 'Refresh Archive' }}
                              </button>
                            </div>
                          </div>

                          <div v-if="seasonDevelopmentArchive" class="dp-season-archive-body">
                            <div class="dp-weekly-panel dp-season-summary-panel">
                              <div class="dp-command-label">Season Summary</div>
                              <div class="dp-report-headline">{{ seasonArchiveSummary.headline || 'No season archive data found yet.' }}</div>
                              <p class="dp-command-sub mt-1">{{ seasonArchiveSummary.summary_text || 'Assign plans and create weekly reports to build the season archive.' }}</p>
                              <p v-if="seasonArchiveSummary.season_story" class="dp-season-story">{{ seasonArchiveSummary.season_story }}</p>
                              <div class="dp-completion-grid mt-3">
                                <div v-for="card in seasonArchiveCards" :key="`season-card-${card.label}`" class="dp-command-card">
                                  <div class="dp-command-label">{{ card.label }}</div>
                                  <div class="dp-command-value">{{ card.value }}</div>
                                  <div class="dp-command-sub">{{ card.detail }}</div>
                                </div>
                              </div>
                            </div>

                            <div class="dp-weekly-panel dp-season-summary-panel">
                              <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                  <div class="dp-command-label">Export Season Review Packet</div>
                                  <p class="dp-command-sub">Create a safe season packet for coaches, staff, directors, players, or parents.</p>
                                </div>
                                <div class="dp-export-controls">
                                  <label class="dp-export-field">
                                    <span>Audience</span>
                                    <select v-model="seasonArchiveExportAudience" class="dp-input dp-input--compact">
                                      <option v-for="option in seasonArchiveAudienceOptions" :key="`season-audience-${option.value}`" :value="option.value">{{ option.label }}</option>
                                    </select>
                                  </label>
                                  <label class="dp-export-field">
                                    <span>Format</span>
                                    <select v-model="seasonArchiveExportFormat" class="dp-input dp-input--compact">
                                      <option v-for="option in seasonArchiveFormatOptions" :key="`season-format-${option.value}`" :value="option.value">{{ option.label }}</option>
                                    </select>
                                  </label>
                                </div>
                              </div>

                              <div class="dp-season-export-toggles">
                                <label><input v-model="seasonArchiveIncludeTimeline" type="checkbox" /> Weekly Timeline</label>
                                <label><input v-model="seasonArchiveIncludeBenchmark" type="checkbox" /> Benchmark Progress</label>
                                <label><input v-model="seasonArchiveIncludePlanner" type="checkbox" /> Planner Progress</label>
                                <label><input v-model="seasonArchiveIncludeCommunication" type="checkbox" /> Communication Summary</label>
                                <label v-if="!seasonArchivePublicAudience"><input v-model="seasonArchiveIncludePlayerRows" type="checkbox" /> Player Rows</label>
                                <label><input v-model="seasonArchiveIncludeNextSteps" type="checkbox" /> Next Steps</label>
                              </div>

                              <div v-if="seasonArchiveAudienceWarning" class="dp-calendar-warning mt-3">{{ seasonArchiveAudienceWarning }}</div>
                              <div class="dp-export-actions">
                                <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="Boolean(seasonArchiveExportLoading)" @click="copySeasonArchiveText">
                                  {{ seasonArchiveExportLoading === 'text' ? 'Copying…' : 'Copy Text' }}
                                </button>
                                <button class="dp-btn dp-btn--small" :disabled="Boolean(seasonArchiveExportLoading)" @click="previewSeasonArchiveHtml">
                                  {{ seasonArchiveExportLoading === 'html' ? 'Loading…' : 'Preview Printable HTML' }}
                                </button>
                                <button class="dp-btn dp-btn--small" :disabled="Boolean(seasonArchiveExportLoading)" @click="runSeasonArchiveSelectedExport">
                                  {{ seasonArchiveExportLoading ? 'Exporting…' : 'Run Selected Export' }}
                                </button>
                              </div>
                              <p class="dp-command-sub mt-2">PDF export is not configured yet. Use Copy Text or Printable HTML.</p>
                              <p v-if="seasonArchiveExportMessage" class="dp-command-message">{{ seasonArchiveExportMessage }}</p>
                            </div>

                            <div class="dp-season-grid">
                              <div class="dp-weekly-panel dp-season-panel--wide">
                                <div class="dp-command-label">Season Timeline</div>
                                <div v-if="seasonArchiveTimeline.length" class="dp-rhythm-timeline">
                                  <div v-for="row in seasonArchiveTimeline" :key="`season-week-${row.week_start_date}`" class="dp-rhythm-row">
                                    <div>
                                      <strong>{{ row.week_label }}</strong>
                                      <span>{{ row.headline }}</span>
                                      <small v-if="row.primary_focus">Focus: {{ row.primary_focus }}</small>
                                      <small v-if="seasonArchiveMetricNames(row.top_remaining_gaps)">Remaining gaps: {{ seasonArchiveMetricNames(row.top_remaining_gaps) }}</small>
                                    </div>
                                    <div class="dp-rhythm-pills">
                                      <span class="dp-status" :class="statusBadgeClass(communicationStatusTone(row.status_label))">{{ human(row.status_label) }}</span>
                                      <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': (row.plans_published || 0) > 0 }">{{ row.plans_published || 0 }} plans</span>
                                      <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': (row.benchmark_values_approved || 0) > 0 }">{{ row.benchmark_values_approved || 0 }} approved</span>
                                      <span class="dp-rhythm-pill" :class="{ 'dp-rhythm-pill--on': (row.reports_shared || 0) > 0 }">{{ row.reports_shared || 0 }} shared</span>
                                    </div>
                                  </div>
                                </div>
                                <div v-else class="dp-empty dp-empty--sm mt-2">No season archive data found yet.</div>
                              </div>

                              <div class="dp-weekly-panel">
                                <div class="dp-command-label">Benchmark Progress</div>
                                <div class="dp-delivery-analytics-list">
                                  <div class="dp-delivery-analytics-row">
                                    <span>Current Confidence</span>
                                    <strong>{{ human(seasonArchiveBenchmark.current_benchmark_confidence) }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Trusted Values Added</span>
                                    <strong>{{ seasonArchiveBenchmark.trusted_values_added || 0 }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Players With Trusted Data</span>
                                    <strong>{{ (seasonArchiveBenchmark.players_with_new_trusted_data || []).length || 0 }}</strong>
                                  </div>
                                </div>
                                <div v-if="(seasonArchiveBenchmark.top_collected_metrics || []).length" class="dp-report-metrics">
                                  <span v-for="metric in seasonArchiveBenchmark.top_collected_metrics.slice(0, 5)" :key="`season-collected-${metric.metric_key}`">
                                    {{ metric.display_name || human(metric.metric_key) }}
                                  </span>
                                </div>
                                <div v-else class="dp-empty dp-empty--sm mt-2">No benchmark progress recorded in this date range.</div>
                              </div>

                              <div class="dp-weekly-panel">
                                <div class="dp-command-label">Planner Progress</div>
                                <div class="dp-delivery-analytics-list">
                                  <div class="dp-delivery-analytics-row">
                                    <span>Plans Created / Published</span>
                                    <strong>{{ seasonArchivePlanner.plans_created || 0 }} / {{ seasonArchivePlanner.plans_published || 0 }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Completion</span>
                                    <strong>{{ oneDecimal(seasonArchivePlanner.completion_percentage) }}%</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Players Needing Follow-Up</span>
                                    <strong>{{ seasonArchivePlanner.players_needing_follow_up_count || 0 }}</strong>
                                  </div>
                                </div>
                                <div v-if="(seasonArchivePlanner.most_common_plan_focuses || []).length" class="dp-delivery-analytics-list">
                                  <div v-for="focus in seasonArchivePlanner.most_common_plan_focuses.slice(0, 4)" :key="`season-focus-${focus.focus}`" class="dp-delivery-analytics-action">
                                    <strong>{{ focus.focus }}</strong>
                                    <small>{{ focus.week_count || 0 }} week(s)</small>
                                  </div>
                                </div>
                              </div>

                              <div class="dp-weekly-panel">
                                <div class="dp-command-label">Communication Summary</div>
                                <div class="dp-delivery-analytics-list">
                                  <div class="dp-delivery-analytics-row">
                                    <span>Parent Updates</span>
                                    <strong>{{ seasonArchiveCommunication.parent_updates || 0 }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Staff Reports</span>
                                    <strong>{{ seasonArchiveCommunication.staff_reports || 0 }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Copy-Only / Blocked</span>
                                    <strong>{{ seasonArchiveCommunication.copy_only_count || 0 }} / {{ seasonArchiveCommunication.blocked_count || 0 }}</strong>
                                  </div>
                                  <div class="dp-delivery-analytics-row">
                                    <span>Rhythm Score</span>
                                    <strong>{{ seasonArchiveCommunication.communication_rhythm_score == null ? '—' : oneDecimal(seasonArchiveCommunication.communication_rhythm_score) }}</strong>
                                  </div>
                                </div>
                                <div v-if="!(seasonArchiveCommunication.reports_created || 0)" class="dp-empty dp-empty--sm mt-2">No communication history recorded in this date range.</div>
                              </div>

                              <div class="dp-weekly-panel">
                                <div class="dp-command-label">Season Highlights</div>
                                <ul v-if="seasonArchiveHighlights.length" class="dp-report-list">
                                  <li v-for="item in seasonArchiveHighlights.slice(0, 5)" :key="`season-highlight-${item}`">{{ item }}</li>
                                </ul>
                                <div v-else class="dp-empty dp-empty--sm mt-2">No season highlights are available yet.</div>
                              </div>

                              <div class="dp-weekly-panel">
                                <div class="dp-command-label">Season Concerns</div>
                                <ul v-if="seasonArchiveConcerns.length" class="dp-report-list">
                                  <li v-for="item in seasonArchiveConcerns.slice(0, 5)" :key="`season-concern-${item}`">{{ item }}</li>
                                </ul>
                                <div v-else class="dp-empty dp-empty--sm mt-2">No urgent season concerns are surfaced.</div>
                              </div>

                              <div class="dp-weekly-panel dp-season-panel--wide">
                                <div class="dp-command-label">Player Development Summary</div>
                                <div v-if="seasonArchivePlayers.length" class="dp-report-player-list">
                                  <div v-for="player in seasonArchivePlayers.slice(0, 10)" :key="`season-player-${player.player_id}`" class="dp-report-player-row">
                                    <div>
                                      <strong>{{ player.player_name }}</strong>
                                      <span>{{ oneDecimal(player.completion_percentage) }}% completion · {{ player.benchmark_values_approved || 0 }} approved benchmark values</span>
                                      <small v-if="player.next_recommended_action">{{ player.next_recommended_action }}</small>
                                    </div>
                                    <div class="dp-report-metrics" v-if="(player.trusted_metrics_added || []).length">
                                      <span v-for="metric in player.trusted_metrics_added.slice(0, 4)" :key="`season-player-metric-${player.player_id}-${metric}`">{{ metricLabels[metric] || human(metric) }}</span>
                                    </div>
                                  </div>
                                </div>
                                <div v-else class="dp-empty dp-empty--sm mt-2">Assign player plans and collect benchmark values to build player development summaries.</div>
                              </div>

                              <div class="dp-weekly-panel dp-season-panel--wide">
                                <div class="dp-command-label">Recommended Next Steps</div>
                                <div v-if="seasonArchiveNextSteps.length" class="dp-delivery-analytics-list">
                                  <div v-for="step in seasonArchiveNextSteps.slice(0, 6)" :key="`season-step-${step}`" class="dp-delivery-analytics-action">
                                    <strong>{{ step }}</strong>
                                  </div>
                                </div>
                                <div v-else class="dp-empty dp-empty--sm mt-2">No season next steps are available yet.</div>
                              </div>
                            </div>
                          </div>

                          <div v-else class="dp-empty dp-empty--sm mt-3">
                            Assign plans and create weekly reports to build the season archive.
                          </div>
                          <p v-if="seasonDevelopmentArchiveMessage" class="dp-command-message">{{ seasonDevelopmentArchiveMessage }}</p>
                        </div>

                        <div v-if="weeklyReportDeliveries.length" class="dp-delivery-history-list">
                          <button
                            v-for="delivery in weeklyReportDeliveries.slice(0, 8)"
                            :key="delivery.delivery_id"
                            type="button"
                            class="dp-delivery-history-row"
                            @click="openWeeklyReportDeliveryDetail(delivery)"
                          >
                            <div>
                              <strong>{{ delivery.subject || human(delivery.template_key || 'Weekly Report') }}</strong>
                              <span>Audience: {{ human(delivery.audience) }} · Channel: {{ human(delivery.channel) }} · Recipients: {{ delivery.recipient_summary?.total_recipients || 0 }}</span>
                              <small v-if="delivery.send_blockers?.length || delivery.delivery_warnings?.length || delivery.privacy_warnings?.length">
                                {{ delivery.send_blockers?.[0] || delivery.delivery_warnings?.[0] || delivery.privacy_warnings?.[0] }}
                              </small>
                            </div>
                            <div class="dp-delivery-history-meta">
                              <span class="dp-status" :class="statusBadgeClass(deliveryStatusTone(delivery.delivery_status))">{{ human(delivery.delivery_status) }}</span>
                              <small>{{ delivery.sent_at || delivery.copied_at || delivery.blocked_at || delivery.created_at || '-' }}</small>
                            </div>
                          </button>
                        </div>
                        <div v-else class="dp-empty dp-empty--sm mt-3">
                          No weekly report delivery history yet. Delivery history will appear after a report is prepared or sent.
                        </div>
                        <p v-if="weeklyReportDeliveryHistoryMessage" class="dp-command-message">{{ weeklyReportDeliveryHistoryMessage }}</p>

                        <div v-if="selectedWeeklyReportDelivery" class="dp-delivery-detail">
                          <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                              <div class="dp-command-label">Delivery Detail</div>
                              <div class="dp-delivery-subject">{{ selectedWeeklyReportDelivery.subject || 'Weekly report delivery' }}</div>
                              <p class="dp-command-sub">
                                {{ human(selectedWeeklyReportDelivery.template_key) }} · {{ human(selectedWeeklyReportDelivery.audience) }} · {{ human(selectedWeeklyReportDelivery.channel) }}
                              </p>
                            </div>
                            <button class="dp-btn dp-btn--small" @click="selectedWeeklyReportDelivery = null">Close</button>
                          </div>
                          <div class="dp-completion-grid mt-3">
                            <div class="dp-command-card">
                              <div class="dp-command-label">Status</div>
                              <div class="dp-command-value">{{ human(selectedWeeklyReportDelivery.delivery_status) }}</div>
                              <div class="dp-command-sub">{{ selectedWeeklyReportDelivery.message || 'Recorded delivery attempt' }}</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Recipients</div>
                              <div class="dp-command-value">{{ selectedWeeklyReportDelivery.recipient_summary?.total_recipients || 0 }}</div>
                              <div class="dp-command-sub">{{ selectedWeeklyReportDelivery.recipient_summary?.safe_recipients || 0 }} safe · {{ selectedWeeklyReportDelivery.recipient_summary?.missing_contact_count || 0 }} missing</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Sent By</div>
                              <div class="dp-command-value">{{ selectedWeeklyReportDelivery.sent_by_name || selectedWeeklyReportDelivery.created_by_name || '-' }}</div>
                              <div class="dp-command-sub">{{ selectedWeeklyReportDelivery.sent_at || selectedWeeklyReportDelivery.copied_at || selectedWeeklyReportDelivery.created_at || '-' }}</div>
                            </div>
                            <div class="dp-command-card">
                              <div class="dp-command-label">Format</div>
                              <div class="dp-command-value">{{ selectedWeeklyReportDelivery.format?.toUpperCase?.() || '-' }}</div>
                              <div class="dp-command-sub">Preview only</div>
                            </div>
                          </div>
                          <div v-if="selectedWeeklyReportDelivery.privacy_warnings?.length || selectedWeeklyReportDelivery.delivery_warnings?.length || selectedWeeklyReportDelivery.send_blockers?.length" class="dp-delivery-warnings mt-3">
                            <div v-for="blocker in selectedWeeklyReportDelivery.send_blockers || []" :key="`detail-blocker-${blocker}`" class="dp-calendar-warning dp-calendar-warning--danger">{{ blocker }}</div>
                            <div v-for="warning in selectedWeeklyReportDelivery.privacy_warnings || []" :key="`detail-privacy-${warning}`" class="dp-calendar-warning">{{ warning }}</div>
                            <div v-for="warning in selectedWeeklyReportDelivery.delivery_warnings || []" :key="`detail-delivery-${warning}`" class="dp-calendar-warning">{{ warning }}</div>
                          </div>
                          <div class="dp-weekly-panel mt-3">
                            <div class="dp-command-label">Message Preview</div>
                            <pre class="dp-delivery-message">{{ selectedWeeklyReportDelivery.message_preview || 'Message preview is hidden or not available for this delivery.' }}</pre>
                          </div>
                        </div>
                      </div>
                    </template>
                    <p v-if="weeklyReportDeliveryMessage" class="dp-command-message">{{ weeklyReportDeliveryMessage }}</p>
                  </div>

                  <div class="dp-report-notes mt-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <div class="dp-command-label">Report Notes</div>
                        <p class="dp-command-sub">Add coach context before sharing this report.</p>
                      </div>
                      <button class="dp-btn dp-btn--small" :disabled="Boolean(weeklyReportExportLoading)" @click="previewWeeklyReportHtml">Include in Export Preview</button>
                    </div>
                    <div class="dp-report-note-warnings">
                      <span>Staff notes will not appear in parent or player exports.</span>
                      <span>Parent/player versions hide private player review details.</span>
                    </div>

                    <div class="dp-report-note-form">
                      <label class="dp-export-field">
                        <span>Type</span>
                        <select v-model="weeklyReportNoteForm.note_type" class="dp-input dp-input--compact">
                          <option v-for="option in weeklyReportNoteTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                      </label>
                      <label class="dp-export-field">
                        <span>Audience</span>
                        <select v-model="weeklyReportNoteForm.audience" class="dp-input dp-input--compact">
                          <option v-for="option in weeklyReportAudienceOptions" :key="`note-audience-${option.value}`" :value="option.value">{{ option.label }}</option>
                        </select>
                      </label>
                      <label class="dp-export-field">
                        <span>Visibility</span>
                        <select v-model="weeklyReportNoteForm.visibility" class="dp-input dp-input--compact">
                          <option v-for="option in weeklyReportVisibilityOptions" :key="`note-visibility-${option.value}`" :value="option.value">{{ option.label }}</option>
                        </select>
                      </label>
                      <label class="dp-export-field">
                        <span>Player</span>
                        <select v-model="weeklyReportNoteForm.player_id" class="dp-input dp-input--compact">
                          <option value="">Team-wide</option>
                          <option v-for="player in teamPlayers" :key="`note-player-${player.id}`" :value="player.id">{{ player.name }}</option>
                        </select>
                      </label>
                      <label class="dp-export-field dp-export-field--wide">
                        <span>Title</span>
                        <input v-model="weeklyReportNoteForm.title" class="dp-input" placeholder="Optional title" />
                      </label>
                      <label class="dp-export-field dp-export-field--full">
                        <span>Body</span>
                        <textarea v-model="weeklyReportNoteForm.body" class="dp-input" rows="3" placeholder="Write the note exactly as it should appear for the selected audience…"></textarea>
                      </label>
                      <div class="dp-report-note-actions">
                        <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="weeklyReportNotesLoading" @click="saveWeeklyReportNote">
                          {{ weeklyReportNoteEditingId ? 'Save Note' : 'Add Note' }}
                        </button>
                        <button v-if="weeklyReportNoteEditingId" class="dp-btn dp-btn--small" @click="resetWeeklyReportNoteForm">Cancel Edit</button>
                        <span class="dp-command-sub">{{ noteTypeHint(weeklyReportNoteForm.note_type) }}</span>
                      </div>
                    </div>

                    <div v-if="weeklyReportNotesMessage" class="dp-command-message">{{ weeklyReportNotesMessage }}</div>
                    <div v-if="weeklyReportNotesLoading" class="dp-command-loading mt-3">Loading report notes…</div>
                    <div v-else-if="!weeklyReportNotes.length" class="dp-empty dp-empty--sm mt-3">
                      No report notes added yet. Add coach context before sharing this report.
                    </div>
                    <div v-else class="dp-report-note-list">
                      <div class="dp-command-sub">
                        {{ weeklyReportVisibleNotes.length }} note{{ weeklyReportVisibleNotes.length === 1 ? '' : 's' }} visible in the current {{ human(weeklyReportExportAudience) }} export.
                      </div>
                      <div v-for="type in weeklyReportNoteTypes" :key="`note-group-${type.value}`" v-show="weeklyReportNotesByType[type.value]?.length" class="dp-report-note-group">
                        <div class="dp-command-label">{{ type.label }}</div>
                        <div v-for="note in weeklyReportNotesByType[type.value]" :key="note.id" class="dp-report-note-row">
                          <div class="min-w-0">
                            <div class="font-extrabold truncate">{{ note.title || type.label }}</div>
                            <div class="dp-command-sub">{{ human(note.visibility) }} · {{ human(note.audience) }}<span v-if="note.player_name"> · {{ note.player_name }}</span></div>
                            <p>{{ note.body }}</p>
                          </div>
                          <div class="dp-report-note-row-actions">
                            <button class="dp-link" @click="editWeeklyReportNote(note)">Edit</button>
                            <button class="dp-link dp-link--danger" @click="deleteWeeklyReportNote(note)">Delete</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="dp-weekly-panel mt-3">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="dp-command-label">Player Report Rows</div>
                    <span class="dp-command-sub">{{ weeklyTeamPlayers.length }} player{{ weeklyTeamPlayers.length === 1 ? '' : 's' }}</span>
                  </div>
                  <div v-if="weeklyTeamPlayers.length" class="dp-report-player-list">
                    <div v-for="row in weeklyTeamPlayers" :key="`weekly-report-player-${row.player_id || row.player_name}`" class="dp-report-player-row">
                      <div class="min-w-0">
                        <div class="font-bold truncate">{{ row.player_name || 'Player' }}</div>
                        <div class="text-white/40 text-xs">{{ row.next_needed_action || 'No action needed' }}</div>
                      </div>
                      <div class="dp-player-status-metrics">
                        <span>{{ oneDecimal(row.completion_percentage) }}%</span>
                        <span>{{ row.plans_completed || 0 }}/{{ row.plans_assigned || 0 }} plans</span>
                        <span>{{ row.benchmark_values_submitted || 0 }} submitted</span>
                        <span>{{ row.pending_review_count || 0 }} review</span>
                        <span>{{ row.approved_count || 0 }} approved</span>
                        <span>{{ row.correction_requested_count || 0 }} correction</span>
                        <span>{{ human(row.status_label) }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-else class="dp-empty dp-empty--sm mt-2">No player completion data is available yet.</div>
                </div>

                <div class="dp-weekly-columns">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Benchmark Submission Summary</div>
                    <div class="dp-report-metrics">
                      <span>{{ weeklyTeamBenchmark.submitted_metric_count || 0 }} submitted</span>
                      <span>{{ weeklyTeamBenchmark.approved_metric_count || 0 }} approved</span>
                      <span>{{ weeklyTeamBenchmark.pending_review_count || 0 }} pending</span>
                      <span>{{ weeklyTeamBenchmark.trusted_values_promoted || 0 }} trusted</span>
                    </div>
                    <div v-if="weeklyTeamCollectedMetrics.length" class="dp-weekly-list">
                      <div v-for="metric in weeklyTeamCollectedMetrics.slice(0, 5)" :key="`collected-${metric.metric_key}`" class="dp-gap-row">
                        <span>{{ metric.display_name || metricLabel(metric.metric_key) }}</span>
                        <span>{{ metric.submitted_count || 0 }} submitted</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No benchmark values were submitted this week.</div>
                  </div>

                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Remaining Missing Metrics</div>
                    <div v-if="weeklyTeamRemainingMetrics.length" class="dp-weekly-list">
                      <div v-for="metric in weeklyTeamRemainingMetrics.slice(0, 6)" :key="`remaining-${metric.metric_key || metric.display_name}`" class="dp-gap-row">
                        <span>{{ metric.display_name || metricLabel(metric.metric_key) }}</span>
                        <span>{{ metric.missing_count || 0 }} missing</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No benchmark gaps are surfaced for this report.</div>
                  </div>
                </div>

                <div class="dp-weekly-columns">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Coach Follow-Ups</div>
                    <div v-if="weeklyTeamFollowUps.length" class="dp-weekly-recommendations dp-weekly-recommendations--single">
                      <div v-for="followUp in weeklyTeamFollowUps.slice(0, 6)" :key="`follow-up-${followUp.title}`" class="dp-action-card">
                        <span class="dp-priority" :class="priorityClass(followUp.priority)">{{ human(followUp.priority) }}</span>
                        <div class="font-extrabold mt-2">{{ followUp.title }}</div>
                        <p class="text-white/55 text-xs mt-2">{{ followUp.why }}</p>
                        <p class="text-white/35 text-xs mt-1">{{ followUp.recommended_action }}</p>
                        <p v-if="followUp.players?.length" class="text-white/35 text-xs mt-1">Players: {{ followUp.players.slice(0, 4).map((player) => player.player_name).join(', ') }}</p>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No coach follow-ups are surfaced yet.</div>
                  </div>

                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Next Week Priorities</div>
                    <div v-if="weeklyTeamPriorities.length" class="dp-weekly-recommendations dp-weekly-recommendations--single">
                      <div v-for="priority in weeklyTeamPriorities.slice(0, 5)" :key="`team-report-priority-${priority.rank}-${priority.title}`" class="dp-action-card">
                        <span class="dp-priority" :class="priorityClass(priority.priority)">{{ human(priority.priority) }}</span>
                        <div class="font-extrabold mt-2">#{{ priority.rank }} {{ priority.title }}</div>
                        <p class="text-white/55 text-xs mt-2">{{ priority.why }}</p>
                        <p v-if="priority.suggested_block" class="text-white/35 text-xs mt-1">{{ priority.suggested_block }} · {{ priority.estimated_minutes || 0 }} min</p>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No next-week priorities are available yet.</div>
                  </div>
                </div>

                <div v-if="weeklyTeamReport.warnings?.length" class="dp-calendar-warning mt-3">{{ weeklyTeamReport.warnings[0] }}</div>
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

            <div class="dp-command-block">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div>
                  <div class="dp-section mb-0">Next Week Calendar Draft</div>
                  <p class="dp-command-sub">Review the generated week by day, then save selected days as Daily Planner drafts.</p>
                </div>
                <div class="dp-calendar-controls">
                  <input v-model="nextWeekCalendarStart" class="dp-input dp-input--compact" type="date" aria-label="Next week start date" />
                  <button class="dp-link" :disabled="nextWeekCalendarLoading" @click="loadNextWeekCalendarDraft">{{ nextWeekCalendarLoading ? 'Generating…' : 'Generate Calendar Draft' }}</button>
                  <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="savingCalendarDays || !selectedCalendarDays.length" @click="saveCalendarDraftDays()">
                    {{ savingCalendarDays ? 'Saving…' : `Save Selected Days${selectedCalendarDays.length ? ` (${selectedCalendarDays.length})` : ''}` }}
                  </button>
                </div>
              </div>

              <div v-if="nextWeekCalendarLoading && !nextWeekCalendarDraft" class="dp-command-loading">Generating next week calendar draft…</div>
              <div v-else-if="nextWeekCalendarError" class="dp-empty dp-empty--sm">{{ nextWeekCalendarError }}</div>
              <div v-else-if="!nextWeekCalendarDraft" class="dp-empty dp-empty--sm">Generate a next-week draft to see calendar days.</div>
              <template v-else>
                <div class="dp-calendar-summary">
                  <div class="dp-command-card">
                    <div class="dp-command-label">Week</div>
                    <div class="dp-command-value">{{ nextWeekCalendarDraft.week_start_date || '—' }}</div>
                    <div class="dp-command-sub">to {{ nextWeekCalendarDraft.week_end_date || '—' }}</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">Total Minutes</div>
                    <div class="dp-command-value">{{ calendarSummary.total_planned_minutes || 0 }}</div>
                    <div class="dp-command-sub">{{ oneDecimal(calendarSummary.average_minutes_per_day) }} average per day</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">High Workload</div>
                    <div class="dp-command-value">{{ calendarSummary.high_workload_days || 0 }}</div>
                    <div class="dp-command-sub">{{ calendarSummary.recovery_support_days || 0 }} recovery/support days</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">Benchmark Targets</div>
                    <div class="dp-command-value">{{ calendarSummary.benchmark_collection_targets || calendarTargets.length || 0 }}</div>
                    <div class="dp-command-sub">{{ calendarSummary.players_needing_follow_up || 0 }} players needing follow-up</div>
                  </div>
                </div>

                <div v-if="calendarDays.length" class="dp-calendar-grid">
                  <div
                    v-for="day in calendarDays"
                    :key="`calendar-${day.day_index}`"
                    class="dp-calendar-day"
                    :class="[`dp-calendar-day--${day.workload_label || 'moderate'}`, { 'dp-calendar-day--saved': day.save_status?.already_saved, 'dp-calendar-day--blocked': day.save_status?.blocking_existing_plan }]"
                  >
                    <div class="dp-calendar-day-top">
                      <label class="dp-calendar-check">
                        <input
                          type="checkbox"
                          :checked="calendarDaySelected(day)"
                          :disabled="Boolean(day.save_status?.existing_daily_plan_id)"
                          @change="toggleCalendarDay(day)"
                        />
                        <span>{{ day.day_label || `Day ${day.day_index}` }}</span>
                      </label>
                      <span class="dp-status" :class="statusBadgeClass(day.workload_label === 'too_heavy' ? 'warning' : day.workload_label === 'light' ? 'info' : 'neutral')">
                        {{ workloadLabel(day.workload_label) }}
                      </span>
                    </div>

                    <div class="dp-command-label mt-2">{{ fmtDate(day.scheduled_for) }}</div>
                    <div class="dp-calendar-title">{{ day.title }}</div>
                    <div class="dp-command-sub">Focus: {{ day.primary_focus || 'Weekly Plan' }} · {{ day.estimated_total_minutes || 0 }} min</div>
                    <p v-if="day.why_this_day" class="dp-empty-copy">FMTRX suggested this block because {{ day.why_this_day }}</p>

                    <div class="dp-calendar-meta">
                      <span>Benchmark targets: {{ (day.metrics_to_collect || []).slice(0, 3).map(metricLabel).join(', ') || 'None' }}</span>
                      <span>Players: {{ (day.players || []).length || (day.player_assignments || []).length || 0 }}</span>
                    </div>

                    <div class="dp-calendar-blocks">
                      <div v-for="block in (day.blocks || []).slice(0, 5)" :key="`calendar-${day.day_index}-${block.title}`" class="dp-calendar-block">
                        <strong>{{ block.title }}</strong>
                        <span>{{ block.duration_minutes || 0 }} min · {{ human(block.category) }}</span>
                      </div>
                      <div v-if="(day.blocks || []).length > 5" class="dp-command-sub">+{{ (day.blocks || []).length - 5 }} more blocks</div>
                    </div>

                    <div v-if="day.warnings?.length || day.save_status?.message" class="dp-calendar-warning">
                      {{ day.save_status?.message || day.warnings[0] }}
                    </div>

                    <div class="dp-next-week-actions mt-3">
                      <button class="dp-btn dp-btn--small" @click="previewCalendarDraftDay(day)">Preview</button>
                      <button
                        v-if="!day.save_status?.existing_daily_plan_id"
                        class="dp-btn dp-btn--primary dp-btn--small"
                        :disabled="savingCalendarDays"
                        @click="saveOneCalendarDraftDay(day)"
                      >
                        {{ savingCalendarDays ? 'Saving…' : 'Save as Draft' }}
                      </button>
                      <button
                        v-else
                        class="dp-btn dp-btn--small"
                        @click="openCalendarDailyPlan(day)"
                      >
                        Open in Daily Planner
                      </button>
                    </div>
                  </div>
                </div>
                <div v-else class="dp-empty dp-empty--sm mt-3">No suggested days were generated.</div>

                <div v-if="previewCalendarDay" class="dp-weekly-panel mt-3">
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <div class="dp-command-label">Calendar Day Preview</div>
                      <div class="font-extrabold">{{ previewCalendarDay.day_label }} · {{ previewCalendarDay.title }}</div>
                      <p class="dp-command-sub">{{ previewCalendarDay.primary_focus }} · {{ previewCalendarDay.estimated_total_minutes || 0 }} min</p>
                    </div>
                    <button class="dp-link" @click="previewCalendarDay = null">Close</button>
                  </div>
                  <ul class="dp-next-week-preview-list">
                    <li v-for="block in (previewCalendarDay.blocks || [])" :key="`calendar-preview-${block.title}`">
                      <strong>{{ block.title }} · {{ block.duration_minutes || 0 }} min</strong>
                      <span>{{ block.description }}</span>
                      <span v-if="block.why">Why: {{ block.why }}</span>
                      <span v-if="block.metrics_to_collect?.length">Benchmark targets: {{ block.metrics_to_collect.map(metricLabel).join(', ') }}</span>
                    </li>
                  </ul>
                </div>

                <div class="dp-weekly-columns">
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Benchmark Targets</div>
                    <div v-if="calendarTargets.length" class="dp-weekly-list">
                      <div v-for="target in calendarTargets.slice(0, 5)" :key="`calendar-target-${target.title}`" class="dp-gap-row">
                        <span>{{ target.title }}</span>
                        <span>{{ (target.metrics || []).map(metricLabel).slice(0, 2).join(', ') || 'Baseline' }}</span>
                      </div>
                    </div>
                    <div v-else class="dp-command-sub">No benchmark targets are carried into this calendar draft.</div>
                  </div>
                  <div class="dp-weekly-panel">
                    <div class="dp-command-label">Coach Notes</div>
                    <ul v-if="calendarNotes.length" class="dp-calendar-notes">
                      <li v-for="note in calendarNotes.slice(0, 4)" :key="`calendar-note-${note}`">{{ note }}</li>
                    </ul>
                    <div v-else class="dp-command-sub">No coach notes are available yet.</div>
                  </div>
                </div>

                <p v-if="nextWeekCalendarMessage" class="dp-command-message">
                  {{ nextWeekCalendarMessage }}
                  <button v-if="savedCalendarPlans[0]?.daily_plan" class="dp-link ml-2" @click="openCalendarDailyPlan(savedCalendarPlans[0])">Open Saved Plan</button>
                </p>
              </template>
            </div>

            <div class="dp-command-block">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <div>
                  <div class="dp-section mb-0">Weekly Draft Publish Flow</div>
                  <p class="dp-command-sub">Publish saved weekly draft days when the coach is ready. Players see them after publish plus assignment.</p>
                </div>
                <div class="dp-calendar-controls">
                  <button class="dp-link" :disabled="weeklyDraftPlansLoading" @click="loadWeeklyDraftPlans">{{ weeklyDraftPlansLoading ? 'Loading…' : 'Refresh Drafts' }}</button>
                  <button class="dp-btn dp-btn--small" :disabled="weeklyPublishLoading || !selectedWeeklyDraftPlans.length" @click="publishSelectedWeeklyDraftPlans(false)">
                    {{ weeklyPublishLoading === 'bulk-publish' ? 'Publishing…' : `Publish Selected${selectedWeeklyDraftPlans.length ? ` (${selectedWeeklyDraftPlans.length})` : ''}` }}
                  </button>
                  <button class="dp-btn dp-btn--primary dp-btn--small" :disabled="weeklyPublishLoading || !selectedWeeklyDraftPlans.length || !teamPlayers.length" @click="publishSelectedWeeklyDraftPlans(true)">
                    {{ weeklyPublishLoading === 'bulk-assign' ? 'Publishing…' : 'Publish Selected + Assign All' }}
                  </button>
                </div>
              </div>

              <div v-if="weeklyDraftPlansLoading && !weeklyDraftPlans" class="dp-command-loading">Loading saved weekly draft plans…</div>
              <div v-else-if="weeklyDraftPlansError" class="dp-empty dp-empty--sm">{{ weeklyDraftPlansError }}</div>
              <div v-else-if="!weeklyDraftPlans" class="dp-empty dp-empty--sm">No weekly draft plans saved yet.</div>
              <template v-else>
                <div class="dp-calendar-summary">
                  <div class="dp-command-card">
                    <div class="dp-command-label">Saved Drafts</div>
                    <div class="dp-command-value">{{ weeklyDraftSummary.draft_count || 0 }}</div>
                    <div class="dp-command-sub">Save days from the weekly calendar before publishing.</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">Published</div>
                    <div class="dp-command-value">{{ weeklyDraftSummary.published_count || 0 }}</div>
                    <div class="dp-command-sub">Published assigned plans appear for players.</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">Available Players</div>
                    <div class="dp-command-value">{{ teamPlayers.length || 0 }}</div>
                    <div class="dp-command-sub">Assign All uses the current roster.</div>
                  </div>
                  <div class="dp-command-card">
                    <div class="dp-command-label">Selected</div>
                    <div class="dp-command-value">{{ selectedWeeklyDraftPlans.length || 0 }}</div>
                    <div class="dp-command-sub">No auto-publish. Coach action only.</div>
                  </div>
                </div>

                <div v-if="savedWeeklyDraftPlans.length" class="dp-weekly-publish-list">
                  <div
                    v-for="plan in savedWeeklyDraftPlans"
                    :key="`weekly-publish-${plan.daily_plan_id}`"
                    class="dp-weekly-publish-row"
                    :class="{ 'dp-weekly-publish-row--published': plan.status === 'published' }"
                  >
                    <label class="dp-calendar-check">
                      <input
                        type="checkbox"
                        :checked="weeklyDraftSelected(plan)"
                        :disabled="plan.status === 'published'"
                        @change="toggleWeeklyDraftPlan(plan)"
                      />
                      <span>{{ fmtDate(plan.scheduled_for) }}</span>
                    </label>
                    <div class="dp-weekly-publish-main">
                      <div class="dp-calendar-title">{{ plan.title }}</div>
                      <div class="dp-command-sub">{{ plan.primary_focus || 'Weekly Plan' }} · {{ plan.estimated_minutes || 0 }} min · {{ plan.block_count || 0 }} blocks</div>
                      <div class="dp-command-mini">
                        <span>{{ human(plan.status) }}</span>
                        <span>{{ plan.assigned_player_count || 0 }} assigned</span>
                        <span v-if="plan.has_progress">Progress preserved</span>
                      </div>
                      <div v-if="plan.warnings?.length" class="dp-calendar-warning mt-2">{{ plan.warnings[0] }}</div>
                    </div>
                    <div class="dp-weekly-publish-actions">
                      <button class="dp-btn dp-btn--small" @click="openWeeklyDraftPlan(plan)">Open in Daily Planner</button>
                      <button
                        class="dp-btn dp-btn--small"
                        :disabled="weeklyPublishLoading || plan.status === 'published'"
                        @click="publishWeeklyDraftPlan(plan, false)"
                      >
                        {{ weeklyPublishLoading === `${plan.daily_plan_id}:publish` ? 'Publishing…' : plan.status === 'published' ? 'Already Published' : 'Publish Day' }}
                      </button>
                      <button
                        class="dp-btn dp-btn--primary dp-btn--small"
                        :disabled="weeklyPublishLoading || !teamPlayers.length"
                        @click="publishWeeklyDraftPlan(plan, true)"
                      >
                        {{ weeklyPublishLoading === `${plan.daily_plan_id}:assign` ? 'Publishing…' : 'Publish + Assign All' }}
                      </button>
                    </div>
                  </div>
                </div>
                <div v-else class="dp-empty dp-empty--sm mt-3">No weekly draft plans saved yet. Save days from the weekly calendar before publishing.</div>

                <div v-if="!teamPlayers.length" class="dp-calendar-warning mt-3">No players are available to assign.</div>
                <p v-if="weeklyPublishMessage" class="dp-command-message">{{ weeklyPublishMessage }}</p>
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
          <div v-for="p in plans" :key="p.id" class="dp-card dp-card--static">
            <div class="flex items-start justify-between gap-2 cursor-pointer" @click="p.status === 'published' ? viewPlayers(p) : editPlan(p)">
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
            <div class="mt-3 pt-3 border-t border-white/10 flex flex-wrap items-center gap-x-4 gap-y-2">
              <button v-if="p.status === 'published'" class="dp-link" @click.stop="viewPlayers(p)">View Players</button>
              <button class="dp-link" @click.stop="editPlan(p)">Edit Workout</button>
              <button class="dp-link" @click.stop="duplicatePlan(p)">Duplicate</button>
              <button class="dp-link dp-link--danger ml-auto" @click.stop="del(p)">Delete</button>
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

    <!-- ══ WEEKLY REPORT PREVIEW MODAL ══ -->
    <div v-if="weeklyReportPreviewOpen" class="dp-modal" @click.self="weeklyReportPreviewOpen = false">
      <div class="dp-modal-card dp-modal-card--report">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <div>
            <div class="font-black text-lg">Printable Weekly Report</div>
            <div class="dp-command-sub">Audience: {{ human(weeklyReportExportAudience) }}</div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="dp-btn dp-btn--small" @click="printWeeklyReportPreview">Print / Save PDF</button>
            <button class="dp-x" @click="weeklyReportPreviewOpen = false">×</button>
          </div>
        </div>
        <iframe class="dp-report-frame" :srcdoc="weeklyReportPreviewHtml" title="FMTRX weekly report preview"></iframe>
      </div>
    </div>

    <!-- ══ SEASON REVIEW PACKET PREVIEW MODAL ══ -->
    <div v-if="seasonArchivePreviewOpen" class="dp-modal" @click.self="seasonArchivePreviewOpen = false">
      <div class="dp-modal-card dp-modal-card--report">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <div>
            <div class="font-black text-lg">Printable Season Review Packet</div>
            <div class="dp-command-sub">Audience: {{ human(seasonArchiveExportAudience) }}</div>
          </div>
          <div class="flex flex-wrap gap-2">
            <button class="dp-btn dp-btn--small" @click="printSeasonArchivePreview">Print / Save PDF</button>
            <button class="dp-x" @click="seasonArchivePreviewOpen = false">×</button>
          </div>
        </div>
        <iframe class="dp-report-frame" :srcdoc="seasonArchivePreviewHtml" title="FMTRX season review packet preview"></iframe>
      </div>
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
.dp-weekly-recommendations--single { grid-template-columns:1fr; }
.dp-report-headline { color:#fff; font-size:18px; line-height:1.2; font-weight:950; margin-top:5px; overflow-wrap:anywhere; }
.dp-report-summary-grid { display:grid; grid-template-columns:1fr; gap:10px; margin-top:10px; }
@media (min-width:900px){ .dp-report-summary-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } }
.dp-weekly-panel--highlight { border-color:rgba(216,35,42,.26); background:rgba(216,35,42,.065); }
.dp-report-list { margin:8px 0 0; padding-left:17px; color:rgba(255,255,255,.68); font-size:12.5px; line-height:1.45; }
.dp-report-list li { margin-top:3px; }
.dp-report-list--compact { font-size:11.5px; color:rgba(255,255,255,.52); }
.dp-report-next-action { color:#fff; font-size:14px; line-height:1.35; font-weight:900; margin-top:8px; overflow-wrap:anywhere; }
.dp-report-player-list { display:grid; gap:7px; margin-top:10px; }
.dp-report-player-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
@media (min-width:820px){ .dp-report-player-row { flex-direction:row; align-items:center; } }
.dp-report-metrics { display:flex; flex-wrap:wrap; gap:6px; margin-top:9px; }
.dp-report-metrics span { background:rgba(255,255,255,.055); border:1px solid rgba(255,255,255,.075); border-radius:999px; padding:4px 8px; color:rgba(255,255,255,.64); font-size:11px; font-weight:850; }
.dp-export-controls { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; justify-content:flex-start; }
@media (min-width:760px){ .dp-export-controls { justify-content:flex-end; } }
.dp-export-field { display:flex; flex-direction:column; gap:4px; }
.dp-export-field span { color:rgba(255,255,255,.48); text-transform:uppercase; letter-spacing:.07em; font-size:10px; font-weight:900; }
.dp-export-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
.dp-export-field--wide { min-width:220px; flex:1; }
.dp-export-field--full { grid-column:1 / -1; }
.dp-season-export-toggles { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
.dp-season-export-toggles label { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); border-radius:999px; padding:7px 10px; color:rgba(255,255,255,.74); font-size:11.5px; font-weight:850; }
.dp-season-export-toggles input { accent-color:#ff2d4d; }
.dp-report-template-preview { margin-top:12px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.025); border-radius:12px; padding:10px 12px; }
.dp-report-template-main { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:8px; }
@media (min-width:760px){ .dp-report-template-main { flex-direction:row; align-items:center; } }
.dp-report-delivery { border-top:1px solid rgba(255,255,255,.08); padding-top:12px; }
.dp-delivery-preview { display:grid; gap:12px; margin-top:12px; }
.dp-delivery-review { display:grid; gap:12px; margin-top:12px; border-top:1px solid rgba(255,255,255,.08); padding-top:12px; }
.dp-delivery-review-body { display:grid; gap:12px; }
.dp-delivery-history { display:grid; gap:12px; margin-top:12px; border-top:1px solid rgba(255,255,255,.08); padding-top:12px; }
.dp-delivery-analytics { display:grid; gap:12px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.025); border-radius:14px; padding:12px; }
.dp-delivery-analytics-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:880px){ .dp-delivery-analytics-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
.dp-delivery-analytics-list { display:grid; gap:8px; margin-top:10px; }
.dp-delivery-analytics-row { display:flex; align-items:center; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:8px 10px; min-width:0; }
.dp-delivery-analytics-row span { color:rgba(255,255,255,.72); font-size:12px; font-weight:850; overflow-wrap:anywhere; }
.dp-delivery-analytics-row strong { color:#fff; font-size:12px; white-space:nowrap; }
.dp-delivery-analytics-action { display:grid; gap:6px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:9px 10px; }
.dp-delivery-analytics-action strong { color:#fff; font-size:13px; font-weight:950; overflow-wrap:anywhere; }
.dp-delivery-analytics-action small { color:rgba(255,255,255,.55); font-size:11.5px; line-height:1.4; overflow-wrap:anywhere; }
.dp-communication-rhythm { display:grid; gap:12px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.025); border-radius:14px; padding:12px; }
.dp-communication-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:940px){ .dp-communication-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .dp-communication-panel--wide { grid-column:1 / -1; } }
.dp-season-archive { display:grid; gap:12px; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.025); border-radius:14px; padding:12px; }
.dp-season-archive-actions { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-start; gap:8px; }
.dp-season-archive-body { display:grid; gap:12px; }
.dp-season-summary-panel { background:linear-gradient(135deg, rgba(216,35,42,.075), rgba(53,90,170,.06)); }
.dp-season-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:940px){ .dp-season-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .dp-season-panel--wide { grid-column:1 / -1; } }
.dp-season-story { color:rgba(255,255,255,.7); font-size:12.5px; line-height:1.45; margin-top:8px; overflow-wrap:anywhere; }
.dp-rhythm-timeline { display:grid; gap:8px; margin-top:10px; }
.dp-rhythm-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
@media (min-width:860px){ .dp-rhythm-row { flex-direction:row; align-items:center; } }
.dp-rhythm-row strong { display:block; color:#fff; font-size:13px; font-weight:950; overflow-wrap:anywhere; }
.dp-rhythm-row span:not(.dp-status):not(.dp-rhythm-pill) { display:block; color:rgba(255,255,255,.58); font-size:11.5px; margin-top:3px; overflow-wrap:anywhere; }
.dp-rhythm-row small { display:block; color:rgba(255,255,255,.42); font-size:11px; margin-top:4px; overflow-wrap:anywhere; }
.dp-rhythm-pills { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
.dp-rhythm-pill { border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.04); color:rgba(255,255,255,.45); border-radius:999px; padding:4px 8px; font-size:10.5px; font-weight:900; text-transform:uppercase; }
.dp-rhythm-pill--on { border-color:rgba(52,211,153,.45); background:rgba(52,211,153,.12); color:#d1fae5; }
.dp-delivery-history-list { display:grid; gap:8px; margin-top:10px; }
.dp-delivery-history-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; width:100%; text-align:left; border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.035); border-radius:12px; padding:10px 12px; color:inherit; }
.dp-delivery-history-row:hover { border-color:rgba(255,255,255,.2); background:rgba(255,255,255,.055); }
@media (min-width:780px){ .dp-delivery-history-row { flex-direction:row; align-items:center; } }
.dp-delivery-history-row strong { display:block; color:#fff; font-size:13px; font-weight:950; overflow-wrap:anywhere; }
.dp-delivery-history-row span:not(.dp-status) { display:block; color:rgba(255,255,255,.58); font-size:11.5px; margin-top:3px; overflow-wrap:anywhere; }
.dp-delivery-history-row small { display:block; color:rgba(255,255,255,.42); font-size:11px; margin-top:4px; overflow-wrap:anywhere; }
.dp-delivery-history-meta { display:flex; flex-direction:column; align-items:flex-start; gap:5px; flex:none; }
@media (min-width:780px){ .dp-delivery-history-meta { align-items:flex-end; } }
.dp-delivery-detail { border:1px solid rgba(255,255,255,.1); background:rgba(9,14,29,.62); border-radius:14px; padding:12px; }
.dp-delivery-warnings { display:grid; gap:7px; }
.dp-delivery-message-grid { display:grid; grid-template-columns:1fr; gap:10px; }
@media (min-width:860px){ .dp-delivery-message-grid { grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr); } }
.dp-delivery-editor { display:grid; grid-template-columns:1fr; gap:10px; }
.dp-delivery-subject { color:#fff; font-weight:950; line-height:1.25; margin-top:7px; overflow-wrap:anywhere; }
.dp-delivery-recipient-list { display:grid; gap:7px; margin-top:8px; }
.dp-delivery-recipient { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:8px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:8px 10px; min-width:0; }
@media (min-width:720px){ .dp-delivery-recipient { flex-direction:row; align-items:center; } }
.dp-delivery-recipient strong { display:block; color:#fff; font-size:12.5px; overflow-wrap:anywhere; }
.dp-delivery-recipient span:not(.dp-status) { display:block; color:rgba(255,255,255,.5); font-size:11.5px; margin-top:2px; overflow-wrap:anywhere; }
.dp-delivery-message { margin:9px 0 0; white-space:pre-wrap; word-break:break-word; max-height:280px; overflow:auto; background:rgba(3,7,18,.6); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:11px; color:rgba(255,255,255,.72); font-size:12px; line-height:1.45; }
.dp-delivery-textarea { min-height:220px; resize:vertical; line-height:1.45; }
.dp-confirm-row { display:flex; align-items:flex-start; gap:9px; margin-top:12px; color:rgba(255,255,255,.72); font-size:12.5px; line-height:1.4; }
.dp-confirm-row input { margin-top:2px; accent-color:#ff304f; flex:none; }
.dp-send-result { border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.035); border-radius:12px; padding:11px; }
.dp-report-notes { border-top:1px solid rgba(255,255,255,.08); padding-top:12px; }
.dp-report-note-warnings { display:flex; flex-wrap:wrap; gap:7px; margin-top:10px; }
.dp-report-note-warnings span { border:1px solid rgba(245,158,11,.24); background:rgba(245,158,11,.075); color:#fcd34d; border-radius:999px; padding:5px 8px; font-size:10.5px; font-weight:900; }
.dp-report-note-form { display:grid; grid-template-columns:1fr; gap:8px; margin-top:12px; }
@media (min-width:760px){ .dp-report-note-form { grid-template-columns:repeat(4, minmax(0,1fr)); } }
.dp-report-note-actions { grid-column:1 / -1; display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
.dp-report-note-list { display:grid; gap:10px; margin-top:12px; }
.dp-report-note-group { display:grid; gap:7px; }
.dp-report-note-row { display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; gap:10px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
@media (min-width:760px){ .dp-report-note-row { flex-direction:row; align-items:flex-start; } }
.dp-report-note-row p { color:rgba(255,255,255,.66); font-size:12.5px; line-height:1.45; margin-top:6px; overflow-wrap:anywhere; }
.dp-report-note-row-actions { display:flex; flex-wrap:wrap; gap:10px; flex:none; }
.dp-report-frame { width:100%; min-height:70vh; border:1px solid rgba(255,255,255,.12); border-radius:12px; background:#fff; }
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
.dp-calendar-controls { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:flex-start; }
@media (min-width:760px){ .dp-calendar-controls { justify-content:flex-end; } }
.dp-calendar-summary { display:grid; grid-template-columns:repeat(1, minmax(0,1fr)); gap:10px; margin-top:10px; }
@media (min-width:760px){ .dp-calendar-summary { grid-template-columns:repeat(4, minmax(0,1fr)); } }
.dp-calendar-grid { display:grid; grid-template-columns:repeat(1, minmax(0,1fr)); gap:12px; margin-top:12px; }
@media (min-width:980px){ .dp-calendar-grid { grid-template-columns:repeat(5, minmax(0,1fr)); align-items:stretch; } }
.dp-calendar-day { background:rgba(9,14,29,.64); border:1px solid rgba(255,255,255,.1); border-radius:15px; padding:13px; min-width:0; display:flex; flex-direction:column; gap:9px; }
.dp-calendar-day--light { border-color:rgba(59,130,246,.22); }
.dp-calendar-day--moderate { border-color:rgba(255,255,255,.12); }
.dp-calendar-day--heavy { border-color:rgba(245,158,11,.28); background:rgba(245,158,11,.055); }
.dp-calendar-day--too_heavy { border-color:rgba(216,35,42,.36); background:rgba(216,35,42,.08); }
.dp-calendar-day--saved { box-shadow:0 0 0 1px rgba(52,211,153,.2) inset; }
.dp-calendar-day--blocked { box-shadow:0 0 0 1px rgba(245,158,11,.2) inset; }
.dp-calendar-day-top { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
.dp-calendar-check { display:flex; align-items:center; gap:8px; color:#fff; font-weight:950; min-width:0; }
.dp-calendar-check input { width:17px; height:17px; flex:none; accent-color:#ff2d55; }
.dp-calendar-check span { overflow-wrap:anywhere; }
.dp-calendar-title { color:#fff; font-size:17px; line-height:1.15; font-weight:950; overflow-wrap:anywhere; }
.dp-calendar-meta { display:grid; gap:5px; color:rgba(255,255,255,.52); font-size:11.5px; font-weight:800; }
.dp-calendar-blocks { display:grid; gap:6px; }
.dp-calendar-block { background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:8px; display:grid; gap:3px; }
.dp-calendar-block strong { font-size:12.5px; line-height:1.25; overflow-wrap:anywhere; }
.dp-calendar-block span { color:rgba(255,255,255,.48); font-size:11px; font-weight:800; }
.dp-calendar-warning { border:1px solid rgba(245,158,11,.28); background:rgba(245,158,11,.08); color:#fcd34d; border-radius:10px; padding:8px; font-size:11.5px; line-height:1.35; font-weight:800; }
.dp-calendar-notes { margin:8px 0 0; padding-left:17px; color:rgba(255,255,255,.66); font-size:12.5px; line-height:1.45; }
.dp-calendar-notes li { margin-top:3px; }
.dp-weekly-publish-list { display:grid; gap:10px; margin-top:12px; }
.dp-weekly-publish-row { background:rgba(9,14,29,.62); border:1px solid rgba(255,255,255,.1); border-radius:14px; padding:12px; display:grid; grid-template-columns:1fr; gap:10px; align-items:start; }
@media (min-width:980px){ .dp-weekly-publish-row { grid-template-columns:150px minmax(0,1fr) auto; } }
.dp-weekly-publish-row--published { border-color:rgba(52,211,153,.22); background:rgba(52,211,153,.055); }
.dp-weekly-publish-main { min-width:0; }
.dp-weekly-publish-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-start; }
@media (min-width:980px){ .dp-weekly-publish-actions { justify-content:flex-end; } }
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
.dp-input--compact { width:155px; max-width:100%; padding:7px 9px; font-size:12.5px; }
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
.dp-card--static { cursor:default; }
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
.dp-modal-card--report { max-width:1040px; height:92vh; }
@media (min-width:640px){ .dp-modal { align-items:center; padding:20px; } .dp-modal-card { border-radius:18px; } }
.dp-cat { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.7); font-size:12px; font-weight:700; padding:5px 11px; border-radius:999px; cursor:pointer; }
.dp-cat--on { background:#d8232a; border-color:#d8232a; color:#fff; }
.dp-modal-list { overflow-y:auto; margin-top:4px; }
.dp-drill-row { display:flex; align-items:center; justify-content:space-between; gap:10px; width:100%; padding:11px 6px; border-bottom:1px solid rgba(255,255,255,.07); cursor:pointer; }
.dp-drill-row:hover { background:rgba(255,255,255,.04); }
.dp-plus { color:#7ca6f5; font-size:20px; font-weight:800; flex:none; }
</style>
