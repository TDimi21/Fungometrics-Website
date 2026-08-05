<script setup>
import RapsodoMovementChart from './RapsodoMovementChart.vue'
import RapsodoPitchTypeSummary from './RapsodoPitchTypeSummary.vue'

const props = defineProps({ report: { type: Object, required: true } })
const num = (value, digits = 1) => value == null ? '—' : Number(value).toFixed(digits)
const whole = value => value == null ? '—' : Math.round(Number(value)).toLocaleString()
const date = value => value ? new Date(`${value}T12:00:00`).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—'
const pitchColor = { FB:'#31c9ff','2FB':'#16d39a',CV:'#b985ff',SL:'#ff5b7d',KN:'#ffd166' }
const color = type => pitchColor[type] || '#d5dde9'
const maxVelocity = Math.max(1, ...props.report.pitch_types.map(row => Number(row.maximum_velocity) || 0))
const velocityPosition = value => `${Math.max(0, Math.min(100, (Number(value || 0) / maxVelocity) * 100))}%`
const print = () => window.print()
</script>

<template>
  <article class="rapsodo-report">
    <header class="hero report-section">
      <div class="brand"><b>FM<span>TRX</span></b><small>DATA. DRILLS. RESULTS.</small></div>
      <div class="title"><span>Player development</span><h1>Rapsodo Pitching Session Report</h1><p>Read-only analysis from a completed, approved Data Hub import.</p></div>
      <button type="button" class="print-button" @click="print">Print / Save PDF</button>
    </header>

    <section class="identity report-section">
      <div><span>Mapped FMTRX player</span><strong>{{ report.player.name }}</strong><small>{{ report.player.throws ? `${report.player.throws}-handed pitcher` : 'Throwing side unavailable' }} · {{ report.player.team || 'Team unavailable' }}</small></div>
      <div><span>Total pitches</span><strong>{{ report.session.total_pitches }}</strong><small>{{ report.summary.pitch_type_count }} pitch types</small></div>
      <div><span>Session date</span><strong>{{ date(report.session.date) }}</strong><small>{{ report.session.start_time || '—' }}–{{ report.session.end_time || '—' }} · {{ num(report.session.duration_minutes) }} min</small></div>
      <div><span>Source</span><strong>Rapsodo</strong><small>Approved Import Batch</small></div>
    </section>

    <section class="kpis report-section" aria-label="Session summary">
      <div><span>Average velocity</span><strong>{{ num(report.summary.average_velocity) }}</strong><small>mph</small></div>
      <div><span>Maximum velocity</span><strong>{{ num(report.summary.maximum_velocity) }}</strong><small>mph</small></div>
      <div><span>Rapsodo source strikes</span><strong>{{ num(report.summary.strike_percentage) }}</strong><small>percent</small></div>
      <div><span>Average total spin</span><strong>{{ whole(report.summary.average_spin_rate) }}</strong><small>rpm</small></div>
    </section>

    <RapsodoPitchTypeSummary :rows="report.pitch_types" />

    <div class="two-column report-section">
      <RapsodoMovementChart :points="report.movement_points" :pitch-types="report.pitch_types" />
      <section class="panel usage" aria-labelledby="usage-title"><div class="panel-heading"><span>Pitch usage</span><h2 id="usage-title">What the athlete threw</h2></div><div class="usage-list"><div v-for="row in report.pitch_types" :key="`usage-${row.pitch_type}`"><header><b>{{ row.pitch_type }} · {{ row.display_name }}</b><span>{{ row.count }} · {{ num(row.usage_percentage) }}%</span></header><div class="track"><i :style="{width:`${row.usage_percentage}%`,background:color(row.pitch_type)}"></i></div></div></div></section>
    </div>

    <section class="panel report-section" aria-labelledby="velocity-title">
      <div class="panel-heading"><span>Velocity and release consistency</span><h2 id="velocity-title">Descriptive pitch ranges</h2><p>Velocity ranges do not imply that higher is automatically better. Release measurements are descriptive.</p></div>
      <div class="velocity-grid"><article v-for="row in report.pitch_types" :key="`velocity-${row.pitch_type}`"><header><b>{{ row.pitch_type }}</b><span>{{ num(row.minimum_velocity) }}–{{ num(row.maximum_velocity) }} mph</span></header><div class="velocity-track"><i :style="{left:velocityPosition(row.minimum_velocity),width:`calc(${velocityPosition(row.maximum_velocity)} - ${velocityPosition(row.minimum_velocity)})`,background:color(row.pitch_type)}"></i><em :style="{left:velocityPosition(row.average_velocity)}"></em></div><footer><span>Avg <b>{{ num(row.average_velocity) }} mph</b></span><span>Release H <b>{{ num(row.average_release_height,2) }} ft</b></span><span>Release Side <b>{{ num(row.average_release_side,2) }} ft</b></span></footer></article></div>
    </section>

    <section class="panel report-section" aria-labelledby="spin-title">
      <div class="panel-heading"><span>Spin efficiency and strike percentage</span><h2 id="spin-title">Separate source measurements</h2><p>Spin Efficiency and Rapsodo source strike percentage are not combined into a score.</p></div>
      <div class="comparison"><article v-for="row in report.pitch_types" :key="`compare-${row.pitch_type}`"><header><b>{{ row.pitch_type }} · {{ row.display_name }}</b><span>Tilt {{ row.average_tilt || '—' }}</span></header><div class="measure"><label>Average spin efficiency <b>{{ num(row.average_spin_efficiency) }}%</b></label><div><i :style="{width:`${row.average_spin_efficiency || 0}%`,background:color(row.pitch_type)}"></i></div></div><div class="measure strike"><label>Rapsodo source strike percentage <b>{{ num(row.strike_percentage) }}%</b></label><div><i :style="{width:`${row.strike_percentage || 0}%`}"></i></div></div></article></div>
    </section>

    <section class="insights report-section"><div class="panel-heading"><span>Coach review</span><h2>Session observations</h2><p>Deterministic, descriptive comparisons from this session only.</p></div><div class="insight-grid"><article v-for="(insight,index) in report.insights" :key="insight.title"><b>{{ index+1 }}</b><div><h3>{{ insight.title }}</h3><p>{{ insight.body }}</p></div></article></div></section>

    <section class="notes report-section"><div><span>Session notes</span><h2>Source boundaries</h2></div><ul><li v-for="note in report.notes" :key="note">{{ note }}</li><li v-if="!report.availability.pitch_location">Pitch location is unavailable; no strike-zone coordinates were inferred.</li><li v-if="!report.availability.external_benchmark">No external age or competition-level benchmark is applied.</li></ul></section>
  </article>
