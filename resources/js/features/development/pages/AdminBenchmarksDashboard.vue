<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'

const TIERS = ['p5', 'p25', 'p50', 'p75', 'p95']
const AGE_GROUPS = [
  { key: '10U_12U', label: '10U–12U' },
  { key: '13U_14U', label: '13U–14U' },
  { key: '15U_16U', label: '15U–16U' },
  { key: '17U_18U', label: '17U–18U' },
  { key: 'COLLEGE_19_PLUS', label: 'College 19+' },
]
const { axiosGet, axiosPut, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const metrics = ref([])
const loading = ref(true)
const savingKey = ref('')
const message = ref({ type: '', text: '' })
const query = ref('')
const category = ref('all')
const activeAge = ref(AGE_GROUPS[0].key)
const edits = ref({})
const teamId = computed(() => String(team.value?.id_team ?? team.value?.id ?? ''))
const categories = computed(() => [...new Set(metrics.value.map(metric => metric.category))].sort())
const filtered = computed(() => metrics.value.filter(metric =>
  (category.value === 'all' || metric.category === category.value)
  && (!query.value || `${metric.display_name} ${metric.metric_key}`.toLowerCase().includes(query.value.toLowerCase()))
))
const availableAgeGroups = computed(() => AGE_GROUPS.filter(({ key }) =>
  metrics.value.some(metric => metric.age_percentile_anchors?.[key])
))
const groupedMetrics = computed(() => {
  const groups = new Map()
  filtered.value.forEach(metric => {
    if (!metric.age_percentile_anchors?.[activeAge.value]) return
    if (!groups.has(metric.category)) groups.set(metric.category, [])
    groups.get(metric.category).push(metric)
  })
  return [...groups.entries()].map(([key, rows]) => ({ key, label: title(key), rows }))
})
const rowKey = (metric, age) => `${metric.metric_key}|${age}`
const title = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase())
const ageLabel = age => AGE_GROUPS.find(group => group.key === age)?.label || title(age)
const selectedRow = metric => metric.age_percentile_anchors?.[activeAge.value] || null

const load = async () => {
  loading.value = true
  message.value = { type: '', text: '' }
  try {
    if (!teamId.value) {
      const teams = await teamStore.getTeamsFromApi()
      if (teams.length) teamStore.setTeam(teams[0])
    }
    const response = await axiosGet(`coach/teams/${teamId.value}/benchmark-overrides`)
    metrics.value = response.data.data.metrics || []
    const next = {}
    metrics.value.forEach(metric => Object.entries(metric.age_percentile_anchors || {}).forEach(([age, row]) => {
      next[rowKey(metric, age)] = { ...row.values }
    }))
    edits.value = next
    if (!availableAgeGroups.value.some(group => group.key === activeAge.value)) {
      activeAge.value = availableAgeGroups.value[0]?.key || AGE_GROUPS[0].key
    }
  } catch (error) {
    message.value = { type: 'error', text: error?.response?.data?.message || 'Unable to load team benchmarks.' }
  } finally { loading.value = false }
}

const valid = (metric, age) => {
  const values = TIERS.map(tier => Number(edits.value[rowKey(metric, age)]?.[tier]))
  if (values.some(value => !Number.isFinite(value))) return false
  return values.every((value, index) => index === 0 || (metric.higher_is_better ? value > values[index - 1] : value < values[index - 1]))
}

const save = async (metric, age) => {
  if (!valid(metric, age)) {
    message.value = { type: 'error', text: `Values for ${metric.display_name} must progress from P5 to P95 in the correct direction.` }
    return
  }
  const key = rowKey(metric, age)
  savingKey.value = key
  try {
    await axiosPut(`coach/teams/${teamId.value}/benchmark-overrides`, {
      metric_key: metric.metric_key, age_group: age, anchors: edits.value[key],
    })
    message.value = { type: 'success', text: `${metric.display_name} (${ageLabel(age)}) saved for ${team.value?.name || 'this team'}.` }
    await load()
  } catch (error) {
    message.value = { type: 'error', text: error?.response?.data?.message || 'Unable to save benchmark.' }
  } finally { savingKey.value = '' }
}

