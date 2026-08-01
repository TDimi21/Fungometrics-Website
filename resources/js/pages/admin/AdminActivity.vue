<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const { axiosGet } = useAxiosAuth()

const range = ref('day')
const role = ref('all')
const loading = ref(false)
const error = ref('')
const activity = ref({
  summary: { active_users: 0, coach_logins: 0, player_logins: 0, sessions_recorded: 0 },
  users: { data: [], current_page: 1, last_page: 1, total: 0 },
})

const RANGE_OPTIONS = [
  { key: 'day', label: 'Last Day' },
  { key: 'week', label: 'Last Week' },
  { key: 'month', label: 'Last Month' },
]
const ROLE_OPTIONS = [
  { key: 'all', label: 'All' },
  { key: 'coach', label: 'Coaches' },
  { key: 'player', label: 'Players' },
]

const users = computed(() => activity.value.users?.data || [])
const page = computed(() => Number(activity.value.users?.current_page || 1))
const lastPage = computed(() => Number(activity.value.users?.last_page || 1))

async function loadActivity(nextPage = 1) {
  loading.value = true
  error.value = ''
  try {
    const response = await axiosGet(`admin/activity?range=${range.value}&role=${role.value}&page=${nextPage}&per_page=50`)
    activity.value = response.data?.data || activity.value
  } catch (requestError) {
    error.value = requestError?.response?.data?.message || 'Unable to load login activity.'
  } finally {
    loading.value = false
  }
}

function formatLogin(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

watch([range, role], () => loadActivity(1))
onMounted(() => loadActivity())
</script>

<template>
  <Layout>
    <div class="flex items-center gap-3 mb-6">
      <button class="text-white/50 hover:text-white" aria-label="Back to admin home" @click="router.push({ name: 'admin.dashboard' })">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <div>
        <h1 class="text-white text-xl font-bold">Login & Session Activity</h1>
        <p class="text-white/40 text-xs mt-1">Successful coach and player logins with sessions recorded in the same period.</p>
      </div>
    </div>

    <div class="flex flex-col md:flex-row gap-3 mb-5">
      <div class="flex gap-2 bg-white/5 border border-white/10 rounded-xl p-1" aria-label="Date range">
        <button
          v-for="option in RANGE_OPTIONS"
          :key="option.key"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg text-xs font-bold transition-colors"
          :class="range === option.key ? 'bg-app-red text-white' : 'text-white/55 hover:text-white'"
          @click="range = option.key"
        >{{ option.label }}</button>
      </div>
      <div class="flex gap-2 bg-white/5 border border-white/10 rounded-xl p-1" aria-label="Account role">
        <button
          v-for="option in ROLE_OPTIONS"
          :key="option.key"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg text-xs font-bold transition-colors"
          :class="role === option.key ? 'bg-white/15 text-white' : 'text-white/55 hover:text-white'"
          @click="role = option.key"
        >{{ option.label }}</button>
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div v-for="card in [
        { label: 'ACTIVE ACCOUNTS', value: activity.summary.active_users },
        { label: 'COACHES', value: activity.summary.coach_logins },
        { label: 'PLAYERS', value: activity.summary.player_logins },
        { label: 'SESSIONS RECORDED', value: activity.summary.sessions_recorded },
      ]" :key="card.label" class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-[11px] font-bold tracking-widest">{{ card.label }}</p>
        <p class="text-white text-3xl font-black mt-1">{{ card.value }}</p>
      </div>
    </div>

    <div class="bg-white/5 border border-white/10 rounded-xl overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-white/10">
        <p class="text-white font-bold text-sm">Most Recent Logins</p>
        <p class="text-white/35 text-xs">{{ activity.users.total || 0 }} accounts</p>
      </div>

      <div v-if="loading" class="flex justify-center py-12">
        <div class="w-7 h-7 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
      </div>
      <div v-else-if="error" class="text-center py-10 px-4">
        <p class="text-red-300 text-sm">{{ error }}</p>
        <button class="text-sky-300 text-sm font-bold mt-3" @click="loadActivity(page)">Try Again</button>
      </div>
      <div v-else-if="!users.length" class="text-white/40 text-sm text-center py-12 px-4">
        No successful logins were recorded in this period.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[700px]">
          <thead class="bg-black/15">
            <tr class="text-left text-white/35 text-[11px] uppercase tracking-widest">
              <th class="px-4 py-3">User</th>
              <th class="px-4 py-3">Role</th>
              <th class="px-4 py-3">Last Login</th>
              <th class="px-4 py-3 text-right">Sessions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-t border-white/5">
              <td class="px-4 py-3">
                <p class="text-white text-sm font-semibold">{{ user.name }}</p>
                <p class="text-white/35 text-xs mt-0.5">{{ user.email || user.phone || '—' }}</p>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase"
                  :class="user.role === 'coach' ? 'bg-sky-400/15 text-sky-300' : 'bg-emerald-400/15 text-emerald-300'">
                  {{ user.role }}
                </span>
              </td>
              <td class="px-4 py-3 text-white/65 text-sm">{{ formatLogin(user.last_login_at) }}</td>
              <td class="px-4 py-3 text-white text-lg font-black text-right">{{ user.sessions_recorded }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-white/10">
        <button class="text-white/60 text-sm font-bold disabled:opacity-25" :disabled="page <= 1 || loading" @click="loadActivity(page - 1)">Previous</button>
        <span class="text-white/35 text-xs">Page {{ page }} of {{ lastPage }}</span>
        <button class="text-white/60 text-sm font-bold disabled:opacity-25" :disabled="page >= lastPage || loading" @click="loadActivity(page + 1)">Next</button>
      </div>
    </div>
  </Layout>
</template>
