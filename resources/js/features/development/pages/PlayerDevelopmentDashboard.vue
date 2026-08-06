<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useAccessStore } from '@/store/access.js'
import { useTeamStore } from '@/store/team'
import { useUserStore } from '@/store/user'
import { canManageSubscriptions } from '@/utils/administration.js'
import { resolvePlayerId, resolveTeamId } from '@/utils/identity.js'
import { buildPlayerDevelopmentDashboard } from '../lib/playerDevelopmentDashboardAdapter.js'
import PlayerIdentityCard from '../components/PlayerIdentityCard.vue'
import PlayerCurrentBaselineCard from '../components/PlayerCurrentBaselineCard.vue'
import PlayerGoalGapCard from '../components/PlayerGoalGapCard.vue'
import PlayerPriorityList from '../components/PlayerPriorityList.vue'
import DevelopmentScoreStack from '../components/DevelopmentScoreStack.vue'
import CoachActionPlanCard from '../components/CoachActionPlanCard.vue'
import PercentileRankingsPanel from '../components/PercentileRankingsPanel.vue'
import CorrelationInsightsCard from '../components/CorrelationInsightsCard.vue'
import RecoverySleepCard from '../components/RecoverySleepCard.vue'
import StrengthMetricsCard from '../components/StrengthMetricsCard.vue'
import MobilityAssessmentCard from '../components/MobilityAssessmentCard.vue'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())
const userStore = useUserStore()
const access = useAccessStore()

const live = ref(null)
const intelligence = ref(null)
const playerOptions = ref([])
const loading = ref(false)
const playersLoading = ref(false)
const state = ref('idle')
const errorMessage = ref('')
const dataWindow = ref(365)

const isPlayerUser = computed(() => String(userStore.userData?.type || '').toLowerCase() === 'player')
const canViewAdmin = computed(() => canManageSubscriptions(userStore.userData))
const canAddToPlanner = computed(() => !isPlayerUser.value && access.canAccess('planner_create'))
const routePlayerId = computed(() => route.params?.playerId || String(route.query?.playerId || '').trim() || null)
const selfPlayerId = computed(() => resolvePlayerId(userStore.userData))
const teamId = computed(() => team.value?.id || team.value?.id_team || resolveTeamId(userStore.userData) || String(route.query?.teamId || '') || null)
const playerId = computed(() => isPlayerUser.value ? (selfPlayerId.value || routePlayerId.value) : routePlayerId.value)
const selectedPlayerName = computed(() => String(route.query?.playerName || route.query?.name || '').trim())
const isEmptySelection = computed(() => !loading.value && !live.value && !playerId.value && !isPlayerUser.value)

const dashboard = computed(() => buildPlayerDevelopmentDashboard(live.value || {}, intelligence.value || {}, {
  canAddToPlanner: canAddToPlanner.value,
  readOnly: isPlayerUser.value,
}))

const assessmentRoute = computed(() => ({
  name: 'assessment.reports',
  query: playerId.value ? { scope: 'player', playerId: playerId.value } : { scope: 'player' },
}))

const playerRoute = (option) => ({
  name: 'development.player',
  params: { playerId: option.id },
  query: { ...(teamId.value ? { teamId: teamId.value } : {}), playerName: option.name },
})

const normalizePlayer = (row) => {
  const id = row?.id || row?.user_id || row?.player_id || row?.user?.id
  if (!id) return null
  const profile = row?.profile || row?.user?.profile || {}
  const name = row?.name || row?.full_name || profile?.full_name || [profile?.first_name, profile?.last_name].filter(Boolean).join(' ') || `Player #${id}`
  return { id, name, jersey: row?.jersey || row?.number_in_shirt || row?.user?.player?.number_in_shirt || null, picture: row?.picture || profile?.picture || null }
}

const loadPlayers = async () => {
  if (!teamId.value || isPlayerUser.value) return
  playersLoading.value = true
  playerOptions.value = []
  try {
    const { data } = await axiosGet(`coach/teams/${teamId.value}/player-development-board`)
    const rows = Array.isArray(data?.data) ? data.data : []
    playerOptions.value = rows.map(normalizePlayer).filter(Boolean)
  } catch {
    playerOptions.value = []
  } finally {
    playersLoading.value = false
  }
}

