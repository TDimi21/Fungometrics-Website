<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'
import { useUserStore } from '@/store/user'
import { storeToRefs } from 'pinia'
import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'

const { axiosGet } = useAxiosAuth()
const teamStore = useTeamStore()
const userStore = useUserStore()
const { team } = storeToRefs(teamStore)
const { userData } = storeToRefs(userStore)

const loading = ref(false)
const rows = ref([])
const selected = ref(null)

const activeTeamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)
const isPlayerUser = computed(() => String(userData.value?.type || '').toLowerCase() === 'player')
const activePlayerId = computed(() => (
  userData.value?.player?.id ||
  userData.value?.user?.player?.id ||
  userData.value?.user?.id ||
  userData.value?.id ||
  null
))

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

const selectedHittingData = computed(() => parseData(selected.value?.hitting_data))
const selectedPitchingData = computed(() => parseData(selected.value?.pitching_data))
const selectedThrowingData = computed(() => parseData(selected.value?.throwing_workload_data))

const summaryScores = computed(() => {
  const r = selected.value
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
  const r = selected.value
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
  const r = selected.value ?? {}
  return [
    ['Shoulder Mobility', r.shoulder_mobility],
    ['Hip Mobility', r.hip_mobility],
    ['Ankle Mobility', r.ankle_mobility],
    ['Hip Flexor Mobility', r.hip_flexor_mobility],
    ['Rotational Mobility', r.rotational_mobility],
  ]
})

const printMetricRows = computed(() => {
  const r = selected.value ?? {}
  return [
    ['10 yd', display(r.sprint_10yd_sec, '')],
    ['60 yd', display(r.yd_60_dash, '')],
    ['Exit Velo', display(selectedHittingData.value.max_exit_velocity, '')],
    ['FB Velo', display(selectedPitchingData.value.fastball_velocity, '')],
    ['Broad', display(r.broad_jump_in, '')],
    ['Vertical', display(r.vertical_jump_in, '')],
    ['Bench', display(r.bench_lbs, '')],
    ['Deadlift', display(r.deadlift_lbs, '')],
  ].filter(([, value]) => value !== '')
})

const printHittingRows = computed(() => hittingRows
  .map(([key, label]) => [label, selectedHittingData.value.mechanics?.[key]])
  .filter(([, value]) => value !== null && value !== undefined && value !== ''))

const printPitchingRows = computed(() => pitchingRows
  .map(([key, label]) => [label, selectedPitchingData.value.mechanics?.[key]])
  .filter(([, value]) => value !== null && value !== undefined && value !== ''))

const printPitchGrades = computed(() => pitchOrder
  .map((pitch) => [pitch, selectedPitchingData.value.pitch_grades?.[pitch]])
  .filter(([, value]) => value !== null && value !== undefined && value !== ''))

const loadReports = async () => {
  if (isPlayerUser.value && !activePlayerId.value) {
    rows.value = []
    selected.value = null
    return
  }

  if (!isPlayerUser.value && !activeTeamId.value) {
    rows.value = []
    selected.value = null
    return
  }

  loading.value = true
  try {
    const endpoint = isPlayerUser.value
      ? `assessments/player/${activePlayerId.value}`
      : `assessments/team/${activeTeamId.value}?all=1`
    const { data } = await axiosGet(endpoint)
    rows.value = data?.data ?? []
    selected.value = rows.value[0] ?? null
  } catch {
    rows.value = []
    selected.value = null
  } finally {
    loading.value = false
  }
}

const printReport = () => window.print()

onMounted(loadReports)
watch(activeTeamId, loadReports)
watch(activePlayerId, loadReports)
</script>

