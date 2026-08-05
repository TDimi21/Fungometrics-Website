<script setup>
import { computed } from 'vue'
const props = defineProps({ metric: { type: Object, required: true } })
const position = computed(() => props.metric.percentile === null || props.metric.percentile === undefined ? 50 : Math.max(0, Math.min(100, Number(props.metric.percentile))))
const ordinal = (value) => {
  const number = Math.round(Number(value)); const mod100 = number % 100
  if (mod100 >= 11 && mod100 <= 13) return `${number}th`
  return `${number}${number % 10 === 1 ? 'st' : number % 10 === 2 ? 'nd' : number % 10 === 3 ? 'rd' : 'th'}`
}
const aria = computed(() => {
  const percentile = props.metric.available ? `${ordinal(props.metric.percentile)} percentile` : 'benchmark needs data'
  const current = props.metric.display_value ? `current value ${props.metric.display_value}` : 'current value unavailable'
  const goal = props.metric.goal_display ? `goal ${props.metric.goal_display}` : 'goal not established'
  return `${props.metric.label}, ${percentile}, ${props.metric.status_label}, ${current}, ${goal}.`
})
const detailTitle = computed(() => [
  `Source: ${props.metric.source || 'Needs Data'}`,
  `Confidence: ${props.metric.confidence || 'Needs Data'}`,
  `Calculated: ${props.metric.calculated_at || 'Needs Data'}`,
].join(' · '))
const trend = computed(() => ['up','improving'].includes(props.metric.trend) ? '↑' : ['down','declining'].includes(props.metric.trend) ? '↓' : ['flat','stable'].includes(props.metric.trend) ? '→' : '—')
</script>
<template>
  <div class="metric-row" :aria-label="aria" :title="detailTitle" :data-testid="`percentile-${metric.key}`">
    <strong class="metric-name">{{ metric.label }}</strong>
    <div class="percentile-cell">
      <div class="track" :class="{ dashed: !metric.available }">
        <span v-if="metric.available" class="fill" :style="{ width: `${position}%` }" />
        <b class="marker" :class="{ neutral: !metric.available }" :style="{ left: `${position}%` }">{{ metric.available ? Math.round(metric.percentile) : '—' }}</b>
      </div>
    </div>
    <span class="value">{{ metric.display_value || '—' }}</span>
    <span class="status" :class="metric.status">{{ metric.status_label }}</span>
    <span class="goal">{{ metric.goal_display || 'Not established' }}</span>
    <span class="gap">{{ metric.gap_display || '—' }}</span>
    <span class="trend" :aria-label="`Trend ${metric.trend || 'needs data'}`">{{ trend }}</span>
  </div>
</template>
<style scoped>
.metric-row{display:grid;grid-template-columns:118px minmax(180px,1fr) 68px 92px 82px 55px 28px;align-items:center;gap:8px;min-height:35px;padding:5px 8px;border-top:1px solid #183344;color:#dce8ef;font-size:9px}.metric-name{font-size:10px}.percentile-cell{padding:0 9px}.track{position:relative;height:7px;border-radius:8px;background:linear-gradient(90deg,#164965 0 24%,#385363 24% 49%,#6b6251 49% 74%,#a34d32 74% 89%,#bd1f2d 89%);box-shadow:inset 0 0 0 1px #ffffff18}.track.dashed{background:repeating-linear-gradient(90deg,#425766 0 8px,transparent 8px 13px)}.fill{display:block;height:100%;border-radius:8px;background:#ef334088}.marker{position:absolute;top:50%;transform:translate(-50%,-50%);width:23px;height:23px;border:2px solid #f24a55;border-radius:50%;display:grid;place-items:center;background:#b71927;color:#fff;font-size:8px}.marker.neutral{border-color:#637987;background:#253946}.status{font-weight:800;text-transform:uppercase;color:#f26a38}.status.needs_data{color:#8196a5}.goal,.gap,.value{color:#b6c5cf}.trend{font-size:13px;color:#5ed3a2;text-align:center}
@media(max-width:760px){.metric-row{grid-template-columns:96px minmax(110px,1fr) 58px 78px;gap:6px}.goal,.gap,.trend{display:none}.metric-name{font-size:9px}.status{font-size:8px}}
</style>
