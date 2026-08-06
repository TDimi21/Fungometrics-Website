<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import ModalPlayer from '@/components/dashboard/ModalPlayer.vue'
import PlayerWorkoutsPanel from '@/components/planner/PlayerWorkoutsPanel.vue'
import StatePanel from '@/features/shared/components/StatePanel.vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth'
import { resolvePlayerId, resolveTeamId } from '@/utils/identity.js'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.webp'
import ProfileCard from '../components/ProfileCard.vue'
import BenchmarkTasksPanel from '../components/BenchmarkTasksPanel.vue'
import SessionCountsCard from '../components/SessionCountsCard.vue'
import RecapList from '../components/RecapList.vue'
import SleepCheckinModal from '../components/SleepCheckinModal.vue'
import StatTabs from '../components/StatTabs.vue'
import BpStats from '../components/BpStats.vue'
import BullpenStats from '../components/BullpenStats.vue'
import CageStats from '../components/CageStats.vue'
import WeightedStats from '../components/WeightedStats.vue'
import ExitVelStats from '../components/ExitVelStats.vue'
import LongTossStats from '../components/LongTossStats.vue'
import {
  buildCoachProfile,
  buildModalPlayerItem,
  buildProfile,
  buildSchoolTeamText,
  buildSpeedLine,
  buildStrengthLine,
  hasSleepLoggedToday,
  mapDashboardSummary,
  metricRowsForTab,
  normalizeImageSrc,
  pick,
  todayDateKey,
} from '../lib/playerHomeAdapter.js'
import { buildExitVelocityReport, buildLongTossReport, buildWeightedReport } from '../lib/trainingReports.js'
import { createSectionState, runSection, SESSION_EXPIRED_MESSAGE } from '../lib/sectionState.js'
import { STAT_TABS } from '../lib/constants.js'

const userStore = useUserStore()
const { userData } = storeToRefs(userStore)
const { axiosGet } = useAxiosAuth()
const router = useRouter()

// ── Per-section fetch state (each panel fails and retries on its own) ────
const profileState = createSectionState()
const statsState = createSectionState()
const rapsodoState = createSectionState()

const summary = ref(mapDashboardSummary(null))
const rapsodoReports = ref([])
const playerFitnessLatest = ref(null)
const playerFitnessRows = ref([])

const activeStatTab = ref('bp')
const lastStatTab = ref('bp')

const toggleWorkout = () => {
  if (activeStatTab.value === 'workout') {
    activeStatTab.value = lastStatTab.value
  } else {
    lastStatTab.value = activeStatTab.value
    activeStatTab.value = 'workout'
  }
}

// ── Identity + profile view models ───────────────────────────────────────
const playerId = computed(() => resolvePlayerId(userData.value))
const teamId = computed(() => resolveTeamId(userData.value))
const playerName = computed(() => userData.value?.name?.full || userData.value?.name || 'Player')
const playerImageSrc = computed(() => {
  const raw = pick(
    userData.value?.avatar,
    userData.value?.profile?.picture,
    userData.value?.profile?.avatar,
    userData.value?.player?.picture,
    userData.value?.user?.avatar,
    userData.value?.user?.profile?.picture,
  )
  return normalizeImageSrc(raw) || updatedLogo
})
const profile = computed(() => buildProfile(userData.value, playerFitnessLatest.value))
const coachProfile = computed(() => buildCoachProfile(userData.value))
const schoolTeamText = computed(() => buildSchoolTeamText(coachProfile.value))
const strengthLine = computed(() => buildStrengthLine(playerFitnessRows.value.length ? playerFitnessRows.value : (playerFitnessLatest.value ? [playerFitnessLatest.value] : [])))
const speedLine = computed(() => buildSpeedLine(playerFitnessRows.value.length ? playerFitnessRows.value : (playerFitnessLatest.value ? [playerFitnessLatest.value] : [])))

// ── Stats view models (single dashboard-summary payload) ────────────────
const breakdowns = computed(() => summary.value.breakdowns)
const sessionCounts = computed(() => summary.value.counts)
const recentSessions = computed(() => summary.value.recentSessions)
const metricRows = computed(() => metricRowsForTab(activeStatTab.value, breakdowns.value))
const weightedReport = computed(() => buildWeightedReport(breakdowns.value.weighted))
const exitVelReport = computed(() => buildExitVelocityReport(breakdowns.value.exitVel))
const longTossReport = computed(() => buildLongTossReport(breakdowns.value.longToss))

