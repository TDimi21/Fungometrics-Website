<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import {
  PLAN_FEATURES,
  PLAN_LABELS,
  FEATURE_META,
  loadPlanOverrides,
  savePlanOverrides,
  clearPlanOverrides,
  getAllFeatureKeys,
} from '@/utils/plans.js'

const router = useRouter()

const PLAN_KEYS = ['free', 'coach_basic', 'coach_pro', 'player_basic', 'player_pro']
const COACH_PLANS  = ['free', 'coach_basic', 'coach_pro']
const PLAYER_PLANS = ['player_basic', 'player_pro']

// planMatrix[plan] = Set of enabled feature keys
const planMatrix = ref({})
const activePlan = ref('coach_pro')
const saved      = ref(false)
const hasOverride = ref(false)

// All feature keys ordered by category
const ALL_FEATURES = getAllFeatureKeys()
const CATEGORIES   = [...new Set(ALL_FEATURES.map(k => FEATURE_META[k]?.category ?? 'Other'))]

function initMatrix() {
  const overrides = loadPlanOverrides()
  hasOverride.value = !!overrides
  const source = overrides ?? PLAN_FEATURES
  const m = {}
  for (const p of PLAN_KEYS) {
    m[p] = new Set(Array.isArray(source[p]) ? source[p] : PLAN_FEATURES[p] ?? [])
  }
  planMatrix.value = m
}

onMounted(initMatrix)

const featuresInPlan = computed(() => planMatrix.value[activePlan.value] ?? new Set())

function isEnabled(featureKey) {
  return featuresInPlan.value.has(featureKey)
}

function toggleFeature(featureKey) {
  const s = new Set(planMatrix.value[activePlan.value])
  if (s.has(featureKey)) s.delete(featureKey)
  else s.add(featureKey)
  planMatrix.value = { ...planMatrix.value, [activePlan.value]: s }
}

// Which other plans already include this feature
function otherPlansWithFeature(featureKey) {
  return PLAN_KEYS.filter(p => p !== activePlan.value && planMatrix.value[p]?.has(featureKey))
}

function featuresByCategory(category) {
  return ALL_FEATURES.filter(k => (FEATURE_META[k]?.category ?? 'Other') === category)
}

// Save overrides to localStorage
function saveChanges() {
  const overrides = {}
  for (const p of PLAN_KEYS) {
    overrides[p] = [...(planMatrix.value[p] ?? [])]
  }
  savePlanOverrides(overrides)
  hasOverride.value = true
  saved.value = true
  setTimeout(() => { saved.value = false }, 2500)
}

function resetToDefaults() {
  if (!confirm('Reset all plan features to defaults? This removes your custom configuration.')) return
  clearPlanOverrides()
  hasOverride.value = false
  initMatrix()
  saved.value = false
}

// Move a feature to a specific plan (enable there, optionally disable current)
function moveToPlan(featureKey, targetPlan) {
  const cur = new Set(planMatrix.value[activePlan.value])
  cur.delete(featureKey)
  const target = new Set(planMatrix.value[targetPlan])
  target.add(featureKey)
  planMatrix.value = {
    ...planMatrix.value,
    [activePlan.value]: cur,
    [targetPlan]: target,
  }
}

const enabledCount = computed(() => featuresInPlan.value.size)
const totalCount   = computed(() => ALL_FEATURES.length)

// Plan color coding
const PLAN_COLORS = {
  free:         'border-white/20 text-white/50',
  coach_basic:  'border-blue-500/50 text-blue-400',
  coach_pro:    'border-app-red/60 text-app-red',
  player_basic: 'border-green-500/50 text-green-400',
  player_pro:   'border-amber-400/60 text-amber-400',
}
const PLAN_ACTIVE = {
  free:         'bg-white/15 border-white/40 text-white',
  coach_basic:  'bg-blue-500/20 border-blue-400 text-blue-300',
  coach_pro:    'bg-app-red/20 border-app-red text-white',
  player_basic: 'bg-green-600/20 border-green-400 text-green-300',
  player_pro:   'bg-amber-500/20 border-amber-400 text-amber-300',
}
</script>

