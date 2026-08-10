<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { categorizeMetrics, benchmarkFor } from '../lib/strengthMetricCatalog.js'

const route = useRoute()
const router = useRouter()
const { axiosGet } = useAxiosAuth()

const PLAYER_COLORS = ['#ff2b4a', '#3b82f6', '#10b981', '#f59e0b', '#a855f7', '#06b6d4', '#ec4899', '#84cc16']
const DATE_RANGES = [
  { label: 'All Time', months: 0 },
  { label: '1 Month', months: 1 },
  { label: '3 Months', months: 3 },
  { label: '6 Months', months: 6 },
]

const teamId = computed(() => String(route.query?.teamId || '').trim())
const playerIds = computed(() => String(route.query?.playerIds || '').split(',').map((id) => id.trim()).filter(Boolean))
const playerNames = computed(() => String(route.query?.names || '').split('|'))

const loading = ref(false)
const errorMessage = ref('')
const players = ref([]) // [{ id, name, color, intelligence, history }]
const activeMetricKey = ref('bench_press')
const sortBy = ref('percentile') // percentile | value | relative | gap
const activeDateRange = ref(0)
const dataWindow = ref(365)

const categorizedMetrics = computed(() => categorizeMetrics())
const activeMetric = computed(() => categorizedMetrics.value
  .flatMap((group) => group.metrics)
  .find((m) => m.key === activeMetricKey.value) || null)

const loadComparison = async () => {
  errorMessage.value = ''
  players.value = []
  if (!teamId.value || playerIds.value.length < 2) {
    errorMessage.value = 'Select a team and at least two players to compare.'
    return
  }
  loading.value = true
  try {
    const results = await Promise.all(playerIds.value.map(async (id, index) => {
      const name = playerNames.value[index] || 'Player'
      const color = PLAYER_COLORS[index % PLAYER_COLORS.length]
      const [intelligenceRes, historyRes] = await Promise.all([
        axiosGet(`coach/teams/${teamId.value}/players/${id}/intelligence`, { days: dataWindow.value }).catch(() => null),
        axiosGet(`player/fitness/${id}`).catch(() => null),
      ])
      return {
        id, name, color,
        intelligence: intelligenceRes?.data?.data || intelligenceRes?.data || null,
        history: Array.isArray(historyRes?.data?.data) ? historyRes.data.data : [],
        failed: !intelligenceRes && !historyRes,
      }
    }))
    players.value = results
  } finally {
    loading.value = false
  }
}

watch([teamId, playerIds, dataWindow], loadComparison, { immediate: true })

// ── Per-player row for the active metric — every number here is either a
// field the backend already computed (raw_value/relative_value/percentile/
// gap/label) or a plain "Needs Data" state, nothing fabricated. ──
const comparisonRows = computed(() => {
  if (!activeMetric.value) return []
  return players.value.map((player) => {
    const metrics = player.intelligence?.benchmark_profile?.metrics
    const benchmark = benchmarkFor(metrics, activeMetric.value)
    return {
      id: player.id,
      name: player.name,
      failed: player.failed,
      available: benchmark?.percentile != null,
      rawValue: benchmark?.raw_value ?? null,
      relative: benchmark?.relative_value ?? null,
      percentile: benchmark?.percentile ?? null,
      label: benchmark?.label ?? null,
      gap: benchmark?.gap ?? null,
      unit: activeMetric.value.unit,
    }
  })
})

const sortedRows = computed(() => {
  const rows = comparisonRows.value.slice()
  const key = { percentile: 'percentile', value: 'rawValue', relative: 'relative', gap: 'gap' }[sortBy.value]
  const higherFirst = sortBy.value !== 'gap' // smaller gap-to-goal is "better" / more urgent to lead with
  return rows.sort((a, b) => {
    if (a[key] == null && b[key] == null) return 0
    if (a[key] == null) return 1
    if (b[key] == null) return -1
    return higherFirst ? b[key] - a[key] : a[key] - b[key]
  })
})

const maxPercentile = computed(() => Math.max(1, ...sortedRows.value.map((r) => r.percentile || 0)))
const displayValue = (row) => row.rawValue != null ? `${row.rawValue} ${row.unit}`.trim() : '—'

// ── Multi-player line series: every logged fitness entry for the active
// metric, one line per player, filtered to the selected date range ──
const cutoffDate = computed(() => {
  if (activeDateRange.value === 0) return null
  const d = new Date()
  d.setMonth(d.getMonth() - activeDateRange.value)
  return d
})

