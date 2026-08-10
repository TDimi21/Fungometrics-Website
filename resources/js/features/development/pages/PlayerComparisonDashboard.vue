<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { categorizeMetrics, benchmarkFor, METRICS } from '../lib/strengthMetricCatalog.js'

const route = useRoute()
const router = useRouter()
const { axiosGet } = useAxiosAuth()

const PLAYER_COLORS = ['#ff2b4a', '#3b82f6', '#10b981', '#f59e0b', '#a855f7', '#06b6d4', '#ec4899', '#84cc16']
// "All" is capped at 365d — PlayerIntelligenceService and GetFitness both hard-limit
// how far back the backend will look/return, so there's no true unlimited window.
const QUICK_RANGES = [
  { label: '1W', days: 7 },
  { label: '1M', days: 30 },
  { label: '3M', days: 90 },
  { label: '1Y', days: 365 },
  { label: 'All', days: 365 },
]
const TABLE_METRIC_KEYS = ['body_weight', 'bench_press', 'front_squat', 'dead_lift', 'power_clean']

const teamId = computed(() => String(route.query?.teamId || '').trim())
const playerIds = computed(() => String(route.query?.playerIds || '').split(',').map((id) => id.trim()).filter(Boolean))
const playerNames = computed(() => String(route.query?.names || '').split('|'))

const loading = ref(false)
const errorMessage = ref('')
const players = ref([]) // [{ id, name, color, intelligence, history, failed }]
const activeMetricKey = ref('bench_press')
const sortBy = ref('percentile') // percentile | value | relative | gap
const highlightedPlayerId = ref('')

// ── Date range: quick chips matching the command-center mockup, plus an
// optional custom from/to pair. ──
const activeQuickIndex = ref(1) // '1M' default
const useCustomRange = ref(false)
const customFrom = ref('')
const customTo = ref('')

const selectQuickRange = (index) => {
  useCustomRange.value = false
  activeQuickIndex.value = index
}

const dataWindowDays = computed(() => {
  if (useCustomRange.value && customFrom.value) {
    const from = new Date(customFrom.value)
    const to = customTo.value ? new Date(customTo.value) : new Date()
    const diffDays = Math.round((to - from) / 86400000)
    return Math.min(365, Math.max(7, diffDays || 30))
  }
  return QUICK_RANGES[activeQuickIndex.value]?.days || 30
})

const rangeCutoff = computed(() => {
  if (useCustomRange.value && customFrom.value) return new Date(customFrom.value)
  const d = new Date()
  d.setDate(d.getDate() - dataWindowDays.value)
  return d
})
const rangeEnd = computed(() => (useCustomRange.value && customTo.value) ? new Date(customTo.value) : null)
const withinRange = (dateStr) => {
  const d = new Date(dateStr)
  if (rangeCutoff.value && d < rangeCutoff.value) return false
  if (rangeEnd.value && d > rangeEnd.value) return false
  return true
}

const rangeLabel = computed(() => {
  if (useCustomRange.value && customFrom.value) {
    const fmt = (s) => new Date(s).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
    return `${fmt(customFrom.value)} – ${customTo.value ? fmt(customTo.value) : 'Today'}`
  }
  const r = QUICK_RANGES[activeQuickIndex.value]
  return r?.label === 'All' ? 'All Time (last 365 days)' : `Last ${r?.label}`
})

const categorizedMetrics = computed(() => categorizeMetrics())
const activeMetric = computed(() => METRICS.find((m) => m.key === activeMetricKey.value) || null)
const tableMetrics = computed(() => TABLE_METRIC_KEYS.map((key) => METRICS.find((m) => m.key === key)).filter(Boolean))

