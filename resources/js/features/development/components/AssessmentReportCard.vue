<script setup>
/**
 * AssessmentReportCard.vue
 * The full FMTRX "Player Assessment Report" card, driven by a single saved
 * assessment record. Extracted from the /assessment-reports page so the same
 * rich report can be reused inline (e.g. under the dashboard Assessment tab).
 */
import { computed } from 'vue'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

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

const summaryScores = computed(() => {
  const r = props.report
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
})

const playerType = computed(() => {
  const r = props.report
  if (!r) return { title: 'Player Profile', body: 'Complete an assessment to generate a development profile.' }
  const mobility = num(r.mobility_overall_score)
  const strength = num(r.strength_overall_score)
  const hitting = num(r.hitting_score)
  const pitching = num(r.pitching_score)
  const arm = num(r.arm_health_score)
  const workload = num(r.throwing_workload_score)

  if (mobility !== null && mobility < 60) return { title: 'Mobility Limited', body: 'Mobility is limiting positions, sequencing, and power transfer.' }
  if (strength !== null && strength < 60) return { title: 'Strength Limited', body: 'Adding usable strength should unlock better force production.' }
  if (hitting !== null && hitting >= 78 && strength !== null && strength < 72) return { title: 'Power Leaker', body: 'The bat has tools, but strength or lower-half transfer is holding back impact.' }
  if (pitching !== null && pitching >= 78 && arm !== null && arm >= 75) return { title: 'Command Pitcher', body: 'Pitching profile is ahead and can keep growing with targeted workload control.' }
  if (workload !== null && workload >= 70) return { title: 'Arm Dominant', body: 'Throwing load is elevated. Manage volume while improving movement quality.' }
  if (hitting !== null && hitting >= 80) return { title: 'Power Hitter', body: 'Hitting profile shows impact potential and should be supported with efficient movement.' }
  if (pitching !== null && pitching >= 80 && hitting !== null && hitting >= 70) return { title: 'Two-Way Athlete', body: 'Both hitting and pitching traits are reportable strengths.' }
  return { title: 'Athletic Mover', body: 'This player has a balanced profile with clear next-step development targets.' }
})

const topStrengths = computed(() => summaryScores.value.filter(s => num(s.value) !== null).sort((a, b) => num(b.value) - num(a.value)).slice(0, 3))
const focusAreas = computed(() => summaryScores.value.filter(s => num(s.value) !== null).sort((a, b) => num(a.value) - num(b.value)).slice(0, 3))

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

const printReport = () => window.print()
</script>

<template>
  <main v-if="report" class="report" id="assessment-print">
    <header class="report-top">
      <div class="brand">
        <span>FMTRX</span>
        <strong>Player Assessment Report</strong>
      </div>
      <div v-if="showActions" class="report-actions no-print">
        <div>
          <small>Assessment Date</small>
          <b>{{ formatDate(report.assessment_date) }}</b>
        </div>
        <button @click="printReport">PDF Export</button>
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
            <div><span>DOB</span><b>{{ display(report.profile?.born_date || report.profile?.dob) }}</b></div>
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
        <h2>{{ playerType.title }}</h2>
        <p>{{ playerType.body }}</p>
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
        <ul class="bullet-list positive">
          <li v-for="item in topStrengths" :key="item.label">{{ item.label }}: {{ sectionStatus(item.value) }}</li>
        </ul>
      </article>

      <article class="panel">
        <div class="section-title">Recommended Focus Areas</div>
        <div class="focus-card" v-for="(item, index) in focusAreas" :key="item.label">
          <b>{{ index + 1 }}. {{ item.label }}</b>
          <span>{{ sectionStatus(item.value) }}</span>
        </div>
      </article>

      <article class="panel">
        <div class="section-title">30-Day Development Plan</div>
        <div class="plan-step" v-for="item in focusAreas" :key="`plan-${item.label}`">
          <b>{{ item.label }}</b>
          <span>Train 2-3x/week and reassess next cycle.</span>
        </div>
      </article>
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
