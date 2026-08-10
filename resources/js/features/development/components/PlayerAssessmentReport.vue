<script setup>
/**
 * PlayerAssessmentReport.vue
 * Loads a single player's saved assessments and renders the full FMTRX report
 * card for the latest (or a chosen past) one — the same report shown on the
 * /assessment-reports page, embedded inline.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import AssessmentReportCard from './AssessmentReportCard.vue'
import { ITEM_CATALOG, buildItemRows } from '../lib/assessmentItemCatalog.js'

const props = defineProps({
  playerId: { type: [String, Number], default: '' },
  playerName: { type: String, default: '' },
  teamName: { type: String, default: '' },
})

const { axiosGet } = useAxiosAuth()
const loading = ref(false)
const history = ref([])
const fitnessHistory = ref([])
const selected = ref(null)

// Browse a single item's value across every saved assessment for this
// player — distinct from the report card above, which only shows one
// assessment date at a time.
const selectedItemKey = ref('')
const selectedItem = computed(() => ITEM_CATALOG.flatMap((g) => g.items).find((i) => i.key === selectedItemKey.value) || null)
const itemRows = computed(() => selectedItem.value ? buildItemRows(selectedItem.value, { assessmentHistory: history.value, fitnessHistory: fitnessHistory.value }) : [])

const scoreColor = (s) => {
  const value = Number(s)
  if (!Number.isFinite(value)) return '#64748B'
  if (value >= 80) return '#65D84E'
  if (value >= 70) return '#FACC15'
  if (value >= 60) return '#F97316'
  return '#EF4444'
}
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

const loadReports = async () => {
  if (!props.playerId) { history.value = []; fitnessHistory.value = []; selected.value = null; return }
  loading.value = true
  selectedItemKey.value = ''
  try {
    const [assessmentRes, fitnessRes] = await Promise.all([
      axiosGet('assessments/player/' + props.playerId).catch(() => null),
      axiosGet('player/fitness/' + props.playerId).catch(() => null),
    ])
    history.value = Array.isArray(assessmentRes?.data?.data) ? assessmentRes.data.data : []
    fitnessHistory.value = Array.isArray(fitnessRes?.data?.data) ? fitnessRes.data.data : []
    selected.value = history.value[0] ?? null
  } catch (_) {
    history.value = []
    fitnessHistory.value = []
    selected.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadReports)
watch(() => props.playerId, loadReports)
</script>

<template>
  <div class="flex flex-col gap-3">
    <div v-if="loading" class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-8 text-center text-white/40 text-sm animate-pulse">
      Loading reports…
    </div>

    <div v-else-if="!history.length" class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-8 text-center text-white/35 text-sm">
      No assessment reports yet for {{ playerName || 'this player' }}. Tap
      <span class="text-white/70 font-bold">Open Assessment</span> to create one.
    </div>

    <template v-else>
      <!-- Browse a single item's data across every saved assessment -->
      <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
        <div class="text-[10px] uppercase tracking-widest text-white/40 mb-1.5">Browse Assessment Items</div>
        <div class="relative">
          <select
            v-model="selectedItemKey"
            class="w-full appearance-none rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400/60"
          >
            <option value="">Select an item to view its data…</option>
            <optgroup v-for="g in ITEM_CATALOG" :key="g.category" :label="g.category">
              <option v-for="i in g.items" :key="i.key" :value="i.key">{{ i.label }}</option>
            </optgroup>
          </select>
          <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/40 text-xs">▼</span>
        </div>

        <div v-if="selectedItem" class="mt-3 rounded-xl border border-white/10 bg-white/[0.03] p-3">
          <div class="flex items-center justify-between gap-3 mb-2">
            <span class="text-sm font-black text-white">{{ selectedItem.label }}</span>
            <span v-if="itemRows.length" class="text-sm font-black text-[#ff8798]">Current: {{ itemRows[0].value }}{{ selectedItem.unit }}</span>
          </div>
          <div v-if="itemRows.length" class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-white/40 uppercase tracking-wide text-[10px]">
                  <th class="text-left font-black py-1.5 px-2">Date</th>
                  <th class="text-left font-black py-1.5 px-2">Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in itemRows" :key="r.date" class="border-t border-white/5">
                  <td class="py-1.5 px-2 text-white/80">{{ formatDate(r.date) }}</td>
                  <td class="py-1.5 px-2 text-white/80">{{ r.value }}{{ selectedItem.unit }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="text-xs text-white/40 py-2">No recorded data for this item yet.</p>
        </div>
      </div>

      <!-- History selector (latest first) -->
      <div v-if="history.length > 1" class="flex flex-wrap gap-2">
        <button
          v-for="r in history"
          :key="r.id"
          class="px-3 py-1.5 rounded-lg border text-xs font-bold"
          :class="selected?.id === r.id ? 'border-[#089BFF]/60 bg-[#089BFF]/15 text-white' : 'border-white/15 bg-white/5 text-white/55 hover:text-white'"
          @click="selected = r"
        >
          {{ formatDate(r.assessment_date) }}
          <span class="ml-1 font-black" :style="{ color: scoreColor(r.overall_score) }">{{ r.overall_score ?? '—' }}</span>
        </button>
      </div>

      <AssessmentReportCard :report="selected" :team-name="teamName" :history="history" />
    </template>
  </div>
</template>