<template>
  <Layout>
    <div class="assessment-page">
      <div class="assessment-shell">
        <aside class="report-list no-print">
          <div class="list-heading">
            <p>FMTRX</p>
            <h1>Assessment Reports</h1>
          </div>

          <div v-if="loading" class="empty-state">Loading reports...</div>
          <div v-else-if="!rows.length" class="empty-state">No assessment reports found for this team.</div>

          <template v-else>
            <button
              v-for="r in rows"
              :key="r.id"
              class="report-row"
              :class="{ active: selected?.id === r.id }"
              @click="selected = r"
            >
              <span>{{ playerName(r) }}</span>
              <small>{{ formatDate(r.assessment_date) }}</small>
              <strong :style="{ color: scoreColor(r.overall_score) }">{{ r.overall_score ?? '—' }}</strong>
            </button>
          </template>
        </aside>

        <main v-if="selected" class="report" id="assessment-print">
          <header class="report-top">
            <div class="brand">
              <span>FMTRX</span>
              <strong>Player Assessment Report</strong>
            </div>
            <div class="report-actions no-print">
              <div>
                <small>Assessment Date</small>
                <b>{{ formatDate(selected.assessment_date) }}</b>
              </div>
              <button @click="printReport">PDF Export</button>
            </div>
          </header>

          <section class="hero-grid">
            <article class="player-card panel">
              <div class="photo-wrap">
                <img v-if="playerPhoto(selected)" :src="playerPhoto(selected)" :alt="playerName(selected)" />
                <span v-else>{{ initials(selected) }}</span>
              </div>
              <div class="player-main">
                <h2>{{ playerName(selected) }}</h2>
                <p>#{{ display(selected.profile?.number_in_shirt || selected.profile?.jersey, '—') }} · {{ display(selected.profile?.primary_position || selected.profile?.position, 'Player') }}</p>
                <div class="bio-grid">
                  <div><span>DOB</span><b>{{ display(selected.profile?.born_date || selected.profile?.dob) }}</b></div>
                  <div><span>Height</span><b>{{ display(selected.profile?.height || selected.height) }}</b></div>
                  <div><span>Weight</span><b>{{ display(selected.body_weight_lbs) }}</b></div>
                  <div><span>Team</span><b>{{ display(team?.name || team?.team_name) }}</b></div>
                  <div><span>Mobile</span><b>{{ display(selected.profile?.mobile_number || selected.profile?.phone) }}</b></div>
                  <div><span>Email</span><b>{{ display(selected.profile?.email || selected.profile?.parent_email) }}</b></div>
                </div>
              </div>
            </article>

            <article class="score-card panel">
              <h3>Overall Development Score</h3>
              <div class="score-ring" :style="{ '--score': selected.overall_score ?? 0, '--score-color': scoreColor(selected.overall_score) }">
                <div>
                  <strong>{{ selected.overall_score ?? '—' }}</strong>
                  <span>/100</span>
                </div>
              </div>
              <b :style="{ color: scoreColor(selected.overall_score) }">{{ scoreLabel(selected.overall_score) }}</b>
            </article>

            <article class="type-card panel">
              <h3>Player Type</h3>
              <h2>{{ playerType.title }}</h2>
              <p>{{ playerType.body }}</p>
              <div class="workload-line">
                <span>Throwing Workload</span>
                <b :style="{ color: scoreColor(100 - (selected.throwing_workload_score ?? 0)) }">{{ selected.throwing_workload_level ?? '—' }}</b>
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
                <div><span>Max Exit Velocity</span><b>{{ display(selectedHittingData.max_exit_velocity) }} mph</b></div>
                <div><span>Avg Exit Velocity</span><b>{{ display(selectedHittingData.average_exit_velocity) }} mph</b></div>
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
                <div><span>Body Weight</span><b>{{ display(selected.body_weight_lbs) }}</b></div>
                <div><span>Bench</span><b>{{ display(selected.bench_lbs) }}</b></div>
                <div><span>Squat</span><b>{{ display(selected.squat_lbs) }}</b></div>
                <div><span>Deadlift</span><b>{{ display(selected.deadlift_lbs) }}</b></div>
                <div><span>Vertical Jump</span><b>{{ display(selected.vertical_jump_in) }} in</b></div>
                <div><span>Broad Jump</span><b>{{ display(selected.broad_jump_in) }} in</b></div>
                <div><span>10 Yard</span><b>{{ display(selected.sprint_10yd_sec) }} sec</b></div>
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

        <section v-if="selected" class="print-sheet" id="assessment-print-sheet">
          <header class="print-header">
            <div>
              <strong>FMTRX</strong>
              <span>Player Assessment Report</span>
            </div>
            <div>
              <small>Assessment Date</small>
              <b>{{ formatDate(selected.assessment_date) }}</b>
            </div>
          </header>

          <section class="print-hero">
            <div class="print-player">
              <img :src="playerPhoto(selected)" :alt="playerName(selected)" />
              <div>
                <h1>{{ playerName(selected) }}</h1>
                <p>#{{ display(selected.profile?.number_in_shirt || selected.profile?.jersey, '—') }} · {{ display(selected.profile?.primary_position || selected.profile?.position, 'Player') }}</p>
                <dl>
                  <div><dt>DOB</dt><dd>{{ display(selected.profile?.born_date || selected.profile?.dob) }}</dd></div>
                  <div><dt>Height</dt><dd>{{ display(selected.profile?.height || selected.height) }}</dd></div>
                  <div><dt>Weight</dt><dd>{{ display(selected.body_weight_lbs) }}</dd></div>
                  <div><dt>Team</dt><dd>{{ display(team?.name || team?.team_name) }}</dd></div>
                </dl>
              </div>
            </div>
            <div class="print-score">
              <span>Overall</span>
              <strong :style="{ color: scoreColor(selected.overall_score) }">{{ selected.overall_score ?? '—' }}</strong>
              <small>{{ scoreLabel(selected.overall_score) }}</small>
            </div>
            <div class="print-type">
              <span>Player Type</span>
              <strong>{{ playerType.title }}</strong>
              <p>{{ playerType.body }}</p>
            </div>
          </section>

          <section class="print-score-grid">
            <div v-for="item in summaryScores" :key="`print-${item.label}`">
              <span>{{ item.label }}</span>
              <strong :style="{ color: scoreColor(item.value) }">{{ item.value ?? '—' }}</strong>
              <small>{{ sectionStatus(item.value) }}</small>
            </div>
          </section>

          <section class="print-main-grid">
            <article>
              <h2>Assessment Snapshot</h2>
              <div class="print-table compact">
                <div v-for="[label, value] in printMetricRows" :key="label">
                  <span>{{ label }}</span>
                  <b>{{ value }}</b>
                </div>
              </div>
              <h2>Throwing / Arm Health</h2>
              <div class="print-table compact">
                <div><span>Role</span><b>{{ display(selectedThrowingData.primary_throwing_role) }}</b></div>
                <div><span>Workload</span><b>{{ display(selected.throwing_workload_level) }}</b></div>
                <div><span>Days/Wk</span><b>{{ display(selectedThrowingData.throwing_days_per_week) }}</b></div>
                <div><span>Throws</span><b>{{ display(selectedThrowingData.throws_per_day_range) }}</b></div>
                <div><span>Arm Pain</span><b>{{ display(selectedThrowingData.arm_pain) }}</b></div>
              </div>
            </article>

            <article>
              <h2>Hitting</h2>
              <div class="print-table">
                <div v-for="[label, value] in printHittingRows.slice(0, 9)" :key="label">
                  <span>{{ label }}</span>
                  <b>{{ value }}</b>
                  <small>{{ mechanicLabel(value) }}</small>
                </div>
              </div>
            </article>

            <article>
              <h2>Pitching</h2>
              <div class="print-table">
                <div v-for="[label, value] in printPitchingRows.slice(0, 8)" :key="label">
                  <span>{{ label }}</span>
                  <b>{{ value }}</b>
                  <small>{{ mechanicLabel(value) }}</small>
                </div>
              </div>
              <h2>Pitch Grades</h2>
              <div class="print-table compact">
                <div v-for="[label, value] in printPitchGrades.slice(0, 6)" :key="label">
                  <span>{{ label }}</span>
                  <b>{{ value }}</b>
                </div>
              </div>
            </article>

            <article>
              <h2>Mobility</h2>
              <div class="print-table">
                <div v-for="[label, value] in mobilityRows" :key="label">
                  <span>{{ label }}</span>
                  <b>{{ value ?? '—' }}</b>
                  <small>{{ value >= 5 ? 'Good' : value >= 3 ? 'Fair' : value ? 'Needs Work' : '—' }}</small>
                </div>
              </div>
              <h2>Focus</h2>
              <ol class="print-focus">
                <li v-for="item in focusAreas.slice(0, 3)" :key="`print-focus-${item.label}`">{{ item.label }} · {{ sectionStatus(item.value) }}</li>
              </ol>
            </article>
          </section>

          <section class="print-notes">
            <div>
              <h2>Strengths</h2>
              <p>{{ topStrengths.map(item => `${item.label}: ${sectionStatus(item.value)}`).join(' · ') || '—' }}</p>
            </div>
            <div>
              <h2>30-Day Plan</h2>
              <p>{{ focusAreas.slice(0, 3).map(item => `${item.label} 2-3x/week`).join(' · ') || 'Reassess next cycle.' }}</p>
            </div>
          </section>
        </section>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.assessment-page {
  min-height: 100%;
  background: #050B14;
  color: #fff;
  overflow-y: auto;
  padding: 20px;
}
.assessment-shell {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr);
  gap: 18px;
  max-width: 1480px;
  margin: 0 auto;
}
.report-list,
.panel,
.report-row {
  border: 1px solid rgba(125, 211, 252, 0.14);
  background: rgba(8, 18, 32, 0.82);
}
.report-list {
  border-radius: 12px;
  padding: 14px;
  height: calc(100vh - 40px);
  overflow-y: auto;
  position: sticky;
  top: 20px;
}
.list-heading p,
.section-title {
  color: #089BFF;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-weight: 900;
  font-size: 12px;
}
.list-heading h1 {
  font-size: 18px;
  font-weight: 900;
  margin: 2px 0 14px;
}
.empty-state {
  color: rgba(255,255,255,.45);
  font-size: 13px;
  padding: 28px 10px;
  text-align: center;
}
.report-row {
  width: 100%;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 4px 10px;
  padding: 12px;
  border-radius: 9px;
  margin-bottom: 8px;
  text-align: left;
}
.report-row.active {
  border-color: rgba(8, 155, 255, .65);
  background: rgba(8, 155, 255, .15);
}
.report-row span {
  font-weight: 800;
}
.report-row small {
  color: rgba(255,255,255,.45);
}
.report-row strong {
  grid-row: span 2;
  align-self: center;
  font-size: 18px;
}
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
.print-sheet {
  display: none;
}
@media (max-width: 1100px) {
  .assessment-shell,
  .hero-grid,
  .three-grid {
    grid-template-columns: 1fr;
  }
  .report-list {
    position: static;
    height: auto;
  }
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 640px) {
  .assessment-page {
    padding: 10px;
  }
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
@media print {
  @page {
    size: letter landscape;
    margin: 0.22in;
  }
  :global(html),
  :global(body),
  :global(#app) {
    width: 100%;
    height: 100%;
    margin: 0 !important;
    padding: 0 !important;
    background: #fff !important;
    overflow: hidden !important;
  }
  .no-print,
  .report-list,
  .report {
    display: none !important;
  }
  .assessment-page {
    padding: 0 !important;
    background: #fff !important;
    overflow: hidden !important;
  }
  .assessment-shell {
    display: block !important;
    max-width: none !important;
    margin: 0 !important;
  }
  .print-sheet {
    display: block !important;
    width: 10.55in;
    height: 7.55in;
    overflow: hidden;
    box-sizing: border-box;
    padding: 0.12in;
    color: #0F172A;
    background: #fff;
    font-family: Inter, Arial, sans-serif;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }
  .print-header,
  .print-hero,
  .print-score-grid,
  .print-main-grid,
  .print-notes,
  .print-player,
  .print-player dl,
  .print-table div {
    display: grid;
  }
  .print-header {
    grid-template-columns: 1fr auto;
    align-items: center;
    background: #061427;
    color: #fff;
    padding: 0.09in 0.14in;
    border-radius: 8px;
    margin-bottom: 0.08in;
  }
  .print-header strong {
    font-size: 25px;
    font-weight: 1000;
    font-style: italic;
    margin-right: 0.18in;
  }
  .print-header span {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 12px;
  }
  .print-header small,
  .print-hero span,
  .print-table span,
  .print-score-grid span {
    display: block;
    color: #64748B;
    text-transform: uppercase;
    font-size: 7.5px;
    letter-spacing: 0.06em;
    font-weight: 800;
  }
  .print-header small {
    color: #93A4B8;
  }
  .print-hero {
    grid-template-columns: 2.1fr 0.55fr 1.1fr;
    gap: 0.08in;
    margin-bottom: 0.08in;
  }
  .print-player,
  .print-score,
  .print-type,
  .print-score-grid div,
  .print-main-grid article,
  .print-notes > div {
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    background: #F8FAFC;
  }
  .print-player {
    grid-template-columns: 1.05in 1fr;
    gap: 0.1in;
    padding: 0.08in;
  }
  .print-player img {
    width: 1.05in;
    height: 1.18in;
    border-radius: 6px;
    object-fit: cover;
    background: #E2E8F0;
  }
  .print-player h1 {
    margin: 0;
    font-size: 23px;
    line-height: 1;
    color: #07111D;
    text-transform: uppercase;
    font-weight: 1000;
  }
  .print-player p {
    margin: 0.03in 0 0.06in;
    color: #0284C7;
    font-size: 11px;
    font-weight: 900;
  }
  .print-player dl {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.05in;
    margin: 0;
  }
  .print-player dt {
    color: #64748B;
    font-size: 7px;
    text-transform: uppercase;
    font-weight: 900;
  }
  .print-player dd {
    margin: 0;
    color: #0F172A;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .print-score,
  .print-type {
    padding: 0.09in;
  }
  .print-score {
    text-align: center;
  }
  .print-score strong {
    display: block;
    font-size: 44px;
    line-height: 0.95;
    font-weight: 1000;
  }
  .print-score small,
  .print-type p,
  .print-focus,
  .print-notes p {
    color: #475569;
    font-size: 9px;
    line-height: 1.35;
    margin: 0;
  }
  .print-type strong {
    display: block;
    color: #15803D;
    font-size: 18px;
    line-height: 1;
    margin: 0.04in 0;
    text-transform: uppercase;
  }
  .print-score-grid {
    grid-template-columns: repeat(6, 1fr);
    gap: 0.06in;
    margin-bottom: 0.08in;
  }
  .print-score-grid div {
    padding: 0.06in;
    min-height: 0.48in;
  }
  .print-score-grid strong {
    display: block;
    font-size: 20px;
    line-height: 1;
    font-weight: 1000;
  }
  .print-score-grid small {
    color: #475569;
    font-size: 7px;
    text-transform: uppercase;
    font-weight: 900;
  }
  .print-main-grid {
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 0.07in;
    margin-bottom: 0.08in;
  }
  .print-main-grid article {
    padding: 0.08in;
    min-height: 3.75in;
  }
  .print-main-grid h2,
  .print-notes h2 {
    margin: 0 0 0.04in;
    color: #0284C7;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 1000;
  }
  .print-main-grid h2:not(:first-child) {
    margin-top: 0.1in;
  }
  .print-table div {
    grid-template-columns: 1fr auto auto;
    gap: 0.05in;
    align-items: center;
    border-bottom: 1px solid #E2E8F0;
    padding: 0.034in 0;
  }
  .print-table.compact div {
    grid-template-columns: 1fr auto;
  }
  .print-table b {
    color: #0F172A;
    font-size: 10px;
    font-weight: 1000;
  }
  .print-table small {
    color: #15803D;
    font-size: 7px;
    text-transform: uppercase;
    text-align: right;
    font-weight: 900;
  }
  .print-focus {
    padding-left: 0.14in;
  }
  .print-focus li {
    margin-bottom: 0.035in;
  }
  .print-notes {
    grid-template-columns: 1fr 1fr;
    gap: 0.07in;
  }
  .print-notes > div {
    padding: 0.08in;
    min-height: 0.56in;
  }
}
</style>
