<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { PLAN_LABELS, groupFeatures } from '@/utils/plans.js'

const router = useRouter()
const route = useRoute()
const { axiosGet } = useAxiosAuth()

const user = ref(null)
const loading = ref(true)
const saving = ref(false)
const currentPlan = ref('free')
const saveMsg = ref('')
const saveMsgType = ref('success')

const COACH_PLANS = ['free', 'coach_basic', 'coach_pro']
const PLAYER_PLANS = ['free', 'player_basic', 'player_pro']

const TAB_COLORS = {
  Coach:  { bg: 'rgba(59,130,246,0.18)', text: '#60A5FA' },
  Player: { bg: 'rgba(22,163,74,0.18)',  text: '#4ADE80' },
}

function normalizeUser(u, role) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first = String(u.first_name || nameObj?.first || u.profile?.first_name || '').trim()
  const last  = String(u.last_name  || nameObj?.last  || u.profile?.last_name  || '').trim()
  const full  = first && last ? `${first} ${last}` : first || last || u.email || 'Unknown'
  return { ...u, _first: first, _last: last, _full: full, _email: String(u.email || u.phone || ''), _role: role }
}

async function loadUser() {
  const id = String(route.params.id)

  // Try sessionStorage first (set by AdminUsers when tapping a row)
  try {
    const cached = sessionStorage.getItem(`admin_user_${id}`)
    if (cached) {
      const parsed = JSON.parse(cached)
      user.value = parsed
      currentPlan.value = parsed.subscription_plan || 'free'
      loading.value = false
      return
    }
  } catch (_) {}

  // Fallback: search all pages until we find this user
  try {
    async function findInPages(endpoint, role) {
      let page = 1
      while (true) {
        const res = await axiosGet(`${endpoint}&page=${page}`)
        const inner = res.data?.data
        const arr = Array.isArray(inner?.data) ? inner.data : []
        const found = arr.find(u => String(u.id) === id)
        if (found) return normalizeUser(found, role)
        if (arr.length === 0 || (inner?.last_page != null && page >= inner.last_page)) break
        page++
      }
      return null
    }

    const coachResult = await findInPages('coach/search/coaches?search=', 'Coach')
    const found = coachResult ?? await findInPages('coach/search/players?search=', 'Player')
    if (found) {
      user.value = found
      currentPlan.value = found.subscription_plan || 'free'
    }
  } catch (e) {
    console.error('AdminUserDetail load error', e)
  }
  loading.value = false
}

onMounted(loadUser)

const isCoach = computed(() => user.value?._role === 'Coach')
const availablePlans = computed(() => isCoach.value ? COACH_PLANS : PLAYER_PLANS)
const featureGroups = computed(() => groupFeatures(currentPlan.value))
const roleColor = computed(() => TAB_COLORS[user.value?._role] ?? { bg: 'rgba(255,255,255,0.1)', text: '#fff' })

async function savePlan(plan) {
  if (plan === currentPlan.value) return
  if (!confirm(`Change ${user.value?._full}'s plan to "${PLAN_LABELS[plan]}"?`)) return
  saving.value = true
  saveMsg.value = ''
  try {
    const apiBase = import.meta.env.VITE_API_ENDPOINT || ''
    const token = (() => {
      try { return JSON.parse(localStorage.getItem('auth') || '{}')?.token || JSON.parse(sessionStorage.getItem('auth') || '{}')?.token || '' } catch (_) { return '' }
    })()
    const res = await fetch(`${apiBase}admin/users/${user.value.id}/plan`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ subscription_plan: plan }),
    })
    const data = await res.json()
    if (res.ok) {
      currentPlan.value = plan
      saveMsg.value = `Plan updated to "${PLAN_LABELS[plan]}"`
      saveMsgType.value = 'success'
    } else {
      saveMsg.value = data?.message || 'Failed to update plan'
      saveMsgType.value = 'error'
    }
  } catch (e) {
    saveMsg.value = 'Network error. Check connection.'
    saveMsgType.value = 'error'
  }
  saving.value = false
  setTimeout(() => { saveMsg.value = '' }, 3000)
}
</script>

<template>
  <Layout>
    <!-- Back -->
    <div class="flex items-center gap-3 mb-6">
      <button class="text-white/50 hover:text-white" @click="router.push({ name: 'admin.users' })">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-white text-xl font-bold">User Detail</h1>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-2 border-app-red border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="!user" class="text-white/40 text-center py-12">User not found.</div>

    <div v-else class="space-y-5">
      <!-- User Header -->
      <div class="flex items-center gap-4 bg-white/5 border border-white/10 rounded-xl p-5">
        <div class="w-14 h-14 rounded-full flex items-center justify-center font-black text-xl flex-shrink-0"
          :style="{ backgroundColor: roleColor.bg }">
          <span :style="{ color: roleColor.text }">{{ (user._first[0] || user._full[0] || '?').toUpperCase() }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-white font-bold text-lg truncate">{{ user._full }}</p>
          <p class="text-white/45 text-sm mt-0.5 truncate">{{ user._email }}</p>
          <span class="inline-block mt-1.5 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg"
            :style="{ backgroundColor: roleColor.bg, color: roleColor.text }">{{ user._role }}</span>
        </div>
      </div>

      <!-- Plan Selector -->
      <div>
        <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-3">Subscription Plan</p>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="plan in availablePlans" :key="plan"
            class="py-3 px-2 rounded-xl text-xs font-bold text-center border transition-all"
            :class="plan === currentPlan
              ? 'bg-app-red/20 border-app-red text-white'
              : 'bg-white/5 border-white/10 text-white/40 hover:text-white hover:border-white/30'"
            :disabled="saving"
            @click="savePlan(plan)"
          >
            {{ PLAN_LABELS[plan] }}
            <div v-if="plan === currentPlan" class="w-1.5 h-1.5 rounded-full bg-app-red mx-auto mt-1.5"></div>
          </button>
        </div>

        <div v-if="saving" class="flex items-center justify-center gap-2 mt-3 text-white/40 text-xs">
          <div class="w-4 h-4 border border-app-red border-t-transparent rounded-full animate-spin"></div>
          Saving...
        </div>
        <div v-if="saveMsg" class="mt-3 text-center text-sm font-semibold rounded-lg p-2"
          :class="saveMsgType === 'success' ? 'bg-green-900/30 text-green-400' : 'bg-red-900/30 text-app-red'">
          {{ saveMsg }}
        </div>
      </div>

      <!-- Features -->
      <div>
        <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-1">Features Included</p>
        <p class="text-white/25 text-xs mb-3">Based on current plan · {{ PLAN_LABELS[currentPlan] }}</p>

        <div v-if="!Object.keys(featureGroups).length" class="text-white/25 text-sm text-center py-6">No features on this plan.</div>

        <div v-for="(features, category) in featureGroups" :key="category"
          class="bg-white/4 border border-white/6 rounded-xl p-4 mb-3">
          <p class="text-app-red text-xs font-black tracking-widest uppercase mb-3">{{ category }}</p>
          <div v-for="f in features" :key="f.key" class="flex items-center py-1.5">
            <div class="w-1.5 h-1.5 rounded-full bg-white/20 mr-3 flex-shrink-0"></div>
            <span class="flex-1 text-white/70 text-sm">{{ f.label }}</span>
            <span class="text-green-400 text-sm font-bold">✓</span>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>
