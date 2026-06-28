<script setup>
/**
 * PlayerAssessmentReport.vue
 * Loads a single player's saved assessments and renders the full FMTRX report
 * card for the latest (or a chosen past) one — the same report shown on the
 * /assessment-reports page, embedded inline.
 */
import { ref, watch, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import AssessmentReportCard from './AssessmentReportCard.vue'

const props = defineProps({
  playerId: { type: [String, Number], default: '' },
  playerName: { type: String, default: '' },
  teamName: { type: String, default: '' },
})

const { axiosGet } = useAxiosAuth()
const loading = ref(false)
const history = ref([])
const selected = ref(null)

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
  if (!props.playerId) { history.value = []; selected.value = null; return }
  loading.value = true
  try {
    const { data } = await axiosGet('assessments/player/' + props.playerId)
    history.value = Array.isArray(data?.data) ? data.data : []
    selected.value = history.value[0] ?? null
  } catch (_) {
    history.value = []
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
