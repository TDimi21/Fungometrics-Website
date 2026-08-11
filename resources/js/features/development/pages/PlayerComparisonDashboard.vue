<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { categorizeMetrics, benchmarkFor, METRICS, positiveMetricNumber } from '../lib/strengthMetricCatalog.js'
import { getPath } from '../lib/assessmentItemCatalog.js'

const route = useRoute()
const router = useRouter()
const { axiosGet } = useAxiosAuth()
const props = defineProps({
  embedded: { type: Boolean, default: false },
  teamId: { type: [String, Number], default: '' },
  teamName: { type: String, default: '' },
  playerOptions: { type: Array, default: () => [] },
})
const pageWrapper = computed(() => props.embedded ? 'div' : Layout)

const PLAYER_COLORS = ['#ff2b4a', '#3b82f6', '#20c878', '#a855f7', '#f59e0b', '#06b6d4', '#ec4899', '#84cc16']
const QUICK_RANGES = [
  { label: '1W', days: 7 },
  { label: '1M', days: 30 },
  { label: '3M', days: 90 },
  { label: '1Y', days: 365 },
  { label: 'All', days: 365 },
]
const TABLE_METRIC_KEYS = ['body_weight', 'bench_press', 'front_squat', 'dead_lift', 'power_clean']
const STRENGTH_METRIC_KEYS = ['bench_press', 'front_squat', 'back_squat', 'dead_lift', 'power_clean']
const INTERVALS = ['daily', 'weekly', 'monthly']

const embeddedSelection = ref([])
watch(
  () => props.playerOptions,
  (options) => {
    if (!props.embedded) return
    embeddedSelection.value = (options || []).map((option) => String(option?.id || '')).filter(Boolean)
  },
  { immediate: true },
)
const teamId = computed(() => String(props.teamId || route.query?.teamId || '').trim())
const teamName = computed(() => String(props.teamName || route.query?.teamName || 'Current Team'))
const playerIds = computed(() => props.embedded
  ? embeddedSelection.value
  : String(route.query?.playerIds || '').split(',').map((id) => id.trim()).filter(Boolean))
const playerNames = computed(() => props.embedded
  ? playerIds.value.map((id) => props.playerOptions.find((option) => String(option?.id) === id)?.name || 'Player')
  : String(route.query?.names || '').split('|'))

const loading = ref(false)
const errorMessage = ref('')
const players = ref([])
const activeMetricKey = ref('body_weight')
const sortBy = ref('percentile')
const highlightedPlayerId = ref('')
const chartInterval = ref('daily')
const showAllAthletes = ref(false)
const selectedChartMetricKeys = ref(['body_weight'])
const showMetricPicker = ref(false)
const metricPickerMessage = ref('')
const selectedMetricCategory = ref('Body')
const metricPickerCategory = ref('Body')
const ageGroupFilter = ref('all')
const weightClassFilter = ref('all')
const activeQuickIndex = ref(1)
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
    return Math.min(365, Math.max(7, Math.round((to - from) / 86400000) || 30))
  }
  return QUICK_RANGES[activeQuickIndex.value]?.days || 30
})

const rangeCutoff = computed(() => {
  if (useCustomRange.value && customFrom.value) return new Date(customFrom.value)
  const date = new Date()
  date.setDate(date.getDate() - dataWindowDays.value)
  return date
})
const rangeEnd = computed(() => useCustomRange.value && customTo.value ? new Date(customTo.value) : null)
const withinRange = (dateString) => {
  const date = new Date(dateString)
  return !(rangeCutoff.value && date < rangeCutoff.value) && !(rangeEnd.value && date > rangeEnd.value)
}
const rangeLabel = computed(() => {
  if (useCustomRange.value && customFrom.value) {
    const format = (date) => new Date(date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
    return `${format(customFrom.value)} – ${customTo.value ? format(customTo.value) : 'Today'}`
  }
  const range = QUICK_RANGES[activeQuickIndex.value]
  return range?.label === 'All' ? 'Last 365 days' : `Last ${range?.label}`
})

const categorizedMetrics = computed(() => categorizeMetrics())
const selectedMetricGroup = computed(() => categorizedMetrics.value.find((group) => group.label === selectedMetricCategory.value) || categorizedMetrics.value[0])
const metricPickerGroup = computed(() => categorizedMetrics.value.find((group) => group.label === metricPickerCategory.value) || categorizedMetrics.value[0])
const activeMetric = computed(() => METRICS.find((metric) => metric.key === activeMetricKey.value) || null)
const selectedChartMetrics = computed(() => selectedChartMetricKeys.value.map((key) => METRICS.find((metric) => metric.key === key)).filter(Boolean))
const multiMetricChart = computed(() => !showAllAthletes.value && selectedChartMetrics.value.length > 1)
const tableMetrics = computed(() => TABLE_METRIC_KEYS.map((key) => METRICS.find((metric) => metric.key === key)).filter(Boolean))
const strengthMetrics = computed(() => STRENGTH_METRIC_KEYS.map((key) => METRICS.find((metric) => metric.key === key)).filter(Boolean))
const comparisonContext = (player) => player?.intelligence?.benchmark_profile?.comparison_context || {}
const formatFilterLabel = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
const ageGroupOptions = computed(() => [...new Set(players.value.map((player) => comparisonContext(player).age_group).filter(Boolean))])
const weightClassOptions = computed(() => [...new Set(players.value.map((player) => comparisonContext(player).bodyweight_band).filter(Boolean))])
const comparedPlayers = computed(() => players.value.filter((player) => {
  const context = comparisonContext(player)
  return (ageGroupFilter.value === 'all' || context.age_group === ageGroupFilter.value)
    && (weightClassFilter.value === 'all' || context.bodyweight_band === weightClassFilter.value)
}))

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
      const [intelligenceResponse, historyResponse, assessmentResponse] = await Promise.all([
        axiosGet(`coach/teams/${teamId.value}/players/${id}/intelligence`, { days: dataWindowDays.value }).catch(() => null),
        axiosGet(`player/fitness/${id}`).catch(() => null),
        axiosGet(`assessments/player/${id}`).catch(() => null),
      ])
      return {
        id,
        name: playerNames.value[index] || `Player ${index + 1}`,
        color: PLAYER_COLORS[index % PLAYER_COLORS.length],
        intelligence: intelligenceResponse?.data?.data || intelligenceResponse?.data || null,
        history: Array.isArray(historyResponse?.data?.data) ? historyResponse.data.data : [],
        assessmentHistory: Array.isArray(assessmentResponse?.data?.data) ? assessmentResponse.data.data : [],
        failed: !intelligenceResponse && !historyResponse && !assessmentResponse,
      }
    }))
    players.value = results
    if (!results.some((player) => player.id === highlightedPlayerId.value)) {
      const metricKey = activeMetricKey.value
      const richestHistory = results.slice().sort((a, b) =>
        (b.history || []).filter((row) => positiveMetricNumber(row?.[metricKey]) !== null).length
        - (a.history || []).filter((row) => positiveMetricNumber(row?.[metricKey]) !== null).length
      )[0]
      highlightedPlayerId.value = richestHistory?.id || results[0]?.id || ''
    }
  } catch {
    errorMessage.value = 'The comparison data could not be loaded. Please refresh and try again.'
  } finally {
    loading.value = false
  }
}

watch([teamId, playerIds, dataWindowDays], loadComparison, { immediate: true })
watch(comparedPlayers, (rows) => {
  if (!rows.some((player) => player.id === highlightedPlayerId.value)) highlightedPlayerId.value = rows[0]?.id || ''
})

