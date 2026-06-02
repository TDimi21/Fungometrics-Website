<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team'

import PlayerSnapshotCard from '../components/PlayerSnapshotCard.vue'
import DevelopmentScoreCard from '../components/DevelopmentScoreCard.vue'
import WhereAreWeCard from '../components/WhereAreWeCard.vue'
import WhereAreWeGoingCard from '../components/WhereAreWeGoingCard.vue'
import HowWeGetThereCard from '../components/HowWeGetThereCard.vue'
import PercentileRankingsTable from '../components/PercentileRankingsTable.vue'
import CorrelationInsightsCard from '../components/CorrelationInsightsCard.vue'
import CoachActionPlanCard from '../components/CoachActionPlanCard.vue'
import RecoverySleepCard from '../components/RecoverySleepCard.vue'
import StrengthMetricsCard from '../components/StrengthMetricsCard.vue'
import MobilityAssessmentCard from '../components/MobilityAssessmentCard.vue'

import updatedLogo from '@/assets/img/login/assteslogin/updatedlogo.png'
import { buildPlayerDevelopmentModel } from '../lib/playerDevelopmentScore'
import { getAgeGroup, getMetricPercentile, getPercentileLabel } from '../lib/percentileEngine'
import { buildCorrelationInsights } from '../lib/correlationEngine'
import { buildRecommendations } from '../lib/recommendationEngine'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const sourceData = ref(null)
const loading = ref(false)
const loadError = ref('')
const selectedScoreKey = ref(null)
const selectedPlayerName = computed(() => String(route.query?.playerName || route.query?.name || '').trim())

const loadLiveData = async () => {
  loadError.value = ''
  sourceData.value = null

  const playerId = route.params?.playerId
  const teamId = team.value?.id || String(route.query?.teamId || '')

  if (!playerId) {
    loadError.value = 'No player selected. Navigate here from the Team Development board.'
    return
  }
  if (!teamId) {
    loadError.value = 'No team selected. Please select a team from the header, then return here.'
    return
  }

  loading.value = true
  try {
    const { data } = await axiosGet(`coach/development/teams/${teamId}/players/${playerId}`, { days: 60 })
    const payload = data?.data

    if (payload?.player && payload?.current) {
      sourceData.value = {
        player: payload.player,
        current: payload.current,
        history: Array.isArray(payload.history) ? payload.history : [],
        coachNotes: payload?.coach_notes || 'Live data mode: recommendations are generated from available session + fitness data.',
      }
      return
    }

    loadError.value = 'No development data found for this player. Make sure they have logged session results.'
  } catch (error) {
    const status = error?.response?.status
    if (status === 404) {
      loadError.value = 'Player not found. They may need to log sessions before development data is available.'
    } else if (status === 403) {
      loadError.value = 'Access denied. Your plan may not include player development dashboards.'
    } else {
      loadError.value = `Could not load development data (${status ?? 'network error'}). Check that the server is running.`
    }
  } finally {
    loading.value = false
  }
}

watch(
  () => [route.params?.playerId, team.value?.id],
  () => { loadLiveData() },
  { immediate: true }
)

const current = computed(() => sourceData.value?.current || {})
const history = computed(() => sourceData.value?.history || [])
const player = computed(() => sourceData.value?.player || {
  id: route.params?.playerId,
  name: selectedPlayerName.value || 'Selected Player',
  age: null,
  grade: '',
  position: '',
  positions: [],
  jersey: null,
  throws: '',
  bats: '',
  level: '',
  role: 'two-way',
  height: '',
  weight: null,
  graduation_year: null,
})
const model = computed(() => buildPlayerDevelopmentModel(current.value, history.value, player.value.role))
const ageGroup = computed(() => getAgeGroup(player.value))

// Patch current with FB velocity fallbacks so all consumers get a value
const effectiveCurrent = computed(() => ({
  ...current.value,
  avg_fb_velocity: current.value?.avg_fb_velocity ?? current.value?.avg_pitch_velocity ?? null,
  max_fb_velocity: current.value?.max_fb_velocity ?? current.value?.max_pitch_velocity ?? null,
}))

