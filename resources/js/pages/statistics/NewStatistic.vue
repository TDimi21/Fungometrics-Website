<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from '@/store/team'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from '@/utils/AlertPlugin'

const router = useRouter()
const { teams } = useTeamStore()
const { userData } = useUserStore()
const { axiosGet } = useAxiosAuth()
const isPlayerLogin = computed(() => userData?.type === 'player')

const selectedTab = ref('stats')
const selectedTeamId = ref('')
const teamPlayers = ref([])
const selectedPlayers = ref([])
const selectedSessions = ref([])
const showSessionPicker = ref(false)
const loadingSessionPicker = ref(false)
const availableSessions = ref([])
const selectedSpecificSessions = ref([])

const today = new Date()
const oneYearAgo = new Date()
oneYearAgo.setFullYear(today.getFullYear() - 1)

const sinceWhen = ref(oneYearAgo.toISOString().slice(0, 10))
const until = ref(today.toISOString().slice(0, 10))

const sessionOptions = [
  { key: 'B', label: 'Batting' },
  { key: 'P', label: 'Bullpen' },
  { key: 'C', label: 'Cage' },
  { key: 'EV', label: 'Exit Velocity' },
  { key: 'LT', label: 'Long Toss' },
  { key: 'WB', label: 'Weighted Ball' },
  { key: 'L', label: 'Live AB' },
  { key: 'BS', label: 'Box Score' },
]

const selectedCountLabel = computed(() => `${selectedPlayers.value.length}/${teamPlayers.value.length}`)

const sessionListKeyBySession = {
  B: 'batting',
  P: 'bullpen',
  C: 'cage',
  EV: 'exit_velocity',
  LT: 'long_toss',
  WB: 'weight_ball',
  L: 'live',
}

const buildOptionsFromSessions = (sessions) => {
  const options = {}
  if (sessions.includes('B')) options.B = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
  if (sessions.includes('P')) options.P = [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28]
  if (sessions.includes('C')) options.C = [29, 30, 31, 32, 33, 34]
  if (sessions.includes('EV')) options.EV = [35, 36, 37, 38]
  if (sessions.includes('LT')) options.LT = [39, 40, 41, 42, 43, 44]
  if (sessions.includes('WB')) options.WB = [45, 46, 47]
  if (sessions.includes('L')) options.L = [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58]
  return options
}

const loadTeamPlayers = async (teamId) => {
  if (isPlayerLogin.value) {
    teamPlayers.value = [{
      id: String(userData.id),
      name: userData?.name?.full || userData?.name || 'Player',
    }]
    selectedPlayers.value = [String(userData.id)]
    return
  }

  if (!teamId) return
  try {
    const response = await axiosGet(`coach/teams/${teamId}`)
    const players = Array.isArray(response?.data?.data) ? response.data.data : []
    teamPlayers.value = players.map((p) => ({
      id: p.id,
      name: p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || 'Player',
    }))
    selectedPlayers.value = []
  } catch (error) {
    teamPlayers.value = []
    selectedPlayers.value = []
  }
}

const togglePlayerSelection = (playerId) => {
  if (selectedPlayers.value.includes(playerId)) {
    selectedPlayers.value = selectedPlayers.value.filter((id) => id !== playerId)
    return
  }
  selectedPlayers.value = [...selectedPlayers.value, playerId]
}

const toggleSessionSelection = (sessionKey) => {
  if (selectedSessions.value.includes(sessionKey)) {
    selectedSessions.value = []
    return
  }
  selectedSessions.value = [sessionKey]
}

const selectAllPlayers = () => {
  selectedPlayers.value = teamPlayers.value.map((p) => p.id)
}

const deselectAllPlayers = () => {
  selectedPlayers.value = []
}

const formatSessionDate = (session) => {
  const raw = session?.date || session?.created_at || session?.started_at || session?.started || ''
  const date = new Date(raw)
  if (Number.isNaN(date.getTime())) return 'No date'
  return date.toLocaleString()
}

const inDateRange = (session) => {
  if (!sinceWhen.value || !until.value) return true
  const s = new Date(session?.date || session?.created_at || session?.started_at || session?.started || '')
  if (Number.isNaN(s.getTime())) return true
  const from = new Date(`${sinceWhen.value}T00:00:00`)
  const to = new Date(`${until.value}T23:59:59`)
  return s >= from && s <= to
}