const rosterOptions = ref([])
const rosterLoaded = ref(false)
const showAddPlayer = ref(false)
const loadRoster = async () => {
  if (rosterLoaded.value || !teamId.value) return
  if (props.embedded) {
    rosterOptions.value = props.playerOptions.map((option) => ({ id: String(option.id), name: option.name }))
    rosterLoaded.value = true
    return
  }
  try {
    const { data } = await axiosGet(`coach/teams/${teamId.value}/player-development-board`)
    const rows = Array.isArray(data?.data) ? data.data : []
    rosterOptions.value = rows.map((row) => {
      const id = String(row?.id ?? row?.user_id ?? row?.player_id ?? row?.user?.id ?? '')
      if (!id) return null
      const profile = row?.profile || row?.user?.profile || {}
      const name = row?.name || row?.full_name || profile?.full_name || [profile?.first_name, profile?.last_name].filter(Boolean).join(' ') || `Player #${id}`
      return { id, name }
    }).filter(Boolean)
    rosterLoaded.value = true
  } catch {
    rosterOptions.value = []
  }
}
const availableToAdd = computed(() => rosterOptions.value.filter((option) => !playerIds.value.includes(option.id)))
const toggleAddPlayer = () => {
  showAddPlayer.value = !showAddPlayer.value
  if (showAddPlayer.value) loadRoster()
}
const addPlayer = (option) => {
  if (props.embedded) {
    embeddedSelection.value = [...embeddedSelection.value, option.id]
    showAddPlayer.value = false
    return
  }
  const ids = [...playerIds.value, option.id]
  const names = [...playerNames.value.slice(0, playerIds.value.length), option.name]
  showAddPlayer.value = false
  router.replace({ query: { ...route.query, playerIds: ids.join(','), names: names.join('|') } })
}
const removePlayer = (id) => {
  const index = playerIds.value.indexOf(id)
  if (index === -1 || playerIds.value.length <= 1) return
  if (props.embedded) {
    embeddedSelection.value = embeddedSelection.value.filter((playerId) => playerId !== id)
    return
  }
  router.replace({
    query: {
      ...route.query,
      playerIds: playerIds.value.filter((playerId) => playerId !== id).join(','),
      names: playerNames.value.filter((_, playerIndex) => playerIndex !== index).join('|'),
    },
  })
}

const metricBenchmark = (player, metric) => benchmarkFor(player?.intelligence?.benchmark_profile?.metrics, metric)
const metricRowsFor = (player, limitToRange = true, metric = activeMetric.value) => {
  if (!metric?.key) return []
  const assessmentSource = metric.source === 'assessment'
  const rows = assessmentSource ? player?.assessmentHistory : player?.history
  const dateField = assessmentSource ? 'assessment_date' : 'fitness_date'
  return (rows || [])
    .map((row) => ({ recordedAt: row?.[dateField], value: positiveMetricNumber(getPath(row, metric.key)) }))
    .filter((row) => row.recordedAt && row.value !== null)
    .filter((row) => !limitToRange || withinRange(row.recordedAt))
    .sort((a, b) => new Date(a.recordedAt) - new Date(b.recordedAt))
}
watch(activeMetricKey, () => {
  selectedChartMetricKeys.value = [activeMetricKey.value]
  metricPickerMessage.value = ''
  if (metricRowsFor(comparedPlayers.value.find((player) => player.id === highlightedPlayerId.value)).length) return
  const best = comparedPlayers.value.slice().sort((a, b) => metricRowsFor(b).length - metricRowsFor(a).length)[0]
  highlightedPlayerId.value = best?.id || highlightedPlayerId.value
})

const toggleChartMetric = (metric) => {
  metricPickerMessage.value = ''
  const selected = selectedChartMetricKeys.value
  if (selected.includes(metric.key)) {
    if (metric.key === activeMetricKey.value) {
      metricPickerMessage.value = 'The primary dashboard metric stays selected.'
      return
    }
    selectedChartMetricKeys.value = selected.filter((key) => key !== metric.key)
    return
  }
  if (selected.length >= 4) {
    metricPickerMessage.value = 'Select up to four metrics so the chart stays readable.'
    return
  }
  selectedChartMetricKeys.value = [...selected, metric.key]
}

const periodStart = (value, interval) => {
  const date = new Date(value)
  date.setHours(0, 0, 0, 0)
  if (interval === 'weekly') date.setDate(date.getDate() - ((date.getDay() + 6) % 7))
  if (interval === 'monthly') date.setDate(1)
  return date.getTime()
}
const aggregateRows = (rows, metric = activeMetric.value) => {
  const groups = new Map()
  rows.forEach((row) => {
    const bucket = periodStart(row.recordedAt, chartInterval.value)
    const values = groups.get(bucket) || []
    values.push(Number(row.value))
    groups.set(bucket, values)
  })
  return [...groups.entries()].map(([x, values]) => ({
    x,
    y: Number((values.reduce((total, value) => total + value, 0) / values.length).toFixed(2)),
  }))
}

const legendCurrentValue = (player) => {
  const benchmark = metricBenchmark(player, activeMetric.value)
  const benchmarkValue = positiveMetricNumber(benchmark?.raw_value)
  if (benchmarkValue !== null) return benchmarkValue
  const rows = metricRowsFor(player, false)
  return rows.length ? Number(rows[rows.length - 1].value) : null
}
const displayMetricValue = (value, metric = activeMetric.value, precision = 1) => positiveMetricNumber(value) === null
  ? '—'
  : `${positiveMetricNumber(value).toFixed(precision)} ${metric?.unit || ''}`.trim()
const legendCurrent = (player) => displayMetricValue(legendCurrentValue(player))
const legendChange = (player) => {
  const rows = metricRowsFor(player)
  if (rows.length < 2) return null
  return Number(rows[rows.length - 1].value) - Number(rows[0].value)
}
const legendChangeLabel = (player) => {
  const change = legendChange(player)
  return change == null ? '—' : `${change > 0 ? '+' : ''}${change.toFixed(1)} ${activeMetric.value?.unit || ''}`.trim()
}
const legendChangeTone = (player) => {
  const change = legendChange(player)
  if (change == null || change === 0) return ''
  return (activeMetric.value?.lowerBetter ? change < 0 : change > 0) ? 'good' : 'bad'
}

const chartPlayers = computed(() => {
  if (showAllAthletes.value) return comparedPlayers.value
  const focused = comparedPlayers.value.find((player) => player.id === highlightedPlayerId.value)
  return focused ? [focused] : comparedPlayers.value.slice(0, 1)
})
const lineSeries = computed(() => {
  if (showAllAthletes.value) {
    return chartPlayers.value.map((player) => ({
      name: player.name,
      data: aggregateRows(metricRowsFor(player), activeMetric.value),
    }))
  }
  const player = chartPlayers.value[0]
  if (!player) return []
  return selectedChartMetrics.value.map((metric) => ({
    name: metric.label,
    data: aggregateRows(metricRowsFor(player, true, metric), metric),
  }))
})
const hasLineData = computed(() => lineSeries.value.some((series) => series.data.length))
const isHighlighted = (player) => chartPlayers.value.length === 1 || player.id === highlightedPlayerId.value
const withAlpha = (hex, alpha) => {
  const clean = (hex || '#888888').replace('#', '')
  return `rgba(${parseInt(clean.slice(0, 2), 16)}, ${parseInt(clean.slice(2, 4), 16)}, ${parseInt(clean.slice(4, 6), 16)}, ${alpha})`
}
const lineColors = computed(() => showAllAthletes.value
  ? chartPlayers.value.map((player) => isHighlighted(player) ? player.color : withAlpha(player.color, 0.48))
  : selectedChartMetrics.value.map((metric) => metric.color))
