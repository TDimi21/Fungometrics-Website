<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const stats = ref({ users: '—', coaches: '—', players: '—', teams: '—' })

const MENU_ITEMS = [
  { key: 'admin.users',     label: 'Users',           countKey: 'users',   desc: 'View and manage all users, coaches, and players',     icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
  { key: 'admin.coaches',   label: 'Coaches',         countKey: 'coaches', desc: 'View and manage all coaches',                           icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
  { key: 'admin.players',   label: 'Players',         countKey: 'players', desc: 'View and manage all players',                           icon: 'M13 10V3L4 14h7v7l9-11h-7z' },
  { key: 'admin.teams',     label: 'Teams',           countKey: 'teams',   desc: 'Manage teams, rosters and assignments',                 icon: 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9' },
  { key: 'admin.teams-players', label: 'Teams & Players by State/Level', countKey: null, desc: 'Browse all teams and players filtered by state and level', icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z' },
  { key: 'admin.plans',     label: 'Plan Features',   countKey: null,      desc: 'Control which features each subscription tier unlocks',  icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
]

// coaches: res.data.data = paginator { data:[...], last_page }
// players: res.data.data = flat array  (SearchPlayersResource transforms fields)
async function fetchAllPages(endpoint) {
  const all = []
  let page = 1
  while (true) {
    try {
      const sep = endpoint.includes('?') ? '&' : '?'
      const res   = await axiosGet(`${endpoint}${sep}page=${page}`)
      const outer = res.data?.data                          // paginator obj OR flat array
      const arr   = Array.isArray(outer?.data) ? outer.data  // coaches: paginator
                  : Array.isArray(outer)        ? outer       // players: flat array
                  : []
      if (!arr.length) break
      all.push(...arr)
      if (outer?.last_page != null && page >= outer.last_page) break
      page++
    } catch (_) { break }                                   // 404 = no more pages
  }
  return all
}

// coaches have profile.first_name; players have name.{first,last} (via SearchPlayersResource)
function normalizeName(u) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first   = String(nameObj?.first || u.first_name || u.profile?.first_name || '').trim()
  const last    = String(nameObj?.last  || u.last_name  || u.profile?.last_name  || '').trim()
  const full    = nameObj?.full || (first && last ? `${first} ${last}` : first || last || u.email || '—')
  const initials = [first[0], last[0]].filter(Boolean).join('').toUpperCase() || '?'
  return { full, initials, email: u.email || u.phone || '' }
}

onMounted(async () => {
  try {
    const [coaches, players] = await Promise.all([
      fetchAllPages('coach/search/coaches?search='),
      fetchAllPages('coach/search/players?search='),
    ])

    // Teams from players' actual_team (the field name after SearchPlayersResource transforms)
    const teamNames = new Set()
    players.forEach(p => {
      if (Array.isArray(p.actual_team)) {
        p.actual_team.forEach(t => { if (t.name?.trim()) teamNames.add(t.name.trim()) })
      }
    })

    stats.value = {
      coaches: coaches.length,
      players: players.length,
      users:   coaches.length + players.length,
      teams:   teamNames.size || '—',
    }

  } catch (e) {
    console.error('AdminDashboard fetch error', e)
  }
})

function navigate(item) {
  if (item.key === 'admin.coaches') return router.push({ name: 'admin.users', query: { tab: 'Coaches' } })
  if (item.key === 'admin.players') return router.push({ name: 'admin.users', query: { tab: 'Players' } })
  router.push({ name: item.key })
}
</script>

<template>
  <Layout>
    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <div class="w-10 h-10 rounded-xl bg-app-red/20 flex items-center justify-center flex-shrink-0">
        <svg class="w-5 h-5 text-app-red" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
      </div>
      <h1 class="text-white text-2xl font-bold">Admin</h1>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div v-for="card in [
          { label: 'USERS',   val: stats.users },
          { label: 'COACHES', val: stats.coaches },
          { label: 'PLAYERS', val: stats.players },
          { label: 'TEAMS',   val: stats.teams },
        ]" :key="card.label"
        class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs font-bold tracking-widest mb-1">{{ card.label }}</p>
        <p class="text-white text-3xl font-black">{{ card.val }}</p>
      </div>
    </div>

    <!-- Menu -->
    <div class="space-y-3 mb-6">
      <button
        v-for="item in MENU_ITEMS" :key="item.key"
        class="w-full flex items-center gap-4 bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-colors text-left"
        @click="navigate(item)"
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
        <div v-if="item.countKey && stats[item.countKey] !== '—'"
          class="bg-app-red text-white text-xs font-bold px-2 py-1 rounded-lg mr-2">
          {{ stats[item.countKey] }}
        </div>
        <svg class="w-4 h-4 text-white/25 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>

  </Layout>
</template>
