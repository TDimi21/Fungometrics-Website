<script setup>
import { ref, computed } from 'vue'
import { estimatePercentileFromAnchors } from '../lib/percentileInterpolation.js'
import { METRICS, categorizeMetrics, benchmarkFor as lookupBenchmark, positiveMetricNumber } from '../lib/strengthMetricCatalog.js'

const props = defineProps({
  history: { type: Array, default: () => [] },
  intelligence: { type: Object, default: () => ({}) },
})

const categorizedMetrics = computed(() => categorizeMetrics())

const DATE_RANGES = [
  { label: 'All Time', months: 0 },
  { label: '1 Mo', months: 1 },
  { label: '3 Mo', months: 3 },
  { label: '6 Mo', months: 6 },
  { label: '1 Year', months: 12 },
]

const activeMetricKeys = ref(new Set(['body_weight']))
const activeDateRange = ref(0)
const viewMode = ref('absolute') // absolute | relative | percentile

const toggleMetric = (key) => {
  const next = new Set(activeMetricKeys.value)
  if (next.has(key)) {
    if (next.size > 1) next.delete(key)
  } else {
    next.add(key)
  }
  activeMetricKeys.value = next
  if (next.size > 1) viewMode.value = 'absolute'
}

const activeMetrics = computed(() => METRICS.filter((m) => activeMetricKeys.value.has(m.key)))
const singleMetric = computed(() => activeMetrics.value.length === 1 ? activeMetrics.value[0] : null)

const cutoffDate = computed(() => {
  if (activeDateRange.value === 0) return null
  const d = new Date()
  d.setMonth(d.getMonth() - activeDateRange.value)
  return d
})

const allRecordsInRange = computed(() => {
  let records = props.history
    .filter((r) => r.fitness_date)
    .slice()
    .sort((a, b) => new Date(a.fitness_date) - new Date(b.fitness_date))
  if (cutoffDate.value) records = records.filter((r) => new Date(r.fitness_date) >= cutoffDate.value)
  return records
})

// ── Governed benchmark lookup — reads the same benchmark_profile.metrics
// array the Percentile Rankings panel uses, so anchors/goal/gap/peer_group
// are the exact numbers the backend already computed (nothing fabricated).
const activeBenchmark = computed(() => singleMetric.value
  ? lookupBenchmark(props.intelligence?.benchmark_profile?.metrics, singleMetric.value)
  : null)
const anchors = computed(() => activeBenchmark.value?.evidence?.age_percentile_anchors || null)
const higherIsBetter = computed(() => activeBenchmark.value?.evidence?.higher_is_better ?? !(singleMetric.value?.lowerBetter))

const canShowRelative = computed(() => positiveMetricNumber(activeBenchmark.value?.relative_value) !== null)
const canShowPercentile = computed(() => !!anchors.value)

// ── Series builders per view mode (single-metric only; compare mode below always uses absolute) ──
const singleSeriesPoints = computed(() => {
  if (!singleMetric.value) return []
  const rows = allRecordsInRange.value.filter((r) => r[singleMetric.value.key] != null && parseFloat(r[singleMetric.value.key]) > 0)
  if (viewMode.value === 'relative') {
    return rows
      .filter((r) => r.body_weight != null && parseFloat(r.body_weight) > 0)
      .map((r) => ({ x: new Date(r.fitness_date).getTime(), y: +(parseFloat(r[singleMetric.value.key]) / parseFloat(r.body_weight)).toFixed(3), raw: parseFloat(r[singleMetric.value.key]) }))
  }
  if (viewMode.value === 'percentile') {
    return rows.map((r) => ({ x: new Date(r.fitness_date).getTime(), y: estimatePercentileFromAnchors(parseFloat(r[singleMetric.value.key]), anchors.value, higherIsBetter.value), raw: parseFloat(r[singleMetric.value.key]) }))
  }
  return rows.map((r) => ({ x: new Date(r.fitness_date).getTime(), y: parseFloat(r[singleMetric.value.key]), raw: parseFloat(r[singleMetric.value.key]) }))
})

