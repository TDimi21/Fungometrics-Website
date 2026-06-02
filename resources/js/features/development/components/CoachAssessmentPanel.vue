<script setup>
/**
 * CoachAssessmentPanel.vue
 * Coach-facing strength + mobility assessment scorer.
 * Submits to POST /api/assessments, loads history from GET /api/assessments/player/{id}.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'
import { storeToRefs } from 'pinia'
import { computeStrengthAssessmentScore, getStrengthLabel } from '@/features/development/lib/strengthAssessmentScore.js'
import { toast } from '@/utils/AlertPlugin'

const props = defineProps({
  playerId: { type: String, required: true },
  playerName: { type: String, default: '' },
})

const { axiosGet, axiosPost } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

// ─── tabs ─────────────────────────────────────────────────────────────────────
const tab = ref('strength') // 'strength' | 'mobility' | 'history'

// ─── form state ───────────────────────────────────────────────────────────────
const saving    = ref(false)
const loadingHx = ref(false)
const history   = ref([])

const form = ref({
  assessment_date: new Date().toISOString().slice(0, 10),
  notes: '',
  // strength raw
  squat_lbs: null, deadlift_lbs: null, bench_lbs: null,
  broad_jump_in: null, vertical_jump_in: null, sprint_10yd_sec: null,
  // strength percentiles
  squat_percentile: null, deadlift_percentile: null, lunge_percentile: null,
  bench_press_percentile: null, pull_up_percentile: null, push_up_percentile: null,
  broad_jump_percentile: null, vertical_jump_percentile: null, sprint_10yd_percentile: null,
  med_ball_rotational_percentile: null, exit_velocity_percentile: null, bat_speed_percentile: null,
  // mobility (0-10)
  hip_mobility: null, shoulder_mobility: null, ankle_mobility: null,
  hip_flexor_mobility: null, rotational_mobility: null,
})

// ─── live score preview ────────────────────────────────────────────────────────
const liveScore = computed(() => computeStrengthAssessmentScore({
  squat_percentile:               form.value.squat_percentile ?? 0,
  deadlift_percentile:            form.value.deadlift_percentile ?? 0,
  lunge_percentile:               form.value.lunge_percentile ?? 0,
  bench_press_percentile:         form.value.bench_press_percentile ?? 0,
  pull_up_percentile:             form.value.pull_up_percentile ?? 0,
  push_up_percentile:             form.value.push_up_percentile ?? 0,
  broad_jump_percentile:          form.value.broad_jump_percentile ?? 0,
  vertical_jump_percentile:       form.value.vertical_jump_percentile ?? 0,
  sprint_10yd_percentile:         form.value.sprint_10yd_percentile ?? 0,
  med_ball_rotational_percentile: form.value.med_ball_rotational_percentile ?? 0,
  exit_velocity_percentile:       form.value.exit_velocity_percentile ?? 0,
  bat_speed_percentile:           form.value.bat_speed_percentile ?? 0,
}))

const mobilityFields = [
  { key: 'hip_mobility',        label: 'Hip Mobility',          tip: 'Hip flexion/extension range' },
  { key: 'shoulder_mobility',   label: 'Shoulder / T-Spine',    tip: 'Overhead reach & thoracic rotation' },
  { key: 'ankle_mobility',      label: 'Ankle Dorsiflexion',    tip: 'Ability to drive knee over toe' },
  { key: 'hip_flexor_mobility', label: 'Hip Flexor / Hamstring',tip: 'Flexibility under load' },
  { key: 'rotational_mobility', label: 'Rotational Range',      tip: 'Core/trunk rotation ROM' },
]

const liveMobility = computed(() => {
  const vals = mobilityFields.map(f => form.value[f.key]).filter(v => v !== null && v !== '')
  if (!vals.length) return null
  return Math.round((vals.reduce((a, b) => a + Number(b), 0) / vals.length) * 10)
})

const liveMobilityLabel = computed(() => {
  const s = liveMobility.value
  if (s === null) return '—'
  if (s >= 85) return 'Elite'
  if (s >= 70) return 'Good'
  if (s >= 55) return 'Average'
  if (s >= 40) return 'Limited'
  return 'Restricted'
})

// ─── score colour helper ───────────────────────────────────────────────────────
const scoreColor = (s) => {
  if (!s && s !== 0) return '#64748B'
  if (s >= 85) return '#2ECC71'
  if (s >= 70) return '#27AE60'
  if (s >= 55) return '#F39C12'
  if (s >= 40) return '#E67E22'
  return '#E74C3C'
}

// ─── strength section definitions ────────────────────────────────────────────
const strengthSections = [
  {
    key: 'lowerBody', label: '🦵 Lower Body', color: '#3B82F6',
    fields: [
      { pctKey: 'squat_percentile',    rawKey: 'squat_lbs',    label: 'Back Squat',  rawLabel: 'Squat (lbs)',    rawType: 'integer' },
      { pctKey: 'deadlift_percentile', rawKey: 'deadlift_lbs', label: 'Deadlift',    rawLabel: 'Deadlift (lbs)', rawType: 'integer' },
      { pctKey: 'lunge_percentile',    rawKey: null,           label: 'Lunge',       rawLabel: null },
    ],
  },
  {
    key: 'upperBody', label: '💪 Upper Body', color: '#A855F7',
    fields: [
      { pctKey: 'bench_press_percentile', rawKey: 'bench_lbs', label: 'Bench Press', rawLabel: 'Bench (lbs)', rawType: 'integer' },
      { pctKey: 'pull_up_percentile',     rawKey: null,        label: 'Pull-Ups',    rawLabel: null },
      { pctKey: 'push_up_percentile',     rawKey: null,        label: 'Push-Ups',    rawLabel: null },
    ],
  },
  {
    key: 'explosivePower', label: '⚡ Explosive Power', color: '#F59E0B',
    fields: [
      { pctKey: 'broad_jump_percentile',    rawKey: 'broad_jump_in',    label: 'Broad Jump',    rawLabel: 'Distance (in)', rawType: 'integer' },
      { pctKey: 'vertical_jump_percentile', rawKey: 'vertical_jump_in', label: 'Vertical Jump', rawLabel: 'Height (in)',   rawType: 'integer' },
      { pctKey: 'sprint_10yd_percentile',   rawKey: 'sprint_10yd_sec',  label: '10-Yd Sprint',  rawLabel: 'Time (sec)',    rawType: 'decimal' },
    ],
  },
  {
    key: 'rotationalPower', label: '🌀 Rotational Power', color: '#EF4444',
    fields: [
      { pctKey: 'med_ball_rotational_percentile', rawKey: null, label: 'Med Ball Throw', rawLabel: null },
      { pctKey: 'exit_velocity_percentile',       rawKey: null, label: 'Exit Velocity',  rawLabel: null },
      { pctKey: 'bat_speed_percentile',           rawKey: null, label: 'Bat Speed',      rawLabel: null },
    ],
  },
]

// ─── load history ─────────────────────────────────────────────────────────────
const loadHistory = async () => {
  if (!props.playerId) return
  loadingHx.value = true
  try {
    const { data } = await axiosGet('assessments/player/' + props.playerId)
    history.value = data?.data ?? []
  } catch (_) {
    history.value = []
  } finally {
    loadingHx.value = false
  }
}

onMounted(loadHistory)
watch(() => props.playerId, loadHistory)

// ─── submit ───────────────────────────────────────────────────────────────────
const submit = async () => {
  saving.value = true
  try {
    const payload = {
      user_id:        props.playerId,
      team_id:        team.value?.id_team ?? team.value?.id ?? null,
      assessment_date: form.value.assessment_date,
      type:           'full',
      notes:          form.value.notes || null,
    }

    // include all non-null form fields
    for (const [k, v] of Object.entries(form.value)) {
      if (k === 'assessment_date' || k === 'notes') continue
      if (v !== null && v !== '') payload[k] = Number(v)
    }

    await axiosPost('assessments', payload)
    toast('Assessment saved!', 'success')
    await loadHistory()
    tab.value = 'history'
  } catch (e) {
    toast('Failed to save assessment. Please try again.', 'error')
  } finally {
    saving.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'
</script>

<template>
  <div class="coach-assessment-panel">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div>
        <div class="text-[10px] uppercase tracking-widest text-white/40 mb-0.5">Coach Assessment Tool</div>
        <div class="text-base font-black text-white">{{ playerName || 'Player' }} — Strength &amp; Mobility</div>
      </div>
      <div class="flex gap-1">
        <button
          v-for="t in [{ key: 'strength', label: '💪 Strength' }, { key: 'mobility', label: '🤸 Mobility' }, { key: 'history', label: '📋 History' }]"
          :key="t.key"
          @click="tab = t.key"
          class="tab-btn"
          :class="tab === t.key ? 'tab-btn--active' : ''"
        >{{ t.label }}</button>
      </div>
    </div>

    <!-- ── STRENGTH TAB ──────────────────────────────────────────────────────── -->
    <div v-if="tab === 'strength'">

      <!-- Live score strip -->
      <div class="score-strip mb-5">
        <div v-for="(sec, i) in [
          { label: 'Lower Body',       val: liveScore.parts.lowerBody,       color: '#3B82F6' },
          { label: 'Upper Body',       val: liveScore.parts.upperBody,       color: '#A855F7' },
          { label: 'Explosive Power',  val: liveScore.parts.explosivePower,  color: '#F59E0B' },
          { label: 'Rotational',       val: liveScore.parts.rotationalPower, color: '#EF4444' },
        ]" :key="i" class="score-tile">
          <div class="score-tile-val" :style="{ color: scoreColor(val) }">{{ sec.val || '—' }}</div>
          <div class="score-tile-label">{{ sec.label }}</div>
        </div>
        <div class="score-tile score-tile--overall">
          <div class="score-tile-val score-tile-val--big" :style="{ color: scoreColor(liveScore.score) }">{{ liveScore.score || '—' }}</div>
          <div class="score-tile-label">Overall</div>
          <div class="score-tile-label" :style="{ color: scoreColor(liveScore.score) }">{{ liveScore.labels.overall }}</div>
        </div>
      </div>

      <!-- Sections -->
      <div class="flex flex-col gap-5">
        <div v-for="section in strengthSections" :key="section.key" class="section-card">
          <div class="section-header" :style="{ borderLeftColor: section.color }">
            <span class="section-title">{{ section.label }}</span>
            <span class="section-score" :style="{ color: scoreColor(liveScore.parts[section.key]) }">
              {{ liveScore.parts[section.key] || '—' }} / 100
            </span>
          </div>
          <div class="section-body">
            <div v-for="field in section.fields" :key="field.pctKey" class="field-row">
              <div class="field-label">{{ field.label }}</div>
              <div class="field-inputs">
                <!-- Raw value (optional) -->
                <div v-if="field.rawKey" class="raw-wrap">
                  <input
                    v-model.number="form[field.rawKey]"
                    type="number"
                    :placeholder="field.rawLabel"
                    class="raw-input"
                    min="0"
                  />
                </div>
                <!-- Percentile 0-100 -->
                <div class="pct-wrap">
                  <input
                    v-model.number="form[field.pctKey]"
                    type="range"
                    min="0" max="100" step="1"
                    class="pct-slider"
                    :style="{ '--pct-color': section.color }"
                  />
                  <span class="pct-val" :style="{ color: scoreColor(form[field.pctKey]) }">
                    {{ form[field.pctKey] ?? 0 }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Date + Notes -->
      <div class="mt-4 flex flex-col gap-3">
        <div class="flex gap-3 flex-wrap">
          <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
            <label class="field-label">Assessment Date</label>
            <input v-model="form.assessment_date" type="date" class="raw-input w-full" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="field-label">Coach Notes</label>
          <textarea v-model="form.notes" rows="3" placeholder="Key observations, areas to target..." class="notes-input" />
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-2">
        <button @click="tab = 'mobility'" class="btn-secondary">Next: Mobility →</button>
        <button @click="submit" :disabled="saving" class="btn-primary">
          {{ saving ? 'Saving...' : '💾 Save Assessment' }}
        </button>
      </div>
    </div>

    <!-- ── MOBILITY TAB ─────────────────────────────────────────────────────── -->
    <div v-if="tab === 'mobility'">

      <!-- Live mobility score -->
      <div class="mb-5 flex items-center gap-4 p-4 rounded-xl border border-white/10 bg-white/5">
        <div class="text-center">
          <div class="text-4xl font-black" :style="{ color: scoreColor(liveMobility) }">
            {{ liveMobility ?? '—' }}
          </div>
          <div class="text-[10px] uppercase tracking-widest text-white/40 mt-1">Mobility Score</div>
        </div>
        <div class="flex-1">
          <div class="text-base font-bold" :style="{ color: scoreColor(liveMobility) }">{{ liveMobilityLabel }}</div>
          <div class="text-xs text-white/50 mt-1">Rate each area 0–10 where 0 = severely restricted, 10 = elite range of motion</div>
        </div>
      </div>

      <!-- Mobility fields -->
      <div class="flex flex-col gap-4">
        <div v-for="mf in mobilityFields" :key="mf.key" class="section-card">
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="text-sm font-bold text-white">{{ mf.label }}</div>
              <div class="text-[11px] text-white/40">{{ mf.tip }}</div>
            </div>
            <span class="text-xl font-black" :style="{ color: scoreColor((form[mf.key] ?? 0) * 10) }">
              {{ form[mf.key] ?? 0 }}<span class="text-xs font-normal text-white/40">/10</span>
            </span>
          </div>
          <input
            v-model.number="form[mf.key]"
            type="range" min="0" max="10" step="1"
            class="pct-slider w-full"
            style="--pct-color: #2ECC71"
          />
          <!-- Tap labels -->
          <div class="flex justify-between text-[9px] text-white/25 mt-1 px-0.5">
            <span>Restricted</span><span>Limited</span><span>Average</span><span>Good</span><span>Elite</span>
          </div>
        </div>
      </div>

      <div class="mt-5 flex justify-between gap-2">
        <button @click="tab = 'strength'" class="btn-secondary">← Back to Strength</button>
        <button @click="submit" :disabled="saving" class="btn-primary">
          {{ saving ? 'Saving...' : '💾 Save Assessment' }}
        </button>
      </div>
    </div>

    <!-- ── HISTORY TAB ──────────────────────────────────────────────────────── -->
    <div v-if="tab === 'history'">
      <div v-if="loadingHx" class="py-10 text-center text-white/40 text-sm animate-pulse">Loading history...</div>

      <div v-else-if="!history.length" class="py-10 text-center text-white/30 text-sm">
        No assessments yet for this player.<br/>
        <button @click="tab = 'strength'" class="mt-3 btn-secondary text-xs">+ New Assessment</button>
      </div>

      <div v-else class="flex flex-col gap-3">
        <button @click="tab = 'strength'" class="btn-primary self-start mb-1">+ New Assessment</button>

        <div
          v-for="a in history"
          :key="a.id"
          class="history-card"
        >
          <div class="flex items-center justify-between mb-3">
            <div>
              <div class="text-xs font-bold text-white">{{ formatDate(a.assessment_date) }}</div>
              <div class="text-[10px] text-white/40 uppercase tracking-wide mt-0.5">{{ a.type }} assessment</div>
            </div>
            <div class="flex gap-2">
              <div v-if="a.strength_overall_score" class="score-badge" :style="{ background: scoreColor(a.strength_overall_score) + '33', color: scoreColor(a.strength_overall_score), borderColor: scoreColor(a.strength_overall_score) + '55' }">
                💪 {{ a.strength_overall_score }}
              </div>
              <div v-if="a.mobility_overall_score" class="score-badge" :style="{ background: scoreColor(a.mobility_overall_score) + '33', color: scoreColor(a.mobility_overall_score), borderColor: scoreColor(a.mobility_overall_score) + '55' }">
                🤸 {{ a.mobility_overall_score }}
              </div>
              <div v-if="a.overall_score" class="score-badge score-badge--overall" :style="{ background: scoreColor(a.overall_score) + '33', color: scoreColor(a.overall_score), borderColor: scoreColor(a.overall_score) + '55' }">
                🏆 {{ a.overall_score }}
              </div>
            </div>
          </div>

          <!-- sub-scores row -->
          <div class="grid grid-cols-4 gap-2 text-center mb-2">
            <div v-for="(sub, label) in { 'Lower': a.strength_lower_body_score, 'Upper': a.strength_upper_body_score, 'Explosive': a.strength_explosive_score, 'Rotational': a.strength_rotational_score }" :key="label" class="rounded-lg bg-white/5 py-1.5">
              <div class="text-[10px] text-white/40">{{ label }}</div>
              <div class="text-sm font-black" :style="{ color: scoreColor(sub) }">{{ sub ?? '—' }}</div>
            </div>
          </div>

          <!-- mobility sub-scores -->
          <div v-if="a.hip_mobility || a.shoulder_mobility || a.ankle_mobility" class="grid grid-cols-5 gap-1 text-center mb-2">
            <div v-for="(val, lbl) in { 'Hip': a.hip_mobility, 'Shoulder': a.shoulder_mobility, 'Ankle': a.ankle_mobility, 'Hip Flx': a.hip_flexor_mobility, 'Rotational': a.rotational_mobility }" :key="lbl" class="rounded-lg bg-white/5 py-1">
              <div class="text-[9px] text-white/30">{{ lbl }}</div>
              <div class="text-xs font-bold" :style="{ color: scoreColor((val ?? 0) * 10) }">{{ val ?? '—' }}<span class="text-[9px] text-white/25">/10</span></div>
            </div>
          </div>

          <div v-if="a.notes" class="text-[11px] text-white/50 italic border-t border-white/10 pt-2 mt-2">{{ a.notes }}</div>
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

/* tabs */
.tab-btn {
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
  border: 1px solid rgba(255,255,255,0.12);
  color: rgba(255,255,255,0.5);
  background: transparent;
  cursor: pointer;
  transition: all 0.15s;
}
.tab-btn--active {
  background: rgba(192,0,0,0.8);
  border-color: #C00000;
  color: #fff;
}
.tab-btn:hover:not(.tab-btn--active) {
  color: #fff;
  background: rgba(255,255,255,0.08);
}

