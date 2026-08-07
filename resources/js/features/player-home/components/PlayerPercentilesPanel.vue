<script setup>
import { computed, ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import PercentileRankingsPanel from '@/features/development/components/PercentileRankingsPanel.vue'
import { buildPlayerDevelopmentDashboard } from '@/features/development/lib/playerDevelopmentDashboardAdapter.js'
import StatePanel from '@/features/shared/components/StatePanel.vue'
import { createSectionState, runSection } from '../lib/sectionState.js'

const props = defineProps({ playerId: { type: [String, Number], default: null } })
const { axiosGet } = useAxiosAuth()
const state = createSectionState()
const live = ref(null)
const intelligence = ref(null)

const dashboard = computed(() => buildPlayerDevelopmentDashboard(
  live.value || {},
  intelligence.value || {},
  { readOnly: true },
))

const loadPercentiles = () => runSection(state, async () => {
  if (!props.playerId) return
  const [liveResponse, intelligenceResponse] = await Promise.all([
    axiosGet(`player/development/players/${props.playerId}`, { days: 365 }),
    axiosGet('player/intelligence', { days: 365 }).catch(() => null),
  ])
  live.value = liveResponse?.data?.data || null
  intelligence.value = intelligenceResponse?.data?.data || intelligenceResponse?.data || null
}, 'Couldn\'t load percentile rankings.')

watch(() => props.playerId, (id) => {
  if (id) loadPercentiles()
}, { immediate: true })
</script>

<template>
  <StatePanel v-if="state.loading" state="loading" message="Loading percentile rankings…" />
  <StatePanel v-else-if="state.error" state="error" :message="state.error" @retry="loadPercentiles" />
  <PercentileRankingsPanel
    v-else
    :groups="dashboard.percentileGroups"
    :comparison="dashboard.comparison"
  />
</template>
