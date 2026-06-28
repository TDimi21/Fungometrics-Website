<script setup>
/**
 * AssessmentReportCard.vue
 * The full FMTRX "Player Assessment Report" card, driven by a single saved
 * assessment record. Extracted from the /assessment-reports page so the same
 * rich report can be reused inline (e.g. under the dashboard Assessment tab).
 */
import { computed, ref, watch, nextTick } from 'vue'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'
import { formatDOB } from '@/utils/dob.js'
import { buildPlayerInsights } from '@/features/development/lib/assessmentInsights.js'

const props = defineProps({
  report: { type: Object, default: null },
  teamName: { type: String, default: '' },
  showActions: { type: Boolean, default: true },
  // Full assessment history for this player (newest first) — drives reassessment
  // growth: total change since the first baseline and change since the previous one.
  history: { type: Array, default: () => [] },
})

const parseData = (value) => {
  if (!value) return {}
  if (typeof value === 'object') return value
  try {
    const parsed = JSON.parse(value)
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

const num = (value) => {
  const n = Number(value)
  return Number.isFinite(n) ? n : null
}

const display = (value, fallback = '—') => {
  if (value === 0 || value === '0') return '0'
  return value ? value : fallback
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

const scoreColor = (s) => {
  const value = num(s)
  if (value === null) return '#64748B'
  if (value >= 80) return '#65D84E'
  if (value >= 70) return '#FACC15'
  if (value >= 60) return '#F97316'
  return '#EF4444'
}

const scoreLabel = (s) => {
  const value = num(s)
  if (value === null) return 'Not Scored'
  if (value >= 90) return 'Elite'
  if (value >= 80) return 'Advanced'
  if (value >= 70) return 'Good'
  if (value >= 60) return 'Developing'
  return 'Needs Development'
}

const sectionStatus = (s) => {
  const value = num(s)
  if (value === null) return 'Not Tested'
  if (value >= 80) return 'Excellent'
  if (value >= 70) return 'Good'
  if (value >= 60) return 'Fair'
  return 'Needs Work'
}

const mechanicLabel = (score) => {
  if (Number(score) >= 5) return 'Excellent'
  if (Number(score) >= 3) return 'Average'
  if (Number(score) > 0) return 'Needs Work'
  return '—'
}

const gradeLabel = (grade) => ({
  20: 'Well Below Avg',
  30: 'Below Avg',
  40: 'Fringe',
  45: 'Fringe Avg',
  50: 'MLB Avg',
  55: 'Above Avg',
  60: 'Plus',
  70: 'Plus-Plus',
  80: 'Elite',
}[Number(grade)] ?? '—')

const playerName = (report) => {
  const p = report?.profile ?? {}
  return [p.first_name, p.last_name].filter(Boolean).join(' ') || 'Player'
}

const playerPhoto = (report) => report?.profile?.picture || report?.picture || updatedLogo

const initials = (report) => playerName(report).split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase()

const hittingRows = [
  ['setup', 'Setup'],
  ['load', 'Load'],
  ['lower_half', 'Lower Half'],
  ['rotation', 'Rotation'],
  ['barrel_path', 'Barrel Path'],
  ['contact', 'Contact'],
  ['attack_angle', 'Attack Angle'],
  ['balance', 'Balance'],
  ['approach', 'Approach'],
]

const pitchingRows = [
  ['posture', 'Posture'],
  ['tempo', 'Tempo'],
  ['lower_half', 'Lower Half'],
  ['front_leg', 'Front Leg'],
  ['hip_rotation', 'Hip Rotation'],
  ['core_stability', 'Core Stability'],
  ['arm_action', 'Arm Action'],
  ['finish', 'Finish'],
]

const pitchOrder = ['Fastball', 'Two-Seam', 'Cutter', 'Slider', 'Curveball', 'Changeup', 'Splitter', 'Sinker', 'Knuckleball']

const selectedHittingData = computed(() => parseData(props.report?.hitting_data))
const selectedPitchingData = computed(() => parseData(props.report?.pitching_data))
const selectedThrowingData = computed(() => parseData(props.report?.throwing_workload_data))

const summaryFor = (r) => {
  const throwing = r?.arm_health_score != null || r?.throwing_workload_score != null
    ? Math.round(((num(r?.arm_health_score) ?? 0) + (100 - (num(r?.throwing_workload_score) ?? 0))) / (r?.arm_health_score != null && r?.throwing_workload_score != null ? 2 : 1))
    : null
  return [
    { label: 'Athletic', value: r?.strength_explosive_score ?? r?.strength_overall_score },
    { label: 'Throwing', value: throwing },
    { label: 'Hitting', value: r?.hitting_score },
    { label: 'Mobility', value: r?.mobility_overall_score },
    { label: 'Arm Health', value: r?.arm_health_score },
    { label: 'Strength', value: r?.strength_overall_score },
  ]
}
const summaryScores = computed(() => summaryFor(props.report))

// ── Reassessment growth ──────────────────────────────────────────────────────
// history is newest-first. The selected report's "previous" is the next-older
// entry; the "first" is the oldest. Deltas are only shown once a player has a
// prior assessment (i.e. this is a 2nd/3rd/… assessment).
const currentIndex = computed(() => {
  const list = props.history || []
  const i = list.findIndex(r => String(r?.id) === String(props.report?.id))
  return i < 0 ? 0 : i
})
const prevReport = computed(() => (props.history || [])[currentIndex.value + 1] || null)
const firstReport = computed(() => {
  const list = props.history || []
  return list.length ? list[list.length - 1] : null
})
const hasPrior = computed(() => !!prevReport.value)
const assessmentNumber = computed(() => {
  const total = (props.history || []).length
  return total ? total - currentIndex.value : 1
})
const metricList = (r) => {
  if (!r) return []
  return [{ label: 'Overall', value: num(r.overall_score) }, ...summaryFor(r).map(x => ({ label: x.label, value: num(x.value) }))]
}
const progressRows = computed(() => {
  const cur = metricList(props.report)
  const prev = metricList(prevReport.value)
  const first = metricList(firstReport.value)
  const find = (arr, label) => { const m = arr.find(x => x.label === label); return m ? m.value : null }
  return cur.map(c => {
    const f = find(first, c.label)
    const p = find(prev, c.label)
    return {
      label: c.label,
      now: c.value,
      vsFirst: c.value != null && f != null ? c.value - f : null,
      vsPrev: c.value != null && p != null ? c.value - p : null,
    }
  })
})
const deltaColor = (d) => (d == null ? '#64748B' : d > 0 ? '#65D84E' : d < 0 ? '#EF4444' : 'rgba(255,255,255,.5)')
const deltaText = (d) => (d == null ? '—' : d > 0 ? `+${d}` : `${d}`)

// ── AI development insights (deterministic engine) ───────────────────────────
const generated = computed(() => buildPlayerInsights(props.report || {}))

const cloneEditable = (g) => ({
  summary: g.summary,
  typeTitle: g.type.type,
  typeBody: g.type.body,
  strengths: [...g.strengths],
  limiters: [...g.limiters],
  focus: JSON.parse(JSON.stringify(g.focus || {})),
  plan: {
    goal: g.plan.goal,
    priorities: [...g.plan.priorities],
    measure: g.plan.measure,
    retestDate: g.plan.retestDate,
  },
  armAdvisory: g.armAdvisory,
})

const editing = ref(false)
const edited = ref(cloneEditable(generated.value))
const insightKey = () => `fmtrx_insights_${props.report?.id || 'unknown'}`

const loadInsights = () => {
  const base = cloneEditable(generated.value)
  try {
    const raw = localStorage.getItem(insightKey())
    if (raw) Object.assign(base, JSON.parse(raw))
  } catch (_) { /* ignore corrupt override */ }
  edited.value = base
  editing.value = false
}
watch(() => props.report?.id, loadInsights, { immediate: true })

const insights = computed(() => edited.value)
const isEdited = computed(() => {
  try { return !!localStorage.getItem(insightKey()) } catch (_) { return false }
})

const saveInsights = () => {
  try { localStorage.setItem(insightKey(), JSON.stringify(edited.value)) } catch (_) { /* noop */ }
  editing.value = false
}
const resetInsights = () => {
  try { localStorage.removeItem(insightKey()) } catch (_) { /* noop */ }
  edited.value = cloneEditable(generated.value)
  editing.value = false
}
// Edit list fields (strengths/limiters/priorities) as newline-separated text.
const linesGet = (arr) => (Array.isArray(arr) ? arr.join('\n') : '')
const linesSet = (obj, key, val) => { obj[key] = String(val).split('\n').map((s) => s.trim()).filter(Boolean) }

const focusTiers = computed(() => ['primary', 'secondary', 'tertiary']
  .map((tier) => ({ tier, ...(insights.value.focus?.[tier] || {}) }))
  .filter((f) => f.title))

const mobilityRows = computed(() => {
  const r = props.report ?? {}
  return [
    ['Shoulder Mobility', r.shoulder_mobility],
    ['Hip Mobility', r.hip_mobility],
    ['Ankle Mobility', r.ankle_mobility],
    ['Hip Flexor Mobility', r.hip_flexor_mobility],
    ['Rotational Mobility', r.rotational_mobility],
  ]
})

const printReport = () => {
  // Always export the finished text, not the edit form. Wait a tick so Vue
  // re-renders the text (instead of the textareas) before we clone.
  editing.value = false
  nextTick(() => {
    // Clone the report to a body-level portal so it prints in normal flow
    // (proper pagination, no clipping) with the rest of the dashboard hidden.
    const el = document.getElementById('assessment-print')
    if (!el) { window.print(); return }
    const portal = document.createElement('div')
    portal.id = 'assessment-print-portal'
    portal.appendChild(el.cloneNode(true))
    document.body.appendChild(portal)
    document.body.classList.add('assessment-printing')
    const cleanup = () => {
      portal.remove()
      document.body.classList.remove('assessment-printing')
      window.removeEventListener('afterprint', cleanup)
    }
    window.addEventListener('afterprint', cleanup)
    window.print()
    // Fallback cleanup in case afterprint doesn't fire.
    setTimeout(cleanup, 1500)
  })
}
</script>

<template>
  <main v-if="report" class="report" id="assessment-print">
    <header class="report-top">
      <div class="brand">
        <span>FMTRX</span>
        <strong>Player Assessment Report</strong>
      </div>
      <div v-if="showActions" class="report-actions">
        <div>
          <small>Assessment Date</small>
          <b>{{ formatDate(report.assessment_date) }}</b>
        </div>
        <button class="no-print" @click="printReport">PDF Export</button>
      </div>
    </header>

    <section class="hero-grid">
      <article class="player-card panel">
        <div class="photo-wrap">
          <img v-if="playerPhoto(report)" :src="playerPhoto(report)" :alt="playerName(report)" />
          <span v-else>{{ initials(report) }}</span>
        </div>
        <div class="player-main">
          <h2>{{ playerName(report) }}</h2>
          <p>#{{ display(report.profile?.number_in_shirt || report.profile?.jersey, '—') }} · {{ display(report.profile?.primary_position || report.profile?.position, 'Player') }}</p>
          <div class="bio-grid">
            <div><span>DOB</span><b>{{ formatDOB(report) }}</b></div>
            <div><span>Height</span><b>{{ display(report.profile?.height || report.height) }}</b></div>
            <div><span>Weight</span><b>{{ display(report.body_weight_lbs) }}</b></div>
            <div><span>Team</span><b>{{ display(teamName) }}</b></div>
            <div><span>Mobile</span><b>{{ display(report.profile?.mobile_number || report.profile?.phone) }}</b></div>
            <div><span>Email</span><b>{{ display(report.profile?.email || report.profile?.parent_email) }}</b></div>
          </div>
        </div>
      </article>

      <article class="score-card panel">
        <h3>Overall Development Score</h3>
        <div class="score-ring" :style="{ '--score': report.overall_score ?? 0, '--score-color': scoreColor(report.overall_score) }">
          <div>
            <strong>{{ report.overall_score ?? '—' }}</strong>
            <span>/100</span>
          </div>
        </div>
        <b :style="{ color: scoreColor(report.overall_score) }">{{ scoreLabel(report.overall_score) }}</b>
      </article>

      <article class="type-card panel">
        <h3>Player Type</h3>
        <h2>{{ insights.typeTitle }}</h2>
        <p>{{ insights.typeBody }}</p>
        <div class="workload-line">
          <span>Throwing Workload</span>
          <b :style="{ color: scoreColor(100 - (report.throwing_workload_score ?? 0)) }">{{ report.throwing_workload_level ?? '—' }}</b>
        </div>
      </article>
    </section>

    <section class="panel">
      <div class="section-title">Development Summary</div>
      <div class="summary-grid">
        <div v-for="item in summaryScores" :key="item.label" class="summary-tile">
          <span>{{ item.label }}</span>
          <strong :style="{ color: scoreColor(item.value) }">{{ item.value ?? '—' }}</strong>
          <small>{{ sectionStatus(item.value) }}</small>
        </div>
      </div>
    </section>

    <!-- Reassessment growth: only on a 2nd/3rd/… assessment. -->
    <section v-if="hasPrior" class="panel">
      <div class="progress-head">
        <div class="section-title">Development Progress</div>
        <span class="reassess-tag">Reassessment · Assessment #{{ assessmentNumber }} of {{ history.length }}</span>
      </div>
      <div class="progress-table">
        <div class="progress-row progress-row--head">
          <span>Metric</span>
          <b>Now</b>
          <b>Since First</b>
          <b>Since Last</b>
        </div>
        <div v-for="row in progressRows" :key="row.label" class="progress-row">
          <span>{{ row.label }}</span>
          <b :style="{ color: scoreColor(row.now) }">{{ row.now ?? '—' }}</b>
          <b :style="{ color: deltaColor(row.vsFirst) }">{{ deltaText(row.vsFirst) }}</b>
          <b :style="{ color: deltaColor(row.vsPrev) }">{{ deltaText(row.vsPrev) }}</b>
        </div>
      </div>
      <p class="note">Since First compares to the original baseline ({{ formatDate(firstReport?.assessment_date) }}). Since Last compares to the previous assessment ({{ formatDate(prevReport?.assessment_date) }}).</p>
    </section>

    <!-- ── AI Development Insights ─────────────────────────────────────────── -->
    <section class="panel ai-insights">
      <div class="ai-head">
        <div class="section-title">AI Development Insights</div>
        <div class="ai-actions no-print">
          <button v-if="!editing" type="button" class="ai-btn" @click="editing = true">Edit</button>
          <template v-else>
            <button type="button" class="ai-btn ai-btn--save" @click="saveInsights">Save</button>
            <button type="button" class="ai-btn" @click="loadInsights">Cancel</button>
          </template>
          <button v-if="isEdited && !editing" type="button" class="ai-btn" @click="resetInsights">Reset to AI</button>
        </div>
      </div>

      <div v-if="insights.armAdvisory || editing" class="ai-advisory">
        <textarea v-if="editing" class="ai-input" rows="2" v-model="insights.armAdvisory" placeholder="Arm-care advisory (leave blank if none)"></textarea>
        <span v-else>⚠ {{ insights.armAdvisory }}</span>
      </div>

      <div class="ai-block">
        <div class="ai-label">Player Summary</div>
        <textarea v-if="editing" class="ai-input" rows="3" v-model="insights.summary"></textarea>
        <p v-else class="ai-text">{{ insights.summary }}</p>
      </div>
    </section>

    <section class="three-grid">
      <article class="panel">
        <div class="section-title">Throwing / Arm Health</div>
        <div class="metric-list">
          <div><span>Role</span><b>{{ display(selectedThrowingData.primary_throwing_role) }}</b></div>
          <div><span>Days / Week</span><b>{{ display(selectedThrowingData.throwing_days_per_week) }}</b></div>
          <div><span>Throws / Day</span><b>{{ display(selectedThrowingData.throws_per_day_range) }}</b></div>
          <div><span>Intensity</span><b>{{ display(selectedThrowingData.throwing_intensity) }}</b></div>
          <div><span>Soreness</span><b>{{ display(selectedThrowingData.arm_soreness) }}</b></div>
          <div><span>Arm Pain</span><b>{{ display(selectedThrowingData.arm_pain) }}</b></div>
        </div>
        <p v-if="selectedThrowingData.arm_pain_notes" class="note">{{ selectedThrowingData.arm_pain_notes }}</p>
      </article>

      <article class="panel">
        <div class="section-title">Pitching Assessment</div>
        <div class="metric-list">
          <div><span>Fastball Velocity</span><b>{{ display(selectedPitchingData.fastball_velocity) }} mph</b></div>
          <div><span>Strike %</span><b>{{ display(selectedPitchingData.strike_percentage) }}%</b></div>
          <div><span>Command %</span><b>{{ display(selectedPitchingData.command_percentage) }}%</b></div>
        </div>
        <div class="grade-table">
          <div v-for="pitch in pitchOrder" :key="pitch">
            <span>{{ pitch }}</span>
            <b>{{ selectedPitchingData.pitch_grades?.[pitch] ?? '—' }}</b>
            <small>{{ gradeLabel(selectedPitchingData.pitch_grades?.[pitch]) }}</small>
          </div>
        </div>
        <p v-if="selectedPitchingData.notes || selectedPitchingData.spin_metrics" class="note">
          {{ selectedPitchingData.notes || selectedPitchingData.spin_metrics }}
        </p>
      </article>

      <article class="panel">
        <div class="section-title">Hitting Assessment</div>
        <div class="metric-list">
          <div><span>Max Exit Velocity</span><b>{{ display(selectedHittingData.max_exit_velo ?? selectedHittingData.max_exit_velocity) }} mph</b></div>
          <div><span>Avg Exit Velocity</span><b>{{ display(selectedHittingData.avg_exit_velo ?? selectedHittingData.average_exit_velocity) }} mph</b></div>
        </div>
        <div class="mechanic-table">
          <div v-for="[key, label] in hittingRows" :key="key">
            <span>{{ label }}</span>
            <b>{{ selectedHittingData.mechanics?.[key] ?? '—' }}</b>
            <small>{{ mechanicLabel(selectedHittingData.mechanics?.[key]) }}</small>
          </div>
        </div>
        <p v-if="selectedHittingData.notes" class="note">{{ selectedHittingData.notes }}</p>
      </article>
    </section>

    <section class="three-grid">
      <article class="panel">
        <div class="section-title">Pitching Mechanics</div>
        <div class="mechanic-table">
          <div v-for="[key, label] in pitchingRows" :key="key">
            <span>{{ label }}</span>
            <b>{{ selectedPitchingData.mechanics?.[key] ?? '—' }}</b>
            <small>{{ mechanicLabel(selectedPitchingData.mechanics?.[key]) }}</small>
          </div>
        </div>
      </article>

      <article class="panel">
        <div class="section-title">Athletic Testing</div>
        <div class="metric-list">
          <div><span>Body Weight</span><b>{{ display(report.body_weight_lbs) }}</b></div>
          <div><span>Bench</span><b>{{ display(report.bench_lbs) }}</b></div>
          <div><span>Squat</span><b>{{ display(report.squat_lbs) }}</b></div>
          <div><span>Deadlift</span><b>{{ display(report.deadlift_lbs) }}</b></div>
          <div><span>Vertical Jump</span><b>{{ display(report.vertical_jump_in) }} in</b></div>
          <div><span>Broad Jump</span><b>{{ display(report.broad_jump_in) }} in</b></div>
          <div><span>10 Yard</span><b>{{ display(report.sprint_10yd_sec) }} sec</b></div>
        </div>
      </article>

      <article class="panel">
        <div class="section-title">Mobility Screen</div>
        <div class="mechanic-table">
          <div v-for="[label, value] in mobilityRows" :key="label">
            <span>{{ label }}</span>
            <b>{{ value ?? '—' }}</b>
            <small>{{ value >= 5 ? 'Good' : value >= 3 ? 'Fair' : value ? 'Needs Work' : '—' }}</small>
          </div>
        </div>
      </article>
    </section>

    <section class="three-grid">
      <article class="panel">
        <div class="section-title">Strengths</div>
        <textarea v-if="editing" class="ai-input" rows="5"
          :value="linesGet(insights.strengths)"
          @input="linesSet(insights, 'strengths', $event.target.value)"></textarea>
        <ul v-else class="bullet-list positive">
          <li v-for="(item, i) in insights.strengths" :key="i">{{ item }}</li>
          <li v-if="!insights.strengths.length" class="ai-muted">Not enough data tested.</li>
        </ul>
      </article>

      <article class="panel">
        <div class="section-title">Areas Holding Player Back</div>
        <textarea v-if="editing" class="ai-input" rows="5"
          :value="linesGet(insights.limiters)"
          @input="linesSet(insights, 'limiters', $event.target.value)"></textarea>
        <ul v-else class="bullet-list negative">
          <li v-for="(item, i) in insights.limiters" :key="i">{{ item }}</li>
          <li v-if="!insights.limiters.length" class="ai-muted">No clear limiters from tested data.</li>
        </ul>
      </article>

      <article class="panel">
        <div class="section-title">Recommended Focus Areas</div>
        <div class="focus-card" v-for="(f, i) in focusTiers" :key="f.tier">
          <b>{{ i + 1 }}. {{ f.title }} <span class="ai-pill">{{ f.drillCategory }}</span></b>
          <textarea v-if="editing" class="ai-input" rows="2" v-model="insights.focus[f.tier].reason"></textarea>
          <span v-else>{{ f.reason }}</span>
        </div>
        <div v-if="!focusTiers.length" class="ai-muted">Not enough data tested to recommend focus areas.</div>
      </article>
    </section>

    <section class="panel">
      <div class="section-title">30-Day Development Plan</div>
      <div class="plan-grid">
        <div class="ai-block">
          <div class="ai-label">Main Goal</div>
          <textarea v-if="editing" class="ai-input" rows="2" v-model="insights.plan.goal"></textarea>
          <p v-else class="ai-text">{{ insights.plan.goal }}</p>
        </div>
        <div class="ai-block">
          <div class="ai-label">Priorities</div>
          <textarea v-if="editing" class="ai-input" rows="3"
            :value="linesGet(insights.plan.priorities)"
            @input="linesSet(insights.plan, 'priorities', $event.target.value)"></textarea>
          <ol v-else class="ai-ol"><li v-for="(p, i) in insights.plan.priorities" :key="i">{{ p }}</li></ol>
        </div>
        <div class="ai-block">
          <div class="ai-label">How We Measure Progress</div>
          <textarea v-if="editing" class="ai-input" rows="2" v-model="insights.plan.measure"></textarea>
          <p v-else class="ai-text">{{ insights.plan.measure }}</p>
        </div>
        <div class="ai-block">
          <div class="ai-label">Retest Date</div>
          <p class="ai-text">{{ insights.plan.retestDate || '—' }}</p>
        </div>
      </div>
    </section>

    <footer class="report-footer">
      <span>Train.</span>
      <span>Track.</span>
      <span>Transform.</span>
    </footer>
  </main>
</template>

<style scoped>
.report {
  background:
    radial-gradient(circle at 80% 10%, rgba(8, 155, 255, .14), transparent 30%),
    linear-gradient(180deg, #07111D 0%, #04101A 100%);
  border: 1px solid rgba(125, 211, 252, 0.16);
  border-radius: 14px;
  padding: 22px;
}
.report-top,
.report-actions,
.brand,
.hero-grid,
.three-grid,
.summary-grid,
.bio-grid,
.metric-list div,
.grade-table div,
.mechanic-table div,
.report-footer {
  display: grid;
}
.report-top {
  grid-template-columns: 1fr auto;
  align-items: center;
  border-bottom: 1px solid rgba(255,255,255,.10);
  padding-bottom: 14px;
  margin-bottom: 16px;
}
.brand {
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 24px;
}
.brand span {
  font-size: 34px;
  font-weight: 1000;
  font-style: italic;
}
.brand strong {
  text-transform: uppercase;
  letter-spacing: .08em;
  color: rgba(255,255,255,.72);
}
.report-actions {
  grid-template-columns: auto auto;
  gap: 14px;
  align-items: center;
}
.report-actions small {
  display: block;
  color: rgba(255,255,255,.42);
  text-transform: uppercase;
  font-size: 10px;
}
.report-actions button {
  border: 1px solid rgba(8,155,255,.55);
  color: #38BDF8;
  border-radius: 8px;
  padding: 9px 14px;
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
}
.hero-grid {
  grid-template-columns: 1.4fr .8fr .9fr;
  gap: 14px;
  margin-bottom: 14px;
}
.panel {
  border: 1px solid rgba(125, 211, 252, 0.14);
  background: rgba(8, 18, 32, 0.82);
  border-radius: 10px;
  padding: 16px;
}
.player-card {
  display: grid;
  grid-template-columns: 156px 1fr;
  gap: 16px;
}
.photo-wrap {
  height: 220px;
  border-radius: 8px;
  overflow: hidden;
  background: rgba(255,255,255,.06);
}
.photo-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.player-main h2 {
  font-size: 36px;
  line-height: 1;
  font-weight: 1000;
  text-transform: uppercase;
}
.player-main p {
  color: #38BDF8;
  font-weight: 900;
  margin: 6px 0 18px;
}
.bio-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.bio-grid div,
.summary-tile {
  border-left: 1px solid rgba(255,255,255,.12);
  padding-left: 10px;
}
.bio-grid span,
.metric-list span,
.grade-table span,
.mechanic-table span {
  color: rgba(255,255,255,.48);
  font-size: 10px;
  text-transform: uppercase;
}
.bio-grid b,
.metric-list b,
.grade-table b,
.mechanic-table b {
  color: #fff;
  font-size: 12px;
}
.score-card,
.type-card {
  text-align: center;
}
.score-card h3,
.type-card h3 {
  color: rgba(255,255,255,.55);
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 14px;
}
.score-ring {
  width: 170px;
  height: 170px;
  border-radius: 999px;
  margin: 0 auto 12px;
  display: grid;
  place-items: center;
  background: conic-gradient(var(--score-color) calc(var(--score) * 1%), rgba(255,255,255,.10) 0);
}
.score-ring > div {
  width: 118px;
  height: 118px;
  border-radius: 999px;
  background: #07111D;
  display: grid;
  place-items: center;
  align-content: center;
}
.score-ring strong {
  font-size: 48px;
  line-height: .9;
}
.score-ring span {
  color: rgba(255,255,255,.45);
}
.type-card h2 {
  color: #65D84E;
  font-size: 26px;
  font-weight: 1000;
  text-transform: uppercase;
}
.type-card p,
.note {
  color: rgba(255,255,255,.62);
  font-size: 12px;
  line-height: 1.5;
}
.workload-line {
  border-top: 1px solid rgba(255,255,255,.10);
  margin-top: 16px;
  padding-top: 12px;
  display: flex;
  justify-content: space-between;
}
.section-title {
  color: #089BFF;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 900;
  font-size: 12px;
}
.summary-grid {
  grid-template-columns: repeat(6, 1fr);
  gap: 10px;
}
.summary-tile strong {
  display: block;
  font-size: 30px;
  font-weight: 1000;
}
.summary-tile small {
  color: rgba(255,255,255,.55);
  text-transform: uppercase;
  font-size: 10px;
}
.progress-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.reassess-tag {
  font-size: 10px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #38BDF8;
  border: 1px solid rgba(8, 155, 255, .4);
  background: rgba(8, 155, 255, .12);
  padding: 4px 9px;
  border-radius: 999px;
}
.progress-table {
  margin-top: 12px;
}
.progress-row {
  display: grid;
  grid-template-columns: 1.4fr .7fr .9fr .9fr;
  gap: 8px;
  align-items: center;
  border-bottom: 1px solid rgba(255,255,255,.08);
  padding: 8px 0;
}
.progress-row span {
  color: rgba(255,255,255,.7);
  font-size: 12px;
  font-weight: 700;
}
.progress-row b {
  text-align: right;
  font-size: 13px;
  font-weight: 900;
}
.progress-row--head span,
.progress-row--head b {
  color: rgba(255,255,255,.45);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: .06em;
  font-weight: 800;
}
.three-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.metric-list,
.grade-table,
.mechanic-table {
  margin-top: 12px;
}
.metric-list div,
.grade-table div,
.mechanic-table div {
  grid-template-columns: 1fr auto auto;
  gap: 10px;
  align-items: center;
  border-bottom: 1px solid rgba(255,255,255,.08);
  padding: 7px 0;
}
.grade-table small,
.mechanic-table small {
  color: #65D84E;
  min-width: 72px;
  text-align: right;
  font-size: 10px;
  text-transform: uppercase;
}
.bullet-list {
  margin: 12px 0 0;
  padding: 0;
  list-style: none;
}
.bullet-list li {
  color: rgba(255,255,255,.75);
  font-size: 12px;
  margin-bottom: 8px;
}
.bullet-list li::before {
  content: "✓";
  color: #65D84E;
  margin-right: 7px;
}
.bullet-list.negative li::before {
  content: "!";
  color: #F97316;
  font-weight: 900;
}

/* ── AI insight styling ── */
.ai-insights { border-color: rgba(8, 155, 255, 0.28); }
.ai-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}
.ai-actions { display: flex; gap: 6px; }
.ai-btn {
  border: 1px solid rgba(8,155,255,.45);
  color: #38BDF8;
  background: transparent;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  cursor: pointer;
}
.ai-btn--save { background: rgba(8,155,255,.18); }
.ai-advisory {
  margin-top: 10px;
  border: 1px solid rgba(249,115,22,.45);
  background: rgba(249,115,22,.12);
  color: #FDBA74;
  border-radius: 8px;
  padding: 9px 11px;
  font-size: 12px;
  line-height: 1.5;
}
.ai-block { margin-top: 12px; }
.ai-label {
  color: rgba(255,255,255,.45);
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .06em;
  margin-bottom: 4px;
}
.ai-text { color: rgba(255,255,255,.78); font-size: 12px; line-height: 1.55; }
.ai-muted { color: rgba(255,255,255,.4); font-size: 12px; font-style: italic; }
.ai-input {
  width: 100%;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 6px;
  color: #fff;
  font-size: 12px;
  line-height: 1.5;
  padding: 7px 9px;
  resize: vertical;
  font-family: inherit;
}
.ai-input:focus { outline: none; border-color: rgba(8,155,255,.6); }
.ai-pill {
  display: inline-block;
  margin-left: 6px;
  font-size: 9px;
  font-weight: 800;
  text-transform: uppercase;
  color: #38BDF8;
  border: 1px solid rgba(8,155,255,.4);
  border-radius: 999px;
  padding: 1px 7px;
}
.ai-ol { margin: 4px 0 0; padding-left: 16px; }
.ai-ol li { color: rgba(255,255,255,.78); font-size: 12px; margin-bottom: 4px; }
.plan-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 8px;
}
.focus-card,
.plan-step {
  border: 1px solid rgba(255,255,255,.10);
  border-radius: 8px;
  padding: 10px;
  margin-top: 10px;
  background: rgba(255,255,255,.04);
}
.focus-card b,
.plan-step b {
  display: block;
  color: #fff;
  font-size: 12px;
}
.focus-card span,
.plan-step span {
  color: rgba(255,255,255,.58);
  font-size: 11px;
}
.report-footer {
  grid-template-columns: repeat(3, auto);
  justify-content: center;
  gap: 34px;
  border-top: 1px solid rgba(255,255,255,.10);
  margin-top: 18px;
  padding-top: 16px;
  color: rgba(255,255,255,.48);
  text-transform: uppercase;
  letter-spacing: .14em;
  font-weight: 900;
}
@media (max-width: 1100px) {
  .hero-grid,
  .three-grid {
    grid-template-columns: 1fr;
  }
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .report {
    padding: 14px;
  }
  .report-top,
  .player-card,
  .bio-grid {
    grid-template-columns: 1fr;
  }
  .photo-wrap {
    height: 260px;
  }
  .brand {
    grid-template-columns: 1fr;
    gap: 2px;
  }
  .report-actions {
    margin-top: 12px;
    grid-template-columns: 1fr;
  }
}
</style>

<!-- Global (unscoped) print rules: print ONLY the report, in colour, on one page. -->
<style>
@media print {
  @page { size: A4 portrait; margin: 7mm; }

  body.assessment-printing { background: #fff !important; }
  /* Hide the live dashboard, show only the cloned report portal. */
  body.assessment-printing > *:not(#assessment-print-portal) { display: none !important; }

  #assessment-print-portal { display: block !important; }
  #assessment-print-portal,
  #assessment-print-portal * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  #assessment-print-portal .no-print { display: none !important; }

  /* Force the wide multi-column layout (the responsive breakpoints otherwise
     collapse everything to one column at print width, making it 2+ pages). */
  #assessment-print-portal .hero-grid { grid-template-columns: 1.4fr 0.8fr 0.9fr !important; }
  #assessment-print-portal .three-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
  #assessment-print-portal .summary-grid { grid-template-columns: repeat(6, 1fr) !important; }
  #assessment-print-portal .plan-grid { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; gap: 8px !important; }
  #assessment-print-portal .ai-actions { display: none !important; }
  #assessment-print-portal .ai-block { margin-top: 7px !important; }
  #assessment-print-portal .ai-text, #assessment-print-portal .ai-ol li { font-size: 11px !important; line-height: 1.4 !important; }
  #assessment-print-portal .player-card { grid-template-columns: 110px 1fr !important; }
  #assessment-print-portal .bio-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
  #assessment-print-portal .report-top { grid-template-columns: 1fr auto !important; }

  /* Compact spacing/typography so the whole report fits one A4 page. */
  #assessment-print-portal .report { padding: 10px !important; border-radius: 0 !important; }
  #assessment-print-portal section { margin-top: 7px !important; }
  #assessment-print-portal .panel { padding: 9px !important; }
  #assessment-print-portal .hero-grid { margin-bottom: 7px !important; }
  #assessment-print-portal .report-top { padding-bottom: 8px !important; margin-bottom: 8px !important; }
  #assessment-print-portal .brand span { font-size: 22px !important; }
  #assessment-print-portal .photo-wrap { height: 96px !important; }
  #assessment-print-portal .player-main h2 { font-size: 19px !important; }
  #assessment-print-portal .player-main p { margin: 3px 0 7px !important; }
  #assessment-print-portal .bio-grid { gap: 5px !important; }
  #assessment-print-portal .score-card h3,
  #assessment-print-portal .type-card h3 { margin-bottom: 6px !important; }
  #assessment-print-portal .score-ring { width: 92px !important; height: 92px !important; margin-bottom: 6px !important; }
  #assessment-print-portal .score-ring > div { width: 64px !important; height: 64px !important; }
  #assessment-print-portal .score-ring strong { font-size: 26px !important; }
  #assessment-print-portal .type-card h2 { font-size: 15px !important; }
  #assessment-print-portal .workload-line { margin-top: 8px !important; padding-top: 7px !important; }
  #assessment-print-portal .summary-tile strong { font-size: 18px !important; }
  #assessment-print-portal .metric-list,
  #assessment-print-portal .grade-table,
  #assessment-print-portal .mechanic-table,
  #assessment-print-portal .progress-table { margin-top: 6px !important; }
  #assessment-print-portal .metric-list div,
  #assessment-print-portal .grade-table div,
  #assessment-print-portal .mechanic-table div,
  #assessment-print-portal .progress-row { padding: 2px 0 !important; }
  #assessment-print-portal .focus-card,
  #assessment-print-portal .plan-step { padding: 6px !important; margin-top: 6px !important; }
  #assessment-print-portal .report-footer { margin-top: 8px !important; padding-top: 8px !important; }

  /* Don't split a section across the page. */
  #assessment-print-portal section,
  #assessment-print-portal .panel { break-inside: avoid; }
}
</style>
