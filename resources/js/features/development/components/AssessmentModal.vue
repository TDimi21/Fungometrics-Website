<script setup>
import { ref, reactive, computed, watch, nextTick } from 'vue'
import {
  computeFmtrxAssessment, throwsPerDayOptions, pitchCountOptions, intensityOptions, scoreColor,
} from '@/features/development/lib/fmtrxAssessmentScore.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const { axiosPost, axiosGet, axiosDelete } = useAxiosAuth()

const props = defineProps({
  visible: { type: Boolean, default: false },
  playerName: { type: String, default: '' },
  playerId: { type: [String, Number], default: '' },
  teamId: { type: [String, Number], default: '' },
  players: { type: Array, default: () => [] }, // [{ id, name }] roster for switching
})
const emit = defineEmits(['close', 'saved', 'player-change'])

const saving = ref(false)
const saveError = ref('')

// Active player inside the modal — lets the coach switch players without closing.
const activePlayerId = ref('')
const activePlayerName = computed(() => {
  const p = (props.players || []).find(x => String(x.id) === String(activePlayerId.value))
  return p?.name || props.playerName || '—'
})
const dirty = ref(false)

// ── Wizard steps (mirror the mobile app) ─────────────────────────────────────
const STEPS = [
  { key: 'baseball', label: 'Baseball Metrics' },
  { key: 'strength', label: 'Strength Testing' },
  { key: 'mobility', label: 'Mobility Testing' },
  { key: 'arm',      label: 'Throwing / Arm Health' },
  { key: 'hitting',  label: 'Hitting Assessment' },
  { key: 'pitching', label: 'Pitching Assessment' },
  { key: 'fmtrx',    label: 'FMTRX Scoring' },
]
const stepIndex = ref(0)
const currentStep = computed(() => STEPS[stepIndex.value])
const currentStepKey = computed(() => currentStep.value?.key || STEPS[0].key)
const goTo = (key) => { const i = STEPS.findIndex(s => s.key === key); if (i >= 0) stepIndex.value = i }
const next = () => { if (stepIndex.value < STEPS.length - 1) stepIndex.value++ }
const back = () => { if (stepIndex.value > 0) stepIndex.value-- }

// ── Form ─────────────────────────────────────────────────────────────────────
const blankForm = () => ({
  fitness_date: new Date().toISOString().slice(0, 10),
  // baseball metrics / athletic
  pitch_velo_mph: '', position_velo: '', exit_velocity_mph: '',
  yd_60_dash_sec: '', sprint_10yd_sec: '', home_to_first: '', catcher_pop_time: '',
  throwing_velo_mph: '',
  // strength
  body_weight_lbs: '', front_squat_lbs: '', back_squat_lbs: '', bench_press_lbs: '',
  dead_lift_lbs: '', pull_ups: '', push_ups: '', trap_bar_deadlift: '',
  power_clean_lbs: '', grip_strength_left: '', grip_strength_right: '',
  vertical_jump_inches: '', broad_jump_inches: '', med_ball_rotational_throw_ft: '', plank_hold: '',
  yd_40_dash_sec: '', sleep_hours: '', sleep_quality_1_to_5: '', recovery_score: '',
  strength_notes: '',
  lift_repetitions: {}, lift_test_methods: {}, push_up_protocol: '', plank_protocol: '',
  grip_device: '', grip_protocol: '', med_ball_weight_lbs: '', med_ball_protocol: '',
  // mobility (0-5)
  shoulder_mobility: '', hip_mobility: '', ankle_mobility: '', hamstring_mobility: '',
  t_spine_rotation: '', overhead_squat: '', single_leg_balance: '',
  // throwing / arm health
  primary_throwing_role: '', throwing_days_per_week: '', bullpens_per_week: '',
  long_toss_sessions_per_week: '', weighted_ball_sessions_per_week: '', games_per_week: '',
  throws_per_day_range: '', weekly_pitch_count_range: '', weighted_ball_usage: '',
  throwing_intensity: '', arm_fatigue: '', arm_soreness: '', arm_pain: '', arm_pain_notes: '',
  // hitting
  max_exit_velo: '', avg_exit_velo: '', contact_percentage: '', hard_hit_percentage: '',
  line_drive_percentage: '', chase_percentage: '', whiff_percentage: '', spray_tendency: '',
  // hitting mechanics (1-5)
  hit_setup: '', hit_load: '', hit_lower_half: '', hit_rotation: '',
  hit_barrel_path: '', hit_contact: '', hit_attack_angle: '', hit_balance: '', hit_approach: '',
  hitting_notes: '',
  // pitching
  fastball_velocity: '', strike_percentage: '', command_percentage: '',
  pitch_types: [], pitch_grades: {}, spin_metrics: '', pitching_notes: '',
  // pitching mechanics (1-5)
  pit_posture: '', pit_tempo: '', pit_lower_half: '', pit_front_leg: '',
  pit_hip_rotation: '', pit_core_stability: '', pit_arm_action: '', pit_finish: '',
  // report
  notes: '',
})
const form = reactive(blankForm())