// ── Compare mode (2+ metrics) — unchanged simple multi-line absolute view ──
const compareSeries = computed(() =>
  activeMetrics.value.map((m) => ({
    name: `${m.label} (${m.unit})`,
    data: allRecordsInRange.value
      .filter((r) => r[m.key] != null && parseFloat(r[m.key]) > 0)
      .map((r) => ({ x: new Date(r.fitness_date).getTime(), y: parseFloat(r[m.key]) })),
  })),
)
const compareStats = computed(() =>
  activeMetrics.value.map((m) => {
    const vals = allRecordsInRange.value.filter((r) => r[m.key] != null && parseFloat(r[m.key]) > 0).map((r) => parseFloat(r[m.key]))
    if (!vals.length) return { ...m, current: null, change: null, low: null, high: null }
    const current = vals[vals.length - 1]
    const change = vals.length > 1 ? +(current - vals[0]).toFixed(2) : null
    const good = change === null ? null : (m.lowerBetter ? change <= 0 : change >= 0)
    return { ...m, current, change, good, low: Math.min(...vals), high: Math.max(...vals), count: vals.length }
  }),
)

const hexToRgba = (hex, alpha) => {
  const h = hex.replace('#', '')
  return `rgba(${parseInt(h.slice(0, 2), 16)},${parseInt(h.slice(2, 4), 16)},${parseInt(h.slice(4, 6), 16)},${alpha})`
}

// ── Single-metric stat row: everything here is either a raw history
// computation or a field the backend already returned — nothing invented ──
const singleStats = computed(() => {
  if (!singleMetric.value) return null
  const m = singleMetric.value
  const rows = allRecordsInRange.value.filter((r) => r[m.key] != null && parseFloat(r[m.key]) > 0)
  if (!rows.length) return null
  const values = rows.map((r) => parseFloat(r[m.key]))
  const current = values[values.length - 1]
  const change = values.length > 1 ? +(current - values[0]).toFixed(2) : null
  const changePct = values.length > 1 && values[0] !== 0 ? +((change / values[0]) * 100).toFixed(1) : null
  const good = change === null ? null : (m.lowerBetter ? change <= 0 : change >= 0)

  // Trend: consecutive-test direction streak, computed from real deltas only.
  let streak = 0
  let streakDir = null
  for (let i = values.length - 1; i > 0; i--) {
    const delta = values[i] - values[i - 1]
    const dir = delta === 0 ? 'flat' : ((m.lowerBetter ? delta < 0 : delta > 0) ? 'up' : 'down')
    if (streakDir === null) { streakDir = dir; streak = 1 } else if (dir === streakDir) { streak += 1 } else break
  }

  const b = activeBenchmark.value
  return {
    current, change, changePct, good,
    currentDisplay: `${current}${m.unit.startsWith('/') ? '' : ' '}${m.unit}`.trim(),
    changeDisplay: change === null ? null : `${change > 0 ? '+' : ''}${change} ${m.unit}`.trim(),
    relative: positiveMetricNumber(b?.relative_value),
    percentile: b?.percentile ?? null,
    label: b?.label ?? null,
    goal: positiveMetricNumber(b?.goal),
    gap: b?.gap ?? null,
    confidence: b?.confidence ?? null,
    peerGroup: b?.peer_group ?? [],
    pointCount: values.length,
    streak, streakDir,
  }
})

