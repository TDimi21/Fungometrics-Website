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

import dummy from '../data/dummyPlayerDevelopmentData'
import { buildPlayerDevelopmentModel } from '../lib/playerDevelopmentScore'
import { getAgeGroup, getMetricPercentile, getPercentileLabel } from '../lib/percentileEngine'
import { buildCorrelationInsights } from '../lib/correlationEngine'
import { buildRecommendations } from '../lib/recommendationEngine'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { team } = storeToRefs(useTeamStore())

const sourceData = ref(dummy)
const loading = ref(false)
const loadError = ref('')

const loadLiveData = async () => {
  loadError.value = ''

  const playerId = route.params?.playerId
  const teamId = team.value?.id

  if (!playerId || !teamId) {
    sourceData.value = dummy
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

    sourceData.value = dummy
    loadError.value = 'No live data found for this player yet. Showing demo data.'
  } catch (error) {
    sourceData.value = dummy
    loadError.value = 'Live API load failed. Showing demo data.'
  } finally {
    loading.value = false
  }
}

watch(
  () => [route.params?.playerId, team.value?.id],
  () => { loadLiveData() },
  { immediate: true }
)

const player = computed(() => sourceData.value.player || dummy.player)
const current = computed(() => sourceData.value.current || dummy.current)
const history = computed(() => sourceData.value.history || dummy.history)
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

const scoreCards = computed(() => ([
  { title: 'Player Development Index', score: model.value.developmentIndex, subtitle: model.value.status },
  { title: 'Performance', score: model.value.performanceScore, subtitle: '40% weight' },
  { title: 'Strength', score: model.value.strengthScore, subtitle: '20% weight' },
  { title: 'Mobility', score: model.value.mobilityScore, subtitle: '15% weight' },
  { title: 'Recovery', score: model.value.recoveryScore, subtitle: '15% weight' },
  { title: 'Trend', score: model.value.trendScore, subtitle: '10% weight' },
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
        Loading live development data...
      </div>
      <div v-if="loadError" class="rounded-xl border border-amber-400/20 bg-amber-500/10 p-3 text-sm text-amber-200">
        {{ loadError }}
      </div>

      <PlayerSnapshotCard :player="player" />

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
        <DevelopmentScoreCard
          v-for="(card, idx) in scoreCards"
          :key="idx"
          :title="card.title"
          :score="card.score"
          :subtitle="card.subtitle"
        />
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

      <CoachActionPlanCard :recommendations="recommendations" :coach-notes="sourceData.coachNotes || dummy.coachNotes" />
    </div>
  </Layout>
</template>
