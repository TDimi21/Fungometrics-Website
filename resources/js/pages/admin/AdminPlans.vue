<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { createPlanFeaturesApi } from '@/services/planFeatures.js'

const router = useRouter()
const { axiosGet, axiosPut } = useAxiosAuth()
const api = createPlanFeaturesApi(axiosGet, axiosPut)
const plans = ref([])
const catalog = ref([])
const coverage = ref([])
const groups = ref([])
const activeKey = ref('coach_pro')
const draft = ref(null)
const reason = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')

const activePlan = computed(() => plans.value.find(plan => plan.key === activeKey.value) || null)
const activeCatalog = computed(() => catalog.value.filter(item => !item.hidden && (item.audience === 'shared' || item.audience === activePlan.value?.audience)))
const coverageByKey = computed(() => Object.fromEntries(coverage.value.map(item => [item.key, item.coverage])))
const dirty = computed(() => draft.value && activePlan.value && JSON.stringify(draft.value) !== JSON.stringify({
  entitlements: activePlan.value.entitlements,
  limits: activePlan.value.limits,
}))

function resetDraft(plan = activePlan.value) {
  if (!plan) return
  draft.value = {
    entitlements: [...plan.entitlements],
    limits: { ...plan.limits },
  }
  reason.value = ''
  error.value = ''
  success.value = ''
}

function selectPlan(key) {
  if (dirty.value && !confirm('Discard unsaved plan changes?')) return
  activeKey.value = key
  resetDraft(plans.value.find(plan => plan.key === key))
}

