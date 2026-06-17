<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const teams = ref([])
const coachesMap = ref({})
const loading = ref(false)
const search = ref('')
const selectedTeam = ref(null)
const codeCopied = ref(false)

async function fetchAllPages(endpoint) {
  const all = []
  let page = 1
  while (true) {
    const sep = endpoint.includes('?') ? '&' : '?'
    const res = await axiosGet(`${endpoint}${sep}page=${page}`)
    const inner = res.data?.data
    const arr = Array.isArray(inner?.data) ? inner.data : (Array.isArray(inner) ? inner : [])
    all.push(...arr)
    if (arr.length === 0 || (inner?.last_page != null && page >= inner.last_page)) break
    page++
  }
  return all
}

async function fetchTeams() {
  loading.value = true
  try {
    const allPlayers = await fetchAllPages('coach/search/players?search=')
    const teamMap = new Map()
    for (const player of allPlayers) {
      const playerTeams = Array.isArray(player.actual_team) ? player.actual_team : []
      for (const t of playerTeams) {
        const name = t.name?.trim()
        if (!name) continue
        const key = t.id ?? name
        if (!teamMap.has(key)) teamMap.set(key, { id: t.id ?? null, key, name, join_code: t.join_code ?? '', players: [] })
        const fullName = player.name?.full ?? `${player.name?.first ?? ''} ${player.name?.last ?? ''}`.trim()
        if (fullName) teamMap.get(key).players.push({ id: player.id, name: fullName })
      }
    }
    teams.value = Array.from(teamMap.values()).sort((a, b) => a.name.localeCompare(b.name))

    const coachRes = await axiosGet('coach/roster/coaches')
    const coachList = Array.isArray(coachRes.data) ? coachRes.data : []
    const map = {}
    for (const c of coachList) {
      const tid = c.team_associate
      if (tid != null) {
        if (!map[tid]) map[tid] = []
        const fullName = c.name?.full ?? `${c.name?.first ?? ''} ${c.name?.last ?? ''}`.trim()
        if (fullName) map[tid].push({ id: c.coach_id, name: fullName })
      }
    }
    coachesMap.value = map
  } catch (e) {
    console.error('AdminTeams fetch error', e)
  }
  loading.value = false
}

onMounted(fetchTeams)

const filtered = computed(() => {
  if (!search.value) return teams.value
  return teams.value.filter(t => t.name.toLowerCase().includes(search.value.toLowerCase()))
})

function teamInitials(name) {
  return name.split(' ').slice(0, 2).map(w => w[0] || '').join('').toUpperCase()
}

function copyCode(code) {
  navigator.clipboard.writeText(code).catch(() => {})
  codeCopied.value = true
  setTimeout(() => { codeCopied.value = false }, 2000)
}

function openTeam(team) {
  codeCopied.value = false
  selectedTeam.value = team
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
      <h1 class="text-white text-xl font-bold">Teams</h1>
    </div>

    <!-- Search -->
    <div class="bg-white/5 border border-white/10 rounded-xl px-4 mb-3">
      <input v-model="search" class="w-full bg-transparent text-white text-sm py-3 outline-none placeholder-white/30" placeholder="Search teams..." />
    </div>
    <p class="text-white/35 text-xs mb-4">{{ filtered.length }} teams registered</p>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- List -->
    <div v-else class="space-y-2">
      <p v-if="!filtered.length" class="text-white/30 text-sm text-center py-10">No teams found.</p>
      <button
        v-for="team in filtered" :key="team.key"
        class="w-full flex items-center gap-4 bg-white/5 border border-white/8 rounded-xl p-4 hover:bg-white/10 transition-colors text-left"
        @click="openTeam(team)"
      >
        <div class="w-11 h-11 rounded-full bg-amber-500/20 flex items-center justify-center font-black text-amber-400 text-sm flex-shrink-0">
          {{ teamInitials(team.name) }}
        </div>
        <div class="flex-1">
          <p class="text-white font-bold text-sm">{{ team.name }}</p>
          <p class="text-white/40 text-xs mt-0.5">{{ team.players.length }} players</p>
        </div>
        <div class="text-center flex-shrink-0 mr-2">
          <p class="text-amber-400 font-black text-lg">{{ team.players.length }}</p>
          <p class="text-white/30 text-xs">players</p>
        </div>
        <svg class="w-4 h-4 text-white/25 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Team Detail Modal -->
    <Transition name="slide-up">
      <div v-if="selectedTeam" class="fixed inset-0 z-50 flex items-end" @click.self="selectedTeam = null">
        <div class="absolute inset-0 bg-black/75" @click="selectedTeam = null"></div>
        <div class="relative w-full bg-[#12172B] border-t border-white/10 rounded-t-3xl p-6 max-h-[85vh] flex flex-col">
          <!-- Modal header -->
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-white font-black text-lg flex-1 pr-4">{{ selectedTeam.name }}</h2>
            <button class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20" @click="selectedTeam = null">✕</button>
          </div>

          <!-- Join Code -->
          <div class="flex items-center gap-4 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-5">
            <div class="flex-1">
              <p class="text-amber-400 text-xs font-bold tracking-widest mb-1">TEAM CODE</p>
              <p class="text-white text-2xl font-black tracking-widest">{{ selectedTeam.join_code || '—' }}</p>
            </div>
            <button v-if="selectedTeam.join_code"
              class="bg-amber-400 text-black font-bold text-sm px-4 py-2 rounded-lg hover:bg-amber-300 transition-colors"
              @click="copyCode(selectedTeam.join_code)">
              {{ codeCopied ? '✓ Copied' : 'Copy' }}
            </button>
          </div>

          <!-- Coaches & Players -->
          <div class="overflow-y-auto flex-1">
            <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-3">
              COACHES ({{ (selectedTeam.id !== null ? coachesMap[selectedTeam.id] : null)?.length ?? 0 }})
            </p>
            <template v-if="selectedTeam.id !== null && coachesMap[selectedTeam.id]?.length">
              <div v-for="c in coachesMap[selectedTeam.id]" :key="c.id" class="flex items-center gap-3 py-2 border-b border-white/5">
                <div class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></div>
                <span class="text-white text-sm">{{ c.name }}</span>
              </div>
            </template>
            <p v-else class="text-white/25 text-sm mb-4 pl-5">No coaches found</p>

            <p class="text-white/35 text-xs font-bold tracking-widest uppercase mt-5 mb-3">
              PLAYERS ({{ selectedTeam.players.length }})
            </p>
            <div v-for="(p, i) in selectedTeam.players" :key="p.id ?? i" class="flex items-center gap-3 py-2 border-b border-white/5 last:border-0">
              <div class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></div>
              <span class="text-white text-sm">{{ p.name }}</span>
            </div>
            <p v-if="!selectedTeam.players.length" class="text-white/25 text-sm pl-5">No players found</p>
          </div>
        </div>
      </div>
    </Transition>
  </Layout>
</template>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active { transition: opacity 0.25s, transform 0.25s; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; }
</style>
