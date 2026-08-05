<script setup>
import { ref } from 'vue'
import PercentileCategorySection from './PercentileCategorySection.vue'
import PercentileScaleLegend from './PercentileScaleLegend.vue'
defineProps({ groups: { type: Array, default: () => [] }, comparison: { type: Object, default: () => ({}) } })
const showInfo = ref(false)
</script>
<template>
  <section class="panel" data-testid="percentile-rankings">
    <header>
      <div><h2>Percentile Rankings</h2><p>Compared with the governed benchmark population.</p></div>
      <button type="button" aria-label="About percentile comparison" @click="showInfo = !showInfo">i</button>
    </header>
    <aside v-if="showInfo" class="info" role="note">
      <p><b>Population:</b> {{ comparison.bucket || 'Comparison population needs data' }}</p>
      <p><b>Age / level:</b> {{ comparison.context?.age_group || 'Needs Data' }} / {{ comparison.context?.level || 'Needs Data' }}</p>
      <p><b>Calculated:</b> {{ comparison.generatedAt || 'Needs Data' }}</p>
      <p><b>Confidence:</b> {{ comparison.confidence?.overall || 'Needs Data' }}</p>
      <p>Benchmark source is retained on each metric as research, FMTRX population, or composite evidence.</p>
    </aside>
    <div class="column-head"><span>Metric</span><span>Percentile</span><span>Value</span><span>Label</span><span>Goal</span><span>Gap</span><span>Trend</span></div>
    <PercentileScaleLegend />
    <div v-if="groups.length"><PercentileCategorySection v-for="group in groups" :key="group.key" :group="group" /></div>
    <p v-else class="empty">Benchmark Needs Data</p>
  </section>
</template>
<style scoped>
.panel{overflow:hidden;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.panel>header{display:flex;justify-content:space-between;align-items:flex-start;padding:12px 14px}.panel h2{text-transform:uppercase;font-size:13px;font-weight:900;letter-spacing:.07em}.panel header p{font-size:9px;color:#708797}.panel header button{width:19px;height:19px;border:1px solid #476174;border-radius:50%;color:#9eb1bf;font-size:10px}.info{margin:0 10px 10px;border:1px solid #1b566c;border-radius:7px;background:#071d2b;padding:9px;color:#9eb2c0;font-size:9px;line-height:1.5}.info b{color:#e4edf2}.column-head{display:grid;grid-template-columns:118px minmax(180px,1fr) 68px 92px 82px 55px 28px;gap:8px;padding:6px 8px;border-top:1px solid #203b4d;background:#0a1c2b;color:#657f91;font-size:8px;text-transform:uppercase}.empty{margin:12px;border:1px dashed #3b5364;border-radius:7px;padding:20px;text-align:center;color:#7f95a5;font-size:11px}
@media(max-width:760px){.panel{overflow-x:hidden}.column-head{grid-template-columns:96px minmax(110px,1fr) 58px 78px;gap:6px}.column-head span:nth-child(n+5){display:none}}
</style>
