<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const coachCount  = ref('—')
const playerCount = ref('—')
const loading     = ref(true)

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

onMounted(async () => {
  try {
    const [coaches, players] = await Promise.all([
      fetchAllPages('coach/search/coaches?search='),
      fetchAllPages('coach/search/players?search='),
    ])
    coachCount.value  = coaches.length
    playerCount.value = players.length
  } catch (e) {
    console.error('AdminReports error', e)
  }
  loading.value = false
})

const userTypeData = computed(() => {
  const coaches = typeof coachCount.value  === 'number' ? coachCount.value  : 0
  const players = typeof playerCount.value === 'number' ? playerCount.value : 0
  const total   = coaches + players + 1  // +1 admin
  const pct     = (n) => total > 0 ? Math.round((n / total) * 100) : 0
  return [
    { label: 'Coaches', count: coachCount.value,  color: '#60A5FA', pct: pct(coaches) },
    { label: 'Players', count: playerCount.value, color: '#4ADE80', pct: pct(players) },
    { label: 'Admins',  count: 1,                 color: '#E10600', pct: pct(1) },
  ]
})

const STAT_ROWS = computed(() => [
  { label: 'Total Coaches',    value: coachCount.value,  trend: null },
  { label: 'Total Players',    value: playerCount.value, trend: null },
  { label: 'Total Users',      value: (typeof coachCount.value === 'number' && typeof playerCount.value === 'number') ? coachCount.value + playerCount.value + 1 : '—', trend: null },
])

const BAR_DATA = [
  { day: 'Mon', value: 18 },
  { day: 'Tue', value: 24 },
  { day: 'Wed', value: 16 },
  { day: 'Thu', value: 30 },
  { day: 'Fri', value: 22 },
  { day: 'Sat', value: 8 },
  { day: 'Sun', value: 6 },
]
const MAX_BAR = Math.max(...BAR_DATA.map(d => d.value))
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
      <h1 class="text-white text-xl font-bold">Reports</h1>
    </div>

    <!-- Key Metrics -->
    <div class="bg-white/5 border border-white/8 rounded-xl p-5 mb-5">
      <p class="text-white/30 text-xs font-bold tracking-widest uppercase mb-4">User Summary</p>
      <div v-if="loading" class="flex justify-center py-6">
        <div class="w-6 h-6 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
      </div>
      <div v-else>
        <div v-for="(row, i) in STAT_ROWS" :key="row.label"
          class="flex items-center justify-between py-3"
          :class="i < STAT_ROWS.length - 1 ? 'border-b border-white/5' : ''">
          <span class="text-white/60 text-sm">{{ row.label }}</span>
          <span class="text-white font-bold text-base">{{ row.value }}</span>
        </div>
      </div>
    </div>

    <!-- Weekly Logins Bar Chart -->
    <div class="bg-white/5 border border-white/8 rounded-xl p-5 mb-5">
      <p class="text-white/30 text-xs font-bold tracking-widest uppercase mb-5">Weekly Logins</p>
      <div class="flex items-end gap-2 h-28">
        <div v-for="d in BAR_DATA" :key="d.day" class="flex-1 flex flex-col items-center gap-1">
          <span class="text-white/35 text-xs">{{ d.value }}</span>
          <div class="w-full bg-white/8 rounded-t flex-1 overflow-hidden flex items-end">
            <div class="w-full bg-app-red rounded-t" :style="{ height: `${(d.value / MAX_BAR) * 100}%` }"></div>
          </div>
          <span class="text-white/35 text-xs">{{ d.day }}</span>
        </div>
      </div>
    </div>

    <!-- User Distribution -->
    <div class="bg-white/5 border border-white/8 rounded-xl p-5 mb-5">
      <p class="text-white/30 text-xs font-bold tracking-widest uppercase mb-4">User Distribution</p>
      <div v-for="u in userTypeData" :key="u.label" class="mb-4 last:mb-0">
        <div class="flex items-center gap-2 mb-1.5">
          <div class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: u.color }"></div>
          <span class="flex-1 text-white/65 text-sm">{{ u.label }}</span>
          <span class="text-white font-semibold text-sm">{{ u.count }}</span>
        </div>
        <div class="w-full bg-white/8 rounded-full h-1.5 overflow-hidden">
          <div class="h-full rounded-full" :style="{ width: `${u.pct}%`, backgroundColor: u.color }"></div>
        </div>
      </div>
    </div>

    <!-- Note -->
    <p class="text-white/25 text-xs text-center py-3">
      Full analytics export and date-range filtering coming soon.
    </p>
  </Layout>
</template>
