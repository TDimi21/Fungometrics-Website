<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const teams      = ref([])
const coachesMap = ref({})   // keyed by team_id → [{ id, name }]
const loading    = ref(false)
const search     = ref('')
const selected   = ref(null)
const codeCopied = ref(false)

// players: res.data.data is flat array (SearchPlayersResource)
async function fetchAllPages(endpoint) {
  const all = []
  let page = 1
  while (true) {
    try {
      const sep   = endpoint.includes('?') ? '&' : '?'
      const res   = await axiosGet(`${endpoint}${sep}page=${page}`)
      const outer = res.data?.data
      const arr   = Array.isArray(outer?.data) ? outer.data
                  : Array.isArray(outer)        ? outer
                  : []
      if (!arr.length) break
      all.push(...arr)
      if (outer?.last_page != null && page >= outer.last_page) break
      page++
    } catch (_) { break }
  }
  return all
}

async function fetchTeams() {
  loading.value = true
  try {
    // Players come back with actual_team (SearchPlayersResource renames teams → actual_team)
    const allPlayers = await fetchAllPages('coach/search/players?search=')

    const teamMap = new Map()
    for (const player of allPlayers) {
      const playerTeams = Array.isArray(player.actual_team) ? player.actual_team : []
      for (const t of playerTeams) {
        const name = t.name?.trim()
        if (!name) continue
        const key = t.id ?? name
        if (!teamMap.has(key)) {
          teamMap.set(key, { id: t.id ?? null, key, name, join_code: t.join_code ?? '', players: [] })
        }
        // player name: SearchPlayersResource gives name.full
        const fullName = player.name?.full
          ?? `${player.name?.first ?? ''} ${player.name?.last ?? ''}`.trim()
          ?? player._full
        if (fullName) teamMap.get(key).players.push({ id: player.id, name: fullName })
      }
    }
    teams.value = Array.from(teamMap.values()).sort((a, b) => a.name.localeCompare(b.name))

    // Coaches per team — GetCoachesList (CoachesResource) returns team_associate = team_id
    try {
      const coachRes  = await axiosGet('coach/roster/coaches')
      const coachList = Array.isArray(coachRes.data?.data)
        ? coachRes.data.data
        : Array.isArray(coachRes.data)
        ? coachRes.data
        : []
      const map = {}
      for (const c of coachList) {
        const tid = c.team_associate
        if (tid != null) {
          if (!map[tid]) map[tid] = []
          const fullName = c.name?.full ?? `${c.name?.first ?? ''} ${c.name?.last ?? ''}`.trim()
          if (fullName) map[tid].push({ id: c.coach_id ?? c.id, name: fullName })
        }
      }
      coachesMap.value = map
    } catch (_) {}

  } catch (e) {
    console.error('AdminTeams error', e)
  }
  loading.value = false
}

onMounted(fetchTeams)

const filtered = computed(() => {
  if (!search.value) return teams.value
  return teams.value.filter(t => t.name.toLowerCase().includes(search.value.toLowerCase()))
})

function initials(name) {
  return name.split(' ').slice(0, 2).map(w => w[0] || '').join('').toUpperCase()
}

function copyCode(code) {
  navigator.clipboard.writeText(code).catch(() => {})
  codeCopied.value = true
  setTimeout(() => { codeCopied.value = false }, 2000)
}

function openTeam(team) {
  codeCopied.value = false
  selected.value   = team
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
      <input v-model="search"
        class="w-full bg-transparent text-white text-sm py-3 outline-none placeholder-white/30"
        placeholder="Search teams..." />
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
          {{ initials(team.name) }}
        </div>
        <div class="flex-1">
          <p class="text-white font-bold text-sm">{{ team.name }}</p>
          <p class="text-white/40 text-xs mt-0.5">
            {{ team.players.length }} players
            <span v-if="team.id !== null && coachesMap[team.id]?.length">
              · {{ coachesMap[team.id].length }} coaches
            </span>
          </p>
          <p v-if="team.join_code" class="text-amber-400/60 text-xs mt-0.5 font-mono">{{ team.join_code }}</p>
        </div>
        <div class="text-center flex-shrink-0 mr-2">
          <p class="text-amber-400 font-black text-xl">{{ team.players.length }}</p>
          <p class="text-white/30 text-xs">players</p>
        </div>
        <svg class="w-4 h-4 text-white/25 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Team Detail Slide-up -->
    <Transition name="slide-up">
      <div v-if="selected" class="fixed inset-0 z-50 flex items-end">
        <div class="absolute inset-0 bg-black/75" @click="selected = null"></div>
        <div class="relative w-full bg-[#12172B] border-t border-white/10 rounded-t-3xl p-6 max-h-[85vh] flex flex-col">

          <!-- Modal header -->
          <div class="flex items-center justify-between mb-5">
            <h2 class="text-white font-black text-lg flex-1 pr-4">{{ selected.name }}</h2>
            <button class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white hover:bg-white/20"
              @click="selected = null">✕</button>
          </div>

          <!-- Join Code -->
          <div v-if="selected.join_code"
            class="flex items-center gap-4 bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-5">
            <div class="flex-1">
              <p class="text-amber-400 text-xs font-bold tracking-widest mb-1">TEAM CODE</p>
              <p class="text-white text-2xl font-black tracking-widest font-mono">{{ selected.join_code }}</p>
            </div>
            <button
              class="bg-amber-400 text-black font-bold text-sm px-4 py-2 rounded-lg hover:bg-amber-300 transition-colors"
              @click="copyCode(selected.join_code)">
              {{ codeCopied ? '✓ Copied' : 'Copy' }}
            </button>
          </div>
          <div v-else class="bg-white/5 border border-white/10 rounded-xl p-3 mb-5 text-center">
            <p class="text-white/30 text-xs">No join code available</p>
          </div>

          <!-- Scrollable roster -->
          <div class="overflow-y-auto flex-1 space-y-1">
            <!-- Coaches -->
            <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-2">
              COACHES ({{ selected.id !== null ? (coachesMap[selected.id]?.length ?? 0) : 0 }})
            </p>
            <template v-if="selected.id !== null && coachesMap[selected.id]?.length">
              <div v-for="c in coachesMap[selected.id]" :key="c.id"
                class="flex items-center gap-3 py-2.5 border-b border-white/5">
                <div class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></div>
                <span class="text-white text-sm">{{ c.name }}</span>
              </div>
            </template>
            <p v-else class="text-white/25 text-sm mb-3 pl-5">No coaches found</p>

            <!-- Players -->
            <p class="text-white/35 text-xs font-bold tracking-widest uppercase mt-5 mb-2">
              PLAYERS ({{ selected.players.length }})
            </p>
            <div v-for="(p, i) in selected.players" :key="p.id ?? i"
              class="flex items-center gap-3 py-2.5 border-b border-white/5 last:border-0">
              <div class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></div>
              <span class="text-white text-sm">{{ p.name }}</span>
            </div>
            <p v-if="!selected.players.length" class="text-white/25 text-sm pl-5">No players found</p>
          </div>
        </div>
      </div>
    </Transition>
  </Layout>
</template>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active { transition: opacity 0.25s; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; }
</style>