const extractSessionPlayerIds = (session) => {
  const ids = new Set()
  const pushId = (v) => {
    if (v !== null && v !== undefined && v !== '') ids.add(String(v))
  }

  ;(session?.players || []).forEach((p) => pushId(p?.id || p?.user_id))
  ;(session?.pitchers || []).forEach((p) => pushId(p?.id || p?.user_id || p?.pitcher_id))
  ;(session?.batters || []).forEach((p) => pushId(p?.id || p?.user_id || p?.batter_id))
  ;(session?.pitching_practice_lineups || []).forEach((p) => pushId(p?.pitcher_id || p?.user_id || p?.id))
  ;(session?.practice_line_ups || []).forEach((p) => pushId(p?.batter_id || p?.user_id || p?.id))
  ;(session?.lineup || []).forEach((p) => pushId(p?.id || p?.user_id))

  return ids
}

const sessionHasSelectedPlayers = (session) => {
  if (selectedPlayers.value.length === 0) return true
  const ids = extractSessionPlayerIds(session)
  if (ids.size === 0) return true
  return selectedPlayers.value.some((id) => ids.has(String(id)))
}

const resolveSessionList = (sessionsData, sessionKey) => {
  const key = sessionListKeyBySession[sessionKey]
  let list = sessionsData?.[key] || []

  const trainingFallback =
    sessionsData?.training ||
    sessionsData?.trainings ||
    sessionsData?.all_training ||
    []

  if (sessionKey === 'EV' && (!Array.isArray(list) || list.length === 0)) {
    list = (Array.isArray(trainingFallback) ? trainingFallback : []).filter((s) => {
      const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
      return ['EV', 'EXIT_VELOCITY', 'EXITVELOCITY'].includes(mode)
    })
  }

  if (sessionKey === 'LT' && (!Array.isArray(list) || list.length === 0)) {
    list = (Array.isArray(trainingFallback) ? trainingFallback : []).filter((s) => {
      const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
      return ['LT', 'LONG_TOSS', 'LONGTOSS'].includes(mode)
    })
  }

  if (sessionKey === 'WB' && (!Array.isArray(list) || list.length === 0)) {
    list = (Array.isArray(trainingFallback) ? trainingFallback : []).filter((s) => {
      const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
      return ['WB', 'WEIGHT_BALL', 'WEIGHTBALL'].includes(mode)
    })
  }

  return Array.isArray(list) ? list : []
}

const toggleSpecificSession = (sessionId) => {
  const id = String(sessionId)
  if (selectedSpecificSessions.value.includes(id)) {
    selectedSpecificSessions.value = selectedSpecificSessions.value.filter((x) => x !== id)
    return
  }
  selectedSpecificSessions.value = [...selectedSpecificSessions.value, id]
}

const closeSessionPicker = () => {
  showSessionPicker.value = false
}

const buildStatsQuery = () => {
  const selectedSession = selectedSessions.value[0]
  const isBS = selectedSession === 'BS'
  const routeSession = isBS ? 'L' : selectedSession

  const query = {
    team: isPlayerLogin.value ? '' : String(selectedTeamId.value),
    teamName: isPlayerLogin.value
      ? (userData?.name?.full || userData?.name || 'Player')
      : (teams.find((t) => String(t.id) === String(selectedTeamId.value))?.name || 'Team'),
    since: sinceWhen.value,
    until: until.value,
    players: selectedPlayers.value.join(','),
    session: routeSession,
  }

  if (isPlayerLogin.value) {
    query.playerId = String(userData.id)
    query.playerName = userData?.name?.full || userData?.name || 'Player'
  }

  if (isBS) query.tab = 'BOX SCORE'
  if (selectedSpecificSessions.value.length > 0) {
    query.sessionIds = selectedSpecificSessions.value.join(',')
  }

  return query
}

const openSessionPicker = async () => {
  loadingSessionPicker.value = true
  try {
    const selectedSession = selectedSessions.value[0]
    const routeSession = selectedSession === 'BS' ? 'L' : selectedSession
    let filtered = []
    if (isPlayerLogin.value) {
      if (routeSession === 'B') {
        const res = await axiosGet('player/sessions/batting')
        filtered = res?.data?.data?.data || []
      } else if (routeSession === 'P') {
        const res = await axiosGet('player/sessions/bullpen')
        filtered = res?.data?.data?.data || []
      } else if (routeSession === 'C') {
        const res = await axiosGet('player/sessions/cage')
        filtered = res?.data?.data?.data || []
      } else {
        const res = await axiosGet('player/sessions/training')
        const training = res?.data?.data?.data || []
        const mode = String(routeSession).toUpperCase()
        filtered = training.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === mode)
      }
    } else {
      const sessionsRes = await axiosGet(`coach/sessions/lasts/${selectedTeamId.value}`)
      const sessionsData = sessionsRes?.data?.data || sessionsRes?.data || {}
      filtered = resolveSessionList(sessionsData, routeSession)
    }

    filtered = filtered
      .filter((session) => inDateRange(session))
      .filter((session) => sessionHasSelectedPlayers(session))

    availableSessions.value = filtered
      .map((session) => {
        const id = String(session?.id || '')
        if (!id) return null
        const playerCount = extractSessionPlayerIds(session).size
        return {
          id,
          label: session?.name || session?.title || `Session ${id.slice(0, 8)}`,
          dateLabel: formatSessionDate(session),
          playerCount,
        }
      })
      .filter(Boolean)

    selectedSpecificSessions.value = availableSessions.value.map((s) => s.id)
    showSessionPicker.value = true
  } catch (error) {
    toast.fire({ icon: 'error', title: 'Error', text: error?.message || 'Failed to load sessions.' })
  } finally {
    loadingSessionPicker.value = false
  }
}

