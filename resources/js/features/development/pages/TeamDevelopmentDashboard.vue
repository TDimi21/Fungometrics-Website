<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import DevelopmentLeaderboard from '../components/DevelopmentLeaderboard.vue'
import PlayerDevelopmentBoard from '../components/PlayerDevelopmentBoard.vue'
import dummy from '../data/dummyPlayerDevelopmentData'
import { buildPlayerDevelopmentModel } from '../lib/playerDevelopmentScore'

const router = useRouter()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const loading = ref(false)
const loadError = ref('')
const board = ref([])

const fallbackPlayers = computed(() => {
  const base = buildPlayerDevelopmentModel(dummy.current, dummy.history, dummy.player.role)
  return [
    { id: 'demo-1', name: 'Carter Jensen', developmentIndex: base.developmentIndex, status: base.status, trend: base.trend.status },
    { id: 'demo-2', name: 'Mason Diaz', developmentIndex: Math.max(0, base.developmentIndex - 5), status: 'steady', trend: 'steady' },
    { id: 'demo-3', name: 'Ryan Brooks', developmentIndex: Math.min(100, base.developmentIndex + 4), status: 'improving', trend: 'up' },
  ]
})

const normalizeStatus = (status) => {
  const map = {
    hot: 'Hot',
    improving: 'Improving',
    steady: 'Steady',
    needs_work: 'Needs Work',
    no_data: 'No Data',
  }
  return map[status] || status || '—'
}

const normalizeTrend = (trend) => {
  const map = { up: 'Improving', down: 'Declining', steady: 'Steady' }
  return map[trend] || trend || '—'
}

const loadTeamBoard = async () => {
  loadError.value = ''
  board.value = []

  const teamId = team.value?.id
  if (!teamId) {
    loadError.value = 'Select a team to load live development data. Showing demo data.'
    return
  }

  loading.value = true
  try {
    const { data } = await axiosGet(`coach/teams/${teamId}/player-development-board`)
    board.value = Array.isArray(data?.data) ? data.data : []

    if (!board.value.length) {
      loadError.value = 'No live development records found for this team yet. Showing demo data.'
    }
  } catch (error) {
    loadError.value = 'Live API load failed. Showing demo data.'
  } finally {
    loading.value = false
  }
}

watch(() => team.value?.id, () => { loadTeamBoard() }, { immediate: true })

const players = computed(() => {
  const src = board.value.length ? board.value : fallbackPlayers.value

  return src
    .map((p) => ({
      id: p.id,
      name: p.name,
      developmentIndex: p.developmentIndex ?? p?.scores?.overall ?? null,
      status: normalizeStatus(p.status),
      trend: normalizeTrend(p.trend),
    }))
    .sort((a, b) => (b.developmentIndex ?? 0) - (a.developmentIndex ?? 0))
})

const openPlayer = (playerId) => {
  if (!playerId || String(playerId).startsWith('demo-')) {
    return
  }

  router.push(`/development/player/${playerId}`)
}
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-7xl space-y-4 px-4 py-6">
      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink to="/dashboard?tab=development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back to Dashboard</RouterLink>
          <RouterLink to="/development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
          <RouterLink to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
          <RouterLink to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
          <RouterLink to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
        </div>
      </div>

      <h1 class="text-2xl font-semibold text-white">Team Development Dashboard</h1>
      <p class="text-sm text-slate-300">Live team board from current FMTRX sessions + fitness data.</p>

      <div v-if="loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading live development board...
      </div>
      <div v-if="loadError" class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <DevelopmentLeaderboard :players="players" />
        <PlayerDevelopmentBoard :players="players" />
      </div>

      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
        <h3 class="text-lg font-semibold text-white">Open Player Development</h3>
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            v-for="p in players"
            :key="p.id"
            class="rounded-md border border-white/15 bg-slate-800 px-3 py-1 text-sm text-slate-200 hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="String(p.id).startsWith('demo-')"
            @click="openPlayer(p.id)"
          >
            {{ p.name }}
          </button>
        </div>
      </div>
    </div>
  </Layout>
</template>
