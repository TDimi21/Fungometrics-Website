<script setup>
/**
 * CoachAssessmentPanel.vue
 * Coach-facing assessment reports viewer.
 * Loads history from GET /api/assessments/player/{id}.
 */
import { ref, watch, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const props = defineProps({
  playerId: { type: String, required: true },
  playerName: { type: String, default: '' },
})

const { axiosGet } = useAxiosAuth()
const selectedReport = ref(null)

const loadingHx = ref(false)
const history   = ref([])

// ─── score colour helper ───────────────────────────────────────────────────────
const scoreColor = (s) => {
  if (!s && s !== 0) return '#64748B'
  if (s >= 85) return '#2ECC71'
  if (s >= 70) return '#27AE60'
  if (s >= 55) return '#F39C12'
  if (s >= 40) return '#E67E22'
  return '#E74C3C'
}

// ─── load history ─────────────────────────────────────────────────────────────
const loadHistory = async () => {
  if (!props.playerId) return
  loadingHx.value = true
  try {
    const { data } = await axiosGet('assessments/player/' + props.playerId)
    history.value = data?.data ?? []
    if (!selectedReport.value && history.value.length) {
      selectedReport.value = history.value[0]
    }
  } catch (_) {
    history.value = []
    selectedReport.value = null
  } finally {
    loadingHx.value = false
  }
}

onMounted(loadHistory)
watch(() => props.playerId, loadHistory)

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
</script>

<template>
  <div class="coach-assessment-panel">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <div class="text-[10px] uppercase tracking-widest text-white/40 mb-0.5">Coach Assessment Reports</div>
        <div class="text-base font-black text-white">{{ playerName || 'Player' }} — Past Reports</div>
        <div class="text-[11px] text-white/45 mt-0.5">Historical saved assessments for this player.</div>
      </div>
    </div>

    <!-- ── REPORTS ──────────────────────────────────────────────────────────── -->
    <div>
      <div v-if="loadingHx" class="py-10 text-center text-white/40 text-sm animate-pulse">Loading history...</div>

      <div v-else-if="!history.length" class="py-10 text-center text-white/30 text-sm">
        No assessment reports yet for this player.
      </div>

      <div v-else class="flex flex-col gap-3">
        <div class="report-table-wrap">
          <div class="report-table-header">
            <div>Date</div>
            <div>Type</div>
            <div class="text-right">Score</div>
          </div>
          <button
            v-for="a in history"
            :key="`row-${a.id}`"
            class="report-table-row"
            :class="selectedReport?.id === a.id ? 'report-table-row--active' : ''"
            @click="selectedReport = a"
          >
            <div>{{ formatDate(a.assessment_date) }}</div>
            <div class="capitalize">{{ a.type || 'full' }}</div>
            <div class="text-right" :style="{ color: scoreColor(a.overall_score) }">{{ a.overall_score ?? '—' }}</div>
          </button>
        </div>

        <div
          v-if="selectedReport"
          :key="selectedReport.id"
          class="history-card"
        >
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="text-xs font-bold text-white">{{ formatDate(selectedReport.assessment_date) }}</div>
              <div class="text-[10px] text-white/40 uppercase tracking-wide mt-0.5">{{ selectedReport.type }} assessment</div>
            </div>
            <div class="flex gap-2">
              <div v-if="selectedReport.strength_overall_score" class="score-badge" :style="{ background: scoreColor(selectedReport.strength_overall_score) + '33', color: scoreColor(selectedReport.strength_overall_score), borderColor: scoreColor(selectedReport.strength_overall_score) + '55' }">
                💪 {{ selectedReport.strength_overall_score }}
              </div>
              <div v-if="selectedReport.mobility_overall_score" class="score-badge" :style="{ background: scoreColor(selectedReport.mobility_overall_score) + '33', color: scoreColor(selectedReport.mobility_overall_score), borderColor: scoreColor(selectedReport.mobility_overall_score) + '55' }">
                🤸 {{ selectedReport.mobility_overall_score }}
              </div>
              <div v-if="selectedReport.overall_score" class="score-badge score-badge--overall" :style="{ background: scoreColor(selectedReport.overall_score) + '33', color: scoreColor(selectedReport.overall_score), borderColor: scoreColor(selectedReport.overall_score) + '55' }">
                🏆 {{ selectedReport.overall_score }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3" v-if="selectedReport.overall_team_percentile || selectedReport.overall_age_percentile || selectedReport.age_group_years">
            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
              <div class="text-[10px] uppercase tracking-widest text-white/40">Team Percentile</div>
              <div class="text-sm font-black text-white">{{ selectedReport.overall_team_percentile ?? '—' }}</div>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
              <div class="text-[10px] uppercase tracking-widest text-white/40">Age Group Percentile</div>
              <div class="text-sm font-black text-white">{{ selectedReport.overall_age_percentile ?? '—' }}</div>
            </div>
            <div class="rounded-lg border border-white/10 bg-white/5 px-3 py-2">
              <div class="text-[10px] uppercase tracking-widest text-white/40">Age Group</div>
              <div class="text-sm font-black text-white">{{ selectedReport.age_group_years != null ? `${selectedReport.age_group_years}U` : '—' }}</div>
            </div>
          </div>

          <!-- sub-scores row -->
          <div class="grid grid-cols-4 gap-2 text-center mb-2">
            <div v-for="(sub, label) in { 'Lower': selectedReport.strength_lower_body_score, 'Upper': selectedReport.strength_upper_body_score, 'Explosive': selectedReport.strength_explosive_score, 'Rotational': selectedReport.strength_rotational_score }" :key="label" class="rounded-lg bg-white/5 py-1.5">
              <div class="text-[10px] text-white/40">{{ label }}</div>
              <div class="text-sm font-black" :style="{ color: scoreColor(sub) }">{{ sub ?? '—' }}</div>
            </div>
          </div>

          <!-- mobility sub-scores -->
          <div v-if="selectedReport.hip_mobility || selectedReport.shoulder_mobility || selectedReport.ankle_mobility" class="grid grid-cols-5 gap-1 text-center mb-2">
            <div v-for="(val, lbl) in { 'Hip': selectedReport.hip_mobility, 'Shoulder': selectedReport.shoulder_mobility, 'Ankle': selectedReport.ankle_mobility, 'Hip Flx': selectedReport.hip_flexor_mobility, 'Rotational': selectedReport.rotational_mobility }" :key="lbl" class="rounded-lg bg-white/5 py-1">
              <div class="text-[9px] text-white/30">{{ lbl }}</div>
              <div class="text-xs font-bold" :style="{ color: scoreColor((val ?? 0) * 10) }">{{ val ?? '—' }}<span class="text-[9px] text-white/25">/10</span></div>
            </div>
          </div>

          <div v-if="selectedReport.notes" class="text-[11px] text-white/50 italic border-t border-white/10 pt-2 mt-2">{{ selectedReport.notes }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.coach-assessment-panel {
  border-radius: 0.75rem;
  border: 1px solid rgba(255,255,255,0.1);
  background: rgba(255,255,255,0.03);
  padding: 1.25rem;
}

/* history */
.history-card {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  padding: 14px;
}
.report-table-wrap {
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  overflow: hidden;
}
.report-table-header,
.report-table-row {
  display: grid;
  grid-template-columns: 1.3fr 1fr 0.8fr;
  align-items: center;
  gap: 8px;
}
.report-table-header {
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.55);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  padding: 10px 12px;
}
.report-table-row {
  width: 100%;
  border: none;
  border-top: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.02);
  color: #fff;
  text-align: left;
  padding: 10px 12px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
}
.report-table-row:hover {
  background: rgba(255,255,255,0.08);
}
.report-table-row--active {
  background: rgba(192,0,0,0.16);
}
.score-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid;
}
.score-badge--overall { font-size: 12px; }
</style>
