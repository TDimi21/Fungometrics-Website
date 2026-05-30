<script setup>
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'

const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const loading = ref(false)
const loadError = ref('')
const board = ref([])

const loadCoachView = async () => {
  loadError.value = ''
  board.value = []

  const teamId = team.value?.id
  if (!teamId) {
    loadError.value = 'Select a team to view coach rollups.'
    return
  }

  loading.value = true
  try {
    const { data } = await axiosGet(`coach/teams/${teamId}/player-development-board`)
    board.value = Array.isArray(data?.data) ? data.data : []
    if (!board.value.length) {
      loadError.value = 'No development records found for this team yet.'
    }
  } catch (error) {
    loadError.value = 'Unable to load coach rollups from live API.'
  } finally {
    loading.value = false
  }
}

watch(() => team.value?.id, () => { loadCoachView() }, { immediate: true })

const totals = computed(() => {
  const rows = board.value
  const players = rows.length

  const byStatus = rows.reduce((acc, p) => {
    const key = p.status || 'no_data'
    acc[key] = (acc[key] || 0) + 1
    return acc
  }, {})

  const averagePdi = players
    ? Math.round(rows.reduce((sum, p) => sum + (p?.scores?.overall ?? 0), 0) / players)
    : null

  const avgCoverage = players
    ? Math.round(rows.reduce((sum, p) => sum + (p?.coverage?.total ?? 0), 0) / players)
    : null

  return {
    players,
    averagePdi,
    avgCoverage,
    hot: byStatus.hot || 0,
    improving: byStatus.improving || 0,
    steady: byStatus.steady || 0,
    needsWork: byStatus.needs_work || 0,
    noData: byStatus.no_data || 0,
  }
})

const lowestCoverage = computed(() => {
  return [...board.value]
    .sort((a, b) => (a?.coverage?.total ?? 0) - (b?.coverage?.total ?? 0))
    .slice(0, 5)
})
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

      <h1 class="text-2xl font-semibold text-white">Coach Development Dashboard</h1>
      <p class="text-slate-300">Live coach rollups across the selected team.</p>

      <div v-if="loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading coach development rollups...
      </div>
      <div v-if="loadError" class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-400">Players</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ totals.players }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-400">Team Avg PDI</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ totals.averagePdi ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-400">Avg Sessions (30d)</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ totals.avgCoverage ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <p class="text-xs uppercase tracking-wide text-slate-400">At-Risk Players</p>
          <p class="mt-2 text-2xl font-semibold text-white">{{ totals.needsWork + totals.noData }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <h3 class="text-lg font-semibold text-white">Status Breakdown</h3>
          <ul class="mt-3 space-y-1 text-sm text-slate-300">
            <li>Hot: {{ totals.hot }}</li>
            <li>Improving: {{ totals.improving }}</li>
            <li>Steady: {{ totals.steady }}</li>
            <li>Needs Work: {{ totals.needsWork }}</li>
            <li>No Data: {{ totals.noData }}</li>
          </ul>
        </div>

        <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
          <h3 class="text-lg font-semibold text-white">Lowest Session Coverage</h3>
          <ul class="mt-3 space-y-1 text-sm text-slate-300">
            <li v-for="p in lowestCoverage" :key="p.id">
              {{ p.name }} — {{ p.coverage?.total ?? 0 }} sessions
            </li>
            <li v-if="lowestCoverage.length === 0">No rows available.</li>
          </ul>
        </div>
      </div>
    </div>
  </Layout>
</template>