const chartBenchmarkAnnotations = computed(() => {
  if (showAllAthletes.value || multiMetricChart.value) return []
  const benchmark = highlightedBenchmark.value
  const anchors = benchmark?.evidence?.age_percentile_anchors
  if (!anchors) return []
  const definitions = [
    { value: anchors.p50, text: 'AVERAGE (50–74%)', color: '#7f8da1' },
    { value: anchors.p75, text: 'ABOVE AVG (75–89%)', color: '#f5a623' },
    { value: anchors.p95 ?? anchors.p90, text: 'ELITE (90–100%)', color: '#28d17c' },
    { value: benchmark?.goal, text: 'GOAL', color: '#36d6a0' },
  ]
  const seen = new Set()
  return definitions.filter((definition) => {
    const value = positiveMetricNumber(definition.value)
    if (value === null || seen.has(value)) return false
    seen.add(value)
    return true
  }).map((definition) => ({
    y: Number(definition.value),
    borderColor: definition.color,
    strokeDashArray: definition.text === 'GOAL' ? 6 : 4,
    label: {
      borderColor: definition.color,
      position: 'right',
      offsetX: -4,
      text: definition.text,
      style: { background: '#10192a', color: definition.color, fontSize: '9px', fontWeight: 800 },
    },
  }))
})
const chartBounds = computed(() => {
  const values = [
    ...lineSeries.value.flatMap((series) => series.data.map((point) => Number(point.y))),
    ...chartBenchmarkAnnotations.value.map((annotation) => Number(annotation.y)),
  ].filter(Number.isFinite)
  if (!values.length) return { min: undefined, max: undefined }
  const low = Math.min(...values)
  const high = Math.max(...values)
  const spread = high - low
  const padding = spread > 0 ? spread * 0.12 : Math.max(Math.abs(high) * 0.1, activeMetric.value?.unit === 's' ? 0.25 : 5)
  const precision = activeMetric.value?.unit === 's' ? 10 : 1
  const paddedMinimum = Math.floor((low - padding) * precision) / precision
  return {
    min: multiMetricChart.value ? paddedMinimum : Math.max(0, paddedMinimum),
    max: Math.ceil((high + padding) * precision) / precision,
  }
})
const multiMetricYAxes = computed(() => {
  const unitOrder = [...new Set(selectedChartMetrics.value.map((metric) => metric.unit || 'value'))]
  const boundsByUnit = new Map(unitOrder.map((unit) => {
    const seriesIndexes = selectedChartMetrics.value.map((metric, index) => metric.unit === unit ? index : -1).filter((index) => index >= 0)
    const values = seriesIndexes.flatMap((index) => lineSeries.value[index]?.data?.map((point) => Number(point.y)) || []).filter(Number.isFinite)
    if (!values.length) return [unit, { min: undefined, max: undefined }]
    const low = Math.min(...values)
    const high = Math.max(...values)
    const spread = high - low
    const padding = spread > 0 ? spread * 0.12 : Math.max(Math.abs(high) * 0.1, unit === 's' ? 0.25 : 5)
    const precision = unit === 's' ? 100 : 10
    return [unit, {
      min: Math.max(0, Math.floor((low - padding) * precision) / precision),
      max: Math.ceil((high + padding) * precision) / precision,
    }]
  }))

  return selectedChartMetrics.value.map((metric, index) => {
    const unit = metric.unit || 'value'
    const unitIndex = unitOrder.indexOf(unit)
    const firstForUnit = selectedChartMetrics.value.findIndex((candidate) => (candidate.unit || 'value') === unit) === index
    const bounds = boundsByUnit.get(unit) || {}
    return {
      seriesName: metric.label,
      show: firstForUnit,
      opposite: unitIndex % 2 === 1,
      min: bounds.min,
      max: bounds.max,
      tickAmount: 5,
      forceNiceScale: false,
      axisBorder: { show: firstForUnit, color: metric.color },
      axisTicks: { show: firstForUnit, color: metric.color },
      title: { text: firstForUnit ? unit : '', style: { color: metric.color, fontSize: '9px', fontWeight: 800 } },
      labels: {
        show: firstForUnit,
        style: { colors: metric.color, fontSize: '9px', fontWeight: 700 },
        formatter: (value) => `${Number(value).toFixed(unit === 's' ? 2 : 1)} ${unit}`,
      },
    }
  })
})
const lineChartOptions = computed(() => ({
  chart: { type: 'line', height: 340, background: 'transparent', toolbar: { show: false }, zoom: { enabled: false }, animations: { enabled: true, speed: 500, animateGradually: { enabled: true, delay: 80 }, dynamicAnimation: { enabled: true, speed: 350 } } },
  annotations: { yaxis: chartBenchmarkAnnotations.value },
  stroke: { curve: 'smooth', lineCap: 'round', width: showAllAthletes.value ? chartPlayers.value.map((player) => isHighlighted(player) ? 3.5 : 2) : lineSeries.value.map(() => 3.5) },
  colors: lineColors.value,
  markers: { size: lineSeries.value.map(() => 5), strokeColors: '#07101f', strokeWidth: 2, hover: { size: 7 } },
  dataLabels: { enabled: false },
  legend: { show: false },
  grid: { borderColor: 'rgba(148,163,184,.13)', strokeDashArray: 0, padding: { left: 8, right: 18, top: 8, bottom: 0 } },
  xaxis: {
    type: 'datetime',
    tickAmount: 8,
    labels: { hideOverlappingLabels: true, style: { colors: '#8794a7', fontSize: '10px', fontWeight: 650 }, datetimeFormatter: { year: 'yyyy', month: 'MMM dd', day: 'MMM dd' } },
    axisBorder: { color: 'rgba(148,163,184,.12)' },
    axisTicks: { color: 'rgba(148,163,184,.12)' },
  },
  yaxis: multiMetricChart.value ? multiMetricYAxes.value : { min: chartBounds.value.min, max: chartBounds.value.max, tickAmount: 5, forceNiceScale: false, labels: { style: { colors: '#8794a7', fontSize: '10px', fontWeight: 650 }, formatter: (value) => displayMetricValue(value) } },
  tooltip: {
    theme: 'dark',
    shared: showAllAthletes.value || multiMetricChart.value,
    intersect: !(showAllAthletes.value || multiMetricChart.value),
    followCursor: false,
    x: { format: 'dd MMM yyyy' },
    marker: { show: true },
    y: {
      formatter: (value, context) => {
        if (!multiMetricChart.value) return displayMetricValue(value)
        const metric = selectedChartMetrics.value[context?.seriesIndex]
        return displayMetricValue(value, metric, metric?.unit === 's' ? 2 : 1)
      },
    },
  },
  theme: { mode: 'dark' },
}))

const tableRows = computed(() => comparedPlayers.value.map((player) => {
  const activeBenchmark = metricBenchmark(player, activeMetric.value)
  return {
    id: player.id,
    name: player.name,
    color: player.color,
    failed: player.failed,
    cells: tableMetrics.value.map((metric) => ({ key: metric.key, value: positiveMetricNumber(metricBenchmark(player, metric)?.raw_value) })),
    rawValue: positiveMetricNumber(activeBenchmark?.raw_value) ?? legendCurrentValue(player),
    relative: positiveMetricNumber(activeBenchmark?.relative_value),
    percentile: activeBenchmark?.percentile ?? null,
    gap: activeBenchmark?.gap ?? null,
  }
}))
const sortedTableRows = computed(() => {
  const key = { percentile: 'percentile', value: 'rawValue', relative: 'relative', gap: 'gap' }[sortBy.value]
  return tableRows.value.slice().sort((a, b) => {
    if (a[key] == null && b[key] == null) return 0
    if (a[key] == null) return 1
    if (b[key] == null) return -1
    return sortBy.value === 'gap' ? a[key] - b[key] : b[key] - a[key]
  })
})
const statusFor = (percentile) => {
  if (percentile == null) return { label: 'No Data', tone: 'muted' }
  if (percentile >= 75) return { label: 'On Track', tone: 'good' }
  if (percentile >= 40) return { label: 'Monitor', tone: 'warn' }
  return { label: 'Needs Work', tone: 'bad' }
}

const highlightedPlayer = computed(() => comparedPlayers.value.find((player) => player.id === highlightedPlayerId.value) || comparedPlayers.value[0] || null)
const highlightedBenchmark = computed(() => metricBenchmark(highlightedPlayer.value, activeMetric.value))
const benchmarkMedian = computed(() => highlightedBenchmark.value?.evidence?.age_percentile_anchors?.p50 ?? null)
const activeTierLabel = computed(() => {
  const percentile = highlightedBenchmark.value?.percentile
  if (percentile == null) return null
  if (percentile >= 90) return 'Elite'
  if (percentile >= 75) return 'Above Average'
  if (percentile >= 25) return 'Average'
  if (percentile >= 10) return 'Needs Development'
  return 'Needs Significant Development'
})
const groupStats = computed(() => {
  const rows = tableRows.value.filter((row) => row.rawValue != null)
  if (!rows.length) return { avg: null, best: null, bestName: null }
  const avg = rows.reduce((total, row) => total + Number(row.rawValue), 0) / rows.length
  const best = rows.reduce((current, row) => {
    if (!current) return row
    return activeMetric.value?.lowerBetter
      ? (Number(row.rawValue) < Number(current.rawValue) ? row : current)
      : (Number(row.rawValue) > Number(current.rawValue) ? row : current)
  }, null)
  return { avg, best: best?.rawValue ?? null, bestName: best?.name ?? null }
})
const dataReadiness = computed(() => {
  const metrics = highlightedPlayer.value?.intelligence?.benchmark_profile?.metrics || []
  const populated = METRICS.filter((metric) => positiveMetricNumber(benchmarkFor(metrics, metric)?.raw_value) !== null).length
  return Math.round((populated / METRICS.length) * 100)
})

