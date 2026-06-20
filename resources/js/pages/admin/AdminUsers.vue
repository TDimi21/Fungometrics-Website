<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { PLAN_LABELS } from '@/utils/plans.js'

const router = useRouter()
const route  = useRoute()
const { axiosGet } = useAxiosAuth()

const TABS = ['All', 'Coaches', 'Players']
const TAB_COLORS = {
  Coach:  { bg: 'rgba(59,130,246,0.18)', text: '#60A5FA' },
  Player: { bg: 'rgba(22,163,74,0.18)',  text: '#4ADE80' },
}

const coaches  = ref([])
const players  = ref([])
const loading  = ref(false)
const search   = ref('')
const apiError = ref('')   // shows actual API error instead of silent "no users found"
const activeTab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'All')

// ── Bulk upgrade ─────────────────────────────────────────────────────────────
const bulk = ref({ running: false, done: 0, total: 0, errors: 0, msg: '' })

async function bulkSetPro(role) {
  const plan = role === 'Coach' ? 'coach_pro' : 'player_pro'
  const list = role === 'Coach' ? coaches.value : players.value
  if (!list.length) return
  if (!confirm(`Set all ${list.length} ${role.toLowerCase()}s to ${plan === 'coach_pro' ? 'Coach Pro' : 'Player Pro'}?`)) return

  bulk.value = { running: true, done: 0, total: list.length, errors: 0, msg: '' }

  const apiBase = import.meta.env.VITE_API_ENDPOINT || ''
  let token = ''
  try {
    token = JSON.parse(localStorage.getItem('auth') || '{}')?.token
           || JSON.parse(sessionStorage.getItem('auth') || '{}')?.token || ''
  } catch (_) {}

  for (const user of list) {
    try {
      const res = await fetch(`${apiBase}admin/users/${user.id}/plan`, {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body:    JSON.stringify({ subscription_plan: plan }),
      })
      if (res.ok) {
        bulk.value.done++
        user._plan = plan
        user.subscription_plan = plan
      } else {
        bulk.value.errors++
      }
    } catch (_) {
      bulk.value.errors++
    }
  }

  bulk.value.running = false
  const label = plan === 'coach_pro' ? 'Coach Pro' : 'Player Pro'
  bulk.value.msg = bulk.value.errors
    ? `Done — ${bulk.value.done} updated, ${bulk.value.errors} failed.`
    : `All ${bulk.value.done} ${role.toLowerCase()}s upgraded to ${label}.`
  setTimeout(() => { bulk.value.msg = '' }, 6000)
}

function formatDate(val) {
  if (!val) return ''
  try {
    return new Date(val).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch (_) { return '' }
}

// coaches: profile.first_name (User + profile eager-loaded)
// players: name.first (SearchPlayersResource transforms field names)
function normalizeUser(u, role) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first   = String(nameObj?.first || u.first_name || u.profile?.first_name || '').trim()
  const last    = String(nameObj?.last  || u.last_name  || u.profile?.last_name  || '').trim()
  const full    = nameObj?.full || (first && last ? `${first} ${last}` : first || last || u.email || 'Unknown')
  // avatar: coaches have profile.picture; players have avatar (renamed by resource)
  const avatar  = u.avatar || u.profile?.picture || null
  // teams: players have actual_team (renamed by SearchPlayersResource); coaches have none here
  const teams   = Array.isArray(u.actual_team) ? u.actual_team : []
  // plan: coaches have subscription_plan on User model; players don't (not selected by SearchPlayers)
  const plan    = u.subscription_plan || null

  return {
    ...u,
    _first:  first,
    _last:   last,
    _full:   full,
    _email:  String(u.email || u.phone || ''),
    _role:   role,
    _avatar: avatar,
    _teams:  teams,
    _plan:   plan,
  }
}

function dedupeById(arr) {
  const seen = new Set()
  return arr.filter(u => {
    const id = u.id ?? u.user_id
    if (id == null) return true
    if (seen.has(id)) return false
    seen.add(id)
    return true
  })
}