const percentileRows = computed(() => {
  const row = (metric, key, suffix = '') => {
    const value = effectiveCurrent.value?.[key]
    const percentile = getMetricPercentile(key, value, ageGroup.value, player.value.level)
    return {
      metric,
      value: value !== null && value !== undefined ? `${value}${suffix}` : '—',
      percentile,
      label: percentile !== null ? getPercentileLabel(percentile) : 'No benchmark',
    }
  }

  return [
    row('Max EV', 'max_exit_velocity', ' mph'),
    row('Avg EV', 'avg_exit_velocity', ' mph'),
    row('Max FB Velo', 'max_fb_velocity', ' mph'),
    row('Avg FB Velo', 'avg_fb_velocity', ' mph'),
    row('BP Score', 'bp_score'),
    row('Bullpen Score', 'bullpen_score'),
    row('Vertical Jump', 'vertical_jump', ' in'),
    row('Broad Jump', 'broad_jump', ' in'),
    row('Sleep Hours', 'sleep_hours', ' hrs'),
    row('Recovery Score', 'recovery_score'),
  ]
})

const insights = computed(() => buildCorrelationInsights(model.value.trend))
const recommendations = computed(() => buildRecommendations({
  scores: model.value,
  trend: model.value.trend,
  mobility: model.value.mobility,
}))

const n = (v) => (Number.isFinite(Number(v)) ? Number(v) : null)
const round1 = (v) => (Number.isFinite(Number(v)) ? Math.round(Number(v) * 10) / 10 : null)
const avg = (arr = []) => {
  const vals = arr.filter((v) => v !== null)
  if (!vals.length) return null
  return vals.reduce((a, b) => a + b, 0) / vals.length
}
const toText = (v, suffix = '') => (v === null || v === undefined ? '—' : `${v}${suffix}`)

