<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const stats = ref({ users: '—', coaches: '—', players: '—', teams: '—' })
const recentActivity = ref([])
const activityLoading = ref(true)

const MENU_ITEMS = [
  { key: 'admin.users',     label: 'Users',           icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', countKey: 'users',   desc: 'View and manage all users, coaches, and players' },
  { key: 'admin.coaches',   label: 'Coaches',         icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',                                                                                                                                                                                                                                               countKey: 'coaches', desc: 'View and manage all coaches' },
  { key: 'admin.players',   label: 'Players',         icon: 'M13 10V3L4 14h7v7l9-11h-7z',                                                                                                                                                                                                                                                                                        countKey: 'players', desc: 'View and manage all players' },
  { key: 'admin.teams',     label: 'Teams',           icon: 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9',                                                                                                                                                                                                                               countKey: 'teams',   desc: 'Manage teams, rosters and assignments' },
  { key: 'admin.roles',     label: 'Role Management', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',                                                                                                                  countKey: null,      desc: 'Assign roles and permissions to users' },
  { key: 'admin.security',  label: 'Security',        icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',                                                                                                                                                                                                           countKey: null,      desc: 'Password resets, MFA, sessions and account security' },
  { key: 'admin.auditlogs', label: 'Audit Logs',      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',                                                                                                                                                                               countKey: null,      desc: 'Login activity, failed attempts and system events' },
  { key: 'admin.reports',   label: 'Reports',         icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',                                                                                                        countKey: null,      desc: 'User activity, engagement and performance reports' },
]

const ROLE_COLORS = { Coach: '#1D4ED8', Player: '#15803D' }

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
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  } catch (_) { return '' }
}

function normalizeName(u) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first = String(u.first_name || nameObj?.first || u.profile?.first_name || '').trim()
  const last  = String(u.last_name  || nameObj?.last  || u.profile?.last_name  || '').trim()
  const full  = first && last ? `${first} ${last}` : first || last || u.email || '—'
  const initials = [first[0], last[0]].filter(Boolean).join('').toUpperCase() || '?'
  return { full, initials, email: u.email || u.phone || '' }
}

async function fetchAllPages(endpoint) {
  const all = []
  let page = 1
  while (true) {
    const sep = endpoint.includes('?') ? '&' : '?'
    const res = await axiosGet(`${endpoint}${sep}page=${page}`)
    const data = res.data?.data
    const arr = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : [])
    all.push(...arr)
    if (arr.length === 0 || (data?.last_page != null && page >= data.last_page)) break
    page++
  }
  return all
}

onMounted(async () => {
  try {
    const [coaches, players] = await Promise.all([
      fetchAllPages('coach/search/coaches?search='),
      fetchAllPages('coach/search/players?search='),
    ])

    const teamNames = new Set()
    players.forEach(p => {
      if (Array.isArray(p.actual_team)) p.actual_team.forEach(t => { if (t.name?.trim()) teamNames.add(t.name.trim()) })
    })

    stats.value = {
      coaches: coaches.length,
      players: players.length,
      users: coaches.length + players.length,
      teams: teamNames.size || '—',
    }

    const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000
    const combined = [
      ...coaches.map(u => ({ ...normalizeName(u), role: 'Coach', dateStr: u.updated_at || u.created_at })),
      ...players.map(u => ({ ...normalizeName(u), role: 'Player', dateStr: u.updated_at || u.created_at })),
    ]
    combined.sort((a, b) => new Date(b.dateStr) - new Date(a.dateStr))
    recentActivity.value = combined.filter(u => u.dateStr && new Date(u.dateStr).getTime() >= sevenDaysAgo).slice(0, 10)
  } catch (e) {
    console.error('AdminDashboard fetch error', e)
  }
  activityLoading.value = false
})
</script>

<template>
  <Layout>
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <div class="w-10 h-10 rounded-xl bg-app-red/20 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-app-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h1 class="text-white text-2xl font-bold">Admin</h1>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="card in [{ label: 'USERS', val: stats.users }, { label: 'COACHES', val: stats.coaches }, { label: 'PLAYERS', val: stats.players }, { label: 'TEAMS', val: stats.teams }]"
        :key="card.label"
        class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs font-bold tracking-widest mb-1">{{ card.label }}</p>
        <p class="text-white text-3xl font-black">{{ card.val }}</p>
      </div>
    </div>

    <!-- Menu Items -->
    <div class="space-y-3 mb-6">
      <button
        v-for="item in MENU_ITEMS" :key="item.key"
        class="w-full flex items-center gap-4 bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-colors text-left"
        @click="router.push(item.key === 'admin.coaches' ? { name: 'admin.users', query: { tab: 'Coaches' } } : item.key === 'admin.players' ? { name: 'admin.users', query: { tab: 'Players' } } : { name: item.key })"
      >
        <div class="w-11 h-11 rounded-full bg-app-red/10 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-app-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-white font-bold text-sm">{{ item.label }}</p>
          <p class="text-white/40 text-xs mt-0.5">{{ item.desc }}</p>
        </div>
        <div v-if="item.countKey && stats[item.countKey] !== '—'" class="bg-app-red text-white text-xs font-bold px-2 py-1 rounded-lg mr-2">
          {{ stats[item.countKey] }}
        </div>
        <svg class="w-4 h-4 text-white/25 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white/5 border border-white/10 rounded-xl p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-white font-bold text-sm">Active Users — Last 7 Days</h2>
        <button class="text-app-red text-xs font-semibold hover:text-red-400" @click="router.push({ name: 'admin.auditlogs' })">View All</button>
      </div>

      <div v-if="activityLoading" class="flex justify-center py-8">
        <div class="w-6 h-6 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
      </div>
      <p v-else-if="!recentActivity.length" class="text-white/30 text-sm text-center py-6">No recent activity found.</p>
      <div v-else class="space-y-3">
        <div v-for="(entry, i) in recentActivity" :key="i" class="flex items-center gap-3 py-3 border-b border-white/5 last:border-0">
          <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-white text-sm"
            :style="{ backgroundColor: ROLE_COLORS[entry.role] ?? 'rgba(255,255,255,0.15)' }">
            {{ entry.initials }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-white font-semibold text-sm truncate">{{ entry.full }}</span>
              <span class="text-xs font-bold text-white px-2 py-0.5 rounded flex-shrink-0"
                :style="{ backgroundColor: ROLE_COLORS[entry.role] ?? '#555' }">{{ entry.role }}</span>
            </div>
            <p class="text-white/35 text-xs mt-0.5 truncate">{{ entry.email }}</p>
          </div>
          <span class="text-white/30 text-xs flex-shrink-0">{{ formatRelative(entry.dateStr) }}</span>
        </div>
      </div>
    </div>
  </Layout>
</template>