// ── Per-player draft (work on multiple players, finalize with Save Baseline) ──
const draftKey = (id = activePlayerId.value) => `fmtrx_assessment_draft_${id || 'unknown'}`
const draftMsg = ref('')
let draftMsgTimer = null
const flashDraftMsg = (text) => {
  draftMsg.value = text
  if (draftMsgTimer) clearTimeout(draftMsgTimer)
  draftMsgTimer = setTimeout(() => { draftMsg.value = '' }, 2500)
}
const applyDraftData = (saved) => {
  Object.assign(form, blankForm())
  if (saved && typeof saved === 'object') {
    Object.assign(form, saved)
    if (!Array.isArray(form.pitch_types)) form.pitch_types = []
    if (!form.pitch_grades || typeof form.pitch_grades !== 'object') form.pitch_grades = {}
  }
  // Reset dirty after the deep watcher has flushed the programmatic changes.
  applyingDraft = true
  nextTick(() => { applyingDraft = false; dirty.value = false })
}
const loadDraft = async (id = activePlayerId.value) => {
  // Server draft (team-shared) first, then the local cache when offline.
  let saved = null
  try {
    const res = await axiosGet(`coach/assessment-drafts/${id}`)
    const draft = res?.data?.data
    if (draft && draft.data) {
      saved = draft.data
      try { localStorage.setItem(draftKey(id), JSON.stringify(saved)) } catch (_) { /* noop */ }
    }
  } catch (_) { /* offline — fall back to local */ }
  if (!saved) {
    try {
      const raw = localStorage.getItem(draftKey(id))
      if (raw) saved = JSON.parse(raw)
    } catch (_) { /* ignore corrupt draft */ }
  }
  applyDraftData(saved)
}
const saveDraft = async () => {
  if (!activePlayerId.value) {
    saveError.value = 'Select a player first.'
    return
  }
  saveError.value = ''
  const snapshot = JSON.parse(JSON.stringify(form))
  try { localStorage.setItem(draftKey(), JSON.stringify(snapshot)) } catch (_) { /* noop */ }
  dirty.value = false
  let synced = false
  try {
    await axiosPost('coach/assessment-drafts', {
      user_id: String(activePlayerId.value),
      team_id: props.teamId ? String(props.teamId) : null,
      data: snapshot,
    })
    synced = true
  } catch (_) { /* offline */ }
  flashDraftMsg(synced ? 'Draft saved & synced — switch players and come back.' : 'Draft saved locally (offline).')
}
const clearDraft = async () => {
  const id = activePlayerId.value
  try { localStorage.removeItem(draftKey(id)) } catch (_) { /* noop */ }
  try { await axiosDelete('coach/assessment-drafts/', id) } catch (_) { /* offline */ }
}

const hasSavedDraft = computed(() => {
  try { return !!localStorage.getItem(draftKey()) } catch (_) { return false }
})

// Any edit marks the form dirty (so we can warn before switching players).
let applyingDraft = false
watch(form, () => { if (!applyingDraft) dirty.value = true }, { deep: true })

// Switch to another player, prompting to save first so nothing is lost.
const switchPlayer = (newId) => {
  if (!newId || String(newId) === String(activePlayerId.value)) return
  if (dirty.value) {
    const ok = window.confirm(
      `Save ${activePlayerName.value}'s data before switching?\n\nClick OK to save this player, or Cancel to stay.`,
    )
    if (!ok) return // stay on the current player
    saveDraft()
  }
  activePlayerId.value = String(newId)
  emit('player-change', activePlayerId.value)
  loadDraft()
  stepIndex.value = 0
  draftMsg.value = ''
}

const resolveInitialPlayerId = () => {
  if (props.playerId) return String(props.playerId)
  const first = (props.players || []).find(p => p?.id)
  return first?.id ? String(first.id) : ''
}

watch(() => props.visible, (v) => {
  if (v) {
    activePlayerId.value = resolveInitialPlayerId()
    loadDraft()
    stepIndex.value = 0
    draftMsg.value = ''
  }
})

const fmtrx = computed(() => {
  // Grip Strength L/R map to the lib's single hand_strength input (avg of the two).
  const gl = Number(form.grip_strength_left) || 0
  const gr = Number(form.grip_strength_right) || 0
  const hand = gl > 0 && gr > 0 ? (gl + gr) / 2 : Math.max(gl, gr)
  return computeFmtrxAssessment({ ...form, hand_strength_lbs: hand || '' })
})
const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v)

const roleOptions = ['SP', 'RP', '2-Way', 'C', 'INF', 'OF', 'UTIL']
const weeklyRows = [
  { key: 'throwing_days_per_week', label: 'Throw Days' },
  { key: 'bullpens_per_week', label: 'Bullpens' },
  { key: 'long_toss_sessions_per_week', label: 'Long Toss' },
  { key: 'weighted_ball_sessions_per_week', label: 'Weighted' },
  { key: 'games_per_week', label: 'Games' },
]
const weightedBallOptions = ['Never', 'Occasionally', 'In-season only', 'Off-season only', 'Year-round']

