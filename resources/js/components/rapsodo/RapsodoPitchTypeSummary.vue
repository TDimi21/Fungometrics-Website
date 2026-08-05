<script setup>
defineProps({ rows: { type: Array, default: () => [] } })
const number = (value, digits = 1) => value == null ? '—' : Number(value).toFixed(digits)
const integer = value => value == null ? '—' : Math.round(Number(value)).toLocaleString()
</script>

<template>
  <section class="arsenal report-section" aria-labelledby="arsenal-title">
    <div class="heading"><div><span>Pitch arsenal summary</span><h2 id="arsenal-title">Session pitch profiles</h2></div><p>Total Spin and True Spin are kept as separate source measurements.</p></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Pitch</th><th>Use</th><th>Velocity mph</th><th>Total Spin</th><th>True Spin</th><th>Efficiency</th><th>Break H / V</th><th>Strike %</th><th>Release H / S</th><th>Tilt</th></tr></thead>
        <tbody><tr v-for="row in rows" :key="row.pitch_type">
          <td><b>{{ row.pitch_type }}</b><small>{{ row.display_name }}</small></td><td>{{ row.count }}<small>{{ number(row.usage_percentage) }}%</small></td><td>{{ number(row.average_velocity) }}<small>{{ number(row.minimum_velocity) }}–{{ number(row.maximum_velocity) }}</small></td><td>{{ integer(row.average_spin_rate) }}<small>rpm</small></td><td>{{ integer(row.average_true_spin) }}<small>rpm</small></td><td>{{ number(row.average_spin_efficiency) }}<small>%</small></td><td>{{ number(row.average_horizontal_break) }} / {{ number(row.average_vertical_break) }}<small>inches</small></td><td>{{ number(row.strike_percentage) }}<small>source Y/N</small></td><td>{{ number(row.average_release_height,2) }} / {{ number(row.average_release_side,2) }}<small>feet</small></td><td>{{ row.average_tilt || '—' }}<small>clock</small></td>
        </tr></tbody>
      </table>
    </div>
    <div class="mobile-cards">
      <article v-for="row in rows" :key="`mobile-${row.pitch_type}`">
        <header><div><b>{{ row.pitch_type }}</b><span>{{ row.display_name }}</span></div><strong>{{ row.count }} pitches · {{ number(row.usage_percentage) }}%</strong></header>
        <dl><div><dt>Velocity</dt><dd>{{ number(row.average_velocity) }} mph</dd><small>{{ number(row.minimum_velocity) }}–{{ number(row.maximum_velocity) }}</small></div><div><dt>Total / True Spin</dt><dd>{{ integer(row.average_spin_rate) }} / {{ integer(row.average_true_spin) }}</dd><small>rpm</small></div><div><dt>Efficiency</dt><dd>{{ number(row.average_spin_efficiency) }}%</dd></div><div><dt>Break H / V</dt><dd>{{ number(row.average_horizontal_break) }} / {{ number(row.average_vertical_break) }}</dd><small>inches</small></div><div><dt>Source strikes</dt><dd>{{ number(row.strike_percentage) }}%</dd></div><div><dt>Tilt</dt><dd>{{ row.average_tilt || '—' }}</dd><small>clock</small></div></dl>
      </article>
    </div>
  </section>
</template>

<style scoped>
.arsenal{padding:24px;border:1px solid rgba(255,255,255,.11);border-radius:18px;background:rgba(10,19,37,.88)}.heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:16px}.heading span{color:#2ed5ce;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.heading h2{margin-top:4px;color:#fff;font-size:22px;font-weight:900}.heading p{max-width:360px;color:#8493a7;font-size:11px;text-align:right}.table-wrap{overflow-x:auto;border:1px solid rgba(255,255,255,.08);border-radius:13px}table{width:100%;min-width:1050px;border-collapse:collapse}th{padding:12px 11px;background:#101d30;color:#8290a3;font-size:9px;letter-spacing:.08em;text-align:left;text-transform:uppercase}td{padding:14px 11px;border-top:1px solid rgba(255,255,255,.075);color:#fff;font-size:13px;font-weight:800}td b{display:block;color:#2ed5ce;font-size:16px}td small{display:block;margin-top:3px;color:#7f8da1;font-size:9px;font-weight:600}.mobile-cards{display:none}.mobile-cards article{padding:15px;border:1px solid rgba(255,255,255,.09);border-radius:13px;background:rgba(255,255,255,.025)}.mobile-cards header{display:flex;justify-content:space-between;gap:12px}.mobile-cards header div{display:flex;flex-direction:column}.mobile-cards header b{color:#2ed5ce;font-size:20px}.mobile-cards header span,.mobile-cards header strong{color:#91a0b4;font-size:10px}.mobile-cards dl{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:13px}.mobile-cards dl div{padding:10px;border-radius:9px;background:rgba(255,255,255,.04)}dt{color:#718197;font-size:8px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}dd{margin-top:3px;color:#fff;font-size:14px;font-weight:900}.mobile-cards small{color:#718197;font-size:9px}@media(max-width:760px){.arsenal{padding:15px}.heading{align-items:flex-start;flex-direction:column}.heading p{text-align:left}.table-wrap{display:none}.mobile-cards{display:grid;gap:10px}}
</style>