<template>
  <Layout>
    <!-- Header -->
    <div class="flex items-center gap-3 mb-1">
      <button class="text-white/50 hover:text-white" @click="router.push({ name: 'admin.dashboard' })">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-white text-xl font-bold flex-1">Plan Features</h1>
      <span v-if="hasOverride"
        class="text-xs font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2 py-1 rounded-lg">
        CUSTOM
      </span>
    </div>
    <p class="text-white/30 text-xs mb-5 ml-8">
      Control which features are included in each subscription tier.
      Changes are saved as web configuration — backend enforcement is done server-side.
    </p>

    <!-- Plan Tabs -->
    <div class="flex gap-1.5 mb-1 flex-wrap">
      <button
        v-for="p in PLAN_KEYS" :key="p"
        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
        :class="activePlan === p ? PLAN_ACTIVE[p] : PLAN_COLORS[p] + ' bg-white/4 hover:bg-white/10'"
        @click="activePlan = p"
      >{{ PLAN_LABELS[p] }}</button>
    </div>

    <!-- Plan Summary Bar -->
    <div class="flex items-center justify-between bg-white/5 border border-white/10 rounded-xl px-4 py-3 mb-4">
      <div>
        <p class="text-white font-bold text-sm">{{ PLAN_LABELS[activePlan] }}</p>
        <p class="text-white/40 text-xs mt-0.5">{{ enabledCount }} of {{ totalCount }} features enabled</p>
      </div>
      <div class="flex gap-2">
        <!-- Quick-enable all for this plan -->
        <button
          class="text-xs text-white/40 hover:text-white px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 transition-colors"
          @click="() => { planMatrix[activePlan] = new Set(ALL_FEATURES) }">
          Enable All
        </button>
        <button
          class="text-xs text-white/40 hover:text-white px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 transition-colors"
          @click="() => { planMatrix[activePlan] = new Set() }">
          Clear All
        </button>
      </div>
    </div>

    <!-- Feature List by Category -->
    <div class="space-y-4 mb-6">
      <div v-for="cat in CATEGORIES" :key="cat">
        <!-- Category Header -->
        <div class="flex items-center gap-2 mb-2">
          <p class="text-white/35 text-xs font-black tracking-widest uppercase">{{ cat }}</p>
          <div class="flex-1 h-px bg-white/8"></div>
          <span class="text-white/20 text-xs">
            {{ featuresByCategory(cat).filter(k => isEnabled(k)).length }}/{{ featuresByCategory(cat).length }}
          </span>
        </div>

        <div class="space-y-1">
          <div v-for="featureKey in featuresByCategory(cat)" :key="featureKey"
            class="flex items-center gap-3 bg-white/4 border rounded-xl px-4 py-3 transition-colors"
            :class="isEnabled(featureKey) ? 'border-white/12' : 'border-white/5 opacity-60'">

            <!-- Toggle -->
            <button
              class="relative w-10 h-6 rounded-full flex-shrink-0 transition-colors focus:outline-none"
              :class="isEnabled(featureKey) ? 'bg-app-red' : 'bg-white/15'"
              @click="toggleFeature(featureKey)">
              <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                :class="isEnabled(featureKey) ? 'translate-x-4' : 'translate-x-0'"></span>
            </button>

            <!-- Feature Label -->
            <div class="flex-1 min-w-0">
              <p class="text-white text-sm font-semibold truncate">{{ FEATURE_META[featureKey]?.label ?? featureKey }}</p>
              <p class="text-white/25 text-xs font-mono mt-0.5 truncate">{{ featureKey }}</p>
            </div>

            <!-- Other plans that have this feature (chips) -->
            <div class="flex gap-1 flex-shrink-0 flex-wrap justify-end max-w-[160px]">
              <span
                v-for="op in otherPlansWithFeature(featureKey)" :key="op"
                class="text-xs px-1.5 py-0.5 rounded text-white/40 bg-white/6 border border-white/8 whitespace-nowrap"
              >{{ PLAN_LABELS[op] }}</span>
            </div>

            <!-- Move-to dropdown -->
            <div class="relative flex-shrink-0" v-if="isEnabled(featureKey)">
              <select
                class="bg-white/8 border border-white/15 text-white/50 text-xs rounded-lg px-2 py-1.5 cursor-pointer hover:bg-white/15 transition-colors appearance-none pr-5"
                @change="(e) => { if (e.target.value) { moveToPlan(featureKey, e.target.value); e.target.value = '' } }"
                :value="''">
                <option value="" disabled>Move →</option>
                <option v-for="p in PLAN_KEYS.filter(p => p !== activePlan)" :key="p" :value="p">
                  {{ PLAN_LABELS[p] }}
                </option>
              </select>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Save / Reset Bar (sticky bottom) -->
    <div class="sticky bottom-0 bg-[#0C1021]/90 backdrop-blur border-t border-white/10 -mx-4 px-4 py-3 flex items-center gap-3">
      <button
        class="flex-1 bg-app-red text-white font-bold text-sm py-3 rounded-xl hover:bg-red-600 transition-colors"
        @click="saveChanges">
        {{ saved ? '✓ Saved' : 'Save Plan Changes' }}
      </button>
      <button
        class="px-4 py-3 bg-white/8 border border-white/15 text-white/50 text-sm font-semibold rounded-xl hover:bg-white/15 hover:text-white transition-colors whitespace-nowrap"
        @click="resetToDefaults">
        Reset Defaults
      </button>
    </div>
  </Layout>
</template>