const sessionExpired = computed(() =>
  profileState.unauthorized || statsState.unauthorized || rapsodoState.unauthorized
)

// ── Section loaders ─────────────────────────────────────────────────────
const loadProfile = () => runSection(profileState, async () => {
  const meRes = await axiosGet('player/me')
  const freshUser = meRes?.data?.data
  if (freshUser && typeof freshUser === 'object') {
    userStore.mergeUserData(freshUser)
  }
  if (playerId.value) {
    const fitnessRes = await axiosGet(`player/fitness/${playerId.value}`)
    const fit = fitnessRes?.data?.data
    const rows = Array.isArray(fit) ? fit : (fit ? [fit] : [])
    playerFitnessRows.value = rows
    playerFitnessLatest.value = rows[0] || null
    maybeOpenSleepCheckin(rows)
  }
}, 'Couldn\'t load profile & fitness.')

const loadStats = () => runSection(statsState, async () => {
  const res = await axiosGet('player/dashboard-summary')
  summary.value = mapDashboardSummary(res?.data?.data)
}, 'Couldn\'t load sessions & stats.')

const loadRapsodo = () => runSection(rapsodoState, async () => {
  const res = await axiosGet('data-hub/rapsodo-reports')
  rapsodoReports.value = Array.isArray(res?.data?.data) ? res.data.data : []
}, 'Couldn\'t load Rapsodo reports.')

const loadData = () => Promise.all([loadProfile(), loadStats(), loadRapsodo()])
onMounted(loadData)

// ── Sleep check-in ──────────────────────────────────────────────────────
const sleepCheckinOpen = ref(false)
const sleepCheckinCheckedDate = ref('')

const maybeOpenSleepCheckin = (fitnessRows = []) => {
  const today = todayDateKey()
  if (sleepCheckinCheckedDate.value === today) return
  sleepCheckinCheckedDate.value = today
  sleepCheckinOpen.value = !hasSleepLoggedToday(fitnessRows)
}

const onSleepCheckinSaved = (saved) => {
  if (saved) playerFitnessLatest.value = saved
}

// ── Player metrics modal ────────────────────────────────────────────────
const isOpenPlayerMetricsModal = ref(false)
const playerMetricsRows = ref([])
const playerMetricsScore = ref({})
const modalPlayerItem = computed(() => buildModalPlayerItem(userData.value, playerId.value))

const openPlayerMetricsModal = async () => {
  const pid = playerId.value || userData.value?.id || null
  if (!pid) return
  isOpenPlayerMetricsModal.value = true
  const [scoreRes, fitnessRes] = await Promise.all([
    axiosGet(`player/statistics/${pid}`).catch(() => null),
    axiosGet(`player/fitness/${pid}`).catch(() => null),
  ])
  playerMetricsScore.value = scoreRes?.data?.data ?? {}
  const fit = fitnessRes?.data?.data
  playerMetricsRows.value = Array.isArray(fit) ? fit : (fit ? [fit] : [])
}

// ── Navigation ──────────────────────────────────────────────────────────
const openDevelopmentProfile = () => {
  const id = playerId.value || userData.value?.id
  if (!id) return
  router.push({
    name: 'development.player',
    params: { playerId: id },
    query: { teamId: teamId.value || undefined, playerName: playerName.value },
  })
}
const openSessionReports = () => router.push({ name: 'sessions.all', query: { scope: 'player' } })
const openRapsodoReport = (report) => router.push(report.report_path || { name: 'player.rapsodo-report', params: { batch: report.id } })
const openAssessmentReports = () => router.push({ name: 'assessment.reports', query: { scope: 'player' } })
const openArmCare = () => router.push({ name: 'arm.care' })
const openRecapReport = (session) => {
  if (!session?.id || !session?._reportType) return
  router.push({
    name: 'session.report',
    params: { id: session.id, type: session._reportType },
    query: { date: session._date || null, note: session.end_note || null },
  })
}
</script>

