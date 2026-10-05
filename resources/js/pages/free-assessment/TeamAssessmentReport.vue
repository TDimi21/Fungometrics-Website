<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const route = useRoute(), { axiosGet } = useAxiosAuth()
const report = ref(null), error = ref(''), loading = ref(false)
let generation = 0
async function load() {
  const current = ++generation; report.value = null; error.value = ''; loading.value = true
  try { const { data } = await axiosGet(`free-assessments/${route.params.assessmentId}/team-report`); if (current === generation) report.value = data }
  catch (e) { if (current === generation) error.value = e?.response?.data?.message || 'Could not load the team report.' }
  finally { if (current === generation) loading.value = false }
}
watch(() => route.params.assessmentId, load, { immediate: true })
const reportLink = player => ({ path: '/assessment-reports', query: { kind: 'free_assessment', assessment: report.value.assessment.id, player } })
const printReport = () => window.print()
async function download() {
  try {
    const { data } = await axiosGet(`free-assessments/${route.params.assessmentId}/team-report`, { format: 'csv' })
    const url = URL.createObjectURL(new Blob(['\ufeff', data], { type: 'text/csv;charset=utf-8' }))
    const link = document.createElement('a'); link.href = url; link.download = `team-assessment-${report.value.assessment.assessment_date}.csv`; link.click(); URL.revokeObjectURL(url)
  } catch (e) { error.value = e?.response?.data?.message || 'Could not export the team report.' }
}
</script>
<template>
  <Layout><section class="team-report-shell"><div class="actions no-print"><RouterLink to="/free-assessment">← Free Assessment</RouterLink><button v-if="report" @click="download">Download Team CSV</button><button v-if="report" class="primary" @click="printReport">Print / Save PDF</button></div><p v-if="loading" role="status">Loading team report…</p><p v-if="error" role="alert">{{ error }} <button @click="load">Retry</button></p>
    <article v-if="report" class="team-report-print"><header><span>FMTRX · THE YARD</span><h1>Team Assessment Report</h1><h2>{{ report.assessment.team_name }} · {{ report.assessment.name }}</h2><p>{{ report.assessment.assessment_date }} · {{ report.assessment.location }}</p></header><div class="stats"><div><strong>{{ report.total_players }}</strong>Players enrolled</div><div><strong>{{ report.complete_players }}</strong>All stations complete</div><div><strong>{{ report.average_stations_completed }} / {{ report.total_stations }}</strong>Average stations completed per player</div></div>
      <h2>Team Averages & Best Results</h2><p class="explanation">Team average uses each tested player's best result, equally weighted. Attempt average uses each player's average across their attempts. Missing tests are excluded; recorded zero reps count. Protocols and grip hands are compared separately.</p><div class="scroll"><table><thead><tr><th>Station / Protocol</th><th>Tested / Enrolled</th><th>Team Average<br><small>Player bests</small></th><th>Attempt Average</th><th>Best Result</th><th>Top Performers</th></tr></thead><tbody><tr v-for="s in report.stations" :key="`${s.station}:${s.side}:${s.protocol}`"><th>{{ s.name }}<small v-if="s.protocol">{{ s.protocol }}</small><small>{{ s.lower_is_better ? 'Lower is better' : 'Higher is better' }}</small></th><td>{{ s.tested }} / {{ s.total_players }}</td><td>{{ s.average_best ?? '—' }} {{ s.unit }}</td><td>{{ s.average_attempts ?? '—' }} {{ s.unit }}</td><td><b>{{ s.best ?? '—' }} {{ s.unit }}</b></td><td><div v-for="p in s.leaders" :key="p.player_id" class="leader"><span>#{{ p.rank }}</span><RouterLink :to="reportLink(p.player_id)">{{ p.name }}</RouterLink><b>{{ p.best }} {{ s.unit }}</b></div><span v-if="!s.leaders.length">No results yet</span></td></tr></tbody></table></div><footer>Top performers show ranks 1–3, including ties. These are raw test comparisons within this assessment, not age-adjusted overall athlete rankings. Different units are not combined into an overall score.</footer></article>
  </section></Layout>
</template>
<style scoped>
.team-report-shell{max-width:1500px;margin:20px auto;background:#fff;color:#15283f;padding:26px;border-radius:14px}.actions{display:flex;align-items:center;gap:12px;margin-bottom:24px}.actions a{margin-right:auto;color:#1260ce}button{padding:11px 15px;border:1px solid #cbd8e7;border-radius:7px;min-height:44px}.primary{background:#0967f8;color:white}header{border-bottom:2px solid #153c69;padding-bottom:20px;margin-bottom:20px}header>span{font-weight:800;letter-spacing:.15em;color:#0967f8;font-size:12px}h1{font-size:30px;font-weight:850;margin:10px 0}h2{font-size:18px;font-weight:750}p{font-size:13px;color:#526981;margin:10px 0}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:20px 0 26px}.stats>div{padding:18px;border:1px solid #dce7f2;background:#f4f8fe;border-radius:8px;font-size:12px}.stats strong{display:block;font-size:28px;color:#125cc4}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:14px 10px;border-bottom:1px solid #e0e8f1;text-align:left;vertical-align:top}thead{background:#edf3fa}th small{display:block;color:#60768e;font-weight:400;font-size:10px;margin-top:4px}.leader{display:flex;gap:8px;align-items:center;margin-bottom:8px;min-width:200px}.leader>span{color:#16934b;font-weight:800}.leader a{color:#095bc9}.leader b{margin-left:auto;white-space:nowrap}footer{margin-top:24px;color:#536c84;font-size:11px;line-height:1.6}@media(max-width:700px){.team-report-shell{padding:14px}.actions{flex-wrap:wrap}.stats{grid-template-columns:1fr}.actions a{width:100%}}@media print{.no-print{display:none!important}.team-report-shell{padding:0;margin:0}.stats{grid-template-columns:repeat(3,1fr)}table{font-size:10px}th,td{padding:9px 6px}tr{break-inside:avoid}.leader{min-width:140px;flex-wrap:wrap}.scroll{overflow:visible}.team-report-print{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
</style>
<style>
@media print{body:has(.team-report-print){page:team-assessment}@page team-assessment{size:A4 landscape;margin:10mm}html:has(.team-report-print),body:has(.team-report-print),body:has(.team-report-print) #app{height:auto!important;overflow:visible!important;background:white!important}body:has(.team-report-print) *{visibility:hidden}body:has(.team-report-print) .team-report-print,body:has(.team-report-print) .team-report-print *{visibility:visible}body:has(.team-report-print) .team-report-print{position:absolute;left:0;top:0;width:100%;background:#fff}}
</style>