/* score strip */
.score-strip {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.score-tile {
  flex: 1;
  min-width: 70px;
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px;
  background: rgba(255,255,255,0.04);
  padding: 10px 8px;
  text-align: center;
}
.score-tile--overall {
  border-color: rgba(192,0,0,0.35);
  background: rgba(192,0,0,0.08);
}
.score-tile-val {
  font-size: 1.4rem;
  font-weight: 900;
  line-height: 1;
}
.score-tile-val--big {
  font-size: 2rem;
}
.score-tile-label {
  font-size: 9px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,0.4);
  margin-top: 3px;
}

/* section card */
.section-card {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  padding: 14px;
}
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-left: 3px solid;
  padding-left: 10px;
  margin-bottom: 12px;
}
.section-title {
  font-size: 13px;
  font-weight: 800;
  color: #fff;
}
.section-score {
  font-size: 12px;
  font-weight: 800;
}
.section-body { display: flex; flex-direction: column; gap: 10px; }

/* field row */
.field-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.field-label {
  font-size: 11px;
  font-weight: 600;
  color: rgba(255,255,255,0.65);
  min-width: 110px;
  flex-shrink: 0;
}
.field-inputs { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; }
.raw-wrap { flex-shrink: 0; }
.raw-input {
  background: rgba(0,0,0,0.35);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 6px;
  color: #fff;
  font-size: 12px;
  padding: 4px 8px;
  width: 80px;
  outline: none;
}
.raw-input:focus { border-color: rgba(192,0,0,0.5); }
.raw-input::placeholder { color: rgba(255,255,255,0.25); }

