<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { PLAN_LABELS, groupFeatures } from '@/utils/plans.js'

const router = useRouter()
const route  = useRoute()
const { axiosGet } = useAxiosAuth()

const user        = ref(null)
const loading     = ref(true)
const saving      = ref(false)
const currentPlan = ref(null)   // null = unknown (player search doesn't return plan)
const saveMsg     = ref('')
const saveMsgType = ref('success')

const COACH_PLANS  = ['free', 'coach_basic', 'coach_pro']
const PLAYER_PLANS = ['free', 'player_basic', 'player_pro']

const TAB_COLORS = {
  Coach:  { bg: 'rgba(59,130,246,0.18)', text: '#60A5FA' },
  Player: { bg: 'rgba(22,163,74,0.18)',  text: '#4ADE80' },
}

// Normalize fields from both coach (profile.first_name) and player (name.first) responses
function normalizeUser(u, role) {
  const nameObj = u.name && typeof u.name === 'object' ? u.name : null
  const first   = String(nameObj?.first || u.first_name || u.profile?.first_name || '').trim()
  const last    = String(nameObj?.last  || u.last_name  || u.profile?.last_name  || '').trim()
  const full    = nameObj?.full || (first && last ? `${first} ${last}` : first || last || u.email || 'Unknown')
  return {
    ...u,
    _first:  first,
    _last:   last,
    _full:   full,
    _email:  String(u.email || u.phone || ''),
    _role:   role,
    _teams:  Array.isArray(u.actual_team) ? u.actual_team : [],
    _plan:   u.subscription_plan || null,
  }
}

async function findInPages(endpoint, role) {
  const id = String(route.params.id)
  let page = 1
  while (true) {
    try {
      const sep   = endpoint.includes('?') ? '&' : '?'
      const res   = await axiosGet(`${endpoint}${sep}page=${page}`)
      const outer = res.data?.data
      const arr   = Array.isArray(outer?.data) ? outer.data
                  : Array.isArray(outer)        ? outer
                  : []
      const found = arr.find(u => String(u.id) === id)
      if (found) return normalizeUser(found, role)
      if (!arr.length || (outer?.last_page != null && page >= outer.last_page)) break
      page++
    } catch (_) { break }
  }
  return null
}

async function loadUser() {
  const id = String(route.params.id)

  // Primary: sessionStorage (set by AdminUsers row tap — avoids re-fetching)
  try {
    const cached = sessionStorage.getItem(`admin_user_${id}`)
    if (cached) {
      const parsed = JSON.parse(cached)
      user.value        = parsed
      currentPlan.value = parsed._plan || parsed.subscription_plan || null
      loading.value = false
      return
    }
  } catch (_) {}

  // Fallback: search all pages (coaches first, then players)
  try {
    const found = await findInPages('coach/search/coaches?search=', 'Coach')
              ?? await findInPages('coach/search/players?search=', 'Player')
    if (found) {
      user.value        = found
      currentPlan.value = found._plan
    }
  } catch (e) {
    console.error('AdminUserDetail load error', e)
  }
  loading.value = false
}

onMounted(loadUser)

const isCoach       = computed(() => user.value?._role === 'Coach')
const availablePlans = computed(() => isCoach.value ? COACH_PLANS : PLAYER_PLANS)
const featureGroups  = computed(() => groupFeatures(currentPlan.value || 'free'))
const roleColor      = computed(() => TAB_COLORS[user.value?._role] ?? { bg: 'rgba(255,255,255,0.1)', text: '#fff' })
const planLabel      = computed(() => currentPlan.value ? (PLAN_LABELS[currentPlan.value] ?? currentPlan.value) : 'Unknown')