// 1-3-5 mechanics grids (qualitative; mirror the app)
const hittingMechanics = [
  { key: 'hit_setup',       label: 'Setup',       opts: [{ v: 1, l: 'Poor' }, { v: 3, l: 'Average' }, { v: 5, l: 'Athletic' }] },
  { key: 'hit_load',        label: 'Load',        opts: [{ v: 1, l: 'Passive' }, { v: 3, l: 'Inconsistent' }, { v: 5, l: 'Explosive' }] },
  { key: 'hit_lower_half',  label: 'Lower Half',  opts: [{ v: 1, l: 'All Arms' }, { v: 3, l: 'Some Drive' }, { v: 5, l: 'Uses the Ground' }] },
  { key: 'hit_rotation',    label: 'Rotation',    opts: [{ v: 1, l: 'Opens Early' }, { v: 3, l: 'Average' }, { v: 5, l: 'Elite Separation' }] },
  { key: 'hit_barrel_path', label: 'Barrel Path', opts: [{ v: 1, l: 'Long' }, { v: 3, l: 'Average' }, { v: 5, l: 'Short & Efficient' }] },
  { key: 'hit_contact',     label: 'Contact',     opts: [{ v: 1, l: 'Weak' }, { v: 3, l: 'Average' }, { v: 5, l: 'Consistent Hard Contact' }] },
  { key: 'hit_attack_angle', label: 'Attack Angle', opts: [{ v: 1, l: 'Low' }, { v: 3, l: 'High' }, { v: 5, l: 'Line Drive' }] },
  { key: 'hit_balance',     label: 'Balance',     opts: [{ v: 1, l: 'Falls Off' }, { v: 3, l: 'Average' }, { v: 5, l: 'Controlled Finish' }] },
  { key: 'hit_approach',    label: 'Approach',    opts: [{ v: 1, l: 'Reactive' }, { v: 3, l: 'Average' }, { v: 5, l: 'Confident & Disciplined' }] },
]
const pitchingMechanics = [
  { key: 'pit_posture',       label: 'Posture',       opts: [{ v: 1, l: 'Poor' }, { v: 3, l: 'Average' }, { v: 5, l: 'Athletic' }] },
  { key: 'pit_tempo',         label: 'Tempo',         opts: [{ v: 1, l: 'Rushed' }, { v: 3, l: 'Average' }, { v: 5, l: 'Smooth' }] },
  { key: 'pit_lower_half',    label: 'Lower Half',    opts: [{ v: 1, l: 'Weak' }, { v: 3, l: 'Average' }, { v: 5, l: 'Powerful' }] },
  { key: 'pit_front_leg',     label: 'Front Leg',     opts: [{ v: 1, l: 'Collapses' }, { v: 3, l: 'Some Block' }, { v: 5, l: 'Firm Block' }] },
  { key: 'pit_hip_rotation',  label: 'Hip Rotation',  opts: [{ v: 1, l: 'Early/Open' }, { v: 3, l: 'Average' }, { v: 5, l: 'Excellent Separation' }] },
  { key: 'pit_core_stability', label: 'Core Stability', opts: [{ v: 1, l: 'Energy Leaks' }, { v: 3, l: 'Average' }, { v: 5, l: 'Stable' }] },
  { key: 'pit_arm_action',    label: 'Arm Action',    opts: [{ v: 1, l: 'Inconsistent' }, { v: 3, l: 'Average' }, { v: 5, l: 'Clean & Repeatable' }] },
  { key: 'pit_finish',        label: 'Finish',        opts: [{ v: 1, l: 'Falls Off' }, { v: 3, l: 'Average' }, { v: 5, l: 'Balanced' }] },
]
const pitchTypeOptions = [
  { label: 'FB', value: 'Fastball' }, { label: '2S', value: 'Two-Seam' }, { label: 'CT', value: 'Cutter' },
  { label: 'SL', value: 'Slider' }, { label: 'CB', value: 'Curveball' }, { label: 'CH', value: 'Changeup' },
  { label: 'SP', value: 'Splitter' }, { label: 'SK', value: 'Sinker' }, { label: 'KN', value: 'Knuckleball' },
]
const mlbPitchGrades = [
  { grade: 20, label: 'Well Below Average' }, { grade: 30, label: 'Below Average' }, { grade: 40, label: 'Fringe' },
  { grade: 45, label: 'Fringe Average' }, { grade: 50, label: 'MLB Average' }, { grade: 55, label: 'Above Average' },
  { grade: 60, label: 'Plus' }, { grade: 70, label: 'Plus-Plus' }, { grade: 80, label: 'Elite' },
]
const togglePitchType = (pt) => {
  const i = form.pitch_types.indexOf(pt)
  if (i >= 0) form.pitch_types.splice(i, 1)
  else form.pitch_types.push(pt)
}
const setPitchGrade = (pt, grade) => { form.pitch_grades = { ...form.pitch_grades, [pt]: grade } }

const fmtrxTiles = computed(() => {
  const f = fmtrx.value
  return [
    { label: 'Athletic', value: f.athletic },
    { label: 'Strength', value: f.strength },
    { label: 'Mobility', value: f.mobility },
    { label: 'Hitting', value: f.hitting },
    { label: 'Pitching', value: f.pitching },
    { label: 'Arm Health', value: f.armHealth },
    { label: 'Overall', value: f.overall },
  ]
})

