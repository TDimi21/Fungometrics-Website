<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const route  = useRoute()
const { axiosGet } = useAxiosAuth()

const TABS = ['All', 'Coaches', 'Players']

const coaches = ref([])
const players = ref([])
const loading = ref(false)
const search = ref('')
const activeTab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'All')
const TAB_COLORS = {
  Coach:  { bg: 'rgba(59,130,246,0.18)', text: '#60A5FA' },
  Player: { bg: 'rgba(22,163,74,0.18)',  text: '#4ADE80' },
}

function formatDate(val) {
  if (!val) return ''
  try {
    const d = new Date(val)
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch (_) { return val }
}

function normalizeUser(u, role) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first = String(u.first_name || nameObj?.first || u.profile?.first_name || '').trim()
  const last  = String(u.last_name  || nameObj?.last  || u.profile?.last_name  || '').trim()
  const full  = first && last ? `${first} ${last}` : first || last || u.email || 'Unknown'
  return { ...u, _first: first, _last: last, _full: full, _email: String(u.email || u.phone || ''), _role: role }
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

async function fetchAll() {
  loading.value = true
  try {
    const [rawCoaches, rawPlayers] = await Promise.all([
      fetchAllPages('coach/search/coaches?search='),
      fetchAllPages('coach/search/players?search='),
    ])
    coaches.value = dedupeById(rawCoaches).map(u => normalizeUser(u, 'Coach'))
      .sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
    players.value = dedupeById(rawPlayers).map(u => normalizeUser(u, 'Player'))
      .sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
  } catch (e) {
    console.error('AdminUsers fetch error', e)
  }
  loading.value = false
}

onMounted(fetchAll)

const baseList = computed(() => {
  if (activeTab.value === 'Coaches') return coaches.value
  if (activeTab.value === 'Players') return players.value
  return [...coaches.value, ...players.value].sort((a, b) => a._first.toLowerCase().localeCompare(b._first.toLowerCase()))
})

const filtered = computed(() => {
  if (!search.value) return baseList.value
  const q = search.value.toLowerCase()
  return baseList.value.filter(u => u._full.toLowerCase().includes(q) || u._email.toLowerCase().includes(q))
})

function goToDetail(user) {
  try { sessionStorage.setItem(`admin_user_${user.id}`, JSON.stringify(user)) } catch (_) {}
  router.push({ name: 'admin.user-detail', params: { id: user.id } })
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
      <h1 class="text-white text-xl font-bold">Users</h1>
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

    <p class="text-white/35 text-xs mb-4">{{ filtered.length }} {{ activeTab === 'All' ? 'users' : activeTab.toLowerCase() }}</p>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- List -->
    <div v-else class="space-y-2">
      <p v-if="!filtered.length" class="text-white/30 text-sm text-center py-10">No users found.</p>
      <button
        v-for="user in filtered" :key="`${user._role}-${user.id}`"
        class="w-full flex items-center gap-3 bg-white/5 border border-white/8 rounded-xl p-3 hover:bg-white/10 transition-colors text-left"
        @click="goToDetail(user)"
      >
        <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-base flex-shrink-0"
          :style="{ backgroundColor: TAB_COLORS[user._role]?.bg ?? 'rgba(255,255,255,0.1)' }">
          <span :style="{ color: TAB_COLORS[user._role]?.text ?? '#fff' }">
            {{ (user._first[0] || user._full[0] || '?').toUpperCase() }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-white font-semibold text-sm truncate">{{ user._full }}</p>
          <p class="text-white/40 text-xs mt-0.5 truncate">{{ user._email || '—' }}</p>
          <p v-if="user.created_at" class="text-white/25 text-xs mt-0.5">Joined {{ formatDate(user.created_at) }}</p>
        </div>
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