// ── Chart options ──
const chartOptions = computed(() => {
  if (singleMetric.value) {
    const m = singleMetric.value
    const unitSuffix = viewMode.value === 'percentile' ? '' : (viewMode.value === 'relative' ? '× BW' : ` ${m.unit}`)
    const yaxisAnnotations = []
    if (viewMode.value === 'absolute' && anchors.value) {
      const bands = [
        { tier: 'p90', text: 'ELITE (90-100%)', color: '#4ade80' },
        { tier: 'p75', text: 'ABOVE AVG (75-89%)', color: '#fb923c' },
        { tier: 'p50', text: 'AVERAGE (50-74%)', color: 'rgba(255,255,255,0.35)' },
        { tier: 'p25', text: 'BELOW AVG (25-49%)', color: '#60a5fa' },
      ]
      bands.forEach((band) => {
        if (positiveMetricNumber(anchors.value[band.tier]) !== null) {
          yaxisAnnotations.push({
            y: anchors.value[band.tier],
            borderColor: band.color,
            strokeDashArray: 4,
            label: { text: band.text, style: { color: band.color, background: 'transparent', fontSize: '9px', fontWeight: 800 }, position: 'right', offsetX: -4 },
          })
        }
      })
      if (singleStats.value?.goal != null) {
        yaxisAnnotations.push({
          y: singleStats.value.goal,
          borderColor: '#34d399',
          strokeDashArray: 2,
          label: { text: `GOAL`, style: { color: '#34d399', background: 'transparent', fontSize: '9px', fontWeight: 800 }, position: 'left' },
        })
      }
    }
    if (viewMode.value === 'percentile') {
      [25, 50, 75, 90].forEach((p) => yaxisAnnotations.push({
        y: p, borderColor: 'rgba(255,255,255,0.2)', strokeDashArray: 4,
        label: { text: `P${p}`, style: { color: 'rgba(255,255,255,0.5)', background: 'transparent', fontSize: '9px' }, position: 'left' },
      }))
    }
    return {
      chart: { type: 'line', height: 300, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 350 } },
      stroke: { curve: 'smooth', width: 2.5 },
      colors: [m.color],
      markers: { size: 5, fillColors: [m.color], strokeColors: '#0b1120', strokeWidth: 2, hover: { size: 7 } },
      dataLabels: { enabled: false },
      legend: { show: false },
      grid: { borderColor: 'rgba(255,255,255,0.07)' },
      annotations: { yaxis: yaxisAnnotations },
      xaxis: {
        type: 'datetime',
        labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, rotate: -35, datetimeFormatter: { year: 'yyyy', month: "MMM 'yy", day: 'MMM dd' } },
        axisBorder: { color: 'rgba(255,255,255,0.08)' },
        axisTicks: { color: 'rgba(255,255,255,0.08)' },
      },
      yaxis: {
        min: viewMode.value === 'percentile' ? 0 : undefined,
        max: viewMode.value === 'percentile' ? 100 : undefined,
        labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, formatter: (v) => v != null ? `${parseFloat(v).toFixed(viewMode.value === 'relative' ? 2 : 1)}${unitSuffix}` : '' },
      },
      tooltip: { theme: 'dark' },
      theme: { mode: 'dark' },
    }
  }
  return {
    chart: { type: 'area', height: 280, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 350 } },
    stroke: { curve: 'smooth', width: 2 },
    colors: activeMetrics.value.map((m) => hexToRgba(m.color, 0.3)),
    fill: { type: 'solid', opacity: 0 },
    markers: { size: 5, fillColors: activeMetrics.value.map((m) => m.color), strokeColors: '#0b1120', strokeWidth: 2, hover: { size: 7 } },
    dataLabels: { enabled: false },
    legend: { show: true, labels: { colors: 'rgba(255,255,255,0.65)' }, markers: { width: 10, height: 10, radius: 9999 } },
    grid: { borderColor: 'rgba(255,255,255,0.07)', row: { colors: ['transparent'], opacity: 1 } },
    xaxis: {
      type: 'datetime',
      labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, rotate: -35, datetimeFormatter: { year: 'yyyy', month: "MMM 'yy", day: 'MMM dd' } },
      axisBorder: { color: 'rgba(255,255,255,0.08)' },
      axisTicks: { color: 'rgba(255,255,255,0.08)' },
    },
    yaxis: { labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, formatter: (v) => v != null ? parseFloat(v).toFixed(1) : '' } },
    tooltip: { theme: 'dark', shared: true, intersect: false },
    theme: { mode: 'dark' },
  }
})
const chartSeries = computed(() => singleMetric.value
  ? [{ name: singleMetric.value.label, data: singleSeriesPoints.value }]
  : compareSeries.value)
const hasAnyData = computed(() => chartSeries.value.some((s) => s.data.length > 0))

