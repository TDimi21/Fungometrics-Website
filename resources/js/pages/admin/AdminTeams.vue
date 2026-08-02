<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const teams        = ref([])
const coachesMap   = ref({})   // keyed by team_id → [{ id, name }]
const loading      = ref(false)
const rosterLoading = ref(false)
const search       = ref('')
const selected     = ref(null)
const codeCopied   = ref(false)

// Single unpaginated, org-wide call with counts pre-aggregated server-side —
// this used to page through every player across the throttled (30 req/min)
// coach/search/players endpoint just to group them by team client-side.
// See AdminDirectoryController.
async function fetchTeams() {
  loading.value = true
  try {
    const res  = await axiosGet('admin/teams')
    const rows = Array.isArray(res.data?.data) ? res.data.data : []
    teams.value = rows
      .map(t => ({
        id: t.id,
        key: t.id,
        name: t.name,
        join_code: t.join_code ?? '',
        players_count: t.players_count ?? 0,
        coaches_count: t.coaches_count ?? 0,
      }))
      .sort((a, b) => a.name.localeCompare(b.name))
  } catch (e) {
    console.error('AdminTeams error', e)
  }
  loading.value = false
}

onMounted(fetchTeams)

// Roster (coaches + players) for one team, loaded lazily only when its
// detail sheet is opened — not fetched up front for every team.
async function loadRoster(team) {
  if (coachesMap.value[team.id]) return // already loaded
  rosterLoading.value = true
  try {
    const [coachRes, playerRes] = await Promise.all([
      axiosGet('admin/coaches', { team_id: team.id }),
      axiosGet('admin/players', { team_id: team.id }),
    ])
    const coachRows  = Array.isArray(coachRes.data?.data) ? coachRes.data.data : []
    const playerRows = Array.isArray(playerRes.data?.data) ? playerRes.data.data : []
    coachesMap.value = {
      ...coachesMap.value,
      [team.id]: coachRows.map(c => ({
        id: c.id,
        name: `${c.profile?.first_name ?? ''} ${c.profile?.last_name ?? ''}`.trim(),
      })),
    }
    if (selected.value?.id === team.id) {
      selected.value = { ...selected.value, players: playerRows.map(p => ({ id: p.id, name: p.name?.full ?? '' })) }
    }
  } catch (e) {
    console.error('AdminTeams roster error', e)
  }
  rosterLoading.value = false
}

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
  selected.value   = { ...team, players: [] }
  loadRoster(team)
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
            {{ team.players_count }} players · {{ team.coaches_count }} coaches
          </p>
          <p v-if="team.join_code" class="text-amber-400/60 text-xs mt-0.5 font-mono">{{ team.join_code }}</p>
        </div>
        <div class="text-center flex-shrink-0 mr-2">
          <p class="text-amber-400 font-black text-xl">{{ team.players_count }}</p>
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

          <!-- Roster loading -->
          <div v-if="rosterLoading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
          </div>

          <!-- Scrollable roster -->
          <div v-else class="overflow-y-auto flex-1 space-y-1">
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
