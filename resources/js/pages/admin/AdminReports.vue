<script setup>
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'

const router = useRouter()

const STAT_ROWS = [
  { label: 'Total Logins (30 days)',   value: '384', trend: '+12%', up: true },
  { label: 'Avg Sessions / Day',       value: '12.8', trend: '+3%', up: true },
  { label: 'Failed Logins',            value: '27', trend: '-8%',  up: false },
  { label: 'New Registrations',        value: '14', trend: '+40%', up: true },
  { label: 'Active Users (7 days)',    value: '38', trend: '+5%',  up: true },
  { label: 'Locked Accounts',          value: '2',  trend: '0%',   up: false },
]

const BAR_DATA = [
  { day: 'Mon', value: 18 },
  { day: 'Tue', value: 24 },
  { day: 'Wed', value: 16 },
  { day: 'Thu', value: 30 },
  { day: 'Fri', value: 22 },
  { day: 'Sat', value: 8 },
  { day: 'Sun', value: 6 },
]

const USER_TYPE_DATA = [
  { label: 'Coaches', count: 9,  color: '#60A5FA', pct: 18 },
  { label: 'Players', count: 41, color: '#4ADE80', pct: 76 },
  { label: 'Admins',  count: 1,  color: '#E10600', pct: 2 },
  { label: 'Pending', count: 3,  color: '#F59E0B', pct: 4 },
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
      <p class="text-white/30 text-xs font-bold tracking-widest uppercase mb-4">Key Metrics — Last 30 Days</p>
      <div v-for="(row, i) in STAT_ROWS" :key="row.label"
        class="flex items-center justify-between py-3"
        :class="i < STAT_ROWS.length - 1 ? 'border-b border-white/5' : ''">
        <span class="text-white/60 text-sm">{{ row.label }}</span>
        <div class="flex items-center gap-3">
          <span class="text-white font-bold text-base">{{ row.value }}</span>
          <span class="text-xs font-semibold" :class="row.up ? 'text-green-400' : 'text-app-red'">{{ row.trend }}</span>
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
      <div v-for="u in USER_TYPE_DATA" :key="u.label" class="mb-4 last:mb-0">
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