// ── Peer comparison table — real absolute-unit thresholds from the same
// governed anchors used for the chart bands ──
const peerTiers = computed(() => {
  if (!anchors.value) return []
  const tiers = [
    { key: 'p95', label: '90th Percentile (Elite)' },
    { key: 'p75', label: '75th Percentile (Above Average)' },
    { key: 'p50', label: '50th Percentile (Average)' },
    { key: 'p25', label: '25th Percentile (Below Average)' },
    { key: 'p10', label: '10th Percentile (Needs Development)' },
  ]
  return tiers.filter((t) => anchors.value[t.key] != null).map((t) => ({ ...t, value: anchors.value[t.key] }))
})

// ── Test history — actual logged rows only (this system stores one current
// value per test date, not rep-based sets, so no Est. 1RM/Notes column) ──
const testHistoryRows = computed(() => {
  if (!singleMetric.value) return []
  return allRecordsInRange.value
    .filter((r) => r[singleMetric.value.key] != null && parseFloat(r[singleMetric.value.key]) > 0)
    .slice()
    .reverse()
    .slice(0, 8)
    .map((r) => {
      const value = parseFloat(r[singleMetric.value.key])
      const bw = r.body_weight != null && parseFloat(r.body_weight) > 0 ? parseFloat(r.body_weight) : null
      return {
        date: r.fitness_date,
        value,
        bodyWeight: bw,
        relative: bw ? +(value / bw).toFixed(2) : null,
      }
    })
})

// ── Trend insights — real linear fit on the visible history, not a fabricated projection model ──
const trendInsights = computed(() => {
  if (!singleMetric.value) return null
  const rows = allRecordsInRange.value.filter((r) => r[singleMetric.value.key] != null && parseFloat(r[singleMetric.value.key]) > 0)
  if (rows.length < 2) return null
  const points = rows.map((r) => [new Date(r.fitness_date).getTime(), parseFloat(r[singleMetric.value.key])])
  const n = points.length
  const meanX = points.reduce((s, p) => s + p[0], 0) / n
  const meanY = points.reduce((s, p) => s + p[1], 0) / n
  const num = points.reduce((s, p) => s + (p[0] - meanX) * (p[1] - meanY), 0)
  const den = points.reduce((s, p) => s + (p[0] - meanX) ** 2, 0)
  const slope = den === 0 ? 0 : num / den // units per millisecond
  const intercept = meanY - slope * meanX
  const threeMonthsMs = 90 * 24 * 60 * 60 * 1000
  const projected = +(intercept + slope * (points[n - 1][0] + threeMonthsMs)).toFixed(1)

  const first = points[0][1]
  const last = points[n - 1][1]
  const trendPct = first !== 0 ? +(((last - first) / first) * 100).toFixed(1) : null

  let improvingSteps = 0
  for (let i = 1; i < n; i++) {
    const delta = points[i][1] - points[i - 1][1]
    if (singleMetric.value.lowerBetter ? delta <= 0 : delta >= 0) improvingSteps += 1
  }
  const consistency = n > 1 ? Math.round((improvingSteps / (n - 1)) * 100) : null

  return {
    trendPct,
    consistency,
    projected,
    pointCount: n,
    dataQuality: n >= 8 ? 'Strong' : n >= 4 ? 'Moderate' : 'Limited',
  }
})
</script>