const loadIntelligence = async () => {
  intelligence.value = null
  if (!teamId.value || !playerId.value || isPlayerUser.value) return
  try {
    const { data } = await axiosGet(`coach/teams/${teamId.value}/players/${playerId.value}/intelligence`, { days: dataWindow.value })
    intelligence.value = data?.data || data || null
  } catch {
    // The live dashboard remains useful when the optional intelligence layer is partial or unavailable.
    intelligence.value = null
  }
}

const loadDashboard = async () => {
  live.value = null
  intelligence.value = null
  errorMessage.value = ''
  state.value = 'idle'

  if (!playerId.value) {
    if (!isPlayerUser.value && teamId.value) await loadPlayers()
    state.value = 'empty'
    return
  }
  if (!teamId.value && !isPlayerUser.value) {
    state.value = 'error'
    errorMessage.value = 'Select a team to view this player development dashboard.'
    return
  }

  loading.value = true
  state.value = 'loading'
  try {
    const endpoint = isPlayerUser.value
      ? `player/development/players/${playerId.value}`
      : `coach/development/teams/${teamId.value}/players/${playerId.value}`
    const { data } = await axiosGet(endpoint, { days: dataWindow.value })
    const payload = data?.data
    if (!payload?.player || !payload?.current) {
      state.value = 'empty'
      return
    }
    live.value = payload
    await loadIntelligence()
    state.value = dashboard.value.dataQuality.partial ? 'partial' : 'ready'
  } catch (error) {
    const statusCode = error?.response?.status
    state.value = statusCode === 401 || statusCode === 403 ? 'unauthorized' : 'error'
    errorMessage.value = state.value === 'unauthorized'
      ? 'You are not authorized to view this player development dashboard.'
      : statusCode === 404
        ? 'No player development data is available yet.'
        : 'The player development dashboard could not be loaded. Please try again.'
  } finally {
    loading.value = false
  }
}

const printDashboard = () => window.print()

watch(
  () => [route.params?.playerId, route.query?.teamId, team.value?.id, userStore.userData?.id, dataWindow.value],
  loadDashboard,
  { immediate: true },
)
</script>

