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

const percentileRows = computed(() => {
  const row = (metric, key, suffix = '') => {
    const value = current.value?.[key]
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
    row('Max Pitch Velo', 'max_pitch_velocity', ' mph'),
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
    <div class="mx-auto w-full max-w-7xl space-y-4 px-4 py-6">
      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink to="/dashboard?tab=development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">← Back to Dashboard</RouterLink>
          <RouterLink to="/development" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Player</RouterLink>
          <RouterLink to="/development/team" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Team</RouterLink>
          <RouterLink to="/development/coach" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Coach</RouterLink>
          <RouterLink to="/development/admin/benchmarks" class="rounded-md border border-white/20 px-3 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-800">Admin</RouterLink>
        </div>
      </div>

      <div v-if="loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-3 text-sm text-slate-300">
        Loading live development data{{ selectedPlayerName ? ` for ${selectedPlayerName}` : '' }}...
      </div>
      <div v-if="loadError" class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <div class="rounded-xl border border-white/10 bg-slate-900/70 p-3">
        <p class="text-xs uppercase tracking-wider text-slate-400">How to read this page</p>
        <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-slate-300 md:grid-cols-2">
          <p><strong>Top score cards:</strong> Overall + category scores on a 0–100 scale.</p>
          <p><strong>Where Are We?:</strong> Current baseline from latest data points.</p>
          <p><strong>Where Are We Going?:</strong> 30-day trend direction and change values.</p>
          <p><strong>How Do We Get There?:</strong> Highest-priority actions for next sessions.</p>
        </div>
      </div>

      <template v-if="sourceData">
        <PlayerSnapshotCard :player="player" />

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
          <DevelopmentScoreCard
            v-for="(card, idx) in scoreCards"
            :key="idx"
            :title="card.title"
            :score="card.score"
            :subtitle="card.subtitle"
            :clickable="true"
            @select="selectedScoreKey = card.key"
          />
        </div>

        <div v-if="selectedScoreDetail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4" @click.self="selectedScoreKey = null">
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

        <WhereAreWeCard :current="current" :scores="model" />
        <WhereAreWeGoingCard :trend="model.trend" />
        <HowWeGetThereCard :recommendations="recommendations" />

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <PercentileRankingsTable :rows="percentileRows" />
          <CorrelationInsightsCard :insights="insights" />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
          <RecoverySleepCard :recovery="model.recovery" :current="current" />
          <StrengthMetricsCard :strength="model.strength" />
          <MobilityAssessmentCard :mobility="model.mobility" />
        </div>

        <CoachActionPlanCard :recommendations="recommendations" :coach-notes="sourceData.coachNotes || ''" />
      </template>

      <div v-else-if="!loading" class="rounded-xl border border-white/10 bg-slate-900/70 p-4 text-sm text-slate-300">
        No development snapshot available for this player yet.
      </div>
    </div>
  </Layout>
</template>