// coaches: res.data.data is Laravel paginator { data:[...], last_page }
// players: res.data.data is flat array (SearchPlayersResource, no last_page exposed)
async function fetchAllPages(endpoint, label) {
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
    } catch (err) {
      const status  = err?.response?.status
      const message = err?.response?.data?.message || err?.message || 'Unknown error'
      // 404 = "no results" from the backend (not a real error for page overflow)
      if (status !== 404) {
        apiError.value += `${label}: HTTP ${status ?? '?'} — ${message}. `
      }
      break
    }
  }
  return all
}

async function fetchAll() {
  loading.value  = true
  apiError.value = ''
  try {
    const [rawCoaches, rawPlayers] = await Promise.all([
      fetchAllPages('coach/search/coaches?search=', 'Coaches'),
      fetchAllPages('coach/search/players?search=', 'Players'),
    ])
    coaches.value = dedupeById(rawCoaches)
      .map(u => normalizeUser(u, 'Coach'))
      .sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
    players.value = dedupeById(rawPlayers)
      .map(u => normalizeUser(u, 'Player'))
      .sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
  } catch (e) {
    apiError.value = `Fetch failed: ${e?.message || e}`
  }
  loading.value = false
}

onMounted(fetchAll)

const baseList = computed(() => {
  if (activeTab.value === 'Coaches') return coaches.value
  if (activeTab.value === 'Players') return players.value
  return [...coaches.value, ...players.value]
    .sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
})

const filtered = computed(() => {
  if (!search.value) return baseList.value
  const q = search.value.toLowerCase()
  return baseList.value.filter(u =>
    u._full.toLowerCase().includes(q) || u._email.toLowerCase().includes(q)
  )
})

function goToDetail(user) {
  try { sessionStorage.setItem(`admin_user_${user.id}`, JSON.stringify(user)) } catch (_) {}
  router.push({ name: 'admin.user-detail', params: { id: user.id } })
}