const continueToStatistics = () => {
  const query = buildStatsQuery()
  const href = router.resolve({ name: 'new-statistic-session-view', query }).href
  window.open(href, '_blank')
  closeSessionPicker()
}

const goToPractice = () => {
  router.push('/sessions')
}

const openStatistics = () => {
  if (!isPlayerLogin.value && !selectedTeamId.value) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'Select a team first.' })
    return
  }
  if (selectedPlayers.value.length === 0) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'Select at least one player.' })
    return
  }
  if (selectedSessions.value.length === 0) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'Select one session type.' })
    return
  }

  const selectedSession = selectedSessions.value[0]
  const isBS = selectedSession === 'BS'
  const routeSession = isBS ? 'L' : selectedSession

  const options = buildOptionsFromSessions([routeSession])
  if (Object.keys(options).length === 0) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'Unsupported session selection.' })
    return
  }

  openSessionPicker()
}

onMounted(async () => {
  if (isPlayerLogin.value) {
    await loadTeamPlayers('')
    return
  }

  if (Array.isArray(teams) && teams.length > 0) {
    selectedTeamId.value = teams[0].id
    await loadTeamPlayers(selectedTeamId.value)
  }
})
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#050b18] text-white px-4 py-4 lg:px-6 lg:py-5">
      <div class="mx-auto max-w-4xl">
        <div class="mb-4 flex items-center justify-center gap-2">
          <RouterLink
            to="/statistic"
            class="rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-black tracking-wider text-white/90 hover:bg-white/20 transition"
          >
            Legacy
          </RouterLink>
          <RouterLink
            to="/new-statistic"
            class="rounded-lg border border-[#ff2d55]/70 bg-[#ff2d55]/30 px-3 py-1.5 text-xs font-black tracking-wider text-white"
          >
            New
          </RouterLink>
        </div>

        <h1 class="text-center text-3xl md:text-4xl font-black tracking-wide text-[#ff2d55] mb-4">Stats</h1>

        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-3 md:p-4 max-h-[calc(100vh-170px)] overflow-y-auto">
          <div class="grid grid-cols-2 gap-2 rounded-xl bg-white/10 p-1 mb-4">
            <button
              type="button"
              class="rounded-lg py-2 text-base md:text-lg font-black transition"
              :class="selectedTab === 'stats' ? 'bg-[#ff2d55] text-white' : 'bg-transparent text-white/80'"
              @click="selectedTab = 'stats'"
            >
              Stats
            </button>
            <button
              type="button"
              class="rounded-lg py-2 text-base md:text-lg font-black transition"
              :class="selectedTab === 'practice' ? 'bg-[#ff2d55] text-white' : 'bg-transparent text-white/80'"
              @click="goToPractice"
            >
              Practice
            </button>
          </div>

          <div v-if="!isPlayerLogin" class="mb-4">
            <label for="new-stats-team" class="block text-sm font-black uppercase tracking-wider text-white/60 mb-2">Team</label>
            <select
              id="new-stats-team"
              name="team"
              v-model="selectedTeamId"
              class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-white outline-none"
              @change="loadTeamPlayers(selectedTeamId)"
            >
              <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div v-else class="mb-4 rounded-xl border border-white/15 bg-white/5 p-3">
            <p class="text-xs uppercase tracking-widest text-white/45">Player Scope</p>
            <p class="text-sm font-black text-white mt-1">{{ userData?.name?.full || userData?.name || 'Player' }}</p>
          </div>

          <div class="mb-4">
            <div class="flex items-center justify-between gap-3 mb-3">
              <h2 class="text-2xl md:text-3xl font-black">Select Players ({{ selectedCountLabel }})</h2>
              <div v-if="!isPlayerLogin" class="flex items-center gap-2">
                <button type="button" class="rounded-lg bg-[#ff2d55] px-3 py-1.5 text-sm font-black" @click="selectAllPlayers">All</button>
                <button type="button" class="rounded-lg bg-[#ff2d55] px-3 py-1.5 text-sm font-black" @click="deselectAllPlayers">None</button>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
              <button
                v-for="player in teamPlayers"
                :key="player.id"
                type="button"
                class="rounded-full border px-4 py-2 text-base font-bold truncate transition"
                :class="selectedPlayers.includes(player.id)
                  ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white'
                  : 'border-[#ff2d55]/70 bg-white/5 text-white/80 hover:bg-white/10'"
                @click="!isPlayerLogin && togglePlayerSelection(player.id)"
              >
                {{ player.name }}
              </button>
            </div>
          </div>

          <div class="mb-4">
            <h2 class="text-2xl md:text-3xl font-black mb-3">Select Sessions</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5">
              <button
                v-for="session in sessionOptions"
                :key="session.key"
                type="button"
                class="rounded-full border px-4 py-2 text-base font-bold transition"
                :class="selectedSessions.includes(session.key)
                  ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white'
                  : 'border-[#ff2d55]/70 bg-white/5 text-white/80 hover:bg-white/10'"
                @click="toggleSessionSelection(session.key)"
              >
                {{ session.label }}
              </button>
            </div>
          </div>

          <button
            type="button"
            class="w-full rounded-xl bg-[#ff2d55] py-3 text-2xl md:text-3xl font-black tracking-wide hover:opacity-95 transition"
            :disabled="loadingSessionPicker"
            @click="openStatistics"
          >
            {{ loadingSessionPicker ? 'Loading Sessions...' : 'Show Statistics' }}
          </button>
          <p class="mt-3 text-center text-base md:text-lg text-white/55">Results open in a new screen.</p>
        </div>
      </div>

      <div v-if="showSessionPicker" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4">
        <div class="w-full max-w-3xl rounded-2xl border border-white/20 bg-[#0b1322] p-4 md:p-6">
          <div class="mb-4 flex items-start justify-between gap-3">
            <div>
              <h3 class="text-2xl md:text-3xl font-black text-[#ff2d55]">Select Sessions</h3>
              <p class="text-sm md:text-base text-white/65">
                Choose which sessions to include in this stats view.
              </p>
            </div>
            <button type="button" class="rounded-lg border border-white/30 px-3 py-1.5 text-sm" @click="closeSessionPicker">Close</button>
          </div>

          <div class="mb-3 flex flex-wrap items-center gap-2">
            <button
              type="button"
              class="rounded-lg bg-[#ff2d55] px-3 py-1.5 text-sm font-black"
              @click="selectedSpecificSessions = availableSessions.map((s) => s.id)"
            >
              Select All
            </button>
            <button
              type="button"
              class="rounded-lg bg-white/15 px-3 py-1.5 text-sm font-black"
              @click="selectedSpecificSessions = []"
            >
              Clear All
            </button>
            <span class="text-sm text-white/70">{{ selectedSpecificSessions.length }}/{{ availableSessions.length }} selected</span>
          </div>

          <div class="max-h-[50vh] overflow-y-auto rounded-xl border border-white/10 bg-white/5 p-2 md:p-3">
            <div v-if="availableSessions.length === 0" class="py-8 text-center text-white/60">
              No sessions found for the selected filters.
            </div>

            <button
              v-for="session in availableSessions"
              :key="session.id"
              type="button"
              class="mb-2 w-full rounded-xl border px-3 py-2 text-left transition last:mb-0"
              :class="selectedSpecificSessions.includes(session.id)
                ? 'border-[#ff2d55] bg-[#ff2d55]/20'
                : 'border-white/20 bg-white/5 hover:bg-white/10'"
              @click="toggleSpecificSession(session.id)"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="font-bold text-white truncate">{{ session.label }}</p>
                <p class="text-xs md:text-sm text-white/70">{{ session.dateLabel }}</p>
              </div>
              <p class="text-xs text-white/60 mt-0.5">Players in session: {{ session.playerCount }}</p>
            </button>
          </div>

          <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="rounded-lg border border-white/30 px-4 py-2 text-sm font-black" @click="closeSessionPicker">Cancel</button>
            <button
              type="button"
              class="rounded-lg bg-[#ff2d55] px-4 py-2 text-sm font-black disabled:opacity-50"
              :disabled="selectedSpecificSessions.length === 0"
              @click="continueToStatistics"
            >
              Show Selected Sessions
            </button>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
