<script setup>
const props = defineProps({ report: { type: Object, required: true }, levels: { type: Object, required: true }, benchmarkLevel: { type: String, required: true } })
const emit = defineEmits(['update:benchmarkLevel'])
const groups = [
  ['swing_quality', 'Swing Quality Scores'], ['speed_power', 'Speed & Power'], ['swing_shape', 'Swing Shape'],
  ['connection_sequence', 'Connection & Sequence'], ['ball_flight', 'Paired Ball Flight'],
]
const rows = key => props.report.metrics.filter(metric => metric.group === key)
const display = value => value === null || value === undefined ? 'Not captured' : value
const unit = metric => metric.available ? metric.unit || '' : ''
const statusIcon = status => ['above_benchmark','elite','well_above_average','above_average'].includes(status) ? '↑' : ['below_benchmark','below_range','below_average','well_below_average','poor','slower_than_range'].includes(status) ? '↓' : status === 'unavailable' ? '—' : '✓'
</script>

<template>
  <section class="blast-report">
    <header class="report-title"><div class="brand">FMT<span>RX</span><small>DATA. DRILLS. RESULTS.</small></div><div><h1>Blast Session Development Report</h1><p>FMTRX Swing Comparison Tool</p></div><label>Benchmark Level<select :value="benchmarkLevel" @change="emit('update:benchmarkLevel', $event.target.value)"><option v-for="(level,key) in levels" :key="key" :value="key">{{ level.label }}</option></select></label></header>
    <div class="summary-strip">
      <div><span>Player</span><strong>{{ report.player.name }}</strong><small>{{ report.player.handedness || 'Handedness unavailable' }}</small></div>
      <div><span>Total Swings</span><strong>{{ report.session.total_swings }}</strong></div>
      <div><span>Equipment</span><strong>{{ report.session.equipment || 'Not captured' }}</strong></div>
      <div><span>Date Range</span><strong>{{ report.session.date_start ? new Date(report.session.date_start).toLocaleDateString() : 'Not captured' }} – {{ report.session.date_end ? new Date(report.session.date_end).toLocaleDateString() : 'Not captured' }}</strong></div>
      <div><span>Session Types</span><strong v-for="type in report.session.swing_types" :key="type.label">{{ type.label }} ({{ type.count }})</strong></div>
    </div>
    <div class="metric-table">
      <div class="table-head"><span>Metric</span><span>Average Swing</span><span>Best Swing</span><span>Benchmark</span></div>
      <section v-for="([key,label]) in groups" :key="key" class="metric-group" :class="{ disabled: key === 'ball_flight' && !report.ball_flight_available }">
        <h2>{{ label }}</h2><p v-if="key === 'ball_flight' && !report.ball_flight_available">No paired ball-flight data was captured in this Blast-only export.</p>
        <article v-for="metric in rows(key)" :key="metric.key">
          <div class="metric-name"><span>Metric</span><strong>{{ metric.label }}</strong></div>
          <div><span>Average Swing</span><strong>{{ display(metric.average) }} <small>{{ unit(metric) }}</small></strong><i v-if="metric.available" :style="{width: `${Math.min(100, Math.abs(metric.average))}%`}"></i></div>
          <div><span>Best Swing</span><strong>{{ display(metric.best_swing) }} <small>{{ unit(metric) }}</small></strong><i v-if="metric.available && metric.best_swing !== null" class="best" :style="{width: `${Math.min(100, Math.abs(metric.best_swing))}%`}"></i></div>
          <div class="benchmark" :class="`status-${metric.benchmark.status}`"><span>{{ metric.benchmark.source || 'Benchmark' }}</span><strong>{{ metric.benchmark.range_label || metric.benchmark.label }}</strong><small :aria-label="`Status: ${metric.benchmark.label}`">{{ statusIcon(metric.benchmark.status) }} {{ metric.benchmark.label }}</small></div>
        </article>
      </section>
    </div>
    <footer class="insights"><h2>Coach Insight &amp; Action Plan</h2><ol><li v-for="insight in report.insights" :key="insight.metric"><strong>{{ insight.metric }}:</strong> {{ insight.direction }} <small>{{ insight.current }} · {{ insight.classification }}</small></li></ol></footer>
  </section>
