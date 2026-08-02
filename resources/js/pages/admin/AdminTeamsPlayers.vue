<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { states, coachLevels } from '@/utils/index.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

// Players default to LevelTypes::PLAYER server-side when registration never
// collected a level, so surface it as a real filterable option.
const levels = { ...coachLevels, PLAYER: 'Player (ungraded)' }

const TABS = ['Teams', 'Players']
const activeTab = ref('Teams')

const teams = ref([])
const players = ref([])
const loading = ref(false)
const stateFilter = ref('')
const levelFilter = ref('')

async function fetchTeams() {
  const params = {}
  if (stateFilter.value) params.state = stateFilter.value
  const res = await axiosGet('admin/teams', params)
  teams.value = res.data?.data ?? []
}

async function fetchPlayers() {
  const params = {}
  if (stateFilter.value) params.state = stateFilter.value
  if (levelFilter.value) params.level = levelFilter.value
  const res = await axiosGet('admin/players', params)
  players.value = res.data?.data ?? []
}

async function fetchActive() {
  loading.value = true
  try {
    if (activeTab.value === 'Teams') await fetchTeams()
    else await fetchPlayers()
  } catch (e) {
    console.error('AdminTeamsPlayers fetch error', e)
  }
  loading.value = false
}

onMounted(fetchActive)
watch([activeTab, stateFilter, levelFilter], fetchActive)

function groupByState(list, getState) {
  const map = new Map()
  for (const item of list) {
    const key = getState(item) || 'UNSET'
    if (!map.has(key)) map.set(key, [])
    map.get(key).push(item)
  }
  return Array.from(map.entries())
    .sort((a, b) => a[0].localeCompare(b[0]))
    .map(([code, items]) => ({ code, label: states[code] || 'Unspecified', items }))
}

const groupedTeams = computed(() => groupByState(teams.value, t => t.state))
const groupedPlayers = computed(() => groupByState(players.value, p => p.state))

function levelLabel(code) {
  return levels[code] || code || '—'
}

function switchTab(tab) {
  activeTab.value = tab
  levelFilter.value = ''
}
</script>

<template>
  <Layout>
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <button class="text-white/50 hover:text-white" @click="router.push({ name: 'admin.dashboard' })">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-white text-xl font-bold">Teams & Players by State/Level</h1>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
      <button
        v-for="tab in TABS" :key="tab"
        class="px-4 py-2 rounded-lg text-sm font-bold transition-colors"
        :class="activeTab === tab ? 'bg-app-red text-white' : 'bg-white/5 text-white/50 hover:bg-white/10'"
        @click="switchTab(tab)"
      >{{ tab }}</button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-4">
      <select v-model="stateFilter"
        class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white outline-none">
        <option value="">All States</option>
        <option v-for="(label, code) in states" :key="code" :value="code">{{ label }}</option>
      </select>

      <select v-if="activeTab === 'Players'" v-model="levelFilter"
        class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white outline-none">
        <option value="">All Levels</option>
        <option v-for="(label, code) in levels" :key="code" :value="code">{{ label }}</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Teams grouped by state -->
    <div v-else-if="activeTab === 'Teams'" class="space-y-6">
      <p v-if="!groupedTeams.length" class="text-white/30 text-sm text-center py-10">No teams found.</p>
      <div v-for="group in groupedTeams" :key="group.code">
        <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-2">
          {{ group.label }} · {{ group.items.length }}
        </p>
        <div class="space-y-2">
          <div v-for="team in group.items" :key="team.id"
            class="flex items-center gap-4 bg-white/5 border border-white/8 rounded-xl p-4">
            <div class="flex-1">
              <p class="text-white font-bold text-sm">{{ team.name }}</p>
              <p class="text-white/40 text-xs mt-0.5">
                {{ team.players_count }} players · {{ team.coaches_count }} coaches
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Players grouped by state -->
    <div v-else class="space-y-6">
      <p v-if="!groupedPlayers.length" class="text-white/30 text-sm text-center py-10">No players found.</p>
      <div v-for="group in groupedPlayers" :key="group.code">
        <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-2">
          {{ group.label }} · {{ group.items.length }}
        </p>
        <div class="space-y-2">
          <div v-for="player in group.items" :key="player.id"
            class="flex items-center gap-4 bg-white/5 border border-white/8 rounded-xl p-4">
            <div class="flex-1">
              <p class="text-white font-bold text-sm">{{ player.name?.full || player.name }}</p>
              <p class="text-white/40 text-xs mt-0.5">{{ player.team?.name || 'No team' }}</p>
            </div>
            <div class="bg-white/10 text-white/70 text-xs font-bold px-2.5 py-1 rounded-lg flex-shrink-0">
              {{ levelLabel(player.level) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
