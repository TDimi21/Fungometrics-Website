<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const events = ref([])
const loading = ref(true)
const filter = ref('All')

const ROLE_COLORS = { Coach: '#1D4ED8', Player: '#15803D' }
const EVENT_CONFIG = {
  login:    { label: 'Logged In',         color: '#22C55E', bg: 'rgba(34,197,94,0.12)',  icon: 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1' },
  register: { label: 'New Registration',  color: '#60A5FA', bg: 'rgba(96,165,250,0.12)', icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
}

const THIRTY_DAYS_MS = 30 * 24 * 60 * 60 * 1000

function formatRelative(dateStr) {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    if (isNaN(d)) return dateStr
    const diffMs = Date.now() - d
    const diffMin = Math.floor(diffMs / 60000)
    if (diffMin < 1) return 'Just now'
    if (diffMin < 60) return `${diffMin}m ago`
    const diffH = Math.floor(diffMin / 60)
    if (diffH < 24) return `${diffH}h ago`
    const diffD = Math.floor(diffH / 24)
    if (diffD < 7) return `${diffD}d ago`
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch (_) { return '' }
}

async function fetchAllPages(endpoint) {
  const all = []
  let page = 1
  while (true) {
    const res = await axiosGet(`${endpoint}&page=${page}`)
    const inner = res.data?.data
    const arr = Array.isArray(inner?.data) ? inner.data : []
    all.push(...arr)
    if (arr.length === 0 || (inner?.last_page != null && page >= inner.last_page)) break
    page++
  }
  return all
}

function buildEvents(users, role) {
  const cutoff = Date.now() - THIRTY_DAYS_MS
  const result = []
  for (const u of users) {
    const nameObj = u.name && typeof u.name === 'object' ? u.name : null
    const first = String(u.first_name || nameObj?.first || u.profile?.first_name || '').trim()
    const last  = String(u.last_name  || nameObj?.last  || u.profile?.last_name  || '').trim()
    const full  = first && last ? `${first} ${last}` : first || last || u.email || '—'
    const initials = [first[0], last[0]].filter(Boolean).join('').toUpperCase() || '?'
    const email = u.email || u.phone || ''
    if (u.updated_at && new Date(u.updated_at).getTime() >= cutoff) {
      result.push({ key: `login-${role}-${u.id}`, type: 'login', full, initials, role, email, dateStr: u.updated_at })
    }
    if (u.created_at && new Date(u.created_at).getTime() >= cutoff) {
      result.push({ key: `reg-${role}-${u.id}`, type: 'register', full, initials, role, email, dateStr: u.created_at })
    }
  }
  return result
}

async function fetchEvents() {
  loading.value = true
  try {
    const [coaches, players] = await Promise.all([
      fetchAllPages('coach/search/coaches?search='),
      fetchAllPages('coach/search/players?search='),
    ])
    const all = [...buildEvents(coaches, 'Coach'), ...buildEvents(players, 'Player')]
    all.sort((a, b) => new Date(b.dateStr) - new Date(a.dateStr))
    events.value = all
  } catch (e) {
    console.error('AdminAuditLogs error', e)
  }
  loading.value = false
}

onMounted(fetchEvents)

const loginCount    = computed(() => events.value.filter(e => e.type === 'login').length)
const registerCount = computed(() => events.value.filter(e => e.type === 'register').length)

const filtered = computed(() => {
  if (filter.value === 'All') return events.value
  return events.value.filter(e => e.type === filter.value.toLowerCase())
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
      <h1 class="text-white text-xl font-bold">Audit Logs</h1>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-3 gap-3 mb-5">
      <div class="bg-white/5 border border-green-500/30 rounded-xl p-3 text-center">
        <p class="text-green-400 font-black text-xl">{{ loginCount }}</p>
        <p class="text-white/40 text-xs mt-0.5">Logins</p>
      </div>
      <div class="bg-white/5 border border-blue-400/30 rounded-xl p-3 text-center">
        <p class="text-blue-400 font-black text-xl">{{ registerCount }}</p>
        <p class="text-white/40 text-xs mt-0.5">New Users</p>
      </div>
      <div class="bg-white/5 border border-app-red/30 rounded-xl p-3 text-center">
        <p class="text-app-red font-black text-xl">{{ events.length }}</p>
        <p class="text-white/40 text-xs mt-0.5">Total</p>
      </div>
    </div>

    <!-- Filter pills -->
    <div class="flex items-center gap-2 mb-4">
      <button
        v-for="f in ['All', 'Login', 'Register']" :key="f"
        class="px-4 py-1.5 rounded-full text-sm font-semibold border transition-colors"
        :class="filter === f ? 'bg-app-red border-app-red text-white' : 'bg-white/5 border-white/10 text-white/40 hover:text-white'"
        @click="filter = f"
      >{{ f }}</button>
      <span class="flex-1 text-right text-white/25 text-xs font-semibold tracking-wide">LAST 30 DAYS</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <p v-else-if="!filtered.length" class="text-white/30 text-sm text-center py-10">No activity found in the last 30 days.</p>

    <!-- Log entries -->
    <div v-else class="space-y-2">
      <div v-for="item in filtered" :key="item.key"
        class="flex items-center gap-3 bg-white/5 border border-white/7 rounded-xl p-3">
        <!-- Event icon -->
        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
          :style="{ backgroundColor: EVENT_CONFIG[item.type]?.bg }">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            :style="{ color: EVENT_CONFIG[item.type]?.color }">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="EVENT_CONFIG[item.type]?.icon" />
          </svg>
        </div>
        <!-- Avatar -->
        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-sm flex-shrink-0"
          :style="{ backgroundColor: ROLE_COLORS[item.role] ?? 'rgba(255,255,255,0.15)' }">
          {{ item.initials }}
        </div>
        <!-- Info -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-white font-semibold text-sm truncate">{{ item.full }}</span>
            <span class="text-xs font-bold text-white px-1.5 py-0.5 rounded flex-shrink-0"
              :style="{ backgroundColor: ROLE_COLORS[item.role] ?? '#555' }">{{ item.role }}</span>
          </div>
          <p class="font-medium text-xs mt-0.5" :style="{ color: EVENT_CONFIG[item.type]?.color }">{{ EVENT_CONFIG[item.type]?.label }}</p>
          <p class="text-white/30 text-xs mt-0.5 truncate">{{ item.email }}</p>
        </div>
        <span class="text-white/25 text-xs flex-shrink-0">{{ formatRelative(item.dateStr) }}</span>
      </div>
    </div>
  </Layout>
</template>