<template>
  <Layout>
    <main class="development-page print-dashboard" data-testid="player-development-dashboard">
      <header class="report-toolbar no-print">
        <div class="brand"><b>FM<span>TRX</span></b><small>Player Development Dashboard</small></div>
        <nav aria-label="Development navigation">
          <RouterLink :to="playerId ? { name: 'development.player', params: { playerId }, query: teamId ? { teamId } : {} } : { name: 'development.index' }">Player</RouterLink>
          <RouterLink v-if="!isPlayerUser" :to="{ name: 'development.team' }">Team</RouterLink>
          <RouterLink v-if="!isPlayerUser" :to="{ name: 'development.coach' }">Coach</RouterLink>
          <RouterLink v-if="canViewAdmin" :to="{ name: 'development.admin.benchmarks' }">Admin</RouterLink>
        </nav>
        <div class="tools">
          <label>Data Window<select v-model.number="dataWindow" aria-label="Data window"><option :value="30">Last 30 Days</option><option :value="60">Last 60 Days</option><option :value="90">Last 90 Days</option><option :value="365">Last 365 Days</option></select></label>
          <button type="button" @click="printDashboard">Print / PDF</button>
        </div>
      </header>

      <header v-if="live" class="print-only print-heading"><b>FMTRX Player Development Dashboard</b><span>{{ dashboard.player.name }} · Last {{ dataWindow }} Days</span></header>

      <section v-if="state === 'loading'" class="dashboard-skeleton" aria-label="Loading player development dashboard" data-testid="loading-state">
        <div v-for="index in 9" :key="index" class="skeleton-card"><i/><i/><i/></div>
      </section>

      <section v-else-if="state === 'unauthorized'" class="state-card unauthorized" data-testid="unauthorized-state"><h1>Player Dashboard Unavailable</h1><p>{{ errorMessage }}</p></section>
      <section v-else-if="state === 'error'" class="state-card error" data-testid="error-state"><h1>Could Not Load Dashboard</h1><p>{{ errorMessage }}</p><button type="button" @click="loadDashboard">Try Again</button></section>

      <section v-else-if="isEmptySelection" class="state-card" data-testid="empty-player-state">
        <h1>Select a Player</h1><p>Choose a player to open their live development dashboard.</p>
        <p v-if="playersLoading">Loading players…</p>
        <div v-else-if="playerOptions.length" class="player-picker"><RouterLink v-for="option in playerOptions" :key="option.id" :to="playerRoute(option)"><span>{{ option.name }}</span><small>{{ option.jersey ? `#${option.jersey}` : 'Player' }}</small></RouterLink></div>
        <p v-else>No players are available for this team.</p>
      </section>

      <section v-else-if="state === 'empty' && !live" class="state-card" data-testid="empty-data-state"><h1>Needs Data</h1><p>{{ selectedPlayerName || 'This player' }} needs a completed session or assessment before the development dashboard can be shown.</p></section>

      <template v-else-if="live">
        <div v-if="state === 'partial'" class="partial-notice no-print" data-testid="partial-data-state"><b>Partial data</b><span>Available panels are shown. Missing measurements remain marked Needs Data.</span></div>
        <div class="dashboard-grid">
          <aside class="left-column">
            <PlayerIdentityCard :player="dashboard.player" :metrics="dashboard.quickMetrics" />
            <PlayerCurrentBaselineCard :rows="dashboard.baseline" :assessment-route="assessmentRoute" />
            <PlayerGoalGapCard :goals="dashboard.goals" />
            <PlayerPriorityList :priorities="dashboard.priorities" />
          </aside>
          <section class="center-column">
            <DevelopmentScoreStack :scores="dashboard.scores" />
            <CoachActionPlanCard :actions="dashboard.actions" :can-add-to-planner="dashboard.permissions.canAddToPlanner" :read-only="dashboard.permissions.readOnly" />
          </section>
          <section class="right-column">
            <PercentileRankingsPanel :groups="dashboard.percentileGroups" :comparison="dashboard.comparison" />
            <CorrelationInsightsCard :correlation="dashboard.correlation" />
            <div class="support-grid">
              <RecoverySleepCard :recovery="dashboard.recovery" />
              <StrengthMetricsCard :metrics="dashboard.strength" />
              <MobilityAssessmentCard :metrics="dashboard.mobility" />
            </div>
          </section>
        </div>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.development-page {
  min-height: 100vh;
  padding: 12px;
  background:
    radial-gradient(circle at 88% 8%, #0c2a3b 0, transparent 25%),
    linear-gradient(145deg, #020a12, #031321 58%, #061b29);
  color: #edf5fa;
  font-family: Inter, system-ui, sans-serif;
  overflow-x: hidden;
}

.report-toolbar {
  display: flex;
  align-items: center;
  gap: 16px;
  max-width: 1780px;
  margin: 0 auto 12px;
  padding: 8px 12px;
  border: 1px solid #233f51;
  border-radius: 9px;
  background: #06131f;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand b {
  font-size: 17px;
  font-style: italic;
}

.brand b span {
  color: #1ac2c0;
}

.brand small {
  text-transform: uppercase;
  color: #91a5b3;
  font-size: 9px;
  letter-spacing: .12em;
}

.report-toolbar nav {
  display: flex;
  gap: 5px;
}

.report-toolbar nav a {
  padding: 5px 10px;
  border: 1px solid #2d4555;
  border-radius: 4px;
  color: #b9c7cf;
  font-size: 9px;
}

.report-toolbar nav a.router-link-active {
  border-color: #ef3340;
  background: #661824;
  color: #fff;
}

.tools {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 7px;
}

.tools label {
  display: flex;
  align-items: center;
  gap: 6px;
  color: #7790a0;
  font-size: 8px;
  text-transform: uppercase;
}

.tools select,
.tools button {
  border: 1px solid #2b485a;
  border-radius: 5px;
  background: #091b29;
  padding: 6px 8px;
  color: #d9e5ec;
  font-size: 9px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(250px, .9fr) minmax(320px, 1.15fr) minmax(620px, 2.2fr);
  gap: 12px;
  max-width: 1780px;
  margin: auto;
  align-items: start;
}

.left-column,
.center-column,
.right-column {
  display: grid;
  gap: 9px;
  min-width: 0;
}

.support-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.partial-notice {
  display: flex;
  gap: 8px;
  max-width: 1780px;
  margin: 0 auto 9px;
  padding: 8px 11px;
  border: 1px solid #8e6d27;
  border-radius: 7px;
  background: #5e471b55;
  color: #d9c38d;
  font-size: 10px;
}

.dashboard-skeleton {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  max-width: 1500px;
  margin: auto;
}

.skeleton-card {
  height: 180px;
  border: 1px solid #243d4e;
  border-radius: 10px;
  padding: 18px;
  background: #071725;
}

.skeleton-card i {
  display: block;
  height: 12px;
  margin-bottom: 14px;
  border-radius: 8px;
  background: linear-gradient(90deg, #172b3a, #294253, #172b3a);
  background-size: 200%;
  animation: pulse 1.4s infinite;
}

.skeleton-card i:nth-child(2) {
  width: 70%;
}

.skeleton-card i:nth-child(3) {
  width: 45%;
}

.state-card {
  max-width: 760px;
  margin: 50px auto;
  padding: 30px;
  border: 1px solid #29485b;
  border-radius: 12px;
  background: #071725;
  text-align: center;
}

.state-card h1 {
  font-size: 22px;
  font-weight: 900;
  text-transform: uppercase;
}

.state-card p {
  margin-top: 6px;
  color: #91a6b5;
  font-size: 12px;
}

.state-card button {
  margin-top: 14px;
  border: 1px solid #ef3340;
  border-radius: 5px;
  padding: 7px 12px;
}

.unauthorized {
  border-color: #7b3140;
}

.error {
  border-color: #8d6727;
}

.player-picker {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-top: 16px;
  text-align: left;
}

.player-picker a {
  padding: 10px;
  border: 1px solid #29495d;
  border-radius: 7px;
}

.player-picker span,
.player-picker small {
  display: block;
}

.player-picker small {
  color: #70889a;
  font-size: 9px;
}

.print-only {
  display: none;
}

@keyframes pulse {
  to {
    background-position: -200%;
  }
}

@media (max-width: 1260px) {
  .dashboard-grid {
    grid-template-columns: minmax(250px, .8fr) minmax(330px, 1fr);
  }

  .left-column {
    grid-column: 1 / -1;
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .left-column > *:first-child {
    grid-column: 1 / -1;
  }

  .right-column {
    grid-column: 1 / -1;
  }

  .support-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 820px) {
  .report-toolbar {
    flex-wrap: wrap;
  }

  .tools {
    width: 100%;
    margin-left: 0;
    justify-content: space-between;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .left-column,
  .center-column,
  .right-column {
    grid-column: 1;
    grid-template-columns: 1fr;
  }

  .left-column > *:first-child {
    grid-column: 1;
  }

  .support-grid {
    grid-template-columns: 1fr;
  }

  .dashboard-skeleton {
    grid-template-columns: 1fr;
  }

  .player-picker {
    grid-template-columns: 1fr;
  }

  .brand small {
    display: none;
  }
}

@media print {
  .development-page {
    background: #fff !important;
    color: #111;
    padding: 0;
    overflow: visible;
  }

  .no-print {
    display: none !important;
  }

  .print-only {
    display: flex;
  }

  .print-heading {
    justify-content: space-between;
    border-bottom: 2px solid #222;
    padding: 0 0 10px;
    margin-bottom: 10px;
    color: #111;
  }

  .dashboard-grid {
    grid-template-columns: 28% 30% 42%;
    gap: 6px;
    max-width: none;
  }

  .dashboard-grid :deep(section),
  .dashboard-grid :deep(article),
  .dashboard-grid :deep(.pd-card),
  .dashboard-grid :deep(.score-card) {
    break-inside: avoid;
    box-shadow: none;
    background: #fff !important;
    color: #111 !important;
    border-color: #9aa4aa !important;
  }

  .dashboard-grid :deep(*) {
    text-shadow: none !important;
  }

  .support-grid {
    grid-template-columns: 1fr;
  }

  .print-dashboard {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
}
</style>
