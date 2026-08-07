<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'

const TIERS = ['p5', 'p25', 'p50', 'p75', 'p95']
const { axiosGet, axiosPut, axiosDelete } = useAxiosAuth()
const teamStore = useTeamStore()
const { team } = storeToRefs(teamStore)
const metrics = ref([])
const loading = ref(true)
const savingKey = ref('')
const message = ref({ type: '', text: '' })
const query = ref('')
const category = ref('all')
const edits = ref({})
const teamId = computed(() => String(team.value?.id_team ?? team.value?.id ?? ''))
const categories = computed(() => [...new Set(metrics.value.map(metric => metric.category))].sort())
const filtered = computed(() => metrics.value.filter(metric =>
  (category.value === 'all' || metric.category === category.value)
  && (!query.value || `${metric.display_name} ${metric.metric_key}`.toLowerCase().includes(query.value.toLowerCase()))
))
const rowKey = (metric, age) => `${metric.metric_key}|${age}`
const title = value => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase())

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
    message.value = { type: 'success', text: `${metric.display_name} (${age}) saved for ${team.value?.name || 'this team'}.` }
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
    message.value = { type: 'success', text: `${metric.display_name} (${age}) reset to FMTRX defaults.` }
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
        <section v-for="metric in filtered" :key="metric.metric_key" class="overflow-hidden rounded-2xl border border-white/10 bg-slate-900/75">
          <header class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
            <div><p class="eyebrow">{{ title(metric.category) }}</p><h2 class="font-black">{{ metric.display_name }}</h2></div>
            <div class="text-right text-xs text-slate-400"><div>{{ metric.unit || 'score' }}</div><div>{{ metric.higher_is_better ? 'Higher is better' : 'Lower is better' }}</div></div>
          </header>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
              <thead><tr class="text-[10px] uppercase tracking-widest text-slate-500"><th class="p-3">Age group</th><th v-for="tier in TIERS" :key="tier" class="p-3">{{ tier.toUpperCase() }}</th><th class="p-3">Source</th><th class="p-3"></th></tr></thead>
              <tbody>
                <tr v-for="(row, age) in metric.age_percentile_anchors" :key="age" class="border-t border-white/5">
                  <td class="p-3 font-black text-slate-200">{{ title(age) }}</td>
                  <td v-for="tier in TIERS" :key="tier" class="p-2"><input v-model.number="edits[rowKey(metric, age)][tier]" type="number" step="0.1" class="anchor-input" :aria-label="`${metric.display_name} ${age} ${tier}`"></td>
                  <td class="p-3"><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase" :class="row.overridden ? 'bg-amber-500/15 text-amber-300' : 'bg-sky-500/15 text-sky-300'">{{ row.overridden ? 'Team override' : 'FMTRX default' }}</span></td>
                  <td class="p-3"><div class="flex justify-end gap-2"><button v-if="row.overridden" class="reset-btn" :disabled="savingKey === rowKey(metric, age)" @click="reset(metric, age)">Reset</button><button class="save-btn" :disabled="savingKey === rowKey(metric, age) || !valid(metric, age)" @click="save(metric, age)">Save</button></div></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
        <p v-if="!filtered.length" class="py-12 text-center text-slate-400">No benchmark metrics match this filter.</p>
      </div>
    </main>
  </Layout>
</template>

<style scoped>
.nav-btn{border:1px solid rgba(255,255,255,.2);border-radius:.375rem;padding:.25rem .75rem;font-size:.75rem;font-weight:700;color:#e2e8f0}.eyebrow{color:#fb7185;font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.control,.anchor-input{border:1px solid rgba(255,255,255,.14);border-radius:.5rem;background:#0b1428;color:#fff;outline:none}.control{min-height:42px;padding:0 .75rem}.anchor-input{width:88px;padding:.55rem .65rem}.control:focus,.anchor-input:focus{border-color:rgba(248,113,113,.7)}.save-btn,.reset-btn{border-radius:.45rem;padding:.45rem .7rem;font-size:10px;font-weight:900;text-transform:uppercase}.save-btn{background:#be123c;color:#fff}.reset-btn{border:1px solid rgba(255,255,255,.15);color:#cbd5e1}.save-btn:disabled,.reset-btn:disabled{opacity:.35}
</style>
