<script setup>
import { computed, ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import StatsStrikeZonePlot from './StatsStrikeZonePlot.vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import { liveabAnalysisBalls, resultColor } from '@/utils/liveabAnalysis'
import { liveabBoxScore } from '@/utils/liveabBoxScore'
const props = defineProps({ playerId: [String, Number], side: String, rows: Array, players: Array })
const emit = defineEmits(['close'])
const pitch = ref('ALL'), opponent = ref('ALL'), heatmap = ref(false)
const isPitcher = computed(() => props.side === 'pitching')
const displayName = id => props.players.find(p => String(p.id) === String(id))?.name?.full || 'Player'
const player = computed(() => props.players.find(p => String(p.id) === String(props.playerId)))
const actorId = row => isPitcher.value ? row.pitching?.pitcher_id : row.batting?.batter_id
const opponentId = row => isPitcher.value ? row.batting?.batter_id : row.pitching?.pitcher_id
const playerRows = computed(() => props.rows.filter(row => String(actorId(row)) === String(props.playerId)))
const opponents = computed(() => [...new Set(playerRows.value.map(opponentId).filter(id => id != null))])
const shownRows = computed(() => playerRows.value.filter(row => opponent.value === 'ALL' || String(opponentId(row)) === opponent.value))
const balls = computed(() => liveabAnalysisBalls(shownRows.value, props.side))
const shown = computed(() => balls.value.filter(b => pitch.value === 'ALL' || b.pitch === pitch.value))
const located = computed(() => shown.value.filter(b => Number(b.pitch_mark) > 0 && Number(b.pitch_mark) <= 3600))
const field = computed(() => shown.value.filter(b => ['GB','FB','LD','PF'].includes(b.trajectory) && Number(b.field_mark) > 0 && Number(b.field_mark) <= 6400))
const usage = computed(() => ['FB','CH','CV','SL','OTHER'].map(type => ({ type, count: balls.value.filter(b => b.pitch === type).length })).filter(p => p.count))
const metrics = computed(() => {
  const box = liveabBoxScore(playerRows.value)
  const p = (isPitcher.value ? box.pitchers : box.hitters).find(p => String(p.id) === String(props.playerId))
  if (!p) return []
  const all = liveabAnalysisBalls(playerRows.value, props.side)
  const swings = all.filter(b => ['GB','FB','LD','PF','FOUL','SM'].includes(b.trajectory))
  const whiff = swings.length ? `${Math.round(swings.filter(b=>b.trajectory==='SM').length / swings.length * 100)}%` : '—'
  return isPitcher.value
    ? [['Pitches',p.pitches],['K',p.SO],['BB',p.BB],['Strike %',`${Math.round(all.filter(b=>b.strike).length/all.length*100)}%`],['Avg MPH',p.avgVelo],['Whiff %',whiff]]
    : [['PA',p.PA],['AVG',Number(p.AVG).toFixed(3)],['OPS',Number(p.OPS).toFixed(3)],['HR',p.homeRun],['BB',p.BB],['Whiff %',whiff]]
})
</script>
<template>
  <Dialog :open="true" @close="emit('close')" class="analytics-dialog">
    <div class="backdrop" aria-hidden="true" />
    <div class="dialog-position">
      <DialogPanel class="analytics-panel">
        <header><button @click="emit('close')">‹ Back to Game</button><DialogTitle>{{ isPitcher ? 'Pitcher' : 'Hitter' }} Analytics</DialogTitle></header>
        <div class="player-summary"><div><h3>{{ displayName(playerId) }}</h3><p>{{ isPitcher ? 'Throws' : 'Bats' }}: {{ (isPitcher ? player?.throw_side : player?.hit_side) || '—' }} · Current session</p></div><dl><div v-for="[label,value] in metrics" :key="label"><dd>{{ value ?? '—' }}</dd><dt>{{ label }}</dt></div></dl></div>
        <p v-if="!playerRows.length" class="empty">No pitches recorded for this player in this session yet.</p>
        <template v-else>
          <div class="filters"><label>{{ isPitcher ? 'Hitter' : 'Pitcher' }}<select v-model="opponent"><option value="ALL">All {{ isPitcher ? 'hitters' : 'pitchers' }}</option><option v-for="id in opponents" :key="id" :value="String(id)">{{ displayName(id) }}</option></select></label><div class="pitch-filters"><button v-for="type in ['ALL','FB','CH','CV','SL','OTHER']" :key="type" :aria-pressed="pitch===type" :class="{active:pitch===type}" @click="pitch=type">{{ type === 'ALL' ? 'All pitches' : type }}</button></div></div>
          <div class="charts"><section><h3>Pitch map · {{ shown.length }} pitches</h3><div class="legend"><span v-for="[label,color] in [['In play','#2ECC71'],['Foul','#F1C40F'],['Swing & miss','#E74C3C'],['Take','#3498DB']]" :key="label"><i :style="{background:color}"/>{{ label }}</span></div><StatsStrikeZonePlot :balls="located" :color-of="resultColor" :display-mode="heatmap ? 'heatmap' : 'balls'"/><p v-if="!located.length" class="empty">No pitch locations recorded for this selection.</p><button :aria-pressed="heatmap" @click="heatmap=!heatmap">{{ heatmap ? 'Show pitch locations' : 'Show location heatmap' }}</button></section><section><h3>Pitch usage</h3><ul><li v-for="item in usage" :key="item.type"><b>{{ item.type }}</b><span>{{ item.count }} ({{ Math.round(item.count / balls.length * 100) }}%)</span></li></ul><h3>Balls in play</h3><VelocitySprayField :balls="field" mode="spray" color-by="trajectory" :show-chrome="false" :trajectory-lines="false" :watermark="true"/><p v-if="!field.length" class="empty">No balls in play with recorded field locations.</p></section></div>
          <details><summary>Pitch list · {{ shown.length }}</summary><div class="table-scroll"><table><thead><tr><th>#</th><th>Pitch</th><th>Result</th><th>{{ isPitcher ? 'Pitch MPH' : 'Exit MPH' }}</th></tr></thead><tbody><tr v-for="(ball,i) in shown" :key="i"><td>{{ i+1 }}</td><td>{{ ball.pitch }}</td><td>{{ ball.trajectory }}</td><td>{{ ball.velocity > 0 ? ball.velocity : '—' }}</td></tr></tbody></table></div></details>
        </template>
      </DialogPanel>
    </div>
  </Dialog>
</template>
<style scoped>
.analytics-dialog{position:relative;z-index:100}.backdrop{position:fixed;inset:0;background:#020617bb}.dialog-position{position:fixed;inset:0;overflow-y:auto;display:flex;align-items:flex-start;justify-content:center;padding:24px}.analytics-panel{width:100%;max-width:1200px;background:#0e1228;color:#eef1ff;border:1px solid #ffffff33;border-radius:24px;padding:20px;box-shadow:0 24px 80px #0008}.analytics-panel header{display:flex;align-items:center;gap:24px;border-bottom:1px solid #ffffff26;padding-bottom:16px}.analytics-panel h2{font-size:20px;font-weight:800}.analytics-panel h3{font-size:18px;font-weight:700}.analytics-panel button{min-height:44px;padding:10px 16px;border:1px solid #ffffff33;background:#20253e;border-radius:10px;color:white;font-weight:700}.analytics-panel button.active{background:#41bde9;color:#071526}.analytics-panel button:focus-visible,select:focus-visible,summary:focus-visible{outline:3px solid #41bde9}.player-summary{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:20px 0}.player-summary p,.empty,dt{color:#abb4cf;font-size:14px}.player-summary dl{display:flex;gap:24px;flex-wrap:wrap;text-align:center}dd{font-size:22px;font-weight:800}.filters{display:grid;gap:12px}.filters select{display:block;width:100%;padding:10px;background:#20253e;color:white;border:1px solid #ffffff33;border-radius:8px;min-height:44px}.pitch-filters{display:flex;gap:8px;flex-wrap:wrap}.charts{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;margin:20px 0}.charts section{min-width:0;border:1px solid #ffffff26;border-radius:16px;padding:16px;background:#151a30}.legend{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;margin:12px 0}.legend i{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:4px}ul{margin:12px 0 24px}li{display:flex;justify-content:space-between;padding:6px 0}.empty{padding:16px 0}summary{cursor:pointer;padding:14px;border:1px solid #ffffff33;border-radius:10px}.table-scroll{overflow:auto}table{width:100%;text-align:center}th,td{padding:12px;border-bottom:1px solid #ffffff22}@media(max-width:700px){.dialog-position{padding:10px}.analytics-panel{padding:14px}.charts{grid-template-columns:1fr}.player-summary{align-items:flex-start;flex-direction:column}.player-summary dl{gap:16px}.analytics-panel header{gap:12px}}
</style>