</template>

<style scoped>
.blast-report{--cyan:#14d9d2;--blue:#339cff;--green:#80d817;--red:#ff4050;color:#edf4f6;border:1px solid #263a40;border-radius:20px;background:radial-gradient(circle at top,#10262a 0,#071116 38%,#050b0f 100%);font-family:Inter,system-ui,sans-serif;overflow:hidden}.report-title{display:grid;grid-template-columns:180px 1fr auto;align-items:center;gap:22px;padding:26px;border-bottom:1px solid #294047}.brand{font-size:32px;font-weight:950;font-style:italic}.brand span{color:var(--cyan)}.brand small{display:block;color:var(--cyan);font-size:8px;letter-spacing:.15em}.report-title h1{text-align:center;text-transform:uppercase;font-size:30px;font-weight:950;letter-spacing:.025em}.report-title p{text-align:center;color:#9ba9ae}.report-title label{color:#91a1a6;font-size:10px;font-weight:800;text-transform:uppercase}.report-title select{display:block;margin-top:6px;padding:9px;border:1px solid #31515a;border-radius:8px;background:#0a171c;color:#fff}.summary-strip{display:grid;grid-template-columns:1.3fr .7fr 1fr 1fr 1.2fr;margin:16px;border:1px solid #294047;border-radius:12px}.summary-strip>div{display:flex;flex-direction:column;gap:5px;padding:16px;border-right:1px solid #294047}.summary-strip span,.metric-table span{color:#91a1a6;font-size:9px;font-weight:800;text-transform:uppercase}.summary-strip small{color:var(--cyan)}.table-head,article{display:grid;grid-template-columns:1.15fr 1fr 1fr 1.25fr}.table-head{margin:0 16px;border:1px solid #294047;background:#0b171c}.table-head span{padding:12px}.metric-group{margin:0 16px;border:1px solid #294047;border-top:0}.metric-group h2{padding:10px 14px;color:var(--cyan);font-size:12px;text-transform:uppercase}.metric-group>p{padding:0 14px 10px;color:#91a1a6;font-size:11px}article>div{position:relative;min-height:64px;padding:13px;border-top:1px solid #23363c;border-right:1px solid #23363c}article div>span{display:none}article strong{display:block}article strong small{color:#91a1a6}article i{display:block;max-width:70%;height:5px;margin-top:10px;border-radius:6px;background:linear-gradient(90deg,var(--blue),#bad0da)}article i.best{background:linear-gradient(90deg,var(--green),#bad0da)}.benchmark small{display:block;margin-top:5px;color:var(--green);font-weight:850}.status-below_benchmark small,.status-below_range small,.status-well_below_average small,.status-poor small,.status-slower_than_range small{color:var(--red)}.status-reference_only small{color:var(--blue)}.status-unavailable small{color:#829197}.disabled article>div:not(.metric-name){opacity:.58}.insights{display:grid;grid-template-columns:260px 1fr;gap:24px;margin:18px;padding:20px;border:1px solid var(--cyan);border-radius:12px}.insights h2{color:var(--cyan);font-size:18px;text-transform:uppercase}.insights li{margin:7px 0}.insights small{color:#91a1a6}@media(max-width:760px){.report-title{grid-template-columns:1fr}.report-title h1,.report-title p{text-align:left}.summary-strip{grid-template-columns:1fr 1fr}.table-head{display:none}.metric-group article{display:block;margin:8px;border:1px solid #294047;border-radius:10px}.metric-group article>div{border-right:0}.metric-group article div>span{display:block;margin-bottom:4px}.metric-name{background:#0d2026}.insights{grid-template-columns:1fr}}
</style>
