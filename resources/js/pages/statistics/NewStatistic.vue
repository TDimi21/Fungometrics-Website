<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from '@/store/team'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from '@/utils/AlertPlugin'

const router = useRouter()
const { teams } = useTeamStore()
const { axiosGet } = useAxiosAuth()

const selectedTab = ref('stats')
const selectedTeamId = ref('')
const teamPlayers = ref([])
const selectedPlayers = ref([])
const selectedSessions = ref([])

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

const goToPractice = () => {
  router.push('/sessions')
}

const openStatistics = () => {
  if (!selectedTeamId.value) {
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

  const options = buildOptionsFromSessions(selectedSessions.value)
  if (Object.keys(options).length === 0) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'Unsupported session selection.' })
    return
  }

  const href = router.resolve({
    name: 'statistic',
    query: {
      auto: '1',
      team: String(selectedTeamId.value),
      since: sinceWhen.value,
      until: until.value,
      players: selectedPlayers.value.join(','),
      sessions: selectedSessions.value.join(','),
    },
  }).href

  window.open(href, '_blank')
}

onMounted(async () => {
  if (Array.isArray(teams) && teams.length > 0) {
    selectedTeamId.value = teams[0].id
    await loadTeamPlayers(selectedTeamId.value)
  }
})
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#050b18] text-white px-4 py-6 lg:px-8 lg:py-8">
      <div class="mx-auto max-w-5xl">
        <h1 class="text-center text-4xl font-black tracking-wide text-[#ff2d55] mb-6">Stats</h1>

        <div class="rounded-2xl border border-white/10 bg-white/10 backdrop-blur-xl p-4 md:p-6">
          <div class="grid grid-cols-2 gap-2 rounded-xl bg-white/10 p-1 mb-5">
            <button
              type="button"
              class="rounded-lg py-2.5 text-lg font-black transition"
              :class="selectedTab === 'stats' ? 'bg-[#ff2d55] text-white' : 'bg-transparent text-white/80'"
              @click="selectedTab = 'stats'"
            >
              Stats
            </button>
            <button
              type="button"
              class="rounded-lg py-2.5 text-lg font-black transition"
              :class="selectedTab === 'practice' ? 'bg-[#ff2d55] text-white' : 'bg-transparent text-white/80'"
              @click="goToPractice"
            >
              Practice
            </button>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-black uppercase tracking-wider text-white/60 mb-2">Team</label>
            <select
              v-model="selectedTeamId"
              class="w-full rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-white outline-none"
              @change="loadTeamPlayers(selectedTeamId)"
            >
              <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div class="mb-6">
            <div class="flex items-center justify-between gap-3 mb-3">
              <h2 class="text-3xl md:text-4xl font-black">Select Players ({{ selectedCountLabel }})</h2>
              <div class="flex items-center gap-2">
                <button type="button" class="rounded-lg bg-[#ff2d55] px-4 py-2 text-base font-black" @click="selectAllPlayers">All</button>
                <button type="button" class="rounded-lg bg-[#ff2d55] px-4 py-2 text-base font-black" @click="deselectAllPlayers">None</button>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <button
                v-for="player in teamPlayers"
                :key="player.id"
                type="button"
                class="rounded-full border px-4 py-3 text-lg font-bold truncate transition"
                :class="selectedPlayers.includes(player.id)
                  ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white'
                  : 'border-[#ff2d55]/70 bg-white/5 text-white/80 hover:bg-white/10'"
                @click="togglePlayerSelection(player.id)"
              >
                {{ player.name }}
              </button>
            </div>
          </div>

          <div class="mb-6">
            <h2 class="text-3xl md:text-4xl font-black mb-3">Select Sessions</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <button
                v-for="session in sessionOptions"
                :key="session.key"
                type="button"
                class="rounded-full border px-4 py-3 text-lg font-bold transition"
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
            class="w-full rounded-xl bg-[#ff2d55] py-4 text-3xl font-black tracking-wide hover:opacity-95 transition"
            @click="openStatistics"
          >
            Show Statistics
          </button>
          <p class="mt-4 text-center text-xl text-white/55">Results open in a new screen.</p>
        </div>
      </div>
    </div>
  </Layout>
</template>