// Map the modal form to the /assessments payload the backend expects.
const buildPayload = () => {
  const s = fmtrx.value
  const num = (v) => { const n = Number(v); return Number.isFinite(n) && n > 0 ? n : null }
  const int = (v) => { const n = Math.round(Number(v)); return Number.isFinite(n) && n > 0 ? n : null }
  const count = (v) => { if (v === '' || v === null || v === undefined) return null; const n = Math.round(Number(v)); return Number.isFinite(n) && n >= 0 ? n : null }
  const p = {
    user_id: String(activePlayerId.value),
    assessment_date: form.fitness_date || null,
    type: 'full',
    notes: form.notes || null,
  }
  if (props.teamId) p.team_id = String(props.teamId)
  const add = (k, v) => { if (v !== null && v !== undefined) p[k] = v }
  // raw strength (backend keys)
  add('squat_lbs', int(form.back_squat_lbs))
  add('deadlift_lbs', int(form.dead_lift_lbs))
  add('bench_lbs', int(form.bench_press_lbs))
  add('broad_jump_in', int(form.broad_jump_inches))
  add('vertical_jump_in', int(form.vertical_jump_inches))
  add('sprint_10yd_sec', num(form.sprint_10yd_sec))
  add('body_weight_lbs', num(form.body_weight_lbs))
  p.fitness_snapshot = {
    front_squat: int(form.front_squat_lbs),
    back_squat: int(form.back_squat_lbs),
    bench_press: int(form.bench_press_lbs),
    dead_lift: int(form.dead_lift_lbs),
    trap_bar_deadlift: num(form.trap_bar_deadlift),
    power_clean: int(form.power_clean_lbs),
    pull_ups: count(form.pull_ups),
    push_ups: count(form.push_ups),
    grip_strength_left: num(form.grip_strength_left),
    grip_strength_right: num(form.grip_strength_right),
    vertical_jump: num(form.vertical_jump_inches),
    broad_jump: num(form.broad_jump_inches),
    med_ball_rotational_throw: num(form.med_ball_rotational_throw_ft),
    plank_hold: num(form.plank_hold),
    yd_40_dash: num(form.yd_40_dash_sec),
    yd_60_dash: num(form.yd_60_dash_sec),
    sleep_hours: num(form.sleep_hours),
    sleep_quality_1_to_5: int(form.sleep_quality_1_to_5),
    recovery_score: int(form.recovery_score),
    strength_test_metadata: {
      metrics: Object.fromEntries(Object.entries(form.lift_repetitions || {}).map(([key, repetitions]) => [key, {
        repetitions: int(repetitions), method: form.lift_test_methods?.[key] || (Number(repetitions) === 1 ? 'tested_1rm' : 'rep_max'),
      }])),
      protocols: {
        push_ups: form.push_up_protocol || null, plank_hold: form.plank_protocol || null,
        grip_device: form.grip_device || null, grip: form.grip_protocol || null,
        med_ball_weight_lbs: num(form.med_ball_weight_lbs), med_ball: form.med_ball_protocol || null,
      },
    },
  }
  // mobility (0-5 fits the 0-10 validation)
  add('shoulder_mobility', int(form.shoulder_mobility))
  add('hip_mobility', int(form.hip_mobility))
  add('ankle_mobility', int(form.ankle_mobility))
  add('rotational_mobility', int(form.t_spine_rotation))
  // computed section scores
  add('arm_health_score', int(s.armHealth))
  add('hitting_score', int(s.hitting))
  add('pitching_score', int(s.pitching))
  add('throwing_workload_score', int(s.workload))
  if (s.workloadLabel) p.throwing_workload_level = s.workloadLabel
  // rich JSON detail
  p.throwing_workload_data = {
    primary_throwing_role: form.primary_throwing_role || null,
    throwing_days_per_week: form.throwing_days_per_week, bullpens_per_week: form.bullpens_per_week,
    long_toss_sessions_per_week: form.long_toss_sessions_per_week,
    weighted_ball_sessions_per_week: form.weighted_ball_sessions_per_week, games_per_week: form.games_per_week,
    throws_per_day_range: form.throws_per_day_range || null, weekly_pitch_count_range: form.weekly_pitch_count_range || null,
    weighted_ball_usage: form.weighted_ball_usage || null, throwing_intensity: form.throwing_intensity || null,
    arm_fatigue: form.arm_fatigue, arm_soreness: form.arm_soreness,
    arm_pain: form.arm_pain || null, arm_pain_notes: form.arm_pain_notes || null,
    workload_score: s.workload, workload_level: s.workloadLabel,
  }
  p.hitting_data = {
    max_exit_velo: form.max_exit_velo, avg_exit_velo: form.avg_exit_velo,
    mechanics: Object.fromEntries(hittingMechanics.map(m => [m.key.replace(/^hit_/, ''), form[m.key]])),
    notes: form.hitting_notes || null,
  }
  p.pitching_data = {
    fastball_velocity: form.fastball_velocity, strike_percentage: form.strike_percentage,
    command_percentage: form.command_percentage, pitch_types: form.pitch_types, pitch_grades: form.pitch_grades,
    mechanics: Object.fromEntries(pitchingMechanics.map(m => [m.key.replace(/^pit_/, ''), form[m.key]])),
    spin_metrics: form.spin_metrics || null, notes: form.pitching_notes || null,
  }
  return p
}