const reset = async (metric, age) => {
  const key = rowKey(metric, age)
  savingKey.value = key
  try {
    const params = new URLSearchParams({ metric_key: metric.metric_key, age_group: age })
    await axiosDelete(`coach/teams/${teamId.value}/benchmark-overrides?${params.toString()}`, '')
    message.value = { type: 'success', text: `${metric.display_name} (${ageLabel(age)}) reset to FMTRX defaults.` }
    await load()
  } catch (error) {
    message.value = { type: 'error', text: error?.response?.data?.message || 'Unable to reset benchmark.' }
  } finally { savingKey.value = '' }
}

onMounted(load)
</script>

<template>
  <Layout>
    <main class="mx-auto w-full max-w-7xl px-4 py-6 text-white">
      <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-white/10 bg-slate-900/70 p-3">
        <RouterLink to="/dashboard?tab=development" class="nav-btn">← Back to Dashboard</RouterLink>
        <span class="ml-auto text-xs font-black uppercase tracking-wider text-slate-400">{{ team?.name }}</span>
      </div>

      <div class="rounded-2xl border border-white/10 bg-slate-950/80 p-5 shadow-2xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div><p class="eyebrow">Team controls</p><h1 class="text-2xl font-black">Percentile Benchmark Editor</h1><p class="mt-2 max-w-3xl text-sm text-slate-400">Set the raw performance values that represent each percentile for this team. Unedited rows continue using FMTRX defaults.</p></div>
          <div class="flex flex-col gap-2 sm:flex-row">
            <input v-model="query" class="control" placeholder="Search metrics…">
            <select v-model="category" class="control"><option value="all">All categories</option><option v-for="item in categories" :key="item" :value="item">{{ title(item) }}</option></select>
          </div>
        </div>
        <p v-if="message.text" class="mt-4 rounded-lg border px-3 py-2 text-sm" :class="message.type === 'error' ? 'border-red-400/30 bg-red-500/10 text-red-200' : 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200'">{{ message.text }}</p>
      </div>

      <div v-if="loading" class="py-16 text-center text-slate-400">Loading FMTRX benchmark categories…</div>
      <div v-else class="mt-5 space-y-5">
        <nav class="age-switcher" aria-label="Benchmark age ranges">
          <div>
            <p class="eyebrow">Benchmark age range</p>
            <p class="mt-1 text-sm text-slate-400">Choose an age range, then edit its percentile values by category.</p>
          </div>
          <div class="age-buttons" role="tablist">
            <button
              v-for="age in availableAgeGroups"
              :key="age.key"
              type="button"
              class="age-btn"
              :class="{ active: activeAge === age.key }"
              :aria-selected="activeAge === age.key"
              role="tab"
              @click="activeAge = age.key"
            >{{ age.label }}</button>
          </div>
        </nav>

        <section v-for="group in groupedMetrics" :key="group.key" class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/75">
          <header class="category-header">
            <div><p class="eyebrow">Category</p><h2 class="text-lg font-black">{{ group.label }}</h2></div>
            <span class="age-pill">{{ ageLabel(activeAge) }}</span>
          </header>
          <div class="overflow-x-auto">
            <table class="benchmark-grid text-left text-sm">
              <thead>
                <tr class="text-[10px] uppercase tracking-widest text-slate-500">
                  <th>Metric</th>
                  <th>Unit</th>
                  <th v-for="tier in TIERS" :key="tier" class="tier-heading">{{ tier.toUpperCase() }}</th>
                  <th>Source</th>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="metric in group.rows" :key="metric.metric_key">
                  <td>
                    <strong class="metric-title">{{ metric.display_name }}</strong>
                    <small>{{ metric.higher_is_better ? 'Higher is better' : 'Lower is better' }}</small>
                  </td>
                  <td class="unit-cell">{{ metric.unit || 'score' }}</td>
                  <td v-for="tier in TIERS" :key="tier" class="tier-cell">
                    <input
                      v-model.number="edits[rowKey(metric, activeAge)][tier]"
                      type="number"
                      step="0.1"
                      class="anchor-input"
                      :aria-label="`${metric.display_name} ${ageLabel(activeAge)} ${tier}`"
                    >
                  </td>
                  <td>
                    <span class="source-pill" :class="selectedRow(metric)?.overridden ? 'overridden' : 'default'">
                      {{ selectedRow(metric)?.overridden ? 'Team override' : 'FMTRX default' }}
                    </span>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button v-if="selectedRow(metric)?.overridden" class="reset-btn" :disabled="savingKey === rowKey(metric, activeAge)" @click="reset(metric, activeAge)">Reset</button>
                      <button class="save-btn" :disabled="savingKey === rowKey(metric, activeAge) || !valid(metric, activeAge)" @click="save(metric, activeAge)">Save</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
        <p v-if="!groupedMetrics.length" class="py-12 text-center text-slate-400">No benchmark metrics match this filter for {{ ageLabel(activeAge) }}.</p>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.nav-btn{border:1px solid rgba(255,255,255,.2);border-radius:.375rem;padding:.25rem .75rem;font-size:.75rem;font-weight:700;color:#e2e8f0}.eyebrow{color:#fb7185;font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.control,.anchor-input{border:1px solid rgba(255,255,255,.14);border-radius:.5rem;background:#0b1428;color:#fff;outline:none}.control{min-height:42px;padding:0 .75rem}.anchor-input{width:78px;padding:.55rem .55rem}.control:focus,.anchor-input:focus{border-color:rgba(248,113,113,.7)}.age-switcher{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:16px 18px;border:1px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(15,23,42,.88)}.age-buttons{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.age-btn{min-height:38px;padding:0 15px;border:1px solid rgba(255,255,255,.14);border-radius:999px;color:#cbd5e1;background:#0b1428;font-size:11px;font-weight:900;letter-spacing:.04em}.age-btn:hover{border-color:rgba(251,113,133,.55);color:#fff}.age-btn.active{border-color:#fb7185;background:#be123c;color:#fff;box-shadow:0 0 0 2px rgba(251,113,133,.12)}.category-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.1)}.age-pill,.source-pill{display:inline-flex;align-items:center;border-radius:999px;font-size:10px;font-weight:900;text-transform:uppercase}.age-pill{padding:6px 10px;background:rgba(190,18,60,.16);color:#fda4af}.source-pill{padding:5px 8px;white-space:nowrap}.source-pill.overridden{background:rgba(245,158,11,.15);color:#fcd34d}.source-pill.default{background:rgba(14,165,233,.15);color:#7dd3fc}.benchmark-grid{width:100%;min-width:980px}.benchmark-grid th{padding:11px 12px;background:rgba(2,6,23,.32)}.benchmark-grid td{padding:10px 12px;border-top:1px solid rgba(255,255,255,.06);vertical-align:middle}.benchmark-grid tbody tr:hover{background:rgba(255,255,255,.025)}.benchmark-grid th:first-child{min-width:190px}.tier-heading,.tier-cell{text-align:center}.metric-title{display:block;color:#e2e8f0;font-size:13px}.benchmark-grid small{display:block;margin-top:3px;color:#64748b;font-size:10px}.unit-cell{color:#94a3b8;font-weight:700}.action-buttons{display:flex;justify-content:flex-end;gap:7px}.save-btn,.reset-btn{border-radius:.45rem;padding:.45rem .7rem;font-size:10px;font-weight:900;text-transform:uppercase}.save-btn{background:#be123c;color:#fff}.reset-btn{border:1px solid rgba(255,255,255,.15);color:#cbd5e1}.save-btn:disabled,.reset-btn:disabled{opacity:.35}
@media(max-width:900px){.age-switcher{align-items:flex-start;flex-direction:column}.age-buttons{justify-content:flex-start}.anchor-input{width:72px}}
</style>
