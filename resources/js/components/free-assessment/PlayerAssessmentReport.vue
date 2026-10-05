<script setup>
import { computed, ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
import { resultSummary } from '@/pages/free-assessment/workflow'
const props = defineProps({ assessmentId: { type: String, required: true }, playerId: { type: String, required: true } })
const { axiosGet } = useAxiosAuth()
const report = ref(null), error = ref(''), loading = ref(false)
let generation = 0
async function load() {
  const current = ++generation
  report.value = null; error.value = ''; loading.value = true
  try {
    const { data } = await axiosGet(`free-assessment-reports/${props.assessmentId}/players/${props.playerId}`)
    if (current === generation) report.value = data.data
  } catch (e) { if (current === generation) error.value = e?.response?.data?.message || 'Could not load this assessment report.' }
  finally { if (current === generation) loading.value = false }
}
watch(() => [props.assessmentId, props.playerId], load, { immediate: true })
const groups = [
  ['Hitting', ['exit_velocity']], ['Pitching', ['pitching_velocity']], ['Speed', ['sprint_10yd']],
  ['Agility', ['shuttle_5_10_5']], ['Explosiveness', ['broad_jump']], ['Strength', ['grip_strength', 'pull_strength', 'pushups', 'pull_ups']],
]
const result = key => report.value?.results.find(r => r.station === key)
const summary = r => resultSummary(r, report.value.stations)
const comparisons = computed(() => (report.value?.results || []).filter(r => r.peer_percentile !== null))
const strengths = computed(() => [...comparisons.value].filter(r => r.peer_percentile >= 75).sort((a,b) => b.peer_percentile-a.peer_percentile).slice(0,3))
const opportunities = computed(() => [...comparisons.value].filter(r => r.peer_percentile < 50).sort((a,b) => a.peer_percentile-b.peer_percentile).slice(0,3))
const average = r => !r ? '—' : r.station === 'grip_strength' ? `L ${r.summary.left.average} / R ${r.summary.right.average}` : r.summary.average
const date = value => new Date(`${value}T12:00:00`).toLocaleDateString()
const printReport = () => window.print()
</script>
<template>
  <section class="free-player-report">
    <p v-if="loading" role="status">Loading Free Assessment report…</p>
    <div v-else-if="error" role="alert">{{ error }} <button @click="load">Retry</button></div>
    <article v-else-if="report" class="free-report-print">
      <header><div><span class="eyebrow">FMTRX · Free Assessment</span><h1>{{ report.player.name }}</h1><p>{{ report.assessment.name }} · {{ report.assessment.location }} · {{ date(report.assessment.assessment_date) }}</p></div><button class="no-print" @click="printReport">Print / Save PDF</button></header>
      <div class="identity"><span>Age <b>{{ report.player.age ?? '—' }}</b></span><span>Graduation <b>{{ report.player.grad_year ?? '—' }}</b></span><span>Height <b>{{ report.player.height || '—' }}</b></span><span>Weight <b>{{ report.player.weight ?? '—' }} lbs</b></span><span>Position <b>{{ report.player.position || '—' }}</b></span><span>Bats / Throws <b>{{ report.player.bats || '—' }} / {{ report.player.throws || '—' }}</b></span></div>
      <p class="completion">{{ report.completed_stations }} of {{ report.total_stations }} stations recorded · {{ report.assessment.status === 'completed' ? 'Completed assessment' : 'Assessment in progress — results update as tests are saved' }}</p>
      <div class="insights"><section><h2>Top Strengths</h2><p v-for="r in strengths" :key="r.station">{{ report.stations[r.station].name }} · {{ r.peer_percentile }}th age/peer percentile <small>({{ r.peer_confidence }} confidence)</small></p><p v-if="!strengths.length">No recorded test currently meets the 75th-percentile threshold with an available age/peer benchmark.</p></section><section><h2>Development Opportunities</h2><p v-for="r in opportunities" :key="r.station">{{ report.stations[r.station].name }} · {{ r.peer_percentile }}th age/peer percentile <small>({{ r.peer_confidence }} confidence)</small></p><p v-if="!opportunities.length">No recorded test currently falls below the 50th-percentile threshold with an available age/peer benchmark.</p></section></div>
      <section v-for="[name, keys] in groups" :key="name" class="category"><h2>{{ name }}</h2><div class="tests"><article v-for="key in keys" :key="key" class="test"><h3>{{ report.stations[key].name }}</h3><template v-if="result(key)"><strong class="measurement">{{ summary(result(key)) }}</strong><p v-if="report.stations[key].count > 1">Average: {{ average(result(key)) }} {{ report.stations[key].unit }}</p><p v-if="key === 'grip_strength'">Left/right difference: {{ result(key).summary.difference_percent }}%</p><dl><div><dt>Assessment rank</dt><dd>#{{ result(key).rank }} of {{ result(key).participants }}</dd></div><div><dt>Age/peer percentile</dt><dd>{{ result(key).peer_percentile ?? 'Unavailable' }} <small v-if="result(key).peer_percentile !== null">· {{ result(key).peer_confidence }} confidence</small></dd></div><div><dt>Yard/FMTRX percentile</dt><dd>{{ result(key).population_percentile ?? 'Unavailable' }} <small>· {{ result(key).confidence }} confidence</small></dd></div></dl><p v-if="result(key).benchmark_note" class="note">{{ result(key).benchmark_note }}</p><p v-if="result(key).protocol">Protocol: {{ result(key).protocol }}</p><details><summary>Recorded attempts</summary><p>{{ result(key).values.join(' · ') }} {{ report.stations[key].unit }}</p><p v-if="key === 'grip_strength'">First three: left hand. Last three: right hand.</p></details><p v-if="result(key).notes" class="notes">{{ result(key).notes }}</p></template><p v-else class="missing">Not tested</p></article></div></section>
      <footer>Rankings compare saved tests within this assessment. Timed tests rank lower times first. Grip rank uses the left hand; pitching and pull-strength ranks compare matching protocols. Unavailable percentiles indicate missing benchmarks or insufficient valid population data. No overall score is assigned.</footer>
    </article>
  </section>
</template>
<style scoped>
.free-player-report{color:#e9f1fa;background:#07111d;border:1px solid #23384b;border-radius:14px;padding:24px;min-width:0}.free-player-report header{display:flex;justify-content:space-between;gap:20px;align-items:center;border-bottom:1px solid #23384b;padding-bottom:20px}.eyebrow{color:#38bdf8;text-transform:uppercase;font-weight:800;letter-spacing:.08em;font-size:12px}h1{font-size:32px;font-weight:800;margin:8px 0}h2{font-size:18px;font-weight:750;color:#38bdf8;margin-bottom:12px}h3{font-weight:700;font-size:16px}p{font-size:13px;line-height:1.6;color:#afc1d5;margin:8px 0}button{color:#38bdf8;border:1px solid #2679a5;border-radius:8px;padding:12px;min-height:44px}.identity{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:22px 0}.identity span{font-size:12px;color:#afc1d5}.identity b{display:block;color:white;font-size:15px}.completion{padding:12px;background:#10283c;border-radius:8px}.insights{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0}.insights section,.test{border:1px solid #23384b;border-radius:10px;padding:18px}.category{margin-top:24px}.tests{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.measurement{display:block;font-size:25px;color:#fff;margin:12px 0}dl{margin:12px 0}dl div{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #23384b;padding:8px 0;font-size:12px}dd{text-align:right}dd small{display:block;color:#afc1d5}.note,.missing{color:#94a3b8}details{font-size:12px;margin:12px 0}summary{cursor:pointer;color:#38bdf8}.notes{white-space:pre-wrap}footer{font-size:11px;line-height:1.6;color:#94a3b8;margin-top:24px;border-top:1px solid #23384b;padding-top:18px}@media(max-width:700px){.free-player-report{padding:16px}.identity{grid-template-columns:repeat(2,1fr)}.tests,.insights{grid-template-columns:1fr}header{flex-wrap:wrap}h1{font-size:25px}}@media print{.free-player-report{border:0;padding:0;background:white;color:#111}.free-report-print{background:white;color:#111}.no-print{display:none!important}h1,h2,h3,.measurement,.identity b,dd{color:#111!important}p,.identity span,footer{color:#333!important}.test,.insights section{break-inside:avoid}.completion{background:#eee}details{display:none}.category{break-inside:auto}}
</style>
<style>
@media print {
  html:has(.free-report-print), body:has(.free-report-print), body:has(.free-report-print) #app {height:auto!important;overflow:visible!important;background:white!important}
  body:has(.free-report-print) * {visibility:hidden}
  body:has(.free-report-print) .free-report-print, body:has(.free-report-print) .free-report-print * {visibility:visible}
  body:has(.free-report-print) .free-report-print {position:absolute;left:0;top:0;width:100%;padding:12px;box-sizing:border-box}
  body:has(.free-report-print) .assessment-page {height:auto!important;overflow:visible!important}
}
</style>