const headerTitle = computed(() => {
  if (activeTab.value === 'Coaches') return 'Coaches'
  if (activeTab.value === 'Players') return 'Players'
  return 'Users'
})
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
      <h1 class="text-white text-xl font-bold">{{ headerTitle }}</h1>
    </div>

    <!-- Bulk Upgrade Actions -->
    <div class="bg-white/4 border border-white/10 rounded-xl p-4 mb-4">
      <p class="text-white/35 text-xs font-black tracking-widest uppercase mb-3">Bulk Actions</p>
      <div class="flex gap-2 flex-wrap">
        <button
          class="flex items-center gap-2 bg-blue-600/20 border border-blue-500/40 text-blue-300 text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-blue-600/30 transition-colors disabled:opacity-40"
          :disabled="bulk.running || !coaches.length"
          @click="bulkSetPro('Coach')">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          Set All Coaches → Coach Pro
          <span v-if="coaches.length" class="bg-blue-500/30 text-blue-200 text-xs px-1.5 py-0.5 rounded ml-1">{{ coaches.length }}</span>
        </button>
        <button
          class="flex items-center gap-2 bg-green-700/20 border border-green-500/40 text-green-300 text-xs font-bold px-4 py-2.5 rounded-xl hover:bg-green-700/30 transition-colors disabled:opacity-40"
          :disabled="bulk.running || !players.length"
          @click="bulkSetPro('Player')">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
          </svg>
          Set All Players → Player Pro
          <span v-if="players.length" class="bg-green-600/30 text-green-200 text-xs px-1.5 py-0.5 rounded ml-1">{{ players.length }}</span>
        </button>
      </div>

      <!-- Progress -->
      <div v-if="bulk.running" class="mt-3">
        <div class="flex items-center justify-between text-xs text-white/40 mb-1.5">
          <span>Upgrading accounts...</span>
          <span>{{ bulk.done }} / {{ bulk.total }}</span>
        </div>
        <div class="w-full bg-white/10 rounded-full h-1.5 overflow-hidden">
          <div class="h-full bg-app-red rounded-full transition-all"
            :style="{ width: `${bulk.total ? (bulk.done / bulk.total) * 100 : 0}%` }"></div>
        </div>
      </div>
      <p v-if="bulk.msg && !bulk.running"
        class="text-xs mt-2 font-semibold"
        :class="bulk.errors ? 'text-amber-400' : 'text-green-400'">
        {{ bulk.msg }}
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex bg-white/5 rounded-xl p-1 mb-4">
      <button
        v-for="tab in TABS" :key="tab"
        class="flex-1 py-2 rounded-lg text-sm font-semibold transition-colors"
        :class="activeTab === tab ? 'bg-app-red text-white' : 'text-white/40 hover:text-white'"
        @click="activeTab = tab; search = ''"
      >{{ tab }}</button>
    </div>

    <!-- Search -->
    <div class="bg-white/5 border border-white/10 rounded-xl px-4 mb-3">
      <input
        v-model="search"
        class="w-full bg-transparent text-white text-sm py-3 outline-none placeholder-white/30"
        placeholder="Search name, email, phone..."
      />
    </div>

    <p class="text-white/35 text-xs mb-4">
      {{ filtered.length }} {{ activeTab === 'All' ? 'users' : activeTab.toLowerCase() }}
    </p>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- API Error Banner -->
    <div v-if="!loading && apiError"
      class="bg-red-900/30 border border-app-red/50 rounded-xl p-4 mb-4 text-sm">
      <p class="text-app-red font-bold mb-1">API Error — could not load users</p>
      <p class="text-white/60 text-xs font-mono break-all">{{ apiError }}</p>
      <button class="mt-3 text-xs text-white/50 underline hover:text-white" @click="fetchAll">Retry</button>
    </div>

    <!-- List -->
    <div v-else-if="!loading" class="space-y-2">
      <p v-if="!filtered.length" class="text-white/30 text-sm text-center py-10">No users found.</p>
      <button
        v-for="user in filtered" :key="`${user._role}-${user.id}`"
        class="w-full flex items-center gap-3 bg-white/5 border border-white/8 rounded-xl p-3 hover:bg-white/10 transition-colors text-left"
        @click="goToDetail(user)"
      >
        <!-- Avatar circle with initial -->
        <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-base flex-shrink-0"
          :style="{ backgroundColor: TAB_COLORS[user._role]?.bg }">
          <span :style="{ color: TAB_COLORS[user._role]?.text }">
            {{ (user._first[0] || user._full[0] || '?').toUpperCase() }}
          </span>
        </div>

        <div class="flex-1 min-w-0">
          <p class="text-white font-semibold text-sm truncate">{{ user._full }}</p>
          <p class="text-white/40 text-xs mt-0.5 truncate">{{ user._email || '—' }}</p>

          <!-- coaches: show plan; players: show team names -->
          <p v-if="user._role === 'Coach' && user._plan" class="text-app-gold text-xs mt-0.5 font-semibold">
            {{ PLAN_LABELS[user._plan] ?? user._plan }}
          </p>
          <p v-else-if="user._role === 'Player' && user._teams.length" class="text-white/30 text-xs mt-0.5 truncate">
            {{ user._teams.map(t => t.name).join(', ') }}
          </p>
          <p v-else-if="user._role === 'Coach' && user.created_at" class="text-white/25 text-xs mt-0.5">
            Joined {{ formatDate(user.created_at) }}
          </p>
        </div>

        <!-- Role badge -->
        <div class="rounded-lg px-2.5 py-1 text-xs font-bold flex-shrink-0"
          :style="{ backgroundColor: TAB_COLORS[user._role]?.bg, color: TAB_COLORS[user._role]?.text }">
          {{ user._role }}
        </div>

        <svg class="w-4 h-4 text-white/20 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </Layout>
</template>
