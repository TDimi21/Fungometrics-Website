<script setup>
import { computed, ref } from 'vue'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import { TabBallByBall, TabPitchBreakdown, TabContact, TabTrajectory, TabVelocity } from '@/components/statistics'
import { TabBall, TabPitch, TabCont, TabVelo } from '@/components/statistics/bullpen'
import { livePracticeRows, groupPracticeRows } from '@/utils/livePracticeStats'
const props = defineProps({ mode: { type: String, required: true }, rows: { type: Array, default: () => [] }, players: { type: Array, default: () => [] } })
const headings = computed(() => props.mode === 'P' ? ['Ball by ball', 'Pitch breakdown', 'Contact', 'Velocity'] : ['Ball by ball', 'Pitch breakdown', 'Contact', 'Trajectory', 'Velocity'])
const enriched = computed(() => livePracticeRows(props.rows, props.players, props.mode))
const grouped = computed(() => groupPracticeRows(enriched.value, props.mode))
const sortKey = ref('sort'), ascending = ref(false)
function sortData(key) { if (!key) return; ascending.value = key === sortKey.value ? !ascending.value : true; sortKey.value = key }
const sorted = computed(() => [...enriched.value].sort((a,b) => {
  const value = row => sortKey.value.split('.').reduce((v,k) => v?.[k], row) ?? ''
  return String(value(a)).localeCompare(String(value(b)), undefined, { numeric: true }) * (ascending.value ? 1 : -1)
}))
</script>
<template>
  <TabGroup>
    <TabList class="live-stat-tabs"><Tab v-for="heading in headings" :key="heading" v-slot="{ selected }" as="template"><button :class="{ active: selected }">{{ heading }}</button></Tab></TabList>
    <TabPanels>
      <TabPanel><TabBall v-if="mode === 'P'" :table-data="sorted" :is-loading="false" :editable="false" @sort-data="sortData" /><TabBallByBall v-else :table-data="sorted" :is-loading="false" :editable="false" @sort-data="sortData" /></TabPanel>
      <TabPanel><TabPitch v-if="mode === 'P'" :breakdown-data="grouped" :ball-data="enriched" /><TabPitchBreakdown v-else :breakdown-data="grouped" :ball-data="enriched" /></TabPanel>
      <TabPanel><TabCont v-if="mode === 'P'" :breakdown-data="grouped" :ball-data="enriched" /><TabContact v-else :contact-data="grouped" :ball-data="enriched" /></TabPanel>
      <TabPanel v-if="mode === 'B'"><TabTrajectory :trajectory-data="grouped" :ball-data="enriched" /></TabPanel>
      <TabPanel><TabVelo v-if="mode === 'P'" :VelocityData="grouped" :ball-data="enriched" /><TabVelocity v-else :VelocityData="grouped" :ball-data="enriched" /></TabPanel>
    </TabPanels>
  </TabGroup>
</template>
<style scoped>
.live-stat-tabs{display:flex;flex-wrap:wrap;justify-content:center;gap:4px;padding:6px;margin:12px auto 24px;max-width:max-content;background:#0d1f3c;border-radius:12px}.live-stat-tabs button{padding:10px 16px;border-radius:8px;color:#c5d0e0;background:transparent;font-size:12px;text-transform:uppercase;min-height:44px}.live-stat-tabs button.active{color:white;background:#e52343}.live-stat-tabs button:focus-visible{outline:3px solid #8de6ff;outline-offset:2px}
</style>