</template>

<style scoped>
.rapsodo-report{--ink:#f4f7fb;--muted:#8493a7;display:grid;gap:15px;max-width:1280px;margin:0 auto;color:var(--ink)}.report-section{break-inside:avoid}.hero{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:28px;padding:26px 30px;border:1px solid rgba(255,255,255,.13);border-radius:20px;background:radial-gradient(circle at 85% 0,rgba(46,213,206,.18),transparent 35%),linear-gradient(135deg,#091523,#101e31)}.brand b{font-size:31px;font-weight:1000;letter-spacing:-.08em}.brand b span{color:#25c9c2}.brand small{display:block;color:#25c9c2;font-size:7px;font-weight:900;letter-spacing:.13em}.title span,.panel-heading>span,.notes>div span{color:#2ed5ce;font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.title h1{margin:3px 0;color:#fff;font-size:clamp(24px,3vw,40px);font-weight:1000;letter-spacing:-.03em}.title p,.panel-heading p{color:var(--muted);font-size:11px}.print-button{padding:11px 15px;border:1px solid rgba(46,213,206,.55);border-radius:9px;background:rgba(46,213,206,.12);color:#dffefa;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.identity{display:grid;grid-template-columns:1.4fr repeat(3,1fr);border:1px solid rgba(255,255,255,.1);border-radius:16px;background:#0b1727}.identity>div{display:flex;min-width:0;flex-direction:column;gap:4px;padding:19px 22px;border-right:1px solid rgba(255,255,255,.09)}.identity>div:last-child{border:0}.identity span,.kpis span{color:#77879b;font-size:8px;font-weight:900;letter-spacing:.1em;text-transform:uppercase}.identity strong{overflow:hidden;color:#fff;font-size:19px;text-overflow:ellipsis;white-space:nowrap}.identity small{color:#8d9db0;font-size:10px}.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.kpis>div{padding:17px 20px;border:1px solid rgba(255,255,255,.09);border-radius:14px;background:linear-gradient(145deg,rgba(15,31,48,.97),rgba(9,20,34,.97))}.kpis strong{display:inline-block;margin:5px 6px 0 0;color:#fff;font-size:29px}.kpis small{color:#2ed5ce;font-size:10px}.two-column{display:grid;grid-template-columns:minmax(0,1.9fr) minmax(300px,.8fr);gap:15px}.two-column :deep(.movement-chart){height:100%}.panel,.insights,.notes{padding:24px;border:1px solid rgba(255,255,255,.11);border-radius:18px;background:rgba(10,19,37,.88)}.panel-heading h2,.notes h2{margin:4px 0;color:#fff;font-size:22px;font-weight:900}.usage-list{display:grid;gap:18px;margin-top:24px}.usage-list header,.velocity-grid header,.comparison header{display:flex;justify-content:space-between;gap:10px;color:#fff;font-size:11px}.usage-list header span,.velocity-grid header span,.comparison header span{color:#8fa0b5}.track,.velocity-track,.measure>div{position:relative;height:8px;margin-top:7px;overflow:hidden;border-radius:99px;background:rgba(255,255,255,.08)}.track i,.measure i{display:block;height:100%;border-radius:inherit}.velocity-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px}.velocity-grid article,.comparison article{padding:15px;border:1px solid rgba(255,255,255,.08);border-radius:12px;background:rgba(255,255,255,.025)}.velocity-track{overflow:visible}.velocity-track i{position:absolute;top:1px;height:6px;border-radius:99px}.velocity-track em{position:absolute;top:-4px;width:3px;height:16px;border:1px solid #07101e;border-radius:4px;background:#fff;transform:translateX(-50%)}.velocity-grid footer{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;margin-top:12px;color:#78889b;font-size:9px}.velocity-grid footer b{color:#fff}.comparison{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px}.measure{margin-top:14px}.measure label{display:flex;justify-content:space-between;color:#8999ad;font-size:9px}.measure label b{color:#fff}.measure.strike i{background:#f0b84b}.insight-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:17px}.insight-grid article{display:flex;gap:13px;padding:16px;border:1px solid rgba(46,213,206,.18);border-radius:13px;background:rgba(46,213,206,.04)}.insight-grid article>b{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:#2ed5ce;color:#07101e}.insight-grid h3{font-size:13px}.insight-grid p{margin-top:6px;color:#90a0b3;font-size:10px;line-height:1.55}.notes{display:grid;grid-template-columns:220px 1fr;gap:25px;border-color:rgba(46,213,206,.25)}.notes ul{display:grid;gap:6px;color:#9aabba;font-size:10px;line-height:1.5}.notes li::marker{color:#2ed5ce}@media(max-width:900px){.hero{grid-template-columns:1fr}.print-button{justify-self:start}.identity{grid-template-columns:repeat(2,1fr)}.identity>div:nth-child(2){border-right:0}.identity>div:nth-child(-n+2){border-bottom:1px solid rgba(255,255,255,.09)}.kpis{grid-template-columns:repeat(2,1fr)}.two-column{grid-template-columns:1fr}.velocity-grid,.comparison{grid-template-columns:1fr}.insight-grid{grid-template-columns:1fr}.notes{grid-template-columns:1fr}}@media(max-width:560px){.hero,.panel,.insights,.notes{padding:17px}.identity{grid-template-columns:1fr}.identity>div{border-right:0;border-bottom:1px solid rgba(255,255,255,.09)!important}.identity>div:last-child{border:0!important}.kpis{grid-template-columns:1fr}.title h1{font-size:26px}}@media print{.rapsodo-report{display:block;max-width:none;color:#172033}.rapsodo-report>*{margin-bottom:12px}.hero,.identity,.kpis>div,.panel,.insights,.notes{border-color:#cbd5df!important;background:#fff!important;color:#172033!important;box-shadow:none!important}.title h1,.identity strong,.kpis strong,.panel-heading h2,.notes h2,.insight-grid h3,.usage-list b,.velocity-grid b,.comparison b{color:#101828!important}.print-button{display:none}.identity span,.identity small,.kpis span,.kpis small,.title p,.panel-heading p,.notes li,.insight-grid p{color:#536273!important}.two-column{grid-template-columns:1fr}.report-section{page-break-inside:avoid}}
</style>