<template>
  <Layout>
    <SleepCheckinModal
      :open="sleepCheckinOpen"
      :player-id="playerId || userData?.id"
      @close="sleepCheckinOpen = false"
      @saved="onSleepCheckinSaved"
    />

    <div class="min-h-full bg-surface px-4 py-5 text-white lg:px-6">
      <div class="mx-auto max-w-6xl space-y-4">
        <div
          v-if="sessionExpired"
          class="rounded-lg border border-accent-2/50 bg-accent-2/15 px-4 py-3 text-sm text-white"
          data-testid="session-expired-banner"
        >
          {{ SESSION_EXPIRED_MESSAGE }}
        </div>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-[380px_1fr]">
          <div class="space-y-4">
            <StatePanel v-if="profileState.loading" state="loading" message="Loading profile…" />
            <StatePanel v-else-if="profileState.error" state="error" :message="profileState.error" @retry="loadProfile" />
            <ProfileCard
              v-else
              :player-name="playerName"
              :player-image-src="playerImageSrc"
              :profile="profile"
              :coach-profile="coachProfile"
              :school-team-text="schoolTeamText"
              :strength-line="strengthLine"
              :speed-line="speedLine"
              :rapsodo-reports="rapsodoReports"
              @open-development="openDevelopmentProfile"
              @open-metrics="openPlayerMetricsModal"
              @open-sessions="openSessionReports"
              @open-rapsodo="openRapsodoReport"
              @open-assessments="openAssessmentReports"
              @open-armcare="openArmCare"
            />
            <StatePanel v-if="rapsodoState.error" state="error" :message="rapsodoState.error" @retry="loadRapsodo" />

            <BenchmarkTasksPanel />

            <StatePanel v-if="statsState.loading" state="loading" message="Loading session counts…" />
            <StatePanel v-else-if="statsState.error" state="error" :message="statsState.error" @retry="loadStats" />
            <template v-else>
              <SessionCountsCard :counts="sessionCounts" @open-metrics="openPlayerMetricsModal" />
              <RecapList :sessions="recentSessions" @open-report="openRecapReport" />
            </template>
          </div>

          <div class="rounded-2xl border border-white/10 bg-surface-raised/75 p-4">
            <StatTabs v-model="activeStatTab" :tabs="STAT_TABS" @toggle-workout="toggleWorkout" />

            <StatePanel v-if="statsState.loading" state="loading" message="Loading player stats…" />
            <StatePanel v-else-if="statsState.error && activeStatTab !== 'workout'" state="error" :message="statsState.error" @retry="loadStats" />

            <div v-else class="space-y-3">
              <PlayerWorkoutsPanel v-if="activeStatTab === 'workout'" />

              <template v-else>
                <BpStats v-if="activeStatTab === 'bp'" :breakdown="breakdowns.batting" />
                <BullpenStats v-if="activeStatTab === 'bullpen'" :breakdown="breakdowns.bullpen" :metric-rows="metricRows" />
                <CageStats v-if="activeStatTab === 'cage'" :breakdown="breakdowns.cage" :metric-rows="metricRows" />
                <WeightedStats v-if="activeStatTab === 'weighted'" :report="weightedReport" :metric-rows="metricRows" />
                <ExitVelStats v-if="activeStatTab === 'exitVel'" :breakdown="breakdowns.exitVel" :report="exitVelReport" :metric-rows="metricRows" />
                <LongTossStats v-if="activeStatTab === 'longToss'" :breakdown="breakdowns.longToss" :report="longTossReport" :metric-rows="metricRows" />

                <div class="pt-2">
                  <div class="flex items-center justify-center gap-8 text-[10px] font-black uppercase tracking-widest">
                    <span class="text-red-500">Poor</span>
                    <span class="text-yellow-400">Average</span>
                    <span class="text-green-500">Great</span>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </section>
      </div>
    </div>

    <ModalPlayer
      v-if="isOpenPlayerMetricsModal"
      :isOpen="isOpenPlayerMetricsModal"
      :item="modalPlayerItem"
      :response="playerMetricsRows"
      :score="playerMetricsScore"
      @closeModal="isOpenPlayerMetricsModal = false"
    />
  </Layout>
</template>