const onSave = async () => {
  if (!activePlayerId.value) { saveError.value = 'Select a player first.'; return }
  saving.value = true
  saveError.value = ''
  try {
    const res = await axiosPost('assessments', buildPayload())
    const ok = res?.data?.status === 'success' || res?.status === 200 || res?.status === 201
    if (!ok) throw new Error(res?.data?.message || 'Save failed')
    clearDraft()
    emit('saved', { playerId: activePlayerId.value })
  } catch (e) {
    const errs = e?.response?.data?.data?.errors
    const first = errs && typeof errs === 'object' ? Object.values(errs)[0]?.[0] : null
    saveError.value = first || e?.response?.data?.message || e?.message || 'Could not save the assessment.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div v-if="visible" class="fixed inset-0 z-[60]" style="background: rgba(2, 8, 20, 0.9)" @click.self="emit('close')">
        <div class="h-full w-full overflow-y-auto">
          <div class="mx-auto max-w-3xl min-h-full bg-[#0a1020] sm:my-6 sm:rounded-2xl sm:border sm:border-white/15 sm:min-h-0 shadow-2xl">

            <!-- Header -->
            <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-4 border-b border-white/10 bg-[#0a1020]/95 backdrop-blur sm:rounded-t-2xl">
              <button class="text-[#ff5a5f] font-black text-sm" @click="emit('close')">&larr; Back</button>
              <div class="text-center flex-1 px-3 min-w-0">
                <div class="text-white font-black text-lg leading-tight">Assessment</div>
                <div v-if="players.length" class="mt-0.5 inline-flex items-center gap-1">
                  <select
                    :value="activePlayerId"
                    @change="switchPlayer($event.target.value)"
                    class="max-w-[200px] appearance-none bg-transparent text-center text-white/70 text-xs font-bold outline-none cursor-pointer truncate"
                  >
                    <option v-for="p in players" :key="p.id" :value="String(p.id)" class="text-black">{{ p.name }}</option>
                  </select>
                  <span class="text-white/40 text-[10px]">▾</span>
                </div>
                <div v-else class="text-white/45 text-xs">{{ activePlayerName }}</div>
              </div>
              <button class="text-[#ff5a5f] font-bold text-sm" @click="emit('close')">Change</button>
            </div>

            <!-- Step selector -->
            <div class="px-5 pt-4">
              <div class="relative">
                <select :value="currentStepKey" @change="goTo($event.target.value)"
                  class="w-full appearance-none rounded-xl border border-white/15 bg-white/5 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400/60">
                  <option v-for="s in STEPS" :key="s.key" :value="s.key">Assessment · {{ s.label }}</option>
                </select>
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-white/40 text-xs">▼</span>
              </div>
            </div>

            <!-- Step body -->
            <div class="px-5 py-4">
              <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">

                <!-- BASEBALL METRICS -->
                <div v-if="currentStepKey === 'baseball'">
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-[#ff5a5f]"></span>Baseball Metrics</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.athletic) }">{{ dash(fmtrx.athletic) }} Athletic</span>
                  </div>
                  <p class="text-xs text-white/45 mb-3">Capture the field testing numbers that feed the baseline athletic and baseball scores.</p>
                  <div class="space-y-2">
                    <label v-for="row in [
                      { k: 'pitch_velo_mph', t: 'Pitching Velocity', u: 'mph' },
                      { k: 'position_velo', t: 'Position Velocity', u: 'mph' },
                      { k: 'exit_velocity_mph', t: 'Exit Velocity', u: 'mph' },
                      { k: 'yd_60_dash_sec', t: '60 Yard Dash', u: 'sec' },
                      { k: 'sprint_10yd_sec', t: '10 Yard Split', u: 'sec' },
                      { k: 'home_to_first', t: 'Home to First', u: 'sec' },
                      { k: 'catcher_pop_time', t: 'Catcher Pop Time', u: 'sec' },
                    ]" :key="row.k" class="flex items-center justify-between gap-3">
                      <span class="text-sm text-white/80">{{ row.t }}</span>
                      <span class="flex items-center gap-2"><input v-model="form[row.k]" type="number" step="0.01" min="0" placeholder="0" class="am-num" /><span class="text-xs text-white/40 w-8">{{ row.u }}</span></span>
                    </label>
                  </div>
                </div>

                <!-- STRENGTH -->
                <div v-else-if="currentStepKey === 'strength'">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-cyan-400"></span>🏋️ Strength Testing</h3>
                  </div>
                  <div class="grid grid-cols-4 gap-2 mb-4">
                    <div v-for="t in [{l:'Strength',v:fmtrx.strength},{l:'Power',v:fmtrx.power},{l:'Speed',v:fmtrx.speed},{l:'Overall',v:fmtrx.strengthOverall}]" :key="t.l"
                      class="rounded-xl border border-white/10 bg-white/5 p-2 text-center" :class="t.l==='Overall' ? 'border-[#ff5a5f]/40 bg-[#ff5a5f]/5' : ''">
                      <div class="text-lg font-black" :style="{ color: scoreColor(t.v) }">{{ dash(t.v) }}</div>
                      <div class="text-[10px] uppercase tracking-wide text-white/45">{{ t.l }}</div>
                    </div>
                  </div>
                  <div class="space-y-3">
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-white/55 mb-1.5">🧱 Strength · <span :style="{ color: scoreColor(fmtrx.strength) }">{{ dash(fmtrx.strength) }} / 100</span></div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input v-model="form.body_weight_lbs" type="number" placeholder="Weight (lbs)" class="am-input" />
                        <div v-for="lift in [
                          { field: 'front_squat_lbs', key: 'front_squat', label: 'Front Squat' },
                          { field: 'back_squat_lbs', key: 'back_squat', label: 'Back Squat' },
                          { field: 'bench_press_lbs', key: 'bench_press', label: 'Bench Press' },
                          { field: 'dead_lift_lbs', key: 'deadlift', label: 'Deadlift' },
                          { field: 'trap_bar_deadlift', key: 'trap_bar_deadlift', label: 'Trap-bar Deadlift' },
                        ]" :key="lift.key" class="grid grid-cols-[1fr_72px] gap-1">
                          <input v-model="form[lift.field]" type="number" :placeholder="`${lift.label} (lbs)`" class="am-input" />
                          <input v-model="form.lift_repetitions[lift.key]" type="number" min="1" max="10" :aria-label="`${lift.label} repetitions`" placeholder="Reps" class="am-input" />
                        </div>
                        <input v-model="form.pull_ups" type="number" placeholder="Pull-Ups (reps)" class="am-input" />
                        <input v-model="form.push_ups" type="number" placeholder="Push-Ups (reps)" class="am-input" />
                        <input v-model="form.push_up_protocol" type="text" placeholder="Push-Up Protocol" class="am-input" />
                      </div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-white/55 mb-1.5">⚡ Power · <span :style="{ color: scoreColor(fmtrx.power) }">{{ dash(fmtrx.power) }} / 100</span></div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div class="grid grid-cols-[1fr_72px] gap-1"><input v-model="form.power_clean_lbs" type="number" placeholder="Power Clean (lbs)" class="am-input" /><input v-model="form.lift_repetitions.power_clean" type="number" min="1" max="10" aria-label="Power Clean repetitions" placeholder="Reps" class="am-input" /></div>
                        <input v-model="form.grip_strength_left" type="number" placeholder="Grip Strength Left (lbs)" class="am-input" />
                        <input v-model="form.grip_strength_right" type="number" placeholder="Grip Strength Right (lbs)" class="am-input" />
                        <input v-model="form.vertical_jump_inches" type="number" placeholder="Vertical Jump (in)" class="am-input" />
                        <input v-model="form.broad_jump_inches" type="number" placeholder="Broad Jump (in)" class="am-input" />
                        <input v-model="form.med_ball_rotational_throw_ft" type="number" placeholder="Med Ball Rotational (ft)" class="am-input" />
                        <input v-model="form.plank_hold" type="number" placeholder="Plank Hold (sec)" class="am-input" />
                        <input v-model="form.plank_protocol" type="text" placeholder="Plank Protocol" class="am-input" />
                        <input v-model="form.med_ball_weight_lbs" type="number" placeholder="Med Ball Weight (lbs)" class="am-input" />
                        <input v-model="form.med_ball_protocol" type="text" placeholder="Med Ball Protocol" class="am-input" />
                        <input v-model="form.grip_device" type="text" placeholder="Grip Device" class="am-input" />
                        <input v-model="form.grip_protocol" type="text" placeholder="Grip Protocol" class="am-input" />
                      </div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-white/55 mb-1.5">🏃 Speed · <span :style="{ color: scoreColor(fmtrx.speed) }">{{ dash(fmtrx.speed) }} / 100</span></div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input v-model="form.sprint_10yd_sec" type="number" step="0.01" placeholder="10 Yard (sec)" class="am-input" />
                        <input v-model="form.yd_40_dash_sec" type="number" step="0.01" placeholder="40 Time (sec)" class="am-input" />
                        <input v-model="form.yd_60_dash_sec" type="number" step="0.01" placeholder="60 Time (sec)" class="am-input" />
                        <input v-model="form.sleep_hours" type="number" step="0.1" placeholder="Sleep Hours" class="am-input" />
                        <input v-model="form.sleep_quality_1_to_5" type="number" placeholder="Sleep Quality (1-5)" class="am-input" />
                        <input v-model="form.recovery_score" type="number" placeholder="Recovery (0-100)" class="am-input" />
                      </div>
                    </div>
                    <div>
                      <div class="text-xs font-black uppercase tracking-widest text-white/55 mb-1.5">Notes</div>
                      <textarea v-model="form.strength_notes" rows="2" placeholder="Key observations, areas to target..." class="am-input w-full resize-none"></textarea>
                    </div>
                  </div>
                </div>

                <!-- MOBILITY -->
                <div v-else-if="currentStepKey === 'mobility'">
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="text-white font-black">Mobility Testing</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.mobility) }">{{ dash(fmtrx.mobility) }}</span>
                  </div>
                  <p class="text-xs text-white/45 mb-3">Rate each area 1–5: 1 = restricted, 5 = excellent movement quality.</p>
                  <div class="space-y-2">
                    <div v-for="m in [
                      { k:'shoulder_mobility', t:'Shoulder Mobility', d:'Overhead reach and shoulder control' },
                      { k:'hip_mobility', t:'Hip Mobility', d:'Hip flexion/extension range' },
                      { k:'ankle_mobility', t:'Ankle Mobility', d:'Knee-over-toe drive ability' },
                      { k:'hamstring_mobility', t:'Hamstring Mobility', d:'Posterior chain flexibility' },
                      { k:'t_spine_rotation', t:'T-Spine Rotation', d:'Thoracic rotation quality' },
                      { k:'overhead_squat', t:'Overhead Squat', d:'Full-chain mobility under load' },
                      { k:'single_leg_balance', t:'Single-Leg Balance', d:'Stability and control' },
                    ]" :key="m.k" class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 p-3">
                      <div>
                        <div class="text-sm font-bold text-white">{{ m.t }}</div>
                        <div class="text-xs text-white/40">{{ m.d }}</div>
                      </div>
                      <div class="flex items-center gap-2">
                        <input v-model="form[m.k]" type="number" min="0" max="5" step="1" placeholder="0" class="am-num" />
                        <span class="text-xs text-white/40">/5</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- THROWING / ARM HEALTH -->
                <div v-else-if="currentStepKey === 'arm'">
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-green-400"></span>Throwing / Arm Health</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.armHealth) }">{{ dash(fmtrx.armHealth) }} / 100</span>
                  </div>
                  <div class="rounded-xl border p-3 mb-3 flex items-center justify-between" :style="{ borderColor: (fmtrx.workloadColor||'#334155')+'66', background:(fmtrx.workloadColor||'#334155')+'1a' }">
                    <div><div class="text-[11px] uppercase tracking-widest text-white/55">Throwing Workload</div><div class="font-black" :style="{ color: fmtrx.workloadColor }">{{ fmtrx.workloadLabel }}</div></div>
                    <div class="text-3xl font-black" :style="{ color: fmtrx.workloadColor }">{{ fmtrx.workload || 0 }}</div>
                  </div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Primary Throwing Role</div>
                  <div class="flex flex-wrap gap-2 mb-3">
                    <button v-for="r in roleOptions" :key="r" type="button" class="am-pill" :class="form.primary_throwing_role===r ? 'am-pill-on' : ''" @click="form.primary_throwing_role=r">{{ r }}</button>
                  </div>

                  <div class="rounded-xl border border-white/10 bg-white/5 p-3 mb-3">
                    <div class="flex items-center justify-between mb-2"><span class="text-sm font-black text-white">Weekly Activity</span><span class="text-xs text-white/40">0–7</span></div>
                    <div v-for="row in weeklyRows" :key="row.key" class="flex items-center gap-1 mb-1.5">
                      <span class="w-20 text-xs text-white/60">{{ row.label }}</span>
                      <button v-for="n in [0,1,2,3,4,5,6,7]" :key="n" type="button" class="am-numpill" :class="String(form[row.key])===String(n) ? 'am-numpill-on' : ''" @click="form[row.key]=n">{{ n }}</button>
                    </div>
                  </div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Average Throws Per Day</div>
                  <div class="flex flex-wrap gap-2 mb-3"><button v-for="o in throwsPerDayOptions" :key="o" type="button" class="am-pill" :class="form.throws_per_day_range===o ? 'am-pill-on':''" @click="form.throws_per_day_range=o">{{ o }}</button></div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Weekly Game Pitch Count</div>
                  <div class="flex flex-wrap gap-2 mb-3"><button v-for="o in pitchCountOptions" :key="o" type="button" class="am-pill" :class="form.weekly_pitch_count_range===o ? 'am-pill-on':''" @click="form.weekly_pitch_count_range=o">{{ o }}</button></div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Weighted Ball Usage</div>
                  <div class="flex flex-wrap gap-2 mb-3"><button v-for="o in weightedBallOptions" :key="o" type="button" class="am-pill" :class="form.weighted_ball_usage===o ? 'am-pill-on':''" @click="form.weighted_ball_usage=o">{{ o }}</button></div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Current Throwing Intensity</div>
                  <div class="flex flex-wrap gap-2 mb-3"><button v-for="o in intensityOptions" :key="o" type="button" class="am-pill" :class="form.throwing_intensity===o ? 'am-pill-on':''" @click="form.throwing_intensity=o">{{ o }}</button></div>

                  <div class="space-y-2">
                    <label class="flex items-center justify-between"><span class="text-sm text-white/80">Arm Fatigue Before Practice</span><span class="flex items-center gap-2"><input v-model="form.arm_fatigue" type="number" min="0" max="5" class="am-num" /><span class="text-xs text-white/40">/5</span></span></label>
                    <label class="flex items-center justify-between"><span class="text-sm text-white/80">Arm Soreness Past 7 Days</span><span class="flex items-center gap-2"><input v-model="form.arm_soreness" type="number" min="0" max="10" class="am-num" /><span class="text-xs text-white/40">/10</span></span></label>
                    <label class="flex items-center justify-between"><span class="text-sm text-white/80">Arm Pain</span>
                      <select v-model="form.arm_pain" class="am-input w-28"><option value="">—</option><option value="No">No</option><option value="Yes">Yes</option></select>
                    </label>
                    <textarea v-if="form.arm_pain === 'Yes'" v-model="form.arm_pain_notes" rows="2" placeholder="Describe location, severity, when it started, throwing triggers, and any action plan..." class="am-input w-full resize-none"></textarea>
                  </div>
                </div>

                <!-- HITTING -->
                <div v-else-if="currentStepKey === 'hitting'">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-[#ff7a18]"></span>Hitting Assessment</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.hitting) }">{{ dash(fmtrx.hitting) }} / 100</span>
                  </div>
                  <div class="space-y-2 mb-4">
                    <label v-for="row in [
                      { k:'max_exit_velo', t:'Max Exit Velocity', u:'mph' },
                      { k:'avg_exit_velo', t:'Average Exit Velocity', u:'mph' },
                    ]" :key="row.k" class="flex items-center justify-between gap-3">
                      <span class="text-sm text-white/80">{{ row.t }}</span>
                      <span class="flex items-center gap-2"><input v-model="form[row.k]" type="number" step="0.1" min="0" placeholder="0" class="am-num" /><span class="text-xs text-white/40 w-6">{{ row.u }}</span></span>
                    </label>
                  </div>

                  <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-[11px] uppercase tracking-widest text-white/45 mb-2">Hitting Mechanics</div>
                    <div class="space-y-3">
                      <div v-for="m in hittingMechanics" :key="m.key">
                        <div class="text-sm font-bold text-white mb-1">{{ m.label }}</div>
                        <div class="grid grid-cols-3 gap-2">
                          <button v-for="opt in m.opts" :key="opt.v" type="button"
                            class="am-mech" :class="String(form[m.key]) === String(opt.v) ? 'am-mech-on' : ''"
                            @click="form[m.key] = opt.v">
                            <div class="text-base font-black">{{ opt.v }}</div>
                            <div class="text-[10px] text-white/50 leading-tight">{{ opt.l }}</div>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="text-[11px] uppercase tracking-widest text-white/45 mt-3 mb-1">Notes</div>
                  <textarea v-model="form.hitting_notes" rows="2" placeholder="Swing notes, approach, timing, barrel control, training priorities..." class="am-input w-full resize-none"></textarea>
                </div>

                <!-- PITCHING -->
                <div v-else-if="currentStepKey === 'pitching'">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-blue-400"></span>Pitching Assessment</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.pitching) }">{{ dash(fmtrx.pitching) }} / 100</span>
                  </div>
                  <div class="space-y-2 mb-4">
                    <label class="flex items-center justify-between gap-3"><span class="text-sm text-white/80">Fastball Velocity</span><span class="flex items-center gap-2"><input v-model="form.fastball_velocity" type="number" step="0.1" class="am-num" /><span class="text-xs text-white/40 w-6">mph</span></span></label>
                    <label class="flex items-center justify-between gap-3"><span class="text-sm text-white/80">Strike Percentage</span><span class="flex items-center gap-2"><input v-model="form.strike_percentage" type="number" class="am-num" /><span class="text-xs text-white/40 w-6">%</span></span></label>
                    <label class="flex items-center justify-between gap-3"><span class="text-sm text-white/80">Command Percentage</span><span class="flex items-center gap-2"><input v-model="form.command_percentage" type="number" class="am-num" /><span class="text-xs text-white/40 w-6">%</span></span></label>
                  </div>

                  <div class="text-[11px] uppercase tracking-widest text-white/45 mb-1.5">Pitch Types</div>
                  <div class="flex flex-wrap gap-2 mb-4">
                    <button v-for="pt in pitchTypeOptions" :key="pt.value" type="button" class="am-pill" :class="form.pitch_types.includes(pt.value) ? 'am-pill-on' : ''" @click="togglePitchType(pt.value)">{{ pt.label }}</button>
                  </div>

                  <div v-if="form.pitch_types.length" class="rounded-xl border border-white/10 bg-white/5 p-3 mb-4">
                    <div class="text-[11px] uppercase tracking-widest text-white/45 mb-2">Pitch Grades · MLB 20–80 Scale</div>
                    <div class="space-y-2">
                      <div v-for="pt in form.pitch_types" :key="pt">
                        <div class="text-sm font-bold text-white mb-1">{{ pt }}</div>
                        <div class="flex flex-wrap gap-1 pb-1">
                          <button v-for="g in mlbPitchGrades" :key="g.grade" type="button"
                            class="am-grade" :class="Number(form.pitch_grades[pt]) === g.grade ? 'am-mech-on' : ''"
                            @click="setPitchGrade(pt, g.grade)">
                            <div class="text-sm font-black">{{ g.grade }}</div>
                            <div class="text-[9px] text-white/50 leading-tight">{{ g.label }}</div>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-[11px] uppercase tracking-widest text-white/45 mb-2">Mechanics</div>
                    <div class="space-y-3">
                      <div v-for="m in pitchingMechanics" :key="m.key">
                        <div class="text-sm font-bold text-white mb-1">{{ m.label }}</div>
                        <div class="grid grid-cols-3 gap-2">
                          <button v-for="opt in m.opts" :key="opt.v" type="button"
                            class="am-mech" :class="String(form[m.key]) === String(opt.v) ? 'am-mech-on' : ''"
                            @click="form[m.key] = opt.v">
                            <div class="text-base font-black">{{ opt.v }}</div>
                            <div class="text-[10px] text-white/50 leading-tight">{{ opt.l }}</div>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="text-[11px] uppercase tracking-widest text-white/45 mt-3 mb-1">Spin / Metrics</div>
                  <input v-model="form.spin_metrics" type="text" placeholder="Spin rates, movement, pitch metrics..." class="am-input w-full" />
                  <div class="text-[11px] uppercase tracking-widest text-white/45 mt-3 mb-1">Notes</div>
                  <textarea v-model="form.pitching_notes" rows="2" placeholder="Delivery notes, pitch usage, development priorities, next bullpen focus..." class="am-input w-full resize-none"></textarea>
                </div>

                <!-- FMTRX SCORING -->
                <div v-else-if="currentStepKey === 'fmtrx'">
                  <div class="flex items-center justify-between mb-3">
                    <h3 class="flex items-center gap-2 text-white font-black"><span class="inline-block h-4 w-1 rounded bg-yellow-400"></span>FMTRX Baseline Score</h3>
                    <span class="text-sm font-black" :style="{ color: scoreColor(fmtrx.overall) }">{{ dash(fmtrx.overall) }} Overall</span>
                  </div>
                  <div class="grid grid-cols-3 gap-2 mb-4">
                    <div v-for="t in fmtrxTiles" :key="t.label" class="rounded-xl border border-white/10 bg-white/5 p-3" :class="t.label==='Overall' ? 'border-[#ff5a5f]/40' : ''">
                      <div class="text-[10px] uppercase tracking-wide text-white/45">{{ t.label }}</div>
                      <div class="text-2xl font-black" :style="{ color: scoreColor(t.value) }">{{ dash(t.value) }}</div>
                    </div>
                  </div>
                  <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                    <div class="text-sm font-black text-white mb-2">Report Notes</div>
                    <textarea v-model="form.notes" rows="4" placeholder="Strengths, weaknesses, recommended plan, reassessment target..." class="am-input w-full resize-none"></textarea>
                  </div>
                </div>

              </div>
            </div>

            <!-- Footer nav -->
            <div class="sticky bottom-0 px-5 py-4 border-t border-white/10 bg-[#0a1020]/95 backdrop-blur sm:rounded-b-2xl">
              <div v-if="draftMsg" class="mb-2 text-xs font-bold text-green-300">{{ draftMsg }}</div>
              <div v-if="saveError" class="mb-2 text-xs font-bold text-red-300">{{ saveError }}</div>
              <div class="flex items-center gap-3">
                <button type="button" class="px-4 py-2.5 rounded-lg bg-white/5 border border-white/20 text-sm font-bold text-white/80" @click="saveDraft">
                  Save Data
                </button>
                <span v-if="hasSavedDraft" class="text-[11px] text-white/40">Draft in progress</span>
                <div class="flex-1"></div>
                <button v-if="stepIndex > 0" type="button" class="px-4 py-2.5 rounded-lg bg-white/5 border border-white/15 text-sm font-bold text-white/70" @click="back">
                  Back: {{ STEPS[stepIndex - 1].label }}
                </button>
                <button v-if="stepIndex < STEPS.length - 1" type="button" class="px-5 py-2.5 rounded-lg bg-[#C00000] hover:bg-red-700 text-sm font-black text-white" @click="next">
                  Next: {{ STEPS[stepIndex + 1].label }}
                </button>
                <button v-else type="button" :disabled="saving" class="px-5 py-2.5 rounded-lg bg-[#C00000] hover:bg-red-700 disabled:opacity-60 text-sm font-black text-white" @click="onSave">
                  {{ saving ? 'Saving…' : 'Save Baseline' }}
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.am-input {
  border-radius: 0.5rem;
  border: 1px solid rgba(255,255,255,0.15);
  background: rgba(255,255,255,0.05);
  padding: 0.5rem 0.75rem;
  font-size: 0.85rem;
  color: #fff;
  outline: none;
}
.am-input:focus { border-color: rgba(248,113,113,0.6); }
.am-num {
  width: 4.5rem;
  text-align: center;
  border-radius: 0.5rem;
  border: 1px solid rgba(255,255,255,0.15);
  background: rgba(255,255,255,0.05);
  padding: 0.4rem 0.5rem;
  font-size: 0.85rem;
  color: #fff;
  outline: none;
}
.am-num:focus { border-color: rgba(248,113,113,0.6); }
.am-pill {
  border-radius: 0.5rem;
  border: 1px solid rgba(255,255,255,0.15);
  background: rgba(255,255,255,0.04);
  padding: 0.4rem 0.7rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: rgba(255,255,255,0.6);
}
.am-pill-on { border-color: rgba(192,0,0,0.6); background: rgba(192,0,0,0.18); color: #fff; }
.am-numpill {
  flex: 1;
  border-radius: 0.4rem;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.03);
  padding: 0.35rem 0;
  font-size: 0.75rem;
  font-weight: 700;
  color: rgba(255,255,255,0.6);
}
.am-numpill-on { border-color: rgba(192,0,0,0.6); background: rgba(192,0,0,0.18); color: #fff; }
.am-mech {
  border-radius: 0.6rem;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.03);
  padding: 0.6rem 0.4rem;
  text-align: center;
  color: rgba(255,255,255,0.85);
}
.am-mech-on { border-color: rgba(192,0,0,0.7); background: rgba(192,0,0,0.18); color: #fff; }
.am-grade {
  width: 4.5rem;
  border-radius: 0.5rem;
  border: 1px solid rgba(255,255,255,0.12);
  background: rgba(255,255,255,0.03);
  padding: 0.45rem 0.3rem;
  text-align: center;
  color: rgba(255,255,255,0.85);
}
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.2s ease; }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
</style>
