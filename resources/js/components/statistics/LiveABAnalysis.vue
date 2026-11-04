<script setup>
import { computed, ref, watch } from 'vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import StatsStrikeZonePlot from './StatsStrikeZonePlot.vue'
import { liveabAnalysisBalls, analysisValue, resultColor, pitchColors, trajectoryColors } from '@/utils/liveabAnalysis'
import stadium from '@/assets/img/fungometrics-stadium.webp'
const props = defineProps({ balls: {type: [Array, Object], default: () => []}, side: {type: String, default: 'pitching'}, mode: {type: String, default: 'pitch'} })
const selected = ref(null), filter = ref('ALL'), view = ref('Balls')
watch(() => [props.side, props.mode], () => { selected.value = null; filter.value = 'ALL'; view.value = 'Balls' })
const all = computed(() => liveabAnalysisBalls(props.balls, props.side))
const field = computed(() => props.side === 'hitting' && props.mode !== 'pitch')
const pitches = ['FB', 'CH', 'CV', 'SL', 'OTHER']
const trajectories = ['GB', 'FB', 'LD', 'SM', 'FOUL', 'TK']
const labels = {ALL:'All', FB:'Fastball', CH:'Change-up', CV:'Curveball', SL:'Slider', OTHER:'Other', GB:'Ground ball', LD:'Line drive', PF:'Pop fly', SM:'Swing & miss', FOUL:'Foul', TK:'Take', LEFT:'Left', MIDDLE:'Middle', RIGHT:'Right'}
const options = computed(() => props.mode === 'trajectory' ? ['ALL','LEFT','MIDDLE','RIGHT'] : props.mode === 'contact' || field.value ? ['ALL', ...trajectories] : ['ALL', ...pitches])
const label = key => key === 'FB' && (field.value || props.mode === 'contact') ? 'Fly ball' : labels[key] || key
const base = computed(() => all.value.filter(b => {
  if (filter.value === 'ALL') return true
  if (props.mode === 'trajectory') return ({LEFT:'L',MIDDLE:'C',RIGHT:'R'})[filter.value] === b.direction.charAt(0)
  return (props.mode === 'contact' || field.value ? b.trajectory : b.pitch) === filter.value
}))
const players = computed(() => [...new Map(all.value.map(b => [b.playerId, b.playerName])).entries()])
const shown = computed(() => base.value.filter(b => selected.value === null || b.playerId === selected.value))
const rows = computed(() => [{id:null, name:'Team', balls:base.value}, ...players.value.map(([id,name]) => ({id,name,balls:base.value.filter(b=>b.playerId===id)}))])
const columns = computed(() => props.mode === 'velocity' && props.side === 'hitting' ? ['MAX','LD','FB','GB'] : [...(props.side === 'pitching' || props.mode === 'pitch' ? pitches : props.mode === 'trajectory' ? ['FB','LD','GB','SM','FOUL'] : trajectories), props.mode === 'velocity' ? 'MAX' : 'TOTAL'])
const color = key => (props.side === 'pitching' || props.mode === 'pitch' ? pitchColors : trajectoryColors)[key]
const located = computed(() => shown.value.filter(b => field.value ? Number(b.field_mark) > 0 && Number(b.field_mark) <= 6400 : Number(b.pitch_mark) > 0 && Number(b.pitch_mark) <= 3600))
const strike = computed(() => shown.value.length ? `${Math.round(shown.value.filter(b=>b.strike).length / shown.value.length * 100)}%` : '—')
</script>
<template>
  <section class="live-analysis" :style="{'--stadium': `url(${stadium})`}">
    <div class="analysis-filters" aria-label="Chart filters">
      <button v-for="key in options" :key="key" :aria-pressed="filter === key" :class="{active:filter === key}" @click="filter = key">{{ label(key) }}</button>
    </div>
    <p class="selection" aria-live="polite">Showing: {{ selected === null ? 'Team — all players' : players.find(p=>p[0]===selected)?.[1] }} · {{ shown.length }} pitches <span v-if="!field">· Strike {{ strike }}</span></p>
    <div class="analysis-grid">
      <div class="plot-card">
        <div v-if="!field" class="legend"><span v-for="[name,c] in [['In Play','#2ECC71'],['Foul','#F1C40F'],['Swing & Miss','#E74C3C'],['Take','#3498DB']]" :key="name"><i :style="{background:c}"/>{{ name }}</span></div>
        <VelocitySprayField v-if="field" :balls="located" :mode="view === 'Heatmap' ? 'heatmap' : 'spray'" :show-chrome="false" :trajectory-lines="false" :color-by="mode === 'velocity' ? 'velocity' : 'trajectory'" :watermark="true" />
        <StatsStrikeZonePlot v-else :balls="located" :color-of="resultColor" :heatmap-metric="mode === 'velocity' ? 'velocity' : 'count'" :display-mode="mode === 'velocity' ? (view === 'Heatmap' ? 'heatmap' : 'velocity') : (view === 'Heatmap' ? 'heatmap' : 'balls')" />
        <p v-if="!located.length" class="empty">No recorded locations for this selection.</p>
        <div v-if="!field || mode === 'velocity'" class="view-buttons"><button v-for="choice in ['Balls','Heatmap']" :key="choice" :aria-pressed="view===choice" :class="{active:view===choice}" @click="view=choice">{{ choice === 'Balls' && mode === 'velocity' ? 'Velo Grid' : choice }}</button></div>
      </div>
      <div class="table-card">
        <table><thead><tr><th>Player</th><th v-for="key in columns" :key="key">{{ key === 'CV' ? 'CV' : key }}{{ mode === 'pitch' && side === 'pitching' && key !== 'TOTAL' ? ' %' : '' }}</th></tr></thead>
          <tbody><tr v-for="row in rows" :key="row.id ?? 'team'" :class="{selected:selected===row.id}"><th><button :aria-pressed="selected===row.id" @click="selected=row.id">{{ row.name }}<small v-if="selected===row.id">Selected</small></button></th><td v-for="key in columns" :key="key" :style="{background:color(key),color:color(key)==='#E6D08A'?'#0B1020':'white'}">{{ analysisValue(row.balls,key,mode,side) }}</td></tr></tbody>
        </table>
        <p v-if="!all.length" class="empty">No pitches recorded yet.</p>
        <p v-if="mode==='velocity'" class="empty">Average velocity by type · maximum in MPH</p>
      </div>
    </div>
  </section>