const scoreDetailByKey = computed(() => {
  const curr = current.value || {}
  const m = model.value || {}

  const perfHitterInputs = [
    ['Avg Exit Velocity', n(curr.avg_exit_velocity)],
    ['Max Exit Velocity', n(curr.max_exit_velocity)],
    ['Hard Contact %', n(curr.hard_contact_percentage)],
    ['Line Drive %', n(curr.line_drive_percentage)],
    ['BP Score', n(curr.bp_score)],
    ['Cage Score', n(curr.cage_score)],
    ['Live AB Score', n(curr.live_ab_score)],
    ['Contact Control (100 - Swing/Miss %)', n(curr.swing_miss_percentage) !== null ? 100 - n(curr.swing_miss_percentage) : null],
  ]

  const perfPitcherInputs = [
    ['Avg FB Velocity', n(curr.avg_fb_velocity)],
    ['Avg Pitch Velocity', n(curr.avg_pitch_velocity)],
    ['Max Pitch Velocity', n(curr.max_pitch_velocity)],
    ['Bullpen Score', n(curr.bullpen_score)],
    ['Command Score', n(curr.command_score)],
    ['Competitive Pitch %', n(curr.competitive_pitch_percentage)],
    ['Strike %', n(curr.strike_percentage)],
    ['Pitch Quality Score', n(curr.pitch_quality_score)],
  ]

  const hitterAvg = round1(avg(perfHitterInputs.map((x) => x[1])))
  const pitcherAvg = round1(avg(perfPitcherInputs.map((x) => x[1])))

  const upCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'up').length
  const downCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'down').length
  const flatCount = Object.values(m.trend?.changes || {}).filter((x) => x?.direction === 'flat').length
  const tally = upCount - downCount

  return {
    developmentIndex: {
      title: 'Player Development Index',
      formula: 'Performance×0.40 + Strength×0.20 + Mobility×0.15 + Recovery×0.15 + Trend×0.10',
      rows: [
        [`Performance (40%)`, `${m.performanceScore} × 0.40 = ${round1((m.performanceScore || 0) * 0.4)}`],
        [`Strength (20%)`, `${m.strengthScore} × 0.20 = ${round1((m.strengthScore || 0) * 0.2)}`],
        [`Mobility (15%)`, `${m.mobilityScore} × 0.15 = ${round1((m.mobilityScore || 0) * 0.15)}`],
        [`Recovery (15%)`, `${m.recoveryScore} × 0.15 = ${round1((m.recoveryScore || 0) * 0.15)}`],
        [`Trend (10%)`, `${m.trendScore} × 0.10 = ${round1((m.trendScore || 0) * 0.1)}`],
      ],
    },
    performance: {
      title: 'Performance Score',
      formula: 'Role-based average of current baseball performance metrics',
      rows: [
        ['Player role', player.value?.role || 'two-way'],
        ['Hitter average', toText(hitterAvg)],
        ['Pitcher average', toText(pitcherAvg)],
        ['Final performance score', `${m.performanceScore}`],
      ],
      inputs: {
        hitter: perfHitterInputs,
        pitcher: perfPitcherInputs,
      },
    },
    strength: {
      title: 'Strength Score',
      formula: 'Power×0.45 + Strength×0.30 + Speed×0.20 + Relative Strength×0.05',
      rows: [
        ['Power part', toText(m.strength?.parts?.power)],
        ['Strength part', toText(m.strength?.parts?.strength)],
        ['Speed part', toText(m.strength?.parts?.speed)],
        ['Relative Strength part', toText(m.strength?.parts?.pwo)],
        ['Final strength score', `${m.strengthScore}`],
      ],
    },
    mobility: {
      title: 'Mobility Score',
      formula: 'Mobility composite minus asymmetry penalties',
      rows: [
        ['Shoulder IR asymmetry', toText(m.mobility?.asymmetries?.shoulder_ir_diff)],
        ['Hip IR asymmetry', toText(m.mobility?.asymmetries?.hip_ir_diff)],
        ['Ankle asymmetry', toText(m.mobility?.asymmetries?.ankle_diff)],
        ['Thoracic asymmetry', toText(m.mobility?.asymmetries?.thoracic_diff)],
        ['Shoulder penalty', toText(round1(m.mobility?.penalties?.shoulderIR))],
        ['Hip penalty', toText(round1(m.mobility?.penalties?.hipIR))],
        ['Ankle penalty', toText(round1(m.mobility?.penalties?.ankle))],
        ['Thoracic penalty', toText(round1(m.mobility?.penalties?.thoracic))],
        ['Final mobility score', `${m.mobilityScore}`],
      ],
    },
    recovery: {
      title: 'Recovery Score',
      formula: 'Sleep×0.40 + SleepQuality×0.20 + Energy×0.15 + SorenessInv×0.10 + Hydration×0.10 + StressInv×0.05',
      rows: [
        ['Sleep score', toText(m.recovery?.parts?.sleep)],
        ['Sleep quality score', toText(m.recovery?.parts?.sleepQuality)],
        ['Energy score', toText(m.recovery?.parts?.energy)],
        ['Soreness (inverted)', toText(m.recovery?.parts?.sorenessInverted)],
        ['Hydration score', toText(m.recovery?.parts?.hydration)],
        ['Stress (inverted)', toText(m.recovery?.parts?.stressInverted)],
        ['Final recovery score', `${m.recoveryScore}`],
      ],
    },
    trend: {
      title: 'Trend Score',
      formula: '50 + (up-metrics - down-metrics) × 6, clamped to 0–100',
      rows: [
        ['Metrics trending up', `${upCount}`],
        ['Metrics trending down', `${downCount}`],
        ['Metrics flat/no change', `${flatCount}`],
        ['Directional tally', `${tally}`],
        ['Trend status', m.trend?.status || '—'],
        ['Final trend score', `${m.trendScore}`],
      ],
    },
  }
})

const selectedScoreDetail = computed(() => {
  if (!selectedScoreKey.value) return null
  return scoreDetailByKey.value[selectedScoreKey.value] || null
})

const scoreCards = computed(() => ([
  { key: 'developmentIndex', title: 'Player Development Index', score: model.value.developmentIndex, subtitle: model.value.status },
  { key: 'performance', title: 'Performance', score: model.value.performanceScore, subtitle: '40% weight' },
  { key: 'strength', title: 'Strength', score: model.value.strengthScore, subtitle: '20% weight' },
  { key: 'mobility', title: 'Mobility', score: model.value.mobilityScore, subtitle: '15% weight' },
  { key: 'recovery', title: 'Recovery', score: model.value.recoveryScore, subtitle: '15% weight' },
  { key: 'trend', title: 'Trend', score: model.value.trendScore, subtitle: '10% weight' },
]))
</script>