const benchmarkTiers = computed(() => {
  const anchors = highlightedBenchmark.value?.evidence?.age_percentile_anchors
  if (!anchors) return []
  const format = (value) => displayMetricValue(value)
  const p5 = anchors.p5 ?? anchors.p10
  const p25 = anchors.p25
  const p75 = anchors.p75
  const p95 = anchors.p95 ?? anchors.p90
  const higherIsBetter = highlightedBenchmark.value?.evidence?.higher_is_better !== false
  return higherIsBetter ? [
    { label: 'Elite', range: `≥ ${format(p95)}`, percentile: '90th+' },
    { label: 'Above Average', range: `${format(p75)} – ${format(p95)}`, percentile: '75th–89th' },
    { label: 'Average', range: `${format(p25)} – ${format(p75)}`, percentile: '25th–74th' },
    { label: 'Needs Development', range: `${format(p5)} – ${format(p25)}`, percentile: '10th–24th' },
    { label: 'Needs Significant Dev.', range: `< ${format(p5)}`, percentile: '< 10th' },
  ] : [
    { label: 'Elite', range: `≤ ${format(p95)}`, percentile: '90th+' },
    { label: 'Above Average', range: `${format(p95)} – ${format(p75)}`, percentile: '75th–89th' },
    { label: 'Average', range: `${format(p75)} – ${format(p25)}`, percentile: '25th–74th' },
    { label: 'Needs Development', range: `${format(p25)} – ${format(p5)}`, percentile: '10th–24th' },
    { label: 'Needs Significant Dev.', range: `> ${format(p5)}`, percentile: '< 10th' },
  ]
})
const percentileBarPosition = computed(() => highlightedBenchmark.value?.percentile == null ? null : Math.min(100, Math.max(0, Number(highlightedBenchmark.value.percentile))))

const strengthBalanceRows = computed(() => strengthMetrics.value.map((metric) => {
  const selected = metricBenchmark(highlightedPlayer.value, metric)?.percentile ?? null
  const peerValues = comparedPlayers.value.map((player) => metricBenchmark(player, metric)?.percentile).filter((value) => value != null).map(Number)
  const average = peerValues.length ? peerValues.reduce((total, value) => total + value, 0) / peerValues.length : null
  return { metric, selected, average }
}))
const hasStrengthBalance = computed(() => strengthBalanceRows.value.some((row) => row.selected != null || row.average != null))
const radarSeries = computed(() => [
  { name: highlightedPlayer.value?.name || 'Selected Athlete', data: strengthBalanceRows.value.map((row) => row.selected ?? 0) },
  { name: 'Selected Average', data: strengthBalanceRows.value.map((row) => row.average ?? 0) },
])
const radarOptions = computed(() => ({
  chart: { type: 'radar', toolbar: { show: false }, background: 'transparent', animations: { enabled: true, speed: 300 } },
  colors: ['#ff2b4a', '#94a3b8'],
  stroke: { width: [2.5, 1.5], dashArray: [0, 5] },
  fill: { opacity: [0.18, 0.04] },
  markers: { size: [3, 0] },
  xaxis: { categories: strengthBalanceRows.value.map((row) => row.metric.label), labels: { style: { colors: Array(5).fill('#9aa8ba'), fontSize: '10px', fontWeight: 700 } } },
  yaxis: { min: 0, max: 100, tickAmount: 4, labels: { show: false } },
  plotOptions: { radar: { polygons: { strokeColors: 'rgba(148,163,184,.16)', connectorColors: 'rgba(148,163,184,.16)', fill: { colors: ['rgba(15,23,42,.35)', 'rgba(2,8,20,.2)'] } } } },
  legend: { show: true, position: 'bottom', labels: { colors: '#94a3b8' }, fontSize: '10px' },
  tooltip: { theme: 'dark', y: { formatter: (value) => `${Math.round(value)}th percentile` } },
}))

const strengthRelationships = computed(() => strengthMetrics.value.map((metric) => {
  const selected = positiveMetricNumber(metricBenchmark(highlightedPlayer.value, metric)?.relative_value)
  const peerValues = comparedPlayers.value.map((player) => positiveMetricNumber(metricBenchmark(player, metric)?.relative_value)).filter((value) => value !== null)
  const average = peerValues.length ? peerValues.reduce((total, value) => total + value, 0) / peerValues.length : null
  const difference = selected != null && average ? ((Number(selected) - average) / average) * 100 : null
  return { metric, selected: selected == null ? null : Number(selected), average, difference }
}))

const coachInsights = computed(() => {
  if (!highlightedPlayer.value) return []
  const insights = []
  const change = legendChange(highlightedPlayer.value)
  if (change != null) {
    const improving = activeMetric.value?.lowerBetter ? change < 0 : change > 0
    insights.push({ tone: improving ? 'good' : 'warn', icon: improving ? '↗' : '!', text: `${highlightedPlayer.value.name} is ${improving ? 'trending in the right direction' : 'moving away from the preferred direction'} in ${activeMetric.value.label} (${legendChangeLabel(highlightedPlayer.value)}).` })
  }
  const percentile = highlightedBenchmark.value?.percentile
  if (percentile != null) insights.push({ tone: percentile >= 75 ? 'good' : percentile >= 40 ? 'warn' : 'bad', icon: percentile >= 75 ? '↑' : '•', text: `${activeMetric.value.label} ranks in the ${Math.round(percentile)}th percentile for the player’s governed age benchmark.` })
  const weakest = strengthBalanceRows.value.filter((row) => row.selected != null).sort((a, b) => a.selected - b.selected)[0]
  if (weakest) insights.push({ tone: weakest.selected < 40 ? 'bad' : 'warn', icon: '↗', text: `${weakest.metric.label} is the lowest strength percentile (${Math.round(weakest.selected)}th); prioritize it without losing progress in stronger lifts.` })
  if (!insights.length) insights.push({ tone: 'muted', icon: 'i', text: 'Log at least two tests and complete benchmark metrics to unlock trend and comparison insights.' })
  return insights.slice(0, 3)
})
const takeaway = computed(() => {
  const percentiles = tableRows.value.map((row) => row.percentile).filter((value) => value != null)
  const above = percentiles.filter((value) => value >= 75).length
  const needsWork = percentiles.filter((value) => value < 40).length
  return `${above} selected athlete${above === 1 ? '' : 's'} above average; ${needsWork} need${needsWork === 1 ? 's' : ''} focused development in ${activeMetric.value?.label || 'this metric'}.`
})
</script>