const loadComparison = async () => {
  errorMessage.value = ''
  if (!teamId.value || playerIds.value.length < 1) {
    errorMessage.value = 'Select a team and at least one player to compare.'
    players.value = []
    return
  }
  loading.value = true
  try {
    const results = await Promise.all(playerIds.value.map(async (id, index) => {
      const name = playerNames.value[index] || 'Player'
      const color = PLAYER_COLORS[index % PLAYER_COLORS.length]
      const [intelligenceRes, historyRes] = await Promise.all([
        axiosGet(`coach/teams/${teamId.value}/players/${id}/intelligence`, { days: dataWindowDays.value }).catch(() => null),
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
    if (!highlightedPlayerId.value || !results.some((p) => p.id === highlightedPlayerId.value)) {
      highlightedPlayerId.value = results[0]?.id || ''
    }
  } finally {
    loading.value = false
  }
}

watch([teamId, playerIds, dataWindowDays], loadComparison, { immediate: true })

// ── Inline roster picker — lets a coach add/remove players to compare
// directly on this page instead of going back to a separate team screen. ──
const rosterOptions = ref([])
const rosterLoaded = ref(false)
const showAddPlayer = ref(false)

const loadRoster = async () => {
  if (rosterLoaded.value || !teamId.value) return
  try {
    const { data } = await axiosGet(`coach/teams/${teamId.value}/player-development-board`)
    const rows = Array.isArray(data?.data) ? data.data : []
    rosterOptions.value = rows.map((row) => {
      const id = String(row?.id ?? row?.user_id ?? row?.player_id ?? row?.user?.id ?? '')
      if (!id) return null
      const profile = row?.profile || row?.user?.profile || {}
      const name = row?.name || row?.full_name || profile?.full_name
        || [profile?.first_name, profile?.last_name].filter(Boolean).join(' ') || `Player #${id}`
      return { id, name }
    }).filter(Boolean)
    rosterLoaded.value = true
  } catch {
    rosterOptions.value = []
  }
}

const availableToAdd = computed(() => rosterOptions.value.filter((o) => !playerIds.value.includes(o.id)))

const toggleAddPlayer = () => {
  showAddPlayer.value = !showAddPlayer.value
  if (showAddPlayer.value) loadRoster()
}

const addPlayer = (option) => {
  const newIds = [...playerIds.value, option.id]
  const newNames = [...playerNames.value.slice(0, playerIds.value.length), option.name]
  showAddPlayer.value = false
  router.replace({ query: { ...route.query, playerIds: newIds.join(','), names: newNames.join('|') } })
}

const removePlayer = (id) => {
  const index = playerIds.value.indexOf(id)
  if (index === -1 || playerIds.value.length <= 1) return
  const newIds = playerIds.value.filter((pid) => pid !== id)
  const newNames = playerNames.value.filter((_, i) => i !== index)
  router.replace({ query: { ...route.query, playerIds: newIds.join(','), names: newNames.join('|') } })
}

// ── Per-player current value / change for the active metric — drives both
// the chart legend and the "highlighted player" stat boxes. ──
const metricRowsFor = (player, limitToRange) => {
  if (!activeMetric.value) return []
  const key = activeMetric.value.key
  return (player.history || [])
    .filter((r) => r.fitness_date && r[key] != null && parseFloat(r[key]) > 0)
    .filter((r) => !limitToRange || withinRange(r.fitness_date))
    .slice()
    .sort((a, b) => new Date(a.fitness_date) - new Date(b.fitness_date))
}
const legendCurrent = (player) => {
  const rows = metricRowsFor(player, false)
  if (!rows.length) return 'No data'
  return `${parseFloat(rows[rows.length - 1][activeMetric.value.key]).toFixed(1)} ${activeMetric.value.unit}`.trim()
}
const legendChange = (player) => {
  const rows = metricRowsFor(player, true)
  if (rows.length < 2) return null
  const first = parseFloat(rows[0][activeMetric.value.key])
  const last = parseFloat(rows[rows.length - 1][activeMetric.value.key])
  return last - first
}
const legendChangeLabel = (player) => {
  const delta = legendChange(player)
  if (delta == null) return '—'
  return `${delta > 0 ? '+' : ''}${delta.toFixed(1)} ${activeMetric.value.unit}`.trim()
}
const legendChangeTone = (player) => {
  const delta = legendChange(player)
  if (!player || delta == null || delta === 0) return ''
  const better = activeMetric.value?.lowerBetter ? delta < 0 : delta > 0
  return better ? 'good' : 'bad'
}

// ── Multi-player line series, one line per player, filtered to range ──
const lineSeries = computed(() => {
  if (!activeMetric.value) return []
  return players.value.map((player) => ({
    name: player.name,
    data: metricRowsFor(player, true).map((r) => ({ x: new Date(r.fitness_date).getTime(), y: parseFloat(r[activeMetric.value.key]) })),
  }))
})
const hasLineData = computed(() => lineSeries.value.some((s) => s.data.length > 0))

// Emphasize the highlighted player's line and mute the rest — with several
// players plotted at once, same-weight lines read as noise, not a chart.
const isHighlighted = (player) => players.value.length === 1 || player.id === highlightedPlayerId.value
const withAlpha = (hex, alpha) => {
  const clean = (hex || '#888888').replace('#', '')
  const r = parseInt(clean.slice(0, 2), 16)
  const g = parseInt(clean.slice(2, 4), 16)
  const b = parseInt(clean.slice(4, 6), 16)
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}
const lineColors = computed(() => players.value.map((p) => isHighlighted(p) ? p.color : withAlpha(p.color, 0.35)))
const lineStrokeWidths = computed(() => players.value.map((p) => isHighlighted(p) ? 3.5 : 1.5))
const lineMarkerSizes = computed(() => players.value.map((p) => isHighlighted(p) ? 5 : 2.5))

const lineChartOptions = computed(() => ({
  chart: { type: 'line', height: 320, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 350 } },
  stroke: { curve: 'straight', width: lineStrokeWidths.value },
  colors: lineColors.value,
  markers: { size: lineMarkerSizes.value, strokeColors: '#0b1120', strokeWidth: 2, hover: { size: 7 } },
  dataLabels: { enabled: false },
  legend: { show: false },
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

// ── Comparison table — every column is either a raw benchmark value the
// backend already computed or an explicit "—", nothing fabricated. ──
const tableRows = computed(() => players.value.map((player) => {
  const metrics = player.intelligence?.benchmark_profile?.metrics
  const activeBenchmark = activeMetric.value ? benchmarkFor(metrics, activeMetric.value) : null
  const cells = tableMetrics.value.map((metric) => ({ key: metric.key, value: benchmarkFor(metrics, metric)?.raw_value ?? null }))
  return {
    id: player.id,
    name: player.name,
    color: player.color,
    failed: player.failed,
    cells,
    rawValue: activeBenchmark?.raw_value ?? null,
    relative: activeBenchmark?.relative_value ?? null,
    percentile: activeBenchmark?.percentile ?? null,
    gap: activeBenchmark?.gap ?? null,
  }
}))

const sortedTableRows = computed(() => {
  const rows = tableRows.value.slice()
  const key = { percentile: 'percentile', value: 'rawValue', relative: 'relative', gap: 'gap' }[sortBy.value]
  const higherFirst = sortBy.value !== 'gap' // smaller gap-to-goal is "better" / more urgent to lead with
  return rows.sort((a, b) => {
    if (a[key] == null && b[key] == null) return 0
    if (a[key] == null) return 1
    if (b[key] == null) return -1
    return higherFirst ? b[key] - a[key] : a[key] - b[key]
  })
})

const statusFor = (percentile) => {
  if (percentile == null) return { label: 'No Data', tone: 'muted' }
  if (percentile >= 75) return { label: 'On Track', tone: 'good' }
  if (percentile >= 40) return { label: 'Monitor', tone: 'warn' }
  return { label: 'Needs Work', tone: 'bad' }
}

// ── Highlighted-player stat boxes ──
const highlightedPlayer = computed(() => players.value.find((p) => p.id === highlightedPlayerId.value) || players.value[0] || null)
const highlightedBenchmark = computed(() => {
  if (!highlightedPlayer.value || !activeMetric.value) return null
  return benchmarkFor(highlightedPlayer.value.intelligence?.benchmark_profile?.metrics, activeMetric.value)
})
const benchmarkMedian = computed(() => highlightedBenchmark.value?.evidence?.age_percentile_anchors?.p50 ?? null)
const activeTierLabel = computed(() => {
  const pct = highlightedBenchmark.value?.percentile
  if (pct == null) return null
  if (pct >= 90) return 'Elite'
  if (pct >= 75) return 'Above Average'
  if (pct >= 25) return 'Average'
  if (pct >= 10) return 'Needs Development'
  return 'Needs Significant Development'
})

const groupStats = computed(() => {
  const rowsWithValue = tableRows.value.filter((r) => r.rawValue != null)
  if (!rowsWithValue.length) return { avg: null, best: null, bestName: null }
  const avg = rowsWithValue.reduce((sum, r) => sum + r.rawValue, 0) / rowsWithValue.length
  const bestRow = activeMetric.value?.lowerBetter
    ? rowsWithValue.reduce((a, b) => (b.rawValue < a.rawValue ? b : a))
    : rowsWithValue.reduce((a, b) => (b.rawValue > a.rawValue ? b : a))
  return { avg, best: bestRow?.rawValue ?? null, bestName: bestRow?.name ?? null }
})

// ── Benchmark bands — same anchor ladder as the single-player Chart tab,
// scoped to the highlighted player + active metric. ──
const benchmarkTiers = computed(() => {
  const anchors = highlightedBenchmark.value?.evidence?.age_percentile_anchors
  if (!anchors) return []
  const unit = activeMetric.value?.unit || ''
  const fmt = (v) => v != null ? `${parseFloat(v).toFixed(1)} ${unit}`.trim() : '—'
  const higherIsBetter = highlightedBenchmark.value?.evidence?.higher_is_better !== false
  return higherIsBetter ? [
    { label: 'Elite', range: `≥ ${fmt(anchors.p90)}`, pct: '90th+' },
    { label: 'Above Average', range: `${fmt(anchors.p75)} – ${fmt(anchors.p90)}`, pct: '75th–89th' },
    { label: 'Average', range: `${fmt(anchors.p25)} – ${fmt(anchors.p75)}`, pct: '25th–74th' },
    { label: 'Needs Development', range: `${fmt(anchors.p10)} – ${fmt(anchors.p25)}`, pct: '10th–24th' },
    { label: 'Needs Significant Dev.', range: `< ${fmt(anchors.p10)}`, pct: '< 10th' },
  ] : [
    { label: 'Elite', range: `≤ ${fmt(anchors.p10)}`, pct: '90th+' },
    { label: 'Above Average', range: `${fmt(anchors.p10)} – ${fmt(anchors.p25)}`, pct: '75th–89th' },
    { label: 'Average', range: `${fmt(anchors.p25)} – ${fmt(anchors.p75)}`, pct: '25th–74th' },
    { label: 'Needs Development', range: `${fmt(anchors.p75)} – ${fmt(anchors.p90)}`, pct: '10th–24th' },
    { label: 'Needs Significant Dev.', range: `> ${fmt(anchors.p90)}`, pct: '< 10th' },
  ]
})
const percentileBarPosition = computed(() => {
  const pct = highlightedBenchmark.value?.percentile
  return pct != null ? Math.min(100, Math.max(0, pct)) : null
})
</script>

<template>
  <Layout>
    <main class="compare-page">
      <header class="compare-toolbar">
        <div class="brand"><b>FM<span>TRX</span></b><small>Strength &amp; Weight Command Center</small></div>
        <button type="button" class="back-link" @click="router.push('/development')">← Back to Player Development</button>
      </header>

      <section v-if="errorMessage" class="state-card"><h1>Compare Players</h1><p>{{ errorMessage }}</p></section>

      <section v-else class="compare-shell">
        <aside class="metric-sidebar">
          <div class="sidebar-title">Date Range</div>
          <div class="range-control">
            <button
              v-for="(r, i) in QUICK_RANGES" :key="r.label" type="button" class="range-chip"
              :class="{ active: !useCustomRange && activeQuickIndex === i }"
              @click="selectQuickRange(i)"
            >{{ r.label }}</button>
            <button type="button" class="range-chip" :class="{ active: useCustomRange }" @click="useCustomRange = true">Custom</button>
          </div>
          <div v-if="useCustomRange" class="custom-range-row">
            <label>From<input type="date" v-model="customFrom" /></label>
            <label>To<input type="date" v-model="customTo" /></label>
          </div>

          <div class="sidebar-title" style="margin-top: 18px;">Compare On</div>
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
              <span>{{ players.length }} Players · {{ activeMetric?.category }} · {{ rangeLabel }}</span>
            </div>
          </div>

          <p v-if="loading" class="loading-text">Loading player data…</p>

          <template v-else>
            <div class="chart-card">
              <div class="chart-card-title">{{ activeMetric?.label }} Over Time</div>
              <div class="chart-body">
                <div class="chart-area">
                  <apexchart v-if="hasLineData" width="100%" type="line" height="320" :options="lineChartOptions" :series="lineSeries" :key="activeMetricKey + '_' + dataWindowDays + '_' + customFrom + '_' + customTo" />
                  <p v-else class="loading-text">No logged tests for this metric in the selected range.</p>
                </div>
                <aside class="chart-legend">
                  <div class="legend-title">Compare Athletes ({{ players.length }})</div>
                  <button
                    v-for="p in players" :key="p.id" type="button" class="legend-row"
                    :class="{ active: highlightedPlayerId === p.id }" @click="highlightedPlayerId = p.id"
                  >
                    <span class="legend-row-top">
                      <span class="legend-swatch" :style="{ background: p.color }"></span>
                      <b>{{ p.name }}</b>
                      <span v-if="players.length > 1" class="legend-remove" title="Remove from comparison" @click.stop="removePlayer(p.id)">×</span>
                    </span>
                    <span class="legend-row-bottom">
                      <small>{{ legendCurrent(p) }}</small>
                      <span class="legend-change" :class="legendChangeTone(p)">{{ legendChangeLabel(p) }}</span>
                    </span>
                  </button>

                  <div class="add-player-wrap">
                    <button type="button" class="add-player-btn" @click="toggleAddPlayer">+ Add Athlete to Compare</button>
                    <div v-if="showAddPlayer" class="add-player-list">
                      <p v-if="!availableToAdd.length" class="loading-text small">No more players on this team.</p>
                      <button v-for="o in availableToAdd" :key="o.id" type="button" class="add-player-option" @click="addPlayer(o)">{{ o.name }}</button>
                    </div>
                  </div>
                </aside>
              </div>
            </div>

            <div class="stat-box-row">
              <div class="stat-box">
                <span class="stat-label">Current</span>
                <b class="stat-value">{{ highlightedBenchmark?.raw_value != null ? `${highlightedBenchmark.raw_value} ${activeMetric?.unit}`.trim() : '—' }}</b>
                <span class="stat-sub">{{ highlightedPlayer?.name || '—' }}</span>
              </div>
              <div class="stat-box">
                <span class="stat-label">Change ({{ rangeLabel }})</span>
                <b class="stat-value" :class="legendChangeTone(highlightedPlayer)">{{ highlightedPlayer ? legendChangeLabel(highlightedPlayer) : '—' }}</b>
              </div>
              <div class="stat-box">
                <span class="stat-label">Group Avg</span>
                <b class="stat-value">{{ groupStats.avg != null ? `${groupStats.avg.toFixed(1)} ${activeMetric?.unit}`.trim() : '—' }}</b>
              </div>
              <div class="stat-box">
                <span class="stat-label">Best in Group</span>
                <b class="stat-value">{{ groupStats.best != null ? `${groupStats.best} ${activeMetric?.unit}`.trim() : '—' }}</b>
                <span class="stat-sub">{{ groupStats.bestName || '—' }}</span>
              </div>
              <div class="stat-box">
                <span class="stat-label">Benchmark (Median)</span>
                <b class="stat-value">{{ benchmarkMedian != null ? `${benchmarkMedian} ${activeMetric?.unit}`.trim() : '—' }}</b>
              </div>
              <div class="stat-box">
                <span class="stat-label">Percentile</span>
                <b class="stat-value accent">{{ highlightedBenchmark?.percentile != null ? `${highlightedBenchmark.percentile}th` : '—' }}</b>
                <span class="stat-sub">{{ activeTierLabel || 'Needs Data' }}</span>
              </div>
            </div>

            <div class="lower-grid">
              <div class="table-card">
                <div class="compare-header">
                  <h3>Player Comparison</h3>
                  <label class="sort-control">Sort by
                    <select v-model="sortBy">
                      <option value="percentile">Percentile</option>
                      <option value="value">Raw Value</option>
                      <option value="relative">Relative Strength</option>
                      <option value="gap">Gap to Goal</option>
                    </select>
                  </label>
                </div>
                <div class="table-scroll">
                  <table class="compare-table">
                    <thead>
                      <tr>
                        <th>Player</th>
                        <th v-for="m in tableMetrics" :key="m.key">{{ m.label }} ({{ m.unit }})</th>
                        <th>Percentile</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="row in sortedTableRows" :key="row.id"
                        :class="{ highlighted: row.id === highlightedPlayerId }" @click="highlightedPlayerId = row.id"
                      >
                        <td class="player-cell"><span class="table-swatch" :style="{ background: row.color }"></span>{{ row.name }}</td>
                        <td v-for="c in row.cells" :key="c.key">{{ c.value != null ? c.value : '—' }}</td>
                        <td>{{ row.percentile != null ? `${row.percentile}th` : '—' }}</td>
                        <td><span class="status-pill" :class="statusFor(row.percentile).tone">{{ statusFor(row.percentile).label }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                  <p v-if="!sortedTableRows.length" class="loading-text">No players to compare.</p>
                </div>
              </div>

              <div class="bands-card">
                <div class="chart-card-title">Benchmark Bands · {{ activeMetric?.label }}</div>
                <p v-if="!benchmarkTiers.length" class="loading-text small">Not enough peer data yet for {{ highlightedPlayer?.name || 'this player' }}.</p>
                <template v-else>
                  <table class="bands-table">
                    <thead><tr><th>Tier</th><th>Range</th><th>Pct.</th></tr></thead>
                    <tbody>
                      <tr v-for="t in benchmarkTiers" :key="t.label" :class="{ active: t.label === activeTierLabel }">
                        <td>{{ t.label }}</td><td>{{ t.range }}</td><td>{{ t.pct }}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div class="position-bar">
                    <div class="position-track">
                      <div class="position-fill" :style="{ width: `${percentileBarPosition}%` }"></div>
                      <div v-if="percentileBarPosition != null" class="position-marker" :style="{ left: `${percentileBarPosition}%` }"></div>
                    </div>
                    <span class="position-caption">
                      <template v-if="highlightedBenchmark?.percentile != null">{{ highlightedPlayer?.name }}: {{ highlightedBenchmark.raw_value }} {{ activeMetric?.unit }} · {{ highlightedBenchmark.percentile }}th Percentile</template>
                      <template v-else>No percentile yet</template>
                    </span>
                  </div>
                </template>
              </div>
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
  width: 100%;
  padding: 12px;
  overflow-x: hidden;
  background:
    radial-gradient(circle at 88% 8%, #0c2a3b 0, transparent 25%),
    linear-gradient(145deg, #020a12, #031321 58%, #061b29);
  color: #edf5fa;
  font-family: Inter, system-ui, sans-serif;
  box-sizing: border-box;
}
.compare-page * { box-sizing: border-box; }

.compare-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  max-width: 1280px;
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
  grid-template-columns: 210px minmax(0, 1fr);
  gap: 16px;
  max-width: 1280px;
  margin: 0 auto;
  padding: 16px;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  background: #060b14;
  min-width: 0;
}

.metric-sidebar { border-right: 1px solid rgba(255,255,255,0.08); padding-right: 14px; }
.sidebar-title { font-size: 9px; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 10px; }
.sidebar-group { margin-bottom: 14px; }
.sidebar-group-label { font-size: 9px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #C00000; margin-bottom: 6px; }
.sidebar-metric-btn { display: block; width: 100%; text-align: left; font-size: 12px; font-weight: 600; padding: 6px 10px; margin-bottom: 3px; border-radius: 7px; border: 1px solid transparent; background: transparent; color: rgba(255,255,255,0.6); cursor: pointer; }
.sidebar-metric-btn:hover { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.9); }
.sidebar-metric-btn.active { background: rgba(192,0,0,0.12); border-color: currentColor; font-weight: 800; }

.range-control { display: flex; flex-wrap: wrap; gap: 6px; }
.range-chip { padding: 6px 10px; border-radius: 999px; border: 1px solid rgba(255,255,255,0.14); background: transparent; color: rgba(255,255,255,0.6); font-size: 10px; font-weight: 800; letter-spacing: 0.03em; cursor: pointer; }
.range-chip:hover { color: white; border-color: rgba(255,255,255,0.3); }
.range-chip.active { background: #C00000; border-color: #C00000; color: white; }
.custom-range-row { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
.custom-range-row label { display: flex; flex-direction: column; gap: 3px; font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); }
.custom-range-row input[type="date"] { background: #0b1120; border: 1px solid rgba(255,255,255,0.14); border-radius: 7px; color: white; font-size: 11px; padding: 6px 8px; color-scheme: dark; }

.compare-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.compare-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.compare-header h2 { font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
.compare-header span { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; }
.compare-header h3 { font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.8); }
.sort-control label { font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); display: flex; align-items: center; gap: 6px; }
.sort-control select { background: #0b1120; border: 1px solid rgba(255,255,255,0.14); border-radius: 7px; color: white; font-size: 11px; padding: 6px 8px; }

.chart-card { background: linear-gradient(160deg, #0f1a2e 0%, #0b1120 100%); border: 1px solid rgba(192,0,0,0.18); border-radius: 14px; padding: 16px; }
.chart-card-title { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(255,255,255,0.4); margin-bottom: 10px; }
.chart-body { display: grid; grid-template-columns: minmax(0, 1fr) 200px; gap: 16px; align-items: start; }
.chart-area { min-width: 0; }

.chart-legend { display: flex; flex-direction: column; gap: 6px; border-left: 1px solid rgba(255,255,255,0.08); padding-left: 14px; max-height: 320px; overflow-y: auto; }
.legend-title { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: rgba(255,255,255,0.4); margin-bottom: 2px; }
.legend-row { display: flex; flex-direction: column; gap: 3px; width: 100%; padding: 7px 8px; border-radius: 8px; border: 1px solid transparent; background: transparent; cursor: pointer; text-align: left; }
.legend-row:hover { background: rgba(255,255,255,0.04); }
.legend-row.active { border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.05); }
.legend-row-top { display: flex; align-items: center; gap: 7px; min-width: 0; }
.legend-row-top b { font-size: 12px; font-weight: 800; color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
.legend-swatch { width: 9px; height: 9px; border-radius: 999px; flex: none; }
.legend-row-bottom { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-left: 16px; }
.legend-row-bottom small { font-size: 10px; color: rgba(255,255,255,0.45); }
.legend-change { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.4); flex: none; }
.legend-change.good { color: #34d399; }
.legend-change.bad { color: #f87171; }
.legend-remove { flex: none; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; border-radius: 999px; color: rgba(255,255,255,0.35); font-size: 13px; line-height: 1; }
.legend-remove:hover { color: #f87171; background: rgba(248,113,113,0.12); }

.add-player-wrap { margin-top: 6px; position: relative; }
.add-player-btn { width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px dashed rgba(255,255,255,0.2); background: transparent; color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 700; cursor: pointer; }
.add-player-btn:hover { color: white; border-color: #C00000; }
.add-player-list { margin-top: 6px; max-height: 180px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; background: #0b1120; }
.add-player-option { display: block; width: 100%; text-align: left; padding: 7px 10px; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.75); background: transparent; border: 0; cursor: pointer; }
.add-player-option:hover { background: rgba(255,255,255,0.06); color: white; }

.loading-text { color: rgba(255,255,255,0.4); font-size: 13px; padding: 20px 0; }
.loading-text.small { font-size: 11px; padding: 10px 0; }

.stat-box-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
.stat-box { display: flex; flex-direction: column; gap: 4px; padding: 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: #0b1120; min-width: 0; }
.stat-label { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.4); }
.stat-value { font-size: 17px; font-weight: 900; color: white; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stat-value.accent { color: #ff8798; }
.stat-value.good { color: #34d399; }
.stat-value.bad { color: #f87171; }
.stat-sub { font-size: 10px; color: rgba(255,255,255,0.4); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.lower-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; align-items: start; }
.table-card, .bands-card { background: linear-gradient(160deg, #0f1a2e 0%, #0b1120 100%); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 16px; }

.table-scroll { overflow-x: auto; margin-top: 10px; }
.compare-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.compare-table th { text-align: left; padding: 8px 10px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.1); white-space: nowrap; }
.compare-table td { padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.85); white-space: nowrap; }
.compare-table tbody tr { cursor: pointer; }
.compare-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.compare-table tbody tr.highlighted { background: rgba(192,0,0,0.1); }
.player-cell { display: flex; align-items: center; gap: 8px; font-weight: 800; color: white; }
.table-swatch { width: 8px; height: 8px; border-radius: 999px; flex: none; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
.status-pill.good { background: rgba(52,211,153,0.15); color: #34d399; }
.status-pill.warn { background: rgba(251,191,36,0.15); color: #fbbf24; }
.status-pill.bad { background: rgba(248,113,113,0.15); color: #f87171; }
.status-pill.muted { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.4); }

.bands-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 10px; }
.bands-table th { text-align: left; padding: 6px 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.1); }
.bands-table td { padding: 7px 8px; color: rgba(255,255,255,0.75); border-bottom: 1px solid rgba(255,255,255,0.05); }
.bands-table tr.active td { color: white; font-weight: 800; background: rgba(192,0,0,0.1); }

.position-bar { margin-top: 14px; }
.position-track { position: relative; height: 10px; border-radius: 999px; background: linear-gradient(90deg, #f87171, #fbbf24, #34d399, #3b82f6); overflow: visible; }
.position-fill { display: none; }
.position-marker { position: absolute; top: -4px; width: 3px; height: 18px; background: white; border-radius: 2px; transform: translateX(-1.5px); box-shadow: 0 0 0 2px rgba(0,0,0,0.4); }
.position-caption { display: block; margin-top: 10px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.7); }

@media (max-width: 1180px) {
  .lower-grid { grid-template-columns: 1fr; }
  .chart-body { grid-template-columns: 1fr; }
  .chart-legend { border-left: 0; border-top: 1px solid rgba(255,255,255,0.08); padding-left: 0; padding-top: 12px; max-height: none; }
}

@media (max-width: 900px) {
  .compare-shell { grid-template-columns: 1fr; }
  .metric-sidebar { border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.08); padding-right: 0; padding-bottom: 12px; }
}

@media (max-width: 480px) {
  .stat-box-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
