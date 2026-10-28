<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAxiosAuth } from '@/composables/axios-auth'
import PlayerAssessmentLinks from '@/components/free-assessment/PlayerAssessmentLinks.vue'
import StatePanel from '@/features/shared/components/StatePanel.vue'
import SessionCountsCard from './SessionCountsCard.vue'
import RecapList from './RecapList.vue'
import StatTabs from './StatTabs.vue'
import BpStats from './BpStats.vue'
import BullpenStats from './BullpenStats.vue'
import CageStats from './CageStats.vue'
import WeightedStats from './WeightedStats.vue'
import ExitVelStats from './ExitVelStats.vue'
import LongTossStats from './LongTossStats.vue'
import { mapDashboardSummary, metricRowsForTab } from '../lib/playerHomeAdapter.js'
import { buildExitVelocityReport, buildLongTossReport, buildWeightedReport } from '../lib/trainingReports.js'
import { STAT_TABS } from '../lib/constants.js'

// The parent keys this view by team ID: pending responses from a previous
// selection can only update its unmounted instance, never the current view.
const props = defineProps({ team: { type: Object, required: true }, playerId: { type: String, default: '' } })
defineEmits(['open-report'])
const { axiosGet } = useAxiosAuth()
const router = useRouter()
const reports = ref([]), reportError = ref(false)
const loading = ref(true), error = ref('')
const summary = ref(mapDashboardSummary(null))
const tab = ref('bp')
const tabs = STAT_TABS.filter(item => item.key !== 'percentiles')
const breakdowns = computed(() => summary.value.breakdowns)
const metricRows = computed(() => metricRowsForTab(tab.value, breakdowns.value))
const weightedReport = computed(() => buildWeightedReport(breakdowns.value.weighted))
const exitVelReport = computed(() => buildExitVelocityReport(breakdowns.value.exitVel))
const longTossReport = computed(() => buildLongTossReport(breakdowns.value.longToss))
async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await axiosGet('player/dashboard-summary', { team_id: props.team.id })
    summary.value = mapDashboardSummary(response?.data?.data)
  } catch (failure) {
    error.value = failure?.response?.status === 403
      ? 'This team is no longer connected to your profile. Choose All My Data to see your history.'
      : 'Could not load your team data. Please try again.'
  } finally { loading.value = false }
}
async function loadReports() {
  reportError.value = false
  try {
    const response = await axiosGet('data-hub/rapsodo-reports', { team_id: props.team.id })
    reports.value = response?.data?.data || []
  } catch { reportError.value = true }
}
onMounted(() => { load(); loadReports() })
</script>

<template>
  <section class="min-w-0 space-y-4" aria-label="Selected team data">
    <div class="rounded-xl border border-white/10 bg-surface-raised p-4" role="status">
      <h2 class="text-lg font-black">{{ team.name }} — My Data</h2>
      <p class="mt-1 text-sm text-white/70">Only your results from sessions and assessments recorded for this team.</p>
      <p class="mt-1 text-xs text-white/60">Choose All My Data for your combined history, profile metrics, percentiles, and workouts.</p>
    </div>
    <StatePanel v-if="loading" state="loading" message="Loading your team data…" />
    <StatePanel v-else-if="error" state="error" :message="error" @retry="load" />
    <template v-else>
      <PlayerAssessmentLinks :player-id="playerId" :team-id="team.id" />
      <div v-if="reports.length || reportError" class="rounded-xl border border-white/10 bg-surface-raised p-4">
        <h3 class="font-bold">Rapsodo Reports</h3>
        <p v-if="reportError" role="alert">Could not load reports. <button type="button" class="underline" @click="loadReports">Try again</button></p>
        <button v-for="report in reports" :key="report.id" type="button" class="mt-2 block w-full rounded-lg border border-white/20 px-3 py-3 text-left hover:bg-white/10"
          @click="router.push(report.report_path)">{{ report.team_name }} · {{ String(report.completed_at || '').slice(0, 10) }} · {{ report.pitch_count }} pitches →</button>
      </div>
      <div class="space-y-4">
        <div class="space-y-4">
          <SessionCountsCard :counts="summary.counts" :show-metrics="false" />
          <RecapList :sessions="summary.recentSessions" @open-report="$emit('open-report', $event)" />
        </div>
        <div class="rounded-2xl border border-white/10 bg-surface-raised/75 p-4">
          <StatTabs v-model="tab" :tabs="tabs" :show-workout="false" />
          <BpStats v-if="tab === 'bp'" :breakdown="breakdowns.batting" />
          <BullpenStats v-if="tab === 'bullpen'" :breakdown="breakdowns.bullpen" :metric-rows="metricRows" />
          <CageStats v-if="tab === 'cage'" :breakdown="breakdowns.cage" :metric-rows="metricRows" />
          <WeightedStats v-if="tab === 'weighted'" :report="weightedReport" :metric-rows="metricRows" />
          <ExitVelStats v-if="tab === 'exitVel'" :breakdown="breakdowns.exitVel" :report="exitVelReport" :metric-rows="metricRows" />
          <LongTossStats v-if="tab === 'longToss'" :report="longTossReport" :metric-rows="metricRows" />
        </div>
      </div>
    </template>
  </section>
</template>