const lineSeries = computed(() => {
  if (!activeMetric.value) return []
  return players.value.map((player) => {
    const rows = (player.history || [])
      .filter((r) => r.fitness_date && r[activeMetric.value.key] != null && parseFloat(r[activeMetric.value.key]) > 0)
      .filter((r) => !cutoffDate.value || new Date(r.fitness_date) >= cutoffDate.value)
      .slice()
      .sort((a, b) => new Date(a.fitness_date) - new Date(b.fitness_date))
    return {
      name: player.name,
      data: rows.map((r) => ({ x: new Date(r.fitness_date).getTime(), y: parseFloat(r[activeMetric.value.key]) })),
    }
  })
})
const hasLineData = computed(() => lineSeries.value.some((s) => s.data.length > 0))
const lineColors = computed(() => players.value.map((p) => p.color))

const lineChartOptions = computed(() => ({
  chart: { type: 'line', height: 320, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 350 } },
  stroke: { curve: 'smooth', width: 2.5 },
  colors: lineColors.value,
  markers: { size: 5, strokeColors: '#0b1120', strokeWidth: 2, hover: { size: 7 } },
  dataLabels: { enabled: false },
  legend: { show: true, labels: { colors: 'rgba(255,255,255,0.65)' }, markers: { width: 10, height: 10, radius: 9999 } },
  grid: { borderColor: 'rgba(255,255,255,0.07)' },
  xaxis: {
    type: 'datetime',
    labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, rotate: -35, datetimeFormatter: { year: 'yyyy', month: "MMM 'yy", day: 'MMM dd' } },
    axisBorder: { color: 'rgba(255,255,255,0.08)' },
    axisTicks: { color: 'rgba(255,255,255,0.08)' },
  },
  yaxis: {
    labels: { style: { colors: 'rgba(255,255,255,0.45)', fontSize: '10px', fontWeight: 600 }, formatter: (v) => v != null ? `${parseFloat(v).toFixed(1)} ${activeMetric.value?.unit || ''}`.trim() : '' },
  },
  tooltip: { theme: 'dark', shared: true, intersect: false },
  theme: { mode: 'dark' },
}))
</script>

<template>
  <Layout>
    <main class="compare-page">
      <header class="compare-toolbar">
        <div class="brand"><b>FM<span>TRX</span></b><small>Player Comparison</small></div>
        <button type="button" class="back-link" @click="router.push('/development')">← Back to Player Development</button>
      </header>

      <section v-if="errorMessage" class="state-card"><h1>Compare Players</h1><p>{{ errorMessage }}</p></section>

      <section v-else class="compare-shell">
        <aside class="metric-sidebar">
          <div class="sidebar-title">Compare On</div>
          <div v-for="group in categorizedMetrics" :key="group.label" class="sidebar-group">
            <div class="sidebar-group-label">{{ group.label }}</div>
            <button
              v-for="m in group.metrics" :key="m.key" class="sidebar-metric-btn"
              :class="{ active: activeMetricKey === m.key }"
              :style="activeMetricKey === m.key ? { borderColor: m.color, color: m.color } : {}"
              @click="activeMetricKey = m.key"
            >{{ m.label }}</button>
          </div>
        </aside>

        <div class="compare-main">
          <div class="compare-header">
            <div>
              <h2>{{ activeMetric?.label }}</h2>
              <span>{{ players.length }} Players · {{ activeMetric?.category }}</span>
            </div>
            <label class="date-range-select">Range
              <select v-model.number="activeDateRange">
                <option v-for="r in DATE_RANGES" :key="r.months" :value="r.months">{{ r.label }}</option>
              </select>
            </label>
          </div>

          <p v-if="loading" class="loading-text">Loading player data…</p>

          <template v-else>
            <div class="chart-card">
              <div class="chart-card-title">{{ activeMetric?.label }} Over Time</div>
              <div v-if="hasLineData" class="chart-area">
                <apexchart width="100%" type="line" height="320" :options="lineChartOptions" :series="lineSeries" :key="activeMetricKey + '_' + activeDateRange" />
              </div>
              <p v-else class="loading-text">No logged tests for this metric in the selected range.</p>
            </div>

            <div class="compare-header">
              <div><h3>Current Standing</h3></div>
              <div class="sort-control">
                <label>Sort by
                  <select v-model="sortBy">
                    <option value="percentile">Percentile</option>
                    <option value="value">Raw Value</option>
                    <option value="relative">Relative Strength</option>
                    <option value="gap">Gap to Goal</option>
                  </select>
                </label>
              </div>
            </div>

            <div class="bar-list">
              <div v-for="row in sortedRows" :key="row.id" class="bar-row">
                <div class="bar-row-label">
                  <span class="bar-row-name">{{ row.name }}</span>
                  <span v-if="row.failed" class="bar-row-flag error">Could not load</span>
                  <span v-else-if="!row.available" class="bar-row-flag">Needs Data</span>
                  <span v-else class="bar-row-flag">{{ row.label }}</span>
                </div>
                <div class="bar-track">
                  <div v-if="row.available" class="bar-fill" :style="{ width: `${Math.max(4, (row.percentile / maxPercentile) * 100)}%` }"></div>
                </div>
                <div class="bar-row-stats">
                  <b v-if="row.available">{{ row.percentile }}th</b>
                  <b v-else class="dim">—</b>
                  <span>{{ displayValue(row) }}</span>
                  <span v-if="row.relative != null">{{ row.relative.toFixed(2) }}× BW</span>
                </div>
              </div>
              <p v-if="!sortedRows.length" class="loading-text">No players to compare.</p>
            </div>
          </template>
        </div>
      </section>
    </main>
  </Layout>