<template>
  <div class="metrics-shell">
    <aside class="metric-sidebar">
      <div class="sidebar-title">Metric Categories</div>
      <div v-for="group in categorizedMetrics" :key="group.label" class="sidebar-group">
        <div class="sidebar-group-label">{{ group.label }}</div>
        <button
          v-for="m in group.metrics" :key="m.key" class="sidebar-metric-btn"
          :class="{ active: activeMetricKeys.has(m.key) }"
          :style="activeMetricKeys.has(m.key) ? { borderColor: m.color, color: m.color } : {}"
          @click="toggleMetric(m.key)"
        >{{ m.label }}</button>
      </div>
    </aside>

    <div class="metric-main">
      <div class="metric-header">
        <div class="metric-heading">
          <h2>{{ singleMetric ? singleMetric.label : `${activeMetrics.length} Metrics` }}</h2>
          <span>{{ singleMetric ? singleMetric.category : 'Comparison' }}</span>
        </div>
        <div v-if="singleMetric" class="mode-toggle">
          <button type="button" :class="{ active: viewMode === 'absolute' }" @click="viewMode = 'absolute'">Absolute</button>
          <button type="button" :class="{ active: viewMode === 'relative' }" :disabled="!canShowRelative" @click="viewMode = 'relative'">Relative</button>
          <button type="button" :class="{ active: viewMode === 'percentile' }" :disabled="!canShowPercentile" @click="viewMode = 'percentile'">Percentile</button>
        </div>
        <label class="date-range-select">Range
          <select v-model.number="activeDateRange">
            <option v-for="r in DATE_RANGES" :key="r.months" :value="r.months">{{ r.label }}</option>
          </select>
        </label>
      </div>

      <!-- Single-metric governed stat row -->
      <div v-if="singleStats" class="stat-grid">
        <div class="stat-box"><span>Current</span><b>{{ singleStats.currentDisplay }}</b></div>
        <div v-if="singleStats.relative != null" class="stat-box"><span>Relative Strength</span><b>{{ singleStats.relative.toFixed(2) }}×</b><small>Body Weight</small></div>
        <div v-if="singleStats.percentile != null" class="stat-box"><span>Peer Percentile</span><b>{{ singleStats.percentile }}<sup>{{ singleStats.percentile === 1 ? 'st' : singleStats.percentile === 2 ? 'nd' : singleStats.percentile === 3 ? 'rd' : 'th' }}</sup></b><small>{{ singleStats.label }}</small></div>
        <div v-if="singleStats.changeDisplay" class="stat-box" :class="singleStats.good ? 'good' : 'bad'"><span>Change</span><b>{{ singleStats.changeDisplay }}</b><small v-if="singleStats.changePct != null">{{ singleStats.changePct > 0 ? '+' : '' }}{{ singleStats.changePct }}%</small></div>
        <div v-if="singleStats.goal != null" class="stat-box"><span>Goal</span><b>{{ singleStats.goal }} {{ singleMetric.unit }}</b></div>
        <div v-if="singleStats.gap != null" class="stat-box"><span>Gap</span><b>{{ singleStats.gap }} {{ singleMetric.unit }}</b></div>
        <div v-if="singleStats.streak > 1" class="stat-box"><span>Trend</span><b :class="singleStats.streakDir === 'up' ? 'text-good' : singleStats.streakDir === 'down' ? 'text-bad' : ''">{{ singleStats.streakDir === 'up' ? 'Improving' : singleStats.streakDir === 'down' ? 'Declining' : 'Steady' }}</b><small>{{ singleStats.streak }} consecutive tests</small></div>
      </div>

      <div class="chart-card">
        <div v-if="hasAnyData" class="chart-area">
          <apexchart
            width="100%" :type="singleMetric ? 'line' : 'area'" :height="singleMetric ? 300 : 280"
            :options="chartOptions" :series="chartSeries"
            :key="[...activeMetricKeys].join('_') + '_' + activeDateRange + '_' + viewMode"
          />
        </div>
        <div v-else class="chart-empty"><p>No data for this selection</p></div>
      </div>

      <!-- Compare-mode stat pills (2+ metrics) -->
      <div v-if="!singleMetric && hasAnyData" class="stats-block">
        <div v-for="s in compareStats" :key="s.key" class="stat-row">
          <div class="stat-metric-label" :style="{ color: s.color }"><span class="stat-dot" :style="{ background: s.color }"></span>{{ s.label }}</div>
          <div class="stat-pills">
            <div v-if="s.current !== null" class="stat-pill"><span class="pill-val">{{ parseFloat(s.current).toFixed(1) }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span><span class="pill-lbl">Current</span></div>
            <div v-if="s.change !== null" class="stat-pill" :class="s.good ? 'pill-good' : 'pill-bad'"><span class="pill-val">{{ s.change > 0 ? '+' : '' }}{{ parseFloat(s.change).toFixed(1) }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span><span class="pill-lbl">Change</span></div>
            <div v-if="s.low !== null" class="stat-pill"><span class="pill-val">{{ parseFloat(s.low).toFixed(1) }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span><span class="pill-lbl">Low</span></div>
            <div v-if="s.high !== null" class="stat-pill"><span class="pill-val">{{ parseFloat(s.high).toFixed(1) }}{{ s.unit.startsWith('/') ? '' : '&thinsp;' }}{{ s.unit }}</span><span class="pill-lbl">High</span></div>
            <div v-if="s.count" class="stat-pill"><span class="pill-val">{{ s.count }}</span><span class="pill-lbl">Points</span></div>
          </div>
        </div>
      </div>

      <!-- Single-metric lower panels -->
      <div v-if="singleMetric" class="lower-grid">
        <div class="panel">
          <div class="panel-title">Test History</div>
          <table v-if="testHistoryRows.length" class="history-table">
            <thead><tr><th>Date</th><th>Value</th><th>Body Weight</th><th v-if="testHistoryRows.some(r=>r.relative)">Relative</th></tr></thead>
            <tbody>
              <tr v-for="row in testHistoryRows" :key="row.date">
                <td>{{ row.date }}</td>
                <td>{{ row.value }} {{ singleMetric.unit }}</td>
                <td>{{ row.bodyWeight ? `${row.bodyWeight} lb` : '—' }}</td>
                <td v-if="testHistoryRows.some(r=>r.relative)">{{ row.relative ? `${row.relative}×` : '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="panel-empty">No logged tests in this range.</p>
        </div>

        <div class="panel">
          <div class="panel-title">Peer Comparison</div>
          <div v-if="peerTiers.length" class="peer-list">
            <div v-for="tier in peerTiers" :key="tier.key" class="peer-row"><span>{{ tier.label }}</span><b>{{ tier.value }} {{ singleMetric.unit }}</b></div>
            <div v-if="singleStats?.current != null" class="peer-row peer-current"><span>Your Current</span><b>{{ singleStats.percentile }}th Percentile ({{ singleStats.current }} {{ singleMetric.unit }})</b></div>
          </div>
          <p v-else class="panel-empty">Governed benchmark not configured for this metric/age group yet.</p>
          <p v-if="singleStats?.peerGroup?.length" class="peer-footer">Peer Group: {{ singleStats.peerGroup.join(' · ') }} <span v-if="singleStats.confidence">· Confidence: {{ singleStats.confidence }}</span></p>
        </div>

        <div class="panel">
          <div class="panel-title">Trend Insights</div>
          <div v-if="trendInsights" class="insight-grid">
            <div class="insight-box"><span>Strength Trend</span><b :class="trendInsights.trendPct >= 0 ? 'text-good' : 'text-bad'">{{ trendInsights.trendPct > 0 ? '+' : '' }}{{ trendInsights.trendPct }}%</b></div>
            <div v-if="trendInsights.consistency != null" class="insight-box"><span>Consistency</span><b>{{ trendInsights.consistency }}%</b></div>
            <div class="insight-box"><span>Projected in 3 Mo.</span><b>{{ trendInsights.projected }} {{ singleMetric.unit }}</b><small>Linear trend estimate</small></div>
            <div class="insight-box"><span>Data Quality</span><b>{{ trendInsights.dataQuality }}</b><small>{{ trendInsights.pointCount }} data points</small></div>
          </div>
          <p v-else class="panel-empty">Log at least 2 tests to see trend insights.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.metrics-shell { display: grid; grid-template-columns: 220px 1fr; gap: 16px; background: #060b14; border-radius: 14px; padding: 16px; color: white; }
.metric-sidebar { border-right: 1px solid rgba(255,255,255,0.08); padding-right: 14px; }
.sidebar-title { font-size: 9px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 10px; }
.sidebar-group { margin-bottom: 14px; }
.sidebar-group-label { font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #C00000; margin-bottom: 6px; }
.sidebar-metric-btn { display: block; width: 100%; text-align: left; font-size: 12px; font-weight: 600; padding: 6px 10px; margin-bottom: 3px; border-radius: 7px; border: 1px solid transparent; background: transparent; color: rgba(255,255,255,0.6); cursor: pointer; transition: all 0.15s; }
.sidebar-metric-btn:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.9); }
.sidebar-metric-btn.active { background: rgba(192,0,0,0.12); border-color: currentColor; font-weight: 800; }

.metric-main { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.metric-header { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 14px; justify-content: space-between; }
.metric-heading h2 { font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.metric-heading span { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; }
.mode-toggle { display: flex; gap: 6px; }
.mode-toggle button { font-size: 10px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; padding: 7px 14px; border-radius: 7px; border: 1px solid rgba(255,255,255,0.14); background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.55); cursor: pointer; }
.mode-toggle button.active { border-color: #C00000; color: #ff6666; background: rgba(192,0,0,0.1); }
.mode-toggle button:disabled { opacity: 0.3; cursor: not-allowed; }
.date-range-select { font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 6px; }
.date-range-select select { background: #0b1120; border: 1px solid rgba(255,255,255,0.14); border-radius: 7px; color: white; font-size: 11px; padding: 6px 8px; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
.stat-box { padding: 10px 12px; border: 1px solid rgba(255,255,255,0.09); border-radius: 10px; background: rgba(255,255,255,0.03); }
.stat-box span { display: block; font-size: 9px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 4px; }
.stat-box b { font-size: 17px; font-weight: 900; color: white; }
.stat-box small { display: block; margin-top: 2px; font-size: 9px; color: rgba(255,255,255,0.4); }
.stat-box.good b { color: #4ade80; }
.stat-box.bad b { color: #f87171; }
.text-good { color: #4ade80; }
.text-bad { color: #f87171; }

.chart-card { background: linear-gradient(160deg, #0f1a2e 0%, #0b1120 100%); border: 1px solid rgba(192,0,0,0.18); border-radius: 14px; padding: 14px; }
.chart-empty { display: flex; align-items: center; justify-content: center; padding: 40px 16px; }
.chart-empty p { color: rgba(255,255,255,0.35); font-size: 13px; font-weight: 600; }

.stats-block { display: flex; flex-direction: column; gap: 8px; }
.stat-row { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.stat-metric-label { display: flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; min-width: 88px; flex-shrink: 0; }
.stat-dot { width: 8px; height: 8px; border-radius: 9999px; flex-shrink: 0; }
.stat-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.stat-pill { display: flex; flex-direction: column; align-items: center; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 5px 11px; min-width: 54px; }
.stat-pill.pill-good { border-color: rgba(74,222,128,0.3); background: rgba(74,222,128,0.07); }
.stat-pill.pill-bad { border-color: rgba(248,113,113,0.3); background: rgba(248,113,113,0.07); }
.pill-val { font-size: 13px; font-weight: 800; color: #ffffff; line-height: 1; }
.pill-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.07em; margin-top: 2px; }

.lower-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
.panel { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 14px; }
.panel-title { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; }
.panel-empty { color: rgba(255,255,255,0.35); font-size: 12px; }
.history-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.history-table th { text-align: left; color: rgba(255,255,255,0.35); font-size: 9px; text-transform: uppercase; padding-bottom: 6px; }
.history-table td { padding: 5px 0; border-top: 1px solid rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); }
.peer-list { display: flex; flex-direction: column; gap: 6px; }
.peer-row { display: flex; justify-content: space-between; font-size: 11px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.06); color: rgba(255,255,255,0.7); }
.peer-row b { color: white; font-weight: 800; }
.peer-row.peer-current { color: #ff8798; font-weight: 800; }
.peer-row.peer-current b { color: #ff8798; }
.peer-footer { margin-top: 10px; font-size: 10px; color: rgba(255,255,255,0.35); }
.insight-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.insight-box span { display: block; font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 3px; }
.insight-box b { font-size: 15px; font-weight: 900; }
.insight-box small { display: block; margin-top: 2px; font-size: 9px; color: rgba(255,255,255,0.35); }

@media (max-width: 900px) {
  .metrics-shell { grid-template-columns: 1fr; }
  .metric-sidebar { border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.08); padding-right: 0; padding-bottom: 12px; display: flex; flex-wrap: wrap; gap: 6px; }
  .sidebar-group { display: contents; }
}
</style>