/* percentile slider */
.pct-wrap { display: flex; align-items: center; gap: 8px; flex: 1; }
.pct-slider {
  flex: 1;
  height: 4px;
  -webkit-appearance: none;
  appearance: none;
  border-radius: 2px;
  background: rgba(255,255,255,0.12);
  outline: none;
  cursor: pointer;
  accent-color: var(--pct-color, #C00000);
}
.pct-val {
  font-size: 13px;
  font-weight: 900;
  min-width: 28px;
  text-align: right;
}

/* notes */
.notes-input {
  background: rgba(0,0,0,0.35);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 8px;
  color: #fff;
  font-size: 12px;
  padding: 8px 10px;
  width: 100%;
  resize: vertical;
  outline: none;
}
.notes-input:focus { border-color: rgba(192,0,0,0.5); }

/* buttons */
.btn-primary {
  padding: 8px 20px;
  border-radius: 8px;
  background: #C00000;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: background 0.15s;
}
.btn-primary:hover:not(:disabled) { background: #a00000; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary {
  padding: 8px 16px;
  border-radius: 8px;
  background: rgba(255,255,255,0.07);
  color: rgba(255,255,255,0.7);
  font-size: 12px;
  font-weight: 600;
  border: 1px solid rgba(255,255,255,0.12);
  cursor: pointer;
  transition: background 0.15s;
}
.btn-secondary:hover { background: rgba(255,255,255,0.12); color: #fff; }

/* history */
.history-card {
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  background: rgba(255,255,255,0.03);
  padding: 14px;
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