async function savePlan(plan) {
  if (plan === currentPlan.value) return
  const name = user.value?._full ?? 'this user'
  if (!confirm(`Change ${name}'s plan to "${PLAN_LABELS[plan]}"?`)) return

  saving.value  = true
  saveMsg.value = ''
  try {
    const apiBase = import.meta.env.VITE_API_ENDPOINT || ''
    let token = ''
    try {
      token = JSON.parse(localStorage.getItem('auth') || '{}')?.token
            || JSON.parse(sessionStorage.getItem('auth') || '{}')?.token
            || ''
    } catch (_) {}

    const res  = await fetch(`${apiBase}admin/users/${user.value.id}/plan`, {
      method:  'PATCH',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body:    JSON.stringify({ subscription_plan: plan }),
    })
    const data = await res.json()

    if (res.ok) {
      currentPlan.value  = plan
      saveMsg.value     = `Plan updated to "${PLAN_LABELS[plan]}"`
      saveMsgType.value = 'success'
      // Update cached user
      try {
        const id  = String(user.value.id)
        const raw = sessionStorage.getItem(`admin_user_${id}`)
        if (raw) {
          const obj = JSON.parse(raw)
          obj._plan = plan
          obj.subscription_plan = plan
          sessionStorage.setItem(`admin_user_${id}`, JSON.stringify(obj))
        }
      } catch (_) {}
    } else {
      saveMsg.value     = data?.message || 'Failed to update plan'
      saveMsgType.value = 'error'
    }
  } catch (_) {
    saveMsg.value     = 'Network error. Check connection.'
    saveMsgType.value = 'error'
  }
  saving.value = false
  setTimeout(() => { saveMsg.value = '' }, 4000)
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

    <p v-else-if="!user" class="text-white/40 text-center py-12">User not found.</p>

    <div v-else class="space-y-5">

      <!-- User Header Card -->
      <div class="flex items-center gap-4 bg-white/5 border border-white/10 rounded-xl p-5">
        <div class="w-14 h-14 rounded-full flex items-center justify-center font-black text-xl flex-shrink-0"
          :style="{ backgroundColor: roleColor.bg }">
          <span :style="{ color: roleColor.text }">
            {{ (user._first[0] || user._full[0] || '?').toUpperCase() }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-white font-bold text-lg truncate">{{ user._full }}</p>
          <p class="text-white/45 text-sm mt-0.5 truncate">{{ user._email }}</p>
          <!-- Teams for players -->
          <p v-if="user._role === 'Player' && user._teams.length"
            class="text-white/30 text-xs mt-1 truncate">
            {{ user._teams.map(t => t.name).join(' · ') }}
          </p>
          <span class="inline-block mt-2 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg"
            :style="{ backgroundColor: roleColor.bg, color: roleColor.text }">{{ user._role }}</span>
        </div>
      </div>

      <!-- Plan Selector -->
      <div>
        <div class="flex items-center justify-between mb-3">
          <p class="text-white/35 text-xs font-bold tracking-widest uppercase">Subscription Plan</p>
          <span class="text-white/40 text-xs">Current: <span class="text-app-gold font-semibold">{{ planLabel }}</span></span>
        </div>

        <!-- Player note: plan not returned by search API, but can still be changed -->
        <div v-if="user._role === 'Player' && !currentPlan"
          class="bg-white/5 border border-white/10 rounded-xl p-3 mb-3 text-white/40 text-xs text-center">
          Current plan not visible from search API — select below to assign a plan.
        </div>

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
        <Transition name="fade">
          <div v-if="saveMsg" class="mt-3 text-center text-sm font-semibold rounded-lg p-2"
            :class="saveMsgType === 'success' ? 'bg-green-900/30 text-green-400' : 'bg-red-900/30 text-app-red'">
            {{ saveMsg }}
          </div>
        </Transition>
      </div>

      <!-- Features for known plan -->
      <div v-if="currentPlan">
        <p class="text-white/35 text-xs font-bold tracking-widest uppercase mb-1">Features Included</p>
        <p class="text-white/25 text-xs mb-3">Based on: {{ PLAN_LABELS[currentPlan] }}</p>

        <p v-if="!Object.keys(featureGroups).length" class="text-white/25 text-sm text-center py-6">
          No features on this plan.
        </p>

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

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