function isEnabled(key) { return draft.value?.entitlements.includes(key) }
function itemDefinition(key) { return catalog.value.find(item => item.key === key) }
function isImmutable(key) { return activePlan.value?.immutable_entitlements.includes(key) || itemDefinition(key)?.toggleable === false }
function implementation(key) { return coverageByKey.value[key] || { implementation_status: 'not_implemented', gaps: ['missing_coverage'] } }
function isIncomplete(key) { return !['fully_wired', 'platform_wired', 'composite_wired'].includes(implementation(key).implementation_status) }
function toggle(key) {
  if (isImmutable(key) || isIncomplete(key) || activePlan.value?.legacy) return
  draft.value.entitlements = isEnabled(key)
    ? draft.value.entitlements.filter(item => item !== key)
    : [...draft.value.entitlements, key].sort()
}
function byGroup(group) { return activeCatalog.value.filter(item => item.category === group) }

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.load()
    plans.value = response.plans
    groups.value = response.feature_groups
    catalog.value = response.entitlements
    coverage.value = response.coverage
    if (!plans.value.some(plan => plan.key === activeKey.value)) activeKey.value = plans.value[0]?.key
    resetDraft(plans.value.find(plan => plan.key === activeKey.value))
  } catch (e) {
    error.value = e?.response?.data?.message || 'Unable to load authoritative plan features.'
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!dirty.value || saving.value || activePlan.value?.legacy) return
  if (reason.value.trim().length < 3) {
    error.value = 'Enter a reason for this change.'
    return
  }
  if (!confirm(`Apply these changes to every ${activePlan.value.display_name} user?`)) return
  saving.value = true
  error.value = ''
  success.value = ''
  try {
    const updated = await api.update(activePlan.value.key, {
      entitlements: draft.value.entitlements,
      limits: draft.value.limits,
      version: activePlan.value.version,
      reason: reason.value.trim(),
    })
    plans.value = plans.value.map(plan => plan.key === updated.key ? updated : plan)
    resetDraft(updated)
    success.value = 'Authoritative plan features saved.'
  } catch (e) {
    if (e?.response?.status === 409) {
      error.value = 'Another administrator changed this plan. The matrix has been refreshed.'
      await load()
    } else {
      const errors = e?.response?.data?.errors
      error.value = errors ? Object.values(errors).flat().join(' ') : (e?.response?.data?.message || 'Save failed.')
    }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <Layout>
    <div class="flex items-center gap-3 mb-1">
      <button class="text-white/50 hover:text-white" @click="router.push({ name: 'admin.dashboard' })">‹</button>
      <h1 class="text-white text-xl font-bold flex-1">Plan Features</h1>
      <button class="text-xs text-white/60" @click="load">Refresh</button>
    </div>
    <p class="text-white/40 text-xs mb-5">Laravel is authoritative. Saved changes affect access on the next refresh.</p>

    <p v-if="loading" class="text-white/60 py-10 text-center">Loading authoritative matrix…</p>
    <p v-else-if="!plans.length" class="text-white/60 py-10 text-center">No plans are available.</p>
    <template v-else>
      <div class="flex gap-1.5 mb-4 flex-wrap">
        <button v-for="plan in plans" :key="plan.key" class="px-3 py-2 rounded-lg text-xs font-bold border"
          :class="activeKey === plan.key ? 'bg-app-red/20 border-app-red text-white' : 'border-white/15 text-white/50'"
          @click="selectPlan(plan.key)">
          {{ plan.display_name }} <span v-if="plan.legacy">(Legacy)</span>
        </button>
      </div>

      <div v-if="activePlan.legacy" class="mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm">
        Coach Basic is a read-only legacy plan.
      </div>
      <div v-if="error" class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-sm">{{ error }}</div>
      <div v-if="success" class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/30 text-green-300 text-sm">{{ success }}</div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
        <label v-for="(label, key) in { players: 'Player limit', coaches: 'Coach-seat limit', teams: 'Team limit' }" :key="key" class="text-white/50 text-xs">
          {{ label }} <span class="text-white/25">(blank = unlimited)</span>
          <input type="number" min="0" :disabled="activePlan.legacy" :value="draft.limits[key]"
            class="mt-1 w-full rounded-lg bg-white/5 border border-white/15 text-white px-3 py-2 disabled:opacity-40"
            @input="draft.limits[key] = $event.target.value === '' ? null : Number($event.target.value)" />
        </label>
      </div>

      <div v-for="group in groups" :key="group" class="mb-5" v-show="byGroup(group).length">
        <h2 class="text-white/40 text-xs font-black uppercase tracking-widest mb-2">{{ group }}</h2>
        <div class="space-y-1">
          <div v-for="item in byGroup(group)" :key="item.key">
            <button class="w-full flex items-center gap-3 text-left p-3 rounded-xl border"
              :class="isEnabled(item.key) ? 'border-white/15 bg-white/5' : 'border-white/5 opacity-60'"
              :disabled="activePlan.legacy || isImmutable(item.key) || isIncomplete(item.key)" @click="toggle(item.key)">
              <span class="w-10 h-6 rounded-full p-0.5" :class="isEnabled(item.key) ? 'bg-app-red' : 'bg-white/15'">
                <span class="block w-5 h-5 bg-white rounded-full transition-transform" :class="isEnabled(item.key) ? 'translate-x-4' : ''"></span>
              </span>
              <span class="flex-1">
                <span class="block text-white text-sm font-semibold">{{ item.display_name }}</span>
                <span class="block text-white/35 text-xs">{{ item.description }}</span>
              </span>
              <span v-if="isImmutable(item.key)" class="text-xs text-amber-300">Required</span>
              <span v-else-if="isIncomplete(item.key)" class="text-right text-xs text-amber-300">
                Incomplete · {{ implementation(item.key).implementation_status }}
              </span>
            </button>
            <p v-for="gap in implementation(item.key).gaps" v-show="isIncomplete(item.key)" :key="`${item.key}-${gap}`"
              class="mt-1 mb-2 px-3 text-[11px] text-white/35">{{ gap }}</p>
          </div>
        </div>
      </div>

      <div class="sticky bottom-0 bg-[#0C1021]/95 border-t border-white/10 -mx-4 px-4 py-3">
        <textarea v-model="reason" :disabled="activePlan.legacy" maxlength="1000" rows="2" placeholder="Required reason for change"
          class="w-full mb-2 rounded-lg bg-white/5 border border-white/15 text-white px-3 py-2 disabled:opacity-40"></textarea>
        <div class="flex gap-2">
          <button class="flex-1 bg-app-red text-white font-bold py-3 rounded-xl disabled:opacity-40" :disabled="!dirty || saving || activePlan.legacy" @click="save">
            {{ saving ? 'Saving…' : 'Save Authoritative Changes' }}
          </button>
          <button class="px-4 border border-white/15 text-white/60 rounded-xl disabled:opacity-40" :disabled="!dirty" @click="resetDraft">Discard</button>
        </div>
      </div>
    </template>
  </Layout>
</template>