<template>
  <Layout>
    <div class="mx-auto w-full max-w-[1600px] px-3 py-4">

      <!-- Top nav bar -->
      <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-white/10 bg-slate-900/70 px-4 py-2">
        <RouterLink to="/dashboard?tab=development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back</RouterLink>
        <RouterLink to="/development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
        <RouterLink to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
        <RouterLink to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
        <RouterLink to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
      </div>

      <!-- Status messages -->
      <div v-if="loading" class="mb-4 rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading live development data{{ selectedPlayerName ? ` for ${selectedPlayerName}` : '' }}...
      </div>
      <div v-if="loadError" class="mb-4 rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <template v-if="sourceData">
        <!-- ═══════════════════════════════════════════════════════════
             SAVANT-STYLE 3-COLUMN LAYOUT
             Col 1 (wide): hero card + where/going/how
             Col 2 (narrow): 6 score cards stacked
             Col 3 (wide): rankings, insights, detail cards
        ════════════════════════════════════════════════════════════ -->
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[300px_1fr_1fr]">

          <!-- ── COLUMN 1 : Hero + narrative ── -->
          <div class="flex flex-col gap-4">

            <!-- Hero card — ModalPlayer style -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 shadow-2xl" style="min-height:340px">
              <!-- blurred bg -->
              <div class="absolute inset-0 bg-cover bg-center scale-110"
                :style="`background-image:url('${player.picture || updatedLogo}')`"></div>
              <div class="absolute inset-0 bg-gradient-to-b from-[#060b14]/50 via-[#060b14]/75 to-[#060b14]/97 backdrop-blur-[3px]"></div>

              <div class="relative z-10 flex flex-col items-center px-5 pb-6 pt-5 text-center">
                <!-- Avatar -->
                <div class="mb-3 h-[110px] w-[110px] overflow-hidden rounded-full ring-4 ring-[#C00000] shadow-2xl bg-slate-800 flex-shrink-0">
                  <img :src="player.picture || updatedLogo" :alt="player.name" class="h-full w-full object-cover object-top" />
                </div>

                <!-- Name + jersey -->
                <h2 class="text-xl font-black uppercase tracking-wide text-white drop-shadow leading-tight">
                  {{ player.name || 'Player' }}
                  <span v-if="player.jersey" class="ml-1 text-[#C00000]">#{{ player.jersey }}</span>
                </h2>

                <!-- Role badge -->
                <p class="mt-1 text-[10px] font-black uppercase tracking-widest text-white/50">
                  {{ player.role === 'two-way' ? 'TWO-WAY' : player.role === 'pitcher' ? 'PITCHER' : 'HITTER' }}
                </p>

                <!-- Divider -->
                <div class="my-3 w-full border-t border-white/10"></div>

                <!-- Info rows -->
                <div class="w-full flex flex-col gap-2 text-xs">
                  <div v-if="player.positions?.length" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Position</span>
                    <span class="font-black text-white">{{ player.positions.join(' · ') }}</span>
                  </div>
                  <div v-if="player.throws" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Throws</span>
                    <span class="font-black text-white">{{ player.throws }}</span>
                  </div>
                  <div v-if="player.bats" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Bats</span>
                    <span class="font-black text-white">{{ player.bats }}</span>
                  </div>
                  <div v-if="player.height" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Height</span>
                    <span class="font-black text-white">{{ player.height }}</span>
                  </div>
                  <div v-if="player.age" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Age</span>
                    <span class="font-black text-white">{{ player.age }}</span>
                  </div>
                  <div v-if="player.weight" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Weight</span>
                    <span class="font-black text-white">{{ player.weight }} lbs</span>
                  </div>
                  <div v-if="player.level" class="flex justify-between">
                    <span class="text-white/45 font-semibold uppercase tracking-wider">Level</span>
                    <span class="font-black text-white capitalize">{{ player.level }}</span>
                  </div>
                </div>

                <!-- Divider -->
                <div class="my-3 w-full border-t border-white/10"></div>

                <!-- Quick stat pills -->
                <div class="flex flex-wrap justify-center gap-2">
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    EV {{ current.avg_exit_velocity ?? '—' }} mph
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    FB {{ effectiveCurrent.avg_fb_velocity ?? '—' }} mph
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    BP {{ current.bp_score ?? '—' }}
                  </span>
                  <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black text-white">
                    Trend <span :class="model.trend?.status === 'improving' ? 'text-green-300' : model.trend?.status === 'declining' ? 'text-red-300' : 'text-white'">{{ model.trend?.status || '—' }}</span>
                  </span>
                </div>
              </div>
            </div>

            <!-- WHERE ARE WE? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">Where Are We?</p>
              <p class="mb-3 text-[11px] text-white/40">Current baseline from latest data points.</p>
              <div class="space-y-2 text-xs">
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Avg Exit Velocity</span>
                  <span class="font-black text-white">{{ current.avg_exit_velocity ?? '—' }} mph</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Max FB Velocity</span>
                  <span class="font-black text-white">{{ effectiveCurrent.max_fb_velocity ?? '—' }} mph</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Avg FB Velocity</span>
                  <span class="font-black text-white">{{ effectiveCurrent.avg_fb_velocity ?? '—' }} mph</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Batting Score</span>
                  <span class="font-black text-white">{{ current.bp_score ?? '—' }}</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Bullpen Score</span>
                  <span class="font-black text-white">{{ current.bullpen_score ?? '—' }}</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Strength</span>
                  <span class="font-black text-white">{{ model.strengthScore ?? '—' }}</span>
                </div>
                <div class="flex justify-between border-b border-white/5 pb-1.5">
                  <span class="text-white/55">Mobility</span>
                  <span class="font-black text-white">{{ model.mobilityScore ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-white/55">Recovery</span>
                  <span class="font-black text-white">{{ model.recoveryScore ?? '—' }}</span>
                </div>
              </div>
            </div>

            <!-- WHERE ARE WE GOING? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">Where Are We Going?</p>
              <p class="mb-1 text-[11px] text-white/40">30-day trend · positive = improving</p>
              <p class="mb-3 text-xs font-black" :class="model.trend?.status === 'improving' ? 'text-green-300' : model.trend?.status === 'declining' ? 'text-red-300' : 'text-yellow-300'">
                {{ model.trend?.status || '—' }}
              </p>
              <div class="space-y-2 text-xs">
                <div v-for="([label, key]) in [['EV','avg_exit_velocity'],['Max FB Velo','max_fb_velocity'],['Avg FB Velo','avg_fb_velocity'],['Hard Contact','hard_contact_percentage'],['Command','command_score'],['Strength','rotational_power_score'],['Sleep','sleep_hours']]" :key="key"
                  class="flex justify-between border-b border-white/5 pb-1.5 last:border-0 last:pb-0">
                  <span class="text-white/55">{{ label }}</span>
                  <span class="font-black"
                    :class="(model.trend?.changes?.[key]?.delta ?? 0) > 0 ? 'text-green-300' : (model.trend?.changes?.[key]?.delta ?? 0) < 0 ? 'text-red-300' : 'text-white/40'">
                    {{ model.trend?.changes?.[key]?.delta != null ? ((model.trend.changes[key].delta > 0 ? '+' : '') + model.trend.changes[key].delta.toFixed(1)) : '—' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- HOW DO WE GET THERE? -->
            <div class="rounded-2xl border border-white/10 bg-[#0a1020]/80 p-4">
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/40">How Do We Get There?</p>
              <p class="mb-3 text-[11px] text-white/40">Top action items from weakest areas.</p>
              <div v-if="recommendations.length" class="space-y-3">
                <div v-for="(r, idx) in recommendations.slice(0, 3)" :key="idx" class="rounded-xl border border-white/8 bg-white/5 p-3">
                  <p class="mb-1 text-[10px] font-black uppercase tracking-widest"
                    :class="r.priority === 'high' ? 'text-red-400' : r.priority === 'medium' ? 'text-yellow-400' : 'text-slate-400'">
                    Priority {{ idx + 1 }} · {{ r.priority }}
                  </p>
                  <p class="text-xs font-bold text-white">{{ r.title }}</p>
                  <p class="mt-1 text-[11px] text-white/55 leading-relaxed">{{ r.recommendation }}</p>
                </div>
              </div>
              <p v-else class="text-xs text-slate-500">No recommendations yet.</p>
            </div>

          </div><!-- /col 1 -->

          <!-- ── COLUMN 2 : Score cards + deep-dive ── -->
          <div class="flex flex-col gap-4">
            <!-- 6 score cards in a 2-col mini-grid -->
            <div>
              <p class="mb-2 text-[10px] font-black uppercase tracking-widest text-white/30 px-1">Development Scores</p>
              <div class="flex flex-col gap-3">
                <button
                  v-for="(card, idx) in scoreCards"
                  :key="idx"
                  type="button"
                  class="w-full rounded-xl border p-4 text-left transition hover:brightness-110"
                  :class="card.score >= 85 ? 'border-emerald-400/30 bg-emerald-950/40' : card.score >= 70 ? 'border-yellow-400/30 bg-yellow-950/40' : 'border-red-400/30 bg-red-950/40'"
                  @click="selectedScoreKey = card.key"
                >
                  <p class="text-[10px] font-black uppercase tracking-widest"
                    :class="card.score >= 85 ? 'text-emerald-400/70' : card.score >= 70 ? 'text-yellow-400/70' : 'text-red-400/70'">
                    {{ card.title }}
                  </p>
                  <p class="mt-1.5 text-4xl font-black leading-none"
                    :class="card.score >= 85 ? 'text-emerald-300' : card.score >= 70 ? 'text-yellow-300' : 'text-red-300'">
                    {{ card.score ?? 0 }}
                  </p>
                  <p class="mt-1 text-[10px] text-white/35">{{ card.subtitle }}</p>
                  <p class="mt-0.5 text-[10px] text-white/25">click for details</p>
                </button>
              </div>
            </div>
            <CoachActionPlanCard :recommendations="recommendations" :coach-notes="sourceData.coachNotes || ''" />
          </div><!-- /col 2 -->

          <!-- ── COLUMN 3 : Percentile Rankings + detail cards ── -->
          <div class="flex flex-col gap-4">
            <PercentileRankingsTable :rows="percentileRows" />
            <CorrelationInsightsCard :insights="insights" />
            <!-- Detail cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <RecoverySleepCard :recovery="model.recovery" :current="current" />
              <StrengthMetricsCard :strength="model.strength" />
              <MobilityAssessmentCard :mobility="model.mobility" />
            </div>
          </div><!-- /col 3 -->

        </div><!-- /3-col grid -->

      </template>

      <!-- No data state -->
      <div v-else-if="!loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-4 text-sm text-slate-300">
        No development snapshot available for this player yet.
      </div>
    </div>

    <!-- Score detail modal (unchanged) -->
    <div v-if="selectedScoreDetail" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 px-4 pt-6" @click.self="selectedScoreKey = null">
      <div class="w-full max-w-3xl rounded-xl border border-white/15 bg-slate-900 p-4">
        <div class="mb-3 flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] uppercase tracking-wider text-slate-400">Score Build Details</p>
            <p class="text-xl font-bold text-white">{{ selectedScoreDetail.title }}</p>
            <p class="mt-1 text-xs text-slate-300">{{ selectedScoreDetail.formula }}</p>
          </div>
          <button class="rounded-md border border-white/20 px-3 py-1 text-sm text-slate-200 hover:bg-slate-800" @click="selectedScoreKey = null">Close</button>
        </div>
        <div class="rounded-lg border border-white/10 bg-slate-950/60 p-3">
          <div class="space-y-2 text-sm">
            <div v-for="(row, i) in selectedScoreDetail.rows" :key="i" class="flex items-start justify-between gap-3 border-b border-white/5 pb-2 last:border-b-0 last:pb-0">
              <p class="text-slate-300">{{ row[0] }}</p>
              <p class="text-right font-semibold text-white">{{ row[1] }}</p>
            </div>
          </div>
          <div v-if="selectedScoreKey === 'performance'" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
            <div class="rounded-md border border-white/10 p-3">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Hitter inputs</p>
              <div class="space-y-1 text-xs">
                <div v-for="(row, i) in selectedScoreDetail.inputs?.hitter || []" :key="`h-${i}`" class="flex justify-between gap-2">
                  <span class="text-slate-300">{{ row[0] }}</span>
                  <span class="font-medium text-white">{{ row[1] ?? '—' }}</span>
                </div>
              </div>
            </div>
            <div class="rounded-md border border-white/10 p-3">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Pitcher inputs</p>
              <div class="space-y-1 text-xs">
                <div v-for="(row, i) in selectedScoreDetail.inputs?.pitcher || []" :key="`p-${i}`" class="flex justify-between gap-2">
                  <span class="text-slate-300">{{ row[0] }}</span>
                  <span class="font-medium text-white">{{ row[1] ?? '—' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </Layout>
</template>