<template>
  <component :is="pageWrapper">
    <main class="command-center" :class="{ embedded }">
      <header class="command-header">
        <div>
          <h1>Strength Center</h1>
          <p>Coach Comparison &amp; Benchmarking</p>
        </div>
        <div class="header-actions">
          <span class="season-chip">{{ rangeLabel }}</span>
          <button v-if="!embedded" type="button" class="back-button" @click="router.push('/dashboard?tab=strengthcenter')">← Coach Dashboard</button>
        </div>
      </header>

      <section v-if="errorMessage" class="state-card">
        <h2>Player comparison unavailable</h2>
        <p>{{ errorMessage }}</p>
        <button type="button" class="primary-button" @click="router.push('/development')">Choose Players</button>
      </section>

      <section v-else class="dashboard-shell">
        <aside class="filter-sidebar">
          <div class="sidebar-heading"><span>Filters</span><span>≡</span></div>

          <label class="filter-field">
            <span>Team</span>
            <select disabled><option>{{ teamName }}</option></select>
          </label>

          <label class="filter-field">
            <span>Age Group</span>
            <select v-model="ageGroupFilter">
              <option value="all">All Age Groups</option>
              <option v-for="option in ageGroupOptions" :key="option" :value="option">{{ formatFilterLabel(option) }}</option>
            </select>
          </label>

          <label class="filter-field">
            <span>Weight Class</span>
            <select v-model="weightClassFilter">
              <option value="all">All Weight Classes</option>
              <option v-for="option in weightClassOptions" :key="option" :value="option">{{ formatFilterLabel(option) }}</option>
            </select>
          </label>

          <div class="filter-field">
            <span>Athletes</span>
            <button type="button" class="athlete-filter" @click="toggleAddPlayer">
              <b>{{ players.length }}</b> selected <span>＋</span>
            </button>
            <div v-if="showAddPlayer" class="sidebar-player-list">
              <button v-for="option in availableToAdd" :key="option.id" type="button" @click="addPlayer(option)">{{ option.name }}</button>
              <p v-if="!availableToAdd.length">No additional players available.</p>
            </div>
          </div>

          <div class="filter-field">
            <span>Date Range</span>
            <div class="range-display">{{ rangeLabel }} <span>▣</span></div>
            <div class="range-control">
              <button v-for="(range, index) in QUICK_RANGES" :key="range.label" type="button" :class="{ active: !useCustomRange && activeQuickIndex === index }" @click="selectQuickRange(index)">{{ range.label }}</button>
              <button type="button" :class="{ active: useCustomRange }" @click="useCustomRange = true">Custom</button>
            </div>
            <div v-if="useCustomRange" class="custom-range">
              <input v-model="customFrom" type="date" aria-label="Start date" />
              <input v-model="customTo" type="date" aria-label="End date" />
            </div>
          </div>

          <div class="sidebar-heading metric-heading"><span>Metric Category</span></div>
          <label class="metric-category-select">
            <select v-model="selectedMetricCategory">
              <option v-for="group in categorizedMetrics" :key="group.label" :value="group.label">{{ group.label }}</option>
            </select>
            <span>▾</span>
          </label>
          <div v-if="selectedMetricGroup" class="metric-group">
            <span>{{ selectedMetricGroup.label }}</span>
            <button v-for="metric in selectedMetricGroup.metrics" :key="metric.key" type="button" :class="{ active: activeMetricKey === metric.key }" @click="activeMetricKey = metric.key">
              <i :style="{ color: metric.color }">◇</i>{{ metric.label }}<b v-if="activeMetricKey === metric.key">⌁</b>
            </button>
          </div>

          <button type="button" class="manage-button" @click="router.push('/development')">▣ Manage Athletes</button>
        </aside>

        <div class="dashboard-content">
          <p v-if="loading" class="loading-state">Loading comparison data…</p>

          <template v-else>
            <section class="panel trend-panel">
              <div class="panel-header trend-header">
                <div>
                  <h2>{{ activeMetric?.label }} Over Time <small>ⓘ</small></h2>
                  <p>{{ showAllAthletes ? `${comparedPlayers.length} athletes` : `${highlightedPlayer?.name || 'Selected athlete'} · ${selectedChartMetrics.length} metric${selectedChartMetrics.length === 1 ? '' : 's'}` }} · {{ rangeLabel }}</p>
                </div>
                <div class="trend-controls">
                  <button type="button" class="chart-mode-button" :class="{ active: showAllAthletes }" @click="showAllAthletes = !showAllAthletes">
                    {{ showAllAthletes ? 'Focus Selected Athlete' : `Compare All (${comparedPlayers.length})` }}
                  </button>
                  <div v-if="!showAllAthletes" class="metric-picker-wrap">
                    <button type="button" class="chart-mode-button metric-picker-button" :class="{ active: selectedChartMetrics.length > 1 }" @click="showMetricPicker = !showMetricPicker">
                      Metrics ({{ selectedChartMetrics.length }}) ▾
                    </button>
                    <div v-if="showMetricPicker" class="metric-picker-menu">
                      <div class="metric-picker-title"><b>Compare metrics</b><span>Up to 4</span></div>
                      <label class="metric-picker-category">
                        <select v-model="metricPickerCategory">
                          <option v-for="group in categorizedMetrics" :key="group.label" :value="group.label">{{ group.label }}</option>
                        </select>
                        <span>▾</span>
                      </label>
                      <div v-if="metricPickerGroup" class="metric-picker-group">
                        <span>{{ metricPickerGroup.label }}</span>
                        <button v-for="metric in metricPickerGroup.metrics" :key="metric.key" type="button" :class="{ selected: selectedChartMetricKeys.includes(metric.key) }" @click="toggleChartMetric(metric)">
                          <i :style="{ background: metric.color }"></i>{{ metric.label }}<b>{{ selectedChartMetricKeys.includes(metric.key) ? '✓' : '+' }}</b>
                        </button>
                      </div>
                      <p v-if="metricPickerMessage">{{ metricPickerMessage }}</p>
                    </div>
                  </div>
                  <div class="segment-control">
                    <button v-for="interval in INTERVALS" :key="interval" type="button" :class="{ active: chartInterval === interval }" @click="chartInterval = interval">{{ interval }}</button>
                  </div>
                </div>
              </div>

              <div class="trend-layout">
                <div class="chart-area">
                  <div v-if="multiMetricChart" class="metric-line-legend">
                    <span v-for="metric in selectedChartMetrics" :key="metric.key"><i :style="{ background: metric.color }"></i>{{ metric.label }}</span>
                    <small>Actual recorded values · each unit uses its own scale</small>
                  </div>
                  <apexchart v-if="hasLineData" width="100%" type="line" height="340" :options="lineChartOptions" :series="lineSeries" :key="`${activeMetricKey}_${selectedChartMetricKeys.join('-')}_${highlightedPlayerId}_${showAllAthletes}_${dataWindowDays}_${chartInterval}_${customFrom}_${customTo}`" />
                  <div v-else class="empty-chart">No logged tests for this metric in the selected range.</div>
                </div>
                <aside class="athlete-legend">
                  <div class="legend-heading"><span>Legend</span><span>Current &nbsp; Chg.</span></div>
                  <button v-for="player in comparedPlayers" :key="player.id" type="button" :class="{ active: highlightedPlayerId === player.id }" @click="highlightedPlayerId = player.id">
                    <span class="legend-name"><i :style="{ background: player.color }"></i>{{ player.name }}</span>
                    <b>{{ legendCurrent(player) }}</b>
                    <em :class="legendChangeTone(player)">{{ legendChangeLabel(player) }}</em>
                    <span v-if="players.length > 1" class="remove-player" title="Remove athlete" @click.stop="removePlayer(player.id)">×</span>
                  </button>
                  <div class="add-athlete-wrap">
                    <button type="button" class="add-athlete-button" @click="toggleAddPlayer">＋ Add Athlete to Compare</button>
                    <div v-if="showAddPlayer" class="add-athlete-menu">
                      <button v-for="option in availableToAdd" :key="option.id" type="button" @click="addPlayer(option)">{{ option.name }}</button>
                      <p v-if="!availableToAdd.length">No more athletes on this team.</p>
                    </div>
                  </div>
                </aside>
              </div>
            </section>

            <section class="summary-grid">
              <article class="summary-card current"><span>Current (Selected)</span><b>{{ displayMetricValue(highlightedBenchmark?.raw_value ?? legendCurrentValue(highlightedPlayer)) }}</b><small>{{ highlightedPlayer?.name || '—' }}</small></article>
              <article class="summary-card"><span>Change ({{ rangeLabel }})</span><b :class="legendChangeTone(highlightedPlayer)">{{ highlightedPlayer ? legendChangeLabel(highlightedPlayer) : '—' }}</b><small>{{ chartInterval }} view</small></article>
              <article class="summary-card"><span>Selected Avg</span><b>{{ displayMetricValue(groupStats.avg) }}</b><small>{{ comparedPlayers.length }} athletes</small></article>
              <article class="summary-card"><span>Best Selected</span><b>{{ displayMetricValue(groupStats.best) }}</b><small>{{ groupStats.bestName || '—' }}</small></article>
              <article class="summary-card"><span>Benchmark</span><b>{{ displayMetricValue(benchmarkMedian) }}</b><small>Age-group median</small></article>
              <article class="summary-card"><span>Percentile</span><b class="accent">{{ highlightedBenchmark?.percentile != null ? `${Math.round(highlightedBenchmark.percentile)}th` : '—' }}</b><small>{{ activeTierLabel || 'Needs data' }}</small></article>
              <article class="summary-card readiness"><span>Data Readiness</span><b>{{ dataReadiness }}%</b><div class="readiness-track"><i :style="{ width: `${dataReadiness}%` }"></i></div></article>
            </section>

            <section class="middle-grid">
              <article class="panel comparison-panel">
                <div class="panel-header">
                  <h2>Player Comparison Table <small>ⓘ</small></h2>
                  <label class="sort-control">Sort by <select v-model="sortBy"><option value="percentile">Percentile</option><option value="value">Raw Value</option><option value="relative">Relative Strength</option><option value="gap">Gap to Goal</option></select></label>
                </div>
                <div class="table-scroll">
                  <table class="comparison-table">
                    <thead><tr><th>#</th><th>Player</th><th v-for="metric in tableMetrics" :key="metric.key">{{ metric.label }}<small>{{ metric.unit }}</small></th><th>Percentile</th><th>Trend</th><th>Status</th></tr></thead>
                    <tbody>
                      <tr v-for="(row, index) in sortedTableRows" :key="row.id" :class="{ selected: row.id === highlightedPlayerId }" @click="highlightedPlayerId = row.id">
                        <td>{{ index + 1 }}</td>
                        <td class="player-cell"><i :style="{ background: row.color }"></i><b>{{ row.name }}</b></td>
                        <td v-for="cell in row.cells" :key="cell.key">{{ cell.value != null ? cell.value : '—' }}</td>
                        <td><b>{{ row.percentile != null ? `${Math.round(row.percentile)}th` : '—' }}</b></td>
                        <td><span class="sparkline">⌁</span></td>
                        <td><span class="status-pill" :class="statusFor(row.percentile).tone">{{ statusFor(row.percentile).label }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="panel benchmark-panel">
                <div class="panel-header"><h2>Benchmark Bands — {{ activeMetric?.label }} <small>ⓘ</small></h2></div>
                <p v-if="!benchmarkTiers.length" class="empty-copy">No governed benchmark is configured for this player and metric.</p>
                <template v-else>
                  <table class="benchmark-table">
                    <thead><tr><th>Tier</th><th>{{ activeMetric?.label }}</th><th>Percentile</th></tr></thead>
                    <tbody><tr v-for="tier in benchmarkTiers" :key="tier.label" :class="{ active: tier.label === activeTierLabel }"><td>{{ tier.label }}</td><td>{{ tier.range }}</td><td>{{ tier.percentile }}</td></tr></tbody>
                  </table>
                  <div class="benchmark-position">
                    <b>{{ highlightedPlayer?.name }}: {{ displayMetricValue(highlightedBenchmark?.raw_value) }}</b>
                    <div class="percentile-track"><i v-if="percentileBarPosition != null" :style="{ left: `${percentileBarPosition}%` }"></i></div>
                    <span>{{ highlightedBenchmark?.percentile != null ? `${Math.round(highlightedBenchmark.percentile)}th Percentile` : 'No percentile yet' }}</span>
                  </div>
                </template>
              </article>
            </section>

            <section class="bottom-grid">
              <article class="panel balance-panel">
                <div class="panel-header"><h2>Strength Balance <small>(Percentile Profile)</small> ⓘ</h2></div>
                <apexchart v-if="hasStrengthBalance" width="100%" type="radar" height="280" :options="radarOptions" :series="radarSeries" :key="`radar_${highlightedPlayerId}_${comparedPlayers.length}`" />
                <p v-else class="empty-copy">Strength benchmarks are needed to build the balance chart.</p>
              </article>

              <article class="panel relationship-panel">
                <div class="panel-header"><h2>Metric Relationships <small>ⓘ</small></h2><span>Relative to Body Weight</span></div>
                <div class="relationship-list">
                  <div v-for="row in strengthRelationships" :key="row.metric.key" class="relationship-row">
                    <span>{{ row.metric.label }}</span>
                    <b>{{ row.selected != null ? `${row.selected.toFixed(2)}x` : '—' }}</b>
                    <div><i :style="{ width: `${Math.min(100, Math.max(0, (row.selected || 0) / 3 * 100))}%` }"></i></div>
                    <small>{{ row.average != null ? `${row.average.toFixed(2)}x avg` : 'No avg' }}</small>
                    <em :class="row.difference != null && row.difference >= 0 ? 'good' : 'bad'">{{ row.difference != null ? `${row.difference >= 0 ? '+' : ''}${row.difference.toFixed(1)}%` : '—' }}</em>
                  </div>
                </div>
              </article>

              <article class="panel insights-panel">
                <div class="panel-header"><h2>Coach Insights <small>ⓘ</small></h2></div>
                <div class="insight-list">
                  <div v-for="insight in coachInsights" :key="insight.text" :class="insight.tone"><i>{{ insight.icon }}</i><p>{{ insight.text }}</p></div>
                </div>
                <div class="takeaway"><span>Takeaway</span><p>{{ takeaway }}</p></div>
              </article>
            </section>
          </template>
        </div>
      </section>
    </main>
  </component>
</template>

<style scoped>
:global(body:has(.command-center:not(.embedded)) .screen-stage) { width: 100%; margin: 0; }
:global(body:has(.command-center:not(.embedded)) .screen-stage > .command-center) { border: 0; border-radius: 0; background: #020817; box-shadow: none; }
:global(body:has(.command-center:not(.embedded)) .app-main-shell) { background: #020817 !important; }

.command-center { min-height: 100vh; width: 100%; padding: 24px clamp(16px, 2vw, 34px) 40px; overflow-x: hidden; color: #f7f9fc; background: radial-gradient(circle at 75% 0, rgba(25,45,85,.16), transparent 34%), #020817; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.command-center.embedded { min-height: 0; padding: 4px 0 12px; overflow: visible; background: transparent; }
.command-center * { box-sizing: border-box; }
button, select, input { font: inherit; }
button { cursor: pointer; }

.command-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; width: 100%; margin: 0 auto 22px; }
.command-header h1 { margin: 0; font-size: clamp(22px, 1.65vw, 32px); line-height: 1.1; font-weight: 850; letter-spacing: -.025em; }
.command-header p { margin: 5px 0 0; color: #9ba8ba; font-size: 15px; }
.header-actions { display: flex; align-items: center; gap: 12px; }
.season-chip, .back-button { border: 1px solid #26344a; border-radius: 9px; background: #091222; color: #c7d1df; padding: 10px 14px; font-size: 12px; font-weight: 750; }
.back-button:hover { border-color: #ff2b4a; color: white; }

.dashboard-shell { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 20px; width: 100%; margin: 0 auto; }
.filter-sidebar { min-width: 0; padding: 2px 18px 0 0; border-right: 1px solid #1d2a3c; }
.sidebar-heading { display: flex; align-items: center; justify-content: space-between; margin: 0 0 18px; color: #a9b5c5; font-size: 11px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
.metric-heading { margin-top: 22px; margin-bottom: 10px; }
.filter-field { display: block; margin-bottom: 18px; }
.filter-field > span { display: block; margin-bottom: 8px; color: #8e9aae; font-size: 10px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.filter-field select, .range-display, .athlete-filter { width: 100%; min-height: 42px; padding: 0 11px; border: 1px solid #26344a; border-radius: 8px; background: #0a1424; color: #edf3fb; font-size: 12px; text-align: left; }
.athlete-filter, .range-display { display: flex; align-items: center; justify-content: space-between; }
.athlete-filter b { color: #ff2b4a; font-size: 16px; }
.range-control { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 8px; }
.range-control button { padding: 7px 2px; border: 1px solid #26344a; border-radius: 7px; background: #091222; color: #8f9bad; font-size: 10px; font-weight: 800; text-transform: uppercase; }
.range-control button.active { border-color: #d91e38; background: #c81730; color: white; box-shadow: 0 0 18px rgba(255,43,74,.2); }
.custom-range { display: grid; gap: 6px; margin-top: 8px; }
.custom-range input { width: 100%; padding: 7px; border: 1px solid #26344a; border-radius: 7px; background: #091222; color: white; color-scheme: dark; font-size: 11px; }
.sidebar-player-list, .add-athlete-menu { z-index: 10; margin-top: 6px; padding: 5px; max-height: 180px; overflow: auto; border: 1px solid #2a3b53; border-radius: 8px; background: #091323; box-shadow: 0 16px 40px rgba(0,0,0,.45); }
.sidebar-player-list button, .add-athlete-menu button { width: 100%; padding: 8px 10px; border: 0; border-radius: 5px; background: transparent; color: #cbd5e1; font-size: 11px; text-align: left; }
.sidebar-player-list button:hover, .add-athlete-menu button:hover { background: #152137; color: white; }
.sidebar-player-list p, .add-athlete-menu p { margin: 5px; color: #728097; font-size: 10px; }
.metric-group { margin: 0 0 10px; }
.metric-category-select, .metric-picker-category { position: relative; display: block; }
.metric-category-select select, .metric-picker-category select { width: 100%; appearance: none; border: 1px solid #2b3a51; border-radius: 8px; background: #0a1424; color: #edf3fb; font-size: 11px; font-weight: 750; outline: none; }
.metric-category-select select { min-height: 42px; padding: 0 32px 0 11px; }
.metric-picker-category select { min-height: 36px; padding: 0 30px 0 10px; }
.metric-category-select > span, .metric-picker-category > span { position: absolute; top: 50%; right: 11px; color: #748196; font-size: 10px; pointer-events: none; transform: translateY(-50%); }
.metric-category-select select:focus, .metric-picker-category select:focus { border-color: #d91e38; }
.metric-group > span { display: block; margin: 10px 0 4px; color: #56647a; font-size: 8px; font-weight: 900; letter-spacing: .13em; text-transform: uppercase; }
.metric-group button { display: grid; grid-template-columns: 18px 1fr auto; align-items: center; width: 100%; margin: 3px 0; padding: 9px 10px; border: 1px solid #243249; border-radius: 7px; background: #091323; color: #a9b4c4; font-size: 11px; font-weight: 700; text-align: left; }
.metric-group button:hover { border-color: #42516a; color: white; }
.metric-group button.active { border-color: #d91e38; background: linear-gradient(90deg, #c6172f, #9c0d24); color: white; box-shadow: 0 8px 22px rgba(220,20,55,.18); }
.metric-group button i { font-style: normal; }
.manage-button { width: 100%; margin-top: 18px; padding: 11px; border: 1px solid #2b3a51; border-radius: 8px; background: #0b1525; color: #c8d2df; font-size: 11px; font-weight: 800; }

.dashboard-content { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.panel { min-width: 0; border: 1px solid #1d2b41; border-radius: 11px; background: linear-gradient(150deg, rgba(10,20,37,.98), rgba(5,13,27,.98)); box-shadow: inset 0 1px 0 rgba(255,255,255,.015), 0 12px 30px rgba(0,0,0,.12); }
.panel-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px 10px; border-bottom: 1px solid rgba(148,163,184,.11); }
.panel-header h2 { margin: 0; color: #dce4ee; font-size: 12px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; }
.panel-header h2 small { color: #78869a; font-size: 9px; font-weight: 700; }
.panel-header > span { color: #77859a; font-size: 9px; }
.trend-header p { margin: 4px 0 0; color: #8491a4; font-size: 10px; }
.trend-controls { display: flex; align-items: center; gap: 20px; }
.chart-mode-button { min-width: 170px; padding: 9px 12px; border: 1px solid #2b3a51; border-radius: 8px; background: #0a1424; color: #c8d1de; font-size: 11px; font-weight: 750; }
.chart-mode-button:hover, .chart-mode-button.active { border-color: #d91e38; background: rgba(192,0,0,.16); color: white; }
.metric-picker-wrap { position: relative; }
.metric-picker-button { min-width: 112px; }
.metric-picker-menu { position: absolute; z-index: 40; top: calc(100% + 7px); right: 0; width: 300px; max-height: 420px; overflow-y: auto; padding: 10px; border: 1px solid #30415a; border-radius: 10px; background: #081322; box-shadow: 0 20px 55px rgba(0,0,0,.65); }
.metric-picker-title { display: flex; align-items: center; justify-content: space-between; padding: 2px 4px 8px; color: white; font-size: 11px; }
.metric-picker-title span { color: #7f8da1; font-size: 9px; text-transform: uppercase; }
.metric-picker-group > span { display: block; margin: 8px 4px 4px; color: #5f6f85; font-size: 8px; font-weight: 900; letter-spacing: .11em; text-transform: uppercase; }
.metric-picker-group button { display: grid; grid-template-columns: 9px 1fr auto; align-items: center; gap: 8px; width: 100%; padding: 7px 8px; border: 1px solid transparent; border-radius: 6px; background: transparent; color: #aeb9c8; font-size: 10px; text-align: left; }
.metric-picker-group button:hover { background: rgba(255,255,255,.04); color: white; }
.metric-picker-group button.selected { border-color: rgba(255,43,74,.25); background: rgba(192,0,0,.1); color: white; }
.metric-picker-group button i { width: 8px; height: 8px; border-radius: 50%; }
.metric-picker-group button b { color: #ff6075; }
.metric-picker-menu > p { margin: 8px 4px 2px; color: #f6c84a; font-size: 9px; line-height: 1.35; }
.segment-control { display: flex; border: 1px solid #2b3a51; border-radius: 999px; overflow: hidden; }
.segment-control button { min-width: 78px; padding: 8px 13px; border: 0; background: transparent; color: #99a5b5; font-size: 10px; font-weight: 750; text-transform: capitalize; }
.segment-control button.active { background: linear-gradient(180deg, #8f1123, #5f0b19); color: white; box-shadow: 0 0 18px rgba(255,43,74,.2); }
.trend-layout { display: grid; grid-template-columns: minmax(0, 1fr) 285px; gap: 0; padding: 0 10px 10px; }
.chart-area { min-width: 0; padding-right: 14px; }
.metric-line-legend { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 10px 12px 0; color: #b8c3d1; font-size: 9px; font-weight: 750; }
.metric-line-legend span { display: flex; align-items: center; gap: 5px; }
.metric-line-legend i { width: 9px; height: 9px; border-radius: 50%; }
.metric-line-legend small { margin-left: auto; color: #68778c; font-size: 8px; font-weight: 650; }
.empty-chart { display: grid; place-items: center; height: 340px; color: #67758a; font-size: 12px; }
.athlete-legend { padding: 15px 0 0 18px; border-left: 1px solid rgba(148,163,184,.12); }
.legend-heading { display: flex; justify-content: space-between; margin-bottom: 8px; color: #68778c; font-size: 9px; font-weight: 850; text-transform: uppercase; }
.athlete-legend > button { position: relative; display: grid; grid-template-columns: minmax(92px, 1fr) auto auto; align-items: center; gap: 8px; width: 100%; padding: 9px 5px; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #dce4ef; text-align: left; }
.athlete-legend > button:hover, .athlete-legend > button.active { border-color: #27364d; background: rgba(255,255,255,.025); }
.legend-name { display: flex; align-items: center; gap: 7px; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; }
.legend-name i { width: 9px; height: 9px; border-radius: 2px; flex: none; }
.athlete-legend b { font-size: 10px; white-space: nowrap; }
.athlete-legend em { color: #8290a4; font-size: 9px; font-style: normal; font-weight: 800; white-space: nowrap; }
.good { color: #28d17c !important; }
.bad { color: #ff5269 !important; }
.remove-player { position: absolute; right: -1px; top: -2px; color: #59677b; font-size: 12px; }
.add-athlete-wrap { position: relative; margin-top: 8px; }
.add-athlete-button { width: 100%; padding: 9px; border: 1px solid #2d3c53; border-radius: 7px; background: #0c1728; color: #c8d2df; font-size: 10px; font-weight: 750; }
.add-athlete-menu { position: absolute; left: 0; right: 0; }

.summary-grid { display: grid; grid-template-columns: repeat(7, minmax(120px, 1fr)); gap: 10px; }
.summary-card { display: flex; flex-direction: column; min-height: 92px; padding: 13px 14px; border: 1px solid #223149; border-radius: 9px; background: linear-gradient(145deg, #0b1628, #081120); }
.summary-card.current { border-color: rgba(255,43,74,.38); box-shadow: inset 3px 0 #ff2b4a; }
.summary-card > span { color: #8e9bad; font-size: 9px; font-weight: 850; letter-spacing: .05em; text-transform: uppercase; }
.summary-card b { margin-top: 9px; color: #f2f6fb; font-size: 20px; line-height: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.summary-card b.accent { color: #f6c84a; }
.summary-card small { margin-top: 6px; color: #94a1b3; font-size: 9px; }
.readiness-track { height: 6px; margin-top: 9px; border-radius: 999px; background: #263247; overflow: hidden; }
.readiness-track i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #31d37f, #75e6a9); }

.middle-grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(320px, .95fr); gap: 14px; align-items: stretch; }
.sort-control { display: flex; align-items: center; gap: 7px; color: #7f8da1; font-size: 9px; }
.sort-control select { padding: 6px 9px; border: 1px solid #2b3a51; border-radius: 7px; background: #091323; color: #cbd5e1; font-size: 9px; }
.table-scroll { overflow: auto; }
.comparison-table, .benchmark-table { width: 100%; border-collapse: collapse; }
.comparison-table th, .comparison-table td { padding: 9px 10px; border-bottom: 1px solid rgba(148,163,184,.08); white-space: nowrap; text-align: left; }
.comparison-table th { color: #748196; font-size: 8px; font-weight: 900; letter-spacing: .04em; text-transform: uppercase; }
.comparison-table th small { display: block; font-size: 7px; }
.comparison-table td { color: #bdc8d7; font-size: 10px; }
.comparison-table tbody tr { cursor: pointer; }
.comparison-table tbody tr:hover, .comparison-table tbody tr.selected { background: rgba(255,43,74,.055); box-shadow: inset 3px 0 #ff2b4a; }
.player-cell { display: flex; align-items: center; gap: 7px; color: white !important; }
.player-cell i { width: 24px; height: 24px; border: 2px solid rgba(255,255,255,.18); border-radius: 50%; }
.status-pill { padding: 4px 8px; border: 1px solid; border-radius: 5px; font-size: 8px; font-weight: 850; text-transform: uppercase; }
.status-pill.good { border-color: rgba(40,209,124,.35); background: rgba(40,209,124,.1); }
.status-pill.warn { border-color: rgba(246,200,74,.35); background: rgba(246,200,74,.1); color: #f6c84a; }
.status-pill.bad { border-color: rgba(255,82,105,.35); background: rgba(255,82,105,.1); }
.status-pill.muted { border-color: #364359; color: #7f8ca0; }
.sparkline { color: #ff2b4a; font-size: 19px; }
.benchmark-table th, .benchmark-table td { padding: 9px 12px; border-bottom: 1px solid rgba(148,163,184,.09); font-size: 9px; text-align: left; }
.benchmark-table th { color: #758398; text-transform: uppercase; }
.benchmark-table td { color: #c5cfdb; }
.benchmark-table tbody tr:nth-child(1) { background: rgba(23,178,99,.11); }
.benchmark-table tbody tr:nth-child(2) { background: rgba(67,188,80,.08); }
.benchmark-table tbody tr:nth-child(3) { background: rgba(246,200,74,.08); }
.benchmark-table tbody tr:nth-child(4) { background: rgba(245,129,37,.08); }
.benchmark-table tbody tr:nth-child(5) { background: rgba(220,38,38,.09); }
.benchmark-table tr.active { outline: 1px solid rgba(255,255,255,.18); outline-offset: -1px; }
.benchmark-position { padding: 16px; text-align: center; }
.benchmark-position b { display: block; margin-bottom: 11px; color: #cdd7e3; font-size: 10px; }
.percentile-track { position: relative; height: 8px; border-radius: 999px; background: linear-gradient(90deg, #e5394e 0 20%, #ef7536 20% 40%, #e5b83d 40% 60%, #77b52e 60% 80%, #21b96d 80%); }
.percentile-track i { position: absolute; top: -5px; width: 3px; height: 18px; border-radius: 2px; background: white; box-shadow: 0 0 0 2px #182235; transform: translateX(-50%); }
.benchmark-position span { display: block; margin-top: 9px; color: #d7e0eb; font-size: 10px; }
.empty-copy { margin: 0; padding: 30px 20px; color: #77859a; font-size: 11px; }

.bottom-grid { display: grid; grid-template-columns: .95fr 1.15fr 1.1fr; gap: 14px; align-items: stretch; }
.balance-panel, .relationship-panel, .insights-panel { min-height: 310px; }
.relationship-list { padding: 14px 16px; }
.relationship-row { display: grid; grid-template-columns: 82px 42px minmax(70px, 1fr) 62px 45px; align-items: center; gap: 8px; padding: 8px 0; }
.relationship-row > span, .relationship-row b, .relationship-row small, .relationship-row em { font-size: 9px; }
.relationship-row > span { color: #aab6c6; }
.relationship-row b { color: white; }
.relationship-row > div { height: 7px; border-radius: 99px; background: #344157; overflow: hidden; }
.relationship-row > div i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #c91d34, #ff455e); }
.relationship-row small { color: #8b98aa; }
.relationship-row em { font-style: normal; font-weight: 850; }
.insight-list { padding: 7px 16px; }
.insight-list > div { display: grid; grid-template-columns: 24px 1fr; gap: 8px; align-items: start; padding: 11px 0; border-bottom: 1px solid rgba(148,163,184,.1); }
.insight-list i { display: grid; place-items: center; width: 20px; height: 20px; border: 1px solid currentColor; border-radius: 50%; font-size: 10px; font-style: normal; }
.insight-list .good { color: #28d17c; }
.insight-list .warn { color: #f6c84a; }
.insight-list .bad { color: #ff5269; }
.insight-list .muted { color: #7c899c; }
.insight-list p { margin: 0; color: #aeb9c8; font-size: 10px; line-height: 1.45; }
.takeaway { margin: 0 12px 12px; padding: 10px 12px; border: 1px solid rgba(255,43,74,.34); border-radius: 7px; background: rgba(119,8,28,.12); }
.takeaway span { color: #ff2b4a; font-size: 8px; font-weight: 900; text-transform: uppercase; }
.takeaway p { margin: 3px 0 0; color: #d4dce6; font-size: 10px; line-height: 1.4; }
.loading-state, .state-card { padding: 50px; border: 1px solid #223149; border-radius: 12px; background: #081221; color: #91a0b5; text-align: center; }
.state-card { max-width: 680px; margin: 80px auto; }
.state-card h2 { color: white; }
.primary-button { margin-top: 14px; padding: 10px 18px; border: 0; border-radius: 7px; background: #d71935; color: white; font-weight: 800; }

@media (max-width: 1450px) {
  .summary-grid { grid-template-columns: repeat(4, 1fr); }
  .bottom-grid { grid-template-columns: 1fr 1fr; }
  .insights-panel { grid-column: 1 / -1; min-height: auto; }
}
@media (max-width: 1180px) {
  .dashboard-shell { grid-template-columns: 190px minmax(0, 1fr); }
  .trend-layout, .middle-grid { grid-template-columns: 1fr; }
  .athlete-legend { display: grid; grid-template-columns: repeat(2, 1fr); gap: 5px; border-left: 0; border-top: 1px solid rgba(148,163,184,.12); padding: 12px; }
  .legend-heading, .add-athlete-wrap { grid-column: 1 / -1; }
}
@media (max-width: 850px) {
  .command-center { padding: 16px 10px 30px; }
  .command-header { align-items: flex-start; }
  .header-actions { flex-direction: column; align-items: stretch; }
  .dashboard-shell { grid-template-columns: 1fr; }
  .filter-sidebar { padding: 12px; border: 1px solid #1d2a3c; border-radius: 10px; }
  .metric-group { display: inline; }
  .metric-group > span { margin-top: 12px; }
  .metric-group button { width: auto; display: inline-grid; margin: 3px; }
  .summary-grid, .bottom-grid { grid-template-columns: repeat(2, 1fr); }
  .insights-panel { grid-column: 1 / -1; }
  .trend-controls { align-items: flex-end; flex-direction: column; gap: 7px; }
}
@media (max-width: 560px) {
  .command-header { flex-direction: column; }
  .header-actions { width: 100%; }
  .summary-grid, .bottom-grid { grid-template-columns: 1fr; }
  .insights-panel { grid-column: auto; }
  .athlete-legend { grid-template-columns: 1fr; }
  .trend-header { align-items: flex-start; }
  .segment-control button { min-width: 58px; padding-inline: 8px; }
  .relationship-row { grid-template-columns: 75px 40px 1fr 45px; }
  .relationship-row small { display: none; }
}
</style>