</template>
<style scoped>
.live-analysis{padding:16px;border:1px solid #ffffff26;border-radius:16px;background:linear-gradient(#0b1020df,#0b1020ed),var(--stadium) center/cover;color:white;margin-top:16px}.analysis-filters,.view-buttons{display:flex;flex-wrap:wrap;gap:8px}.live-analysis button{min-height:44px;border:1px solid #ffffff26;border-radius:8px;background:#2e3039;padding:10px 16px;color:white;font-weight:700}.live-analysis button.active{background:#ff2b4a;border-color:#ff2b4a}.selection{color:#bdc7dc;font-size:14px;margin:14px 0}.analysis-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:16px}.plot-card,.table-card{min-width:0;background:#101626d9;border:1px solid #ffffff26;border-radius:12px;padding:12px}.table-card{overflow-x:auto;align-self:start}.plot-card{display:flex;flex-direction:column;justify-content:center;gap:12px}.legend{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;font-size:12px;color:#a8adbe}.legend span{display:flex;align-items:center;gap:5px}.legend i{height:10px;width:10px;border-radius:50%}.view-buttons{justify-content:center}table{width:100%;border-collapse:collapse;font-size:14px;text-align:center}th,td{padding:12px 8px;min-width:62px}thead{background:#1a1f35}thead th:first-child{color:#ff2b4a}tbody th{min-width:150px;background:#1a1f35}.selected{outline:2px solid #ff2b4a;outline-offset:-2px}.table-card button{background:transparent;border:0;padding:4px;width:100%;font-size:14px}.table-card small{display:block;color:#ff8297;margin-top:4px}.empty{font-size:12px;color:#a8adbe;text-align:center;margin-top:12px}@media(max-width:1000px){.analysis-grid{grid-template-columns:1fr}.plot-card{max-height:none}.live-analysis{padding:10px}.analysis-filters button{flex:1 0 110px}}
</style>