</template>

<style scoped>
.compare-page {
  min-height: 100vh;
  padding: 12px;
  background:
    radial-gradient(circle at 88% 8%, #0c2a3b 0, transparent 25%),
    linear-gradient(145deg, #020a12, #031321 58%, #061b29);
  color: #edf5fa;
  font-family: Inter, system-ui, sans-serif;
}

.compare-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  max-width: 1200px;
  margin: 0 auto 12px;
  padding: 8px 12px;
  border: 1px solid #233f51;
  border-radius: 9px;
  background: #06131f;
}

.brand { display: flex; align-items: center; gap: 12px; }
.brand b { font-size: 17px; font-style: italic; }
.brand b span { color: #1ac2c0; }
.brand small { text-transform: uppercase; color: #91a5b3; font-size: 9px; letter-spacing: .12em; }

.back-link {
  padding: 7px 14px;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 7px;
  background: transparent;
  color: #91a5b3;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
}
.back-link:hover { color: #edf5fa; border-color: #ff2b4a; }

.state-card {
  max-width: 640px;
  margin: 60px auto;
  padding: 30px;
  text-align: center;
  border: 1px solid #233f51;
  border-radius: 12px;
  background: #06131f;
}

.compare-shell {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 16px;
  max-width: 1200px;
  margin: 0 auto;
  padding: 16px;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  background: #060b14;
}

.metric-sidebar { border-right: 1px solid rgba(255,255,255,0.08); padding-right: 14px; }
.sidebar-title { font-size: 9px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 10px; }
.sidebar-group { margin-bottom: 14px; }
.sidebar-group-label { font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #C00000; margin-bottom: 6px; }
.sidebar-metric-btn { display: block; width: 100%; text-align: left; font-size: 12px; font-weight: 600; padding: 6px 10px; margin-bottom: 3px; border-radius: 7px; border: 1px solid transparent; background: transparent; color: rgba(255,255,255,0.6); cursor: pointer; }
.sidebar-metric-btn:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.9); }
.sidebar-metric-btn.active { background: rgba(192,0,0,0.12); border-color: currentColor; font-weight: 800; }

.compare-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.compare-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.compare-header h2 { font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.compare-header span { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; }
.compare-header h3 { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.8); }
.sort-control label,
.date-range-select { font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 6px; }
.sort-control select,
.date-range-select select { background: #0b1120; border: 1px solid rgba(255,255,255,0.14); border-radius: 7px; color: white; font-size: 11px; padding: 6px 8px; }

.chart-card { background: linear-gradient(160deg, #0f1a2e 0%, #0b1120 100%); border: 1px solid rgba(192,0,0,0.18); border-radius: 14px; padding: 16px; }
.chart-card-title { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; }

.loading-text { color: rgba(255,255,255,0.4); font-size: 13px; padding: 20px 0; }

.bar-list { display: flex; flex-direction: column; gap: 12px; }
.bar-row { display: grid; grid-template-columns: 140px 1fr 140px; align-items: center; gap: 12px; }
.bar-row-label { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.bar-row-name { font-size: 13px; font-weight: 800; color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-row-flag { font-size: 9px; font-weight: 700; text-transform: uppercase; color: rgba(255,255,255,0.4); }
.bar-row-flag.error { color: #f87171; }
.bar-track { height: 20px; background: rgba(255,255,255,0.05); border-radius: 6px; overflow: hidden; }
.bar-fill { height: 100%; background: linear-gradient(90deg, #7f1d1d, #ff2b4a); border-radius: 6px; transition: width 0.4s ease; }
.bar-row-stats { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
.bar-row-stats b { font-size: 15px; font-weight: 900; color: #ff8798; }
.bar-row-stats b.dim { color: rgba(255,255,255,0.3); }
.bar-row-stats span { font-size: 10px; color: rgba(255,255,255,0.5); }

@media (max-width: 760px) {
  .compare-shell { grid-template-columns: 1fr; }
  .metric-sidebar { border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.08); padding-right: 0; padding-bottom: 12px; }
  .bar-row { grid-template-columns: 1fr; }
}
</style>
