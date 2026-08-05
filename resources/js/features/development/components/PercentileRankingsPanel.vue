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
.panel{overflow:hidden;background:#071725;border:1px solid #254154;border-radius:10px;color:#edf5fa}.panel>header{display:flex;justify-content:space-between;align-items:flex-start;padding:15px 16px}.panel h2{text-transform:uppercase;font-size:16px;font-weight:900;letter-spacing:.07em}.panel header p{margin-top:3px;font-size:11px;color:#8398a7}.panel header button{width:23px;height:23px;border:1px solid #476174;border-radius:50%;color:#afc0ca;font-size:12px}.info{margin:0 12px 12px;border:1px solid #1b566c;border-radius:7px;background:#071d2b;padding:11px;color:#aabcc7;font-size:11px;line-height:1.55}.info b{color:#e4edf2}.column-head{display:grid;grid-template-columns:132px minmax(190px,1fr) 76px 110px 94px 62px 34px;gap:9px;padding:8px 10px;border-top:1px solid #203b4d;background:#0a1c2b;color:#8298a8;font-size:9px;font-weight:800;text-transform:uppercase}.empty{margin:12px;border:1px dashed #3b5364;border-radius:7px;padding:20px;text-align:center;color:#8fa3b1;font-size:12px}
@media(max-width:760px){.panel{overflow-x:hidden}.column-head{grid-template-columns:108px minmax(120px,1fr) 64px 92px;gap:7px}.column-head span:nth-child(n+5){display:none}}
</style>
