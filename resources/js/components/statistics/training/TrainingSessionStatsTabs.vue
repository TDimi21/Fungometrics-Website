<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  mode: {
    type: String,
    required: true,
  },
  rows: {
    type: [Array, Object],
    default: () => [],
  },
  teamName: {
    type: String,
    default: 'Team',
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  editable: {
    type: Boolean,
    default: false,
  },
  activeTab: {
    type: String,
    default: '',
  },
  showTabs: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['edit-row'])

const tabs = ['BALL BY BALL', 'LEADERS', 'PLAYER']
const internalActiveTab = ref('BALL BY BALL')
const selectedFilter = ref('ALL')
const selectedPlayerId = ref('')
const WB_WEIGHTS = [3, 4, 5, 6, 7]
const WB_EXPECTED_MULTIPLIER = {
  3: 1.04,
  4: 1.02,
  5: 1,
  6: 0.97,
  7: 0.94,
}
const WB_CURVE_KEYS = ['actualAvg', 'topVelo', 'expected']
const WB_MINI_KEYS = ['value', 'expected']
const CHART_PADDING = { left: 34, right: 16, top: 20, bottom: 34 }

const modeKey = computed(() => String(props.mode || '').toUpperCase())
const activeTabName = computed(() => props.activeTab || internalActiveTab.value)

const rowsArray = computed(() => {
  if (Array.isArray(props.rows)) return props.rows
  if (props.rows && typeof props.rows === 'object') return Object.values(props.rows)
  return []
})

const toNumber = (value) => {
  if (value === null || value === undefined || value === '') return null
  const n = Number(value)
  return Number.isFinite(n) ? n : null
}

const formatNumber = (value, decimals = 1) => {
  const n = toNumber(value)
  if (n === null) return '-'
  return Number.isInteger(n) ? String(n) : n.toFixed(decimals)
}

const round1 = (value) => {
  const n = toNumber(value)
  return n === null ? null : Math.round(n * 10) / 10
}

const formatMph = (value) => {
  const rounded = round1(value)
  if (rounded === null) return '--'
  return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(1)
}

const formatDelta = (value) => {
  const rounded = round1(value)
  if (rounded === null) return '--'
  const prefix = rounded > 0 ? '+' : ''
  return `${prefix}${formatMph(rounded)}`
}

const clamp = (value, min, max) => Math.max(min, Math.min(max, value))

const scoreFromExpected = (actual, expected) => {
  if (!actual || !expected) return null
  const diff = Math.abs(actual - expected)
  return Math.round(clamp(100 - diff * 6, 40, 100))
}

const gradeLabel = (score) => {
  if (score === null || score === undefined) return 'No Data'
  if (score >= 90) return 'Elite'
  if (score >= 80) return 'Strong'
  if (score >= 70) return 'Developing'
  if (score >= 60) return 'Inconsistent'
  return 'Needs Work'
}

const getProfile = (row) => row?.profile || row?.player || row?.athlete || {}

const getPlayerId = (row, fallback = '') => {
  const profile = getProfile(row)
  const id =
    row?.player_id ??
    row?.user_id ??
    row?.profile_id ??
    profile?.id ??
    profile?.user_id ??
    fallback
  return id === null || id === undefined || id === '' ? String(fallback) : String(id)
}

const getPlayerName = (row) => {
  const explicit = row?.player_name || row?.name || row?.profile?.full_name || row?.profile?.full
  if (explicit) return String(explicit)
  const profile = getProfile(row)
  const first = profile?.first_name || profile?.name?.first || row?.first_name || ''
  const last = profile?.last_name || profile?.name?.last || row?.last_name || ''
  const full = `${first} ${last}`.trim()
  return full || 'Player'
}

const getSortNumber = (row, index) => {
  const n = toNumber(row?.sort)
  return n === null ? index + 1 : n + 1
}

const getSetValue = (row) => row?.set ?? row?.round ?? '-'

const getVelocity = (row) =>
  toNumber(
    row?.velocity ??
      row?.miles_per_hour ??
      row?.exit_velocity ??
      row?.weighted_velocity ??
      row?.weighted_ball_velocity ??
      row?.mph ??
      row?.velo ??
      null,
  )

const getDistance = (row) => toNumber(row?.distance ?? row?.dist ?? row?.throw_distance ?? row?.feet ?? null)

const getWeight = (row) =>
  toNumber(
    row?.weight ??
      row?.weighted_ball ??
      row?.weightball ??
      row?.ball_weight ??
      row?.weight_oz ??
      row?.oz ??
      row?.ball ??
      null,
  )

const normalizeTrajectory = (row) => {
  const raw = String(row?.trajectory ?? row?.type_of_hit ?? row?.position ?? '').trim().toUpperCase()
  if (raw === 'LD' || raw === 'LINE DRIVE' || raw === 'LINEDRIVE') return 'LD'
  if (raw === 'GB' || raw === 'GROUND BALL' || raw === 'GROUNDBALL') return 'GB'
  if (raw === 'FB' || raw === 'FLY' || raw === 'FLY BALL' || raw === 'FLYBALL') return 'FB'
  return raw || '-'
}

const trajectoryLabel = (value) => {
  if (value === 'LD') return 'Line Drive'
  if (value === 'GB') return 'Ground Ball'
  if (value === 'FB') return 'Fly Ball'
  return value || '-'
}

const getHop = (row) => {
  const hop = toNumber(row?.hop ?? row?.hops ?? row?.player_hop ?? row?.hop_count ?? row?.number_of_hops)
  // A throw with no recorded hop count is treated as zero hops.
  if (hop === null) return 0
  return Math.max(0, Math.round(hop))
}

const hopLabel = (hop) => {
  if (hop === null) return '-'
  if (hop <= 0) return 'No Hop'
  if (hop === 1) return '1 Hop'
  return `${hop} Hops`
}

const normalizedRows = computed(() =>
  rowsArray.value.map((row, index) => {
    const playerId = getPlayerId(row, `unknown-${index}`)
    const velocity = getVelocity(row)
    const distance = getDistance(row)
    const weight = getWeight(row)
    const hop = getHop(row)
    const trajectory = normalizeTrajectory(row)

    return {
      raw: row,
      idx: getSortNumber(row, index),
      rowKey: row?.id ?? row?.uuid ?? `${playerId}-${index}`,
      playerId,
      player: getPlayerName(row),
      set: getSetValue(row),
      trajectory,
      velocity,
      distance,
      weight,
      hop,
    }
  }),
)

const dynamicWeightFilters = computed(() => {
  const weights = [...new Set(normalizedRows.value.map((row) => row.weight).filter((w) => w !== null))]
    .sort((a, b) => a - b)
    .map((weight) => ({ key: String(weight), label: `${formatNumber(weight, 0)} oz` }))
  return weights.length ? weights : [3, 4, 5, 6, 7].map((weight) => ({ key: String(weight), label: `${weight} oz` }))
})

const filters = computed(() => {
  if (modeKey.value === 'EV') {
    return [
      { key: 'ALL', label: 'All' },
      { key: 'LD', label: 'Line Drive' },
      { key: 'GB', label: 'Ground Ball' },
      { key: 'FB', label: 'Fly Ball' },
    ]
  }
  if (modeKey.value === 'LT') {
    return [
      { key: 'ALL', label: 'All' },
      { key: '0', label: 'No Hop' },
      { key: '1', label: '1 Hop' },
      { key: '2', label: '2 Hop' },
      { key: '3', label: '3 Hop' },
    ]
  }
  return [{ key: 'ALL', label: 'All' }, ...dynamicWeightFilters.value]
})

const rowMatchesFilter = (row) => {
  if (selectedFilter.value === 'ALL') return true
  if (modeKey.value === 'EV') return row.trajectory === selectedFilter.value
  if (modeKey.value === 'LT') return String(row.hop ?? '') === selectedFilter.value
  if (modeKey.value === 'WB') return String(row.weight ?? '') === selectedFilter.value
  return true
}

const filteredRows = computed(() =>
  normalizedRows.value.filter((row) => {
    if (selectedPlayerId.value && row.playerId !== selectedPlayerId.value) return false
    return rowMatchesFilter(row)
  }),
)

const average = (values) => {
  const clean = values.filter((v) => v !== null)
  if (!clean.length) return null
  return clean.reduce((sum, value) => sum + value, 0) / clean.length
}

const maxOf = (values) => {
  const clean = values.filter((v) => v !== null)
  return clean.length ? Math.max(...clean) : null
}

// ── Player-tab charts (EV: avg velocity by trajectory · LT: avg distance by hop) ──
// Respect the selected player (or show the team when none is selected), mirroring
// the app's ExitVelocity/LongToss player charts. Bars scale to the tallest category.
const rowsForPlayerChart = computed(() =>
  normalizedRows.value.filter((row) => !selectedPlayerId.value || row.playerId === selectedPlayerId.value),
)

const withBars = (cats) => {
  const maxAvg = Math.max(1, ...cats.map((c) => c.avg || 0))
  return cats.map((c) => ({ ...c, barPct: c.avg ? Math.max(5, Math.round((c.avg / maxAvg) * 100)) : 0 }))
}

const evTrajectoryChart = computed(() => {
  const rows = rowsForPlayerChart.value
  const cats = [
    { key: 'LD', label: 'Line Drive', color: '#37D67A' },
    { key: 'GB', label: 'Ground Ball', color: '#F59E0B' },
    { key: 'FB', label: 'Fly Ball', color: '#34A7FF' },
  ].map((c) => {
    const vals = rows.filter((r) => r.trajectory === c.key).map((r) => r.velocity).filter((v) => v !== null && v > 0)
    return { ...c, avg: vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null, count: vals.length }
  })
  return withBars(cats)
})

const ltHopChart = computed(() => {
  const rows = rowsForPlayerChart.value
  const cats = [
    { key: 0, label: 'No Hop', color: '#37D67A' },
    { key: 1, label: '1 Hop', color: '#34A7FF' },
    { key: 2, label: '2 Hop', color: '#F59E0B' },
    { key: 3, label: '3 Hop', color: '#EF4444' },
  ].map((c) => {
    const vals = rows.filter((r) => (r.hop ?? -1) === c.key).map((r) => r.distance).filter((v) => v !== null && v > 0)
    return { ...c, avg: vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null, count: vals.length }
  })
  return withBars(cats)
})

const playerChart = computed(() => (modeKey.value === 'EV' ? evTrajectoryChart.value : ltHopChart.value))

// Long Toss chart as an X/Y line: x = hop count (0,1,2,3), y = avg distance.
const LT_HOP_KEYS = ['value']
const ltHopCurvePoints = computed(() =>
  ltHopChart.value.map((c) => ({ label: String(c.key), value: c.avg, color: c.color, count: c.count })),
)
const ltChartHasData = computed(() => ltHopChart.value.some((c) => c.avg != null))

const groupRowsByPlayer = computed(() => {
  const map = new Map()
  normalizedRows.value.forEach((row) => {
    if (!map.has(row.playerId)) {
      map.set(row.playerId, {
        id: row.playerId,
        name: row.player,
        rows: [],
      })
    }
    map.get(row.playerId).rows.push(row)
  })
  return [...map.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const buildPlayerSummary = (player) => {
  const rows = player.rows
  const velocities = rows.map((row) => row.velocity)
  const distances = rows.map((row) => row.distance)
  const weights = [...new Set(rows.map((row) => row.weight).filter((v) => v !== null))].sort((a, b) => a - b)
  const byTrajectory = (key) => rows.filter((row) => row.trajectory === key).map((row) => row.velocity)
  const byHop = (key) => rows.filter((row) => Number(row.hop) === key).map((row) => row.distance)
  const byWeight = (key) => rows.filter((row) => Number(row.weight) === key).map((row) => row.velocity)

  return {
    ...player,
    total: rows.length,
    avgVelocity: average(velocities),
    topVelocity: maxOf(velocities),
    avgDistance: average(distances),
    topDistance: maxOf(distances),
    weights,
    ev: {
      ldAvg: average(byTrajectory('LD')),
      gbAvg: average(byTrajectory('GB')),
      fbAvg: average(byTrajectory('FB')),
      ldTop: maxOf(byTrajectory('LD')),
      gbTop: maxOf(byTrajectory('GB')),
      fbTop: maxOf(byTrajectory('FB')),
    },
    lt: {
      noHopAvg: average(byHop(0)),
      oneHopAvg: average(byHop(1)),
      twoHopAvg: average(byHop(2)),
      threeHopAvg: average(byHop(3)),
    },
    wb: Object.fromEntries(
      weights.map((weight) => {
        const values = byWeight(weight)
        return [
          String(weight),
          {
            avg: average(values),
            top: maxOf(values),
            count: values.length,
          },
        ]
      }),
    ),
  }
}

const playerSummaries = computed(() => groupRowsByPlayer.value.map(buildPlayerSummary))

const selectedPlayerSummary = computed(() => {
  if (selectedPlayerId.value) {
    return playerSummaries.value.find((player) => player.id === selectedPlayerId.value) || null
  }
  return null
})

const teamSummary = computed(() => {
  const allRows = normalizedRows.value
  return buildPlayerSummary({
    id: 'team',
    name: props.teamName || 'Team',
    rows: allRows,
  })
})

const summaryForDisplay = computed(() => selectedPlayerSummary.value || teamSummary.value)

const buildWeightedReport = (summary, isTeam = false) => {
  if (!summary) return null
  const sourceRows = Array.isArray(summary.rows) ? summary.rows : []
  const validRows = sourceRows.filter((row) => row.velocity !== null && row.weight !== null)
  if (!validRows.length) return null

  const byWeight = Object.fromEntries(
    WB_WEIGHTS.map((weight) => {
      const velos = validRows
        .filter((row) => Number(row.weight) === weight)
        .map((row) => row.velocity)
        .filter((value) => value !== null)
      const avg = velos.length ? round1(velos.reduce((sum, value) => sum + value, 0) / velos.length) : null
      const top = velos.length ? round1(Math.max(...velos)) : null
      return [
        weight,
        {
          weight,
          label: `${weight} oz`,
          throws: velos,
          avg,
          top,
          count: velos.length,
        },
      ]
    }),
  )

  const baseline = byWeight[5]?.avg ?? null

  WB_WEIGHTS.forEach((weight) => {
    const expected = baseline ? baseline * WB_EXPECTED_MULTIPLIER[weight] : null
    byWeight[weight].expected = round1(expected)
    byWeight[weight].score = scoreFromExpected(byWeight[weight].avg, expected)
    byWeight[weight].avgDelta =
      byWeight[weight].avg !== null && byWeight[weight].expected !== null
        ? round1(byWeight[weight].avg - byWeight[weight].expected)
        : null
    byWeight[weight].topDelta =
      byWeight[weight].top !== null && byWeight[weight].expected !== null
        ? round1(byWeight[weight].top - byWeight[weight].expected)
        : null
  })

  const recordedScores = WB_WEIGHTS.map((weight) => byWeight[weight].score).filter((score) => score !== null)
  const developmentScore = recordedScores.length
    ? Math.round(recordedScores.reduce((sum, score) => sum + score, 0) / recordedScores.length)
    : null
  const transferScore =
    byWeight[3].avg && byWeight[5].avg && byWeight[3].expected && byWeight[5].expected
      ? Math.round(
          clamp(
            100 - Math.abs((byWeight[3].avg - byWeight[5].avg) - (byWeight[3].expected - byWeight[5].expected)) * 8,
            40,
            100,
          ),
        )
      : null
  const spread = byWeight[3].avg && byWeight[7].avg ? round1(byWeight[3].avg - byWeight[7].avg) : null
  const ranked = WB_WEIGHTS.map((weight) => byWeight[weight])
    .filter((item) => item.score !== null)
    .sort((a, b) => b.score - a.score)
  const bestWeight = ranked[0] || null
  const needsWork = ranked.length ? ranked[ranked.length - 1] : null
  const profile = (() => {
    if (!recordedScores.length) return 'No Velocity Baseline'
    if (recordedScores.every((score) => score >= 85)) return 'Balanced'
    if ((byWeight[3].score || 0) >= 85 && (byWeight[6].score || 0) < 75) return 'Underload Speed'
    if ((byWeight[6].score || 0) >= 85 && (byWeight[3].score || 0) < 75) return 'Overload Strength'
    if (spread !== null && spread > 12) return 'Large Weight Spread'
    return 'Developing'
  })()

  const curvePoints = WB_WEIGHTS.map((weight) => ({
    label: `${weight}oz`,
    actualAvg: byWeight[weight].avg,
    topVelo: byWeight[weight].top,
    expected: byWeight[weight].expected,
  }))

  const reportCards = [
    { label: 'Velocity Development Score', value: developmentScore ?? '--', subtext: gradeLabel(developmentScore) },
    ...WB_WEIGHTS.map((weight) => ({
      label: `${weight} oz Top / Avg`,
      value: `${formatMph(byWeight[weight].top)} / ${formatMph(byWeight[weight].avg)}`,
      subtext: `Expected ${formatMph(byWeight[weight].expected)} mph`,
    })),
    { label: 'Transfer Score', value: transferScore ?? '--', subtext: '3 oz to 5 oz carryover' },
    { label: 'Spread', value: spread !== null ? `${formatMph(spread)} mph` : '--', subtext: '3 oz avg minus 7 oz avg' },
    { label: 'Best Weight', value: bestWeight?.label || '--', subtext: bestWeight ? `${bestWeight.score} score` : '' },
    { label: 'Needs Work', value: needsWork?.label || '--', subtext: needsWork ? `${needsWork.score} score` : '' },
  ]

  const deltaCells = WB_WEIGHTS.map((weight) => ({
    weight,
    label: `${weight} oz`,
    avgDelta: byWeight[weight].avgDelta,
    topDelta: byWeight[weight].topDelta,
  }))

  const miniCharts = WB_WEIGHTS.map((weight) => ({
    weight,
    title: `${weight} oz Velocity by Throw`,
    expected: byWeight[weight].expected,
    points: byWeight[weight].throws.map((mph, index) => ({
      label: String(index + 1),
      value: mph,
      expected: byWeight[weight].expected,
    })),
  }))

  const recommendation = (() => {
    if (!baseline) return 'Record at least one 5 oz throw so expected velocity can lock to the regulation-ball baseline.'
    if (needsWork?.weight >= 6) return 'Continue current program and add more 6/7 oz strength-focused work.'
    if (needsWork?.weight <= 4) return 'Add more intent work with 3/4 oz balls while keeping mechanics under control.'
    return 'Keep the current weighted ball mix and build more complete sets across all five weights.'
  })()

  return {
    id: isTeam ? 'team-average' : summary.id,
    name: isTeam ? 'Team Average' : summary.name,
    subtitle: isTeam ? 'Team Average' : 'Weighted Ball Session Report',
    totalThrows: validRows.length,
    byWeight,
    baseline,
    developmentScore,
    transferScore,
    spread,
    bestWeight,
    needsWork,
    profile,
    curvePoints,
    reportCards,
    deltaCells,
    miniCharts,
    recommendation,
  }
}

const weightedPlayerReports = computed(() => playerSummaries.value.map((player) => buildWeightedReport(player)).filter(Boolean))
const weightedTeamReport = computed(() => buildWeightedReport(teamSummary.value, true))
const weightedVisibleReports = computed(() => {
  if (selectedPlayerId.value) {
    const selected = weightedPlayerReports.value.find((report) => String(report.id) === String(selectedPlayerId.value))
    return selected ? [selected] : []
  }
  return weightedTeamReport.value ? [weightedTeamReport.value] : weightedPlayerReports.value
})

const deltaClass = (value) => {
  if (value === null || value === undefined) return 'wb-delta-neutral'
  if (value > 0) return 'wb-delta-positive'
  if (value < 0) return 'wb-delta-negative'
  return 'wb-delta-neutral'
}

const chartSeries = (points, keys) =>
  points
    .flatMap((point) => keys.map((key) => point?.[key]))
    .filter((value) => value !== null && value !== undefined && Number.isFinite(Number(value)))

const chartBounds = (points, keys) => {
  const values = chartSeries(points, keys)
  if (!values.length) return { min: 0, max: 1 }
  const min = Math.floor(Math.min(...values) - 2)
  const max = Math.ceil(Math.max(...values) + 2)
  return { min, max: max > min ? max : min + 1 }
}

const chartX = (index, total, width = 520) => {
  const chartWidth = width - CHART_PADDING.left - CHART_PADDING.right
  return CHART_PADDING.left + (total <= 1 ? chartWidth / 2 : (index / (total - 1)) * chartWidth)
}

const chartY = (value, points, keys, height = 220) => {
  const bounds = chartBounds(points, keys)
  const chartHeight = height - CHART_PADDING.top - CHART_PADDING.bottom
  return CHART_PADDING.top + chartHeight - ((value - bounds.min) / Math.max(1, bounds.max - bounds.min)) * chartHeight
}

const chartPolyline = (points, key, keys, width = 520, height = 220) =>
  points
    .map((point, index) => {
      const value = point?.[key]
      if (value === null || value === undefined) return null
      return `${chartX(index, points.length, width)},${chartY(value, points, keys, height)}`
    })
    .filter(Boolean)
    .join(' ')

const chartHasData = (points, keys) => chartSeries(points, keys).length > 0

const leaderValue = (player) => {
  if (modeKey.value === 'LT') return player.topDistance
  return player.topVelocity
}

const leaderUnit = computed(() => (modeKey.value === 'LT' ? 'ft' : 'mph'))
const leaderLabel = computed(() => (modeKey.value === 'LT' ? 'Top Distance' : 'Top Velo'))
const averageLabel = computed(() => {
  if (modeKey.value === 'LT') return 'Avg Distance'
  if (modeKey.value === 'EV') return 'Avg EV'
  return 'Avg Velo'
})

const leaders = computed(() =>
  [...playerSummaries.value]
    .sort((a, b) => (leaderValue(b) ?? -1) - (leaderValue(a) ?? -1))
    .map((player, index) => ({ ...player, rank: index + 1 })),
)

const playerTabRows = computed(() => (selectedPlayerSummary.value ? [selectedPlayerSummary.value] : playerSummaries.value))

watch(filters, () => {
  if (!filters.value.some((filter) => filter.key === selectedFilter.value)) {
    selectedFilter.value = 'ALL'
  }
})

const selectPlayer = (playerId) => {
  selectedPlayerId.value = selectedPlayerId.value === playerId ? '' : playerId
  if (props.showTabs) internalActiveTab.value = 'PLAYER'
}

const setActiveTab = (tab) => {
  internalActiveTab.value = tab
}

const clearPlayer = () => {
  selectedPlayerId.value = ''
}

const modeTitle = computed(() => {
  if (modeKey.value === 'EV') return 'Exit Velocity'
  if (modeKey.value === 'LT') return 'Long Toss'
  if (modeKey.value === 'WB') return 'Weighted Balls'
  return 'Training'
})

const metricCards = computed(() => {
  const summary = summaryForDisplay.value
  if (modeKey.value === 'LT') {
    return [
      { label: 'Throws', value: formatNumber(summary.total, 0), unit: '' },
      { label: 'Top Distance', value: formatNumber(summary.topDistance), unit: 'ft' },
      { label: 'Avg Distance', value: formatNumber(summary.avgDistance), unit: 'ft' },
    ]
  }
  return [
    { label: modeKey.value === 'EV' ? 'Swings' : 'Throws', value: formatNumber(summary.total, 0), unit: '' },
    { label: modeKey.value === 'EV' ? 'Top EV' : 'Top Velo', value: formatNumber(summary.topVelocity), unit: 'mph' },
    { label: modeKey.value === 'EV' ? 'Avg EV' : 'Avg Velo', value: formatNumber(summary.avgVelocity), unit: 'mph' },
  ]
})
</script>

<template>
  <section class="training-stats-card">
    <div v-if="showTabs" class="training-tabs">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="training-tab"
        :class="{ 'training-tab--active': activeTabName === tab }"
        @click="setActiveTab(tab)"
      >
        {{ tab }}
      </button>
    </div>

    <div class="training-panel">
      <div v-if="!(modeKey === 'WB' && activeTabName === 'PLAYER')" class="training-header">
        <div>
          <p class="training-eyebrow">{{ modeTitle }} Stats</p>
          <h2>{{ activeTabName }}</h2>
        </div>
        <div class="training-subject">
          {{ selectedPlayerSummary?.name || teamName || 'Team' }}
        </div>
      </div>

      <div v-if="isLoading" class="training-empty">Loading stats...</div>
      <div v-else-if="normalizedRows.length === 0" class="training-empty">No training data is available yet.</div>

      <template v-else>
        <div v-if="!(modeKey === 'WB' && activeTabName === 'PLAYER')" class="training-metrics">
          <div v-for="card in metricCards" :key="card.label" class="training-metric">
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}<small v-if="card.unit"> {{ card.unit }}</small></strong>
          </div>
        </div>

        <div v-if="activeTabName !== 'BALL BY BALL' && !(modeKey === 'WB' && activeTabName === 'PLAYER')" class="training-filter-row">
          <button
            v-for="filterItem in filters"
            :key="filterItem.key"
            type="button"
            class="training-filter"
            :class="{ 'training-filter--active': selectedFilter === filterItem.key }"
            @click="selectedFilter = filterItem.key"
          >
            {{ filterItem.label }}
          </button>
        </div>

        <div v-if="activeTabName === 'BALL BY BALL'" class="training-table-wrap">
          <table class="training-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Player</th>
                <th>Set</th>
                <th v-if="modeKey === 'EV'">Trajectory</th>
                <th v-if="modeKey === 'LT'">Distance</th>
                <th v-if="modeKey === 'LT'">Hops</th>
                <th v-if="modeKey === 'WB'">Weight</th>
                <th v-if="modeKey !== 'LT'">Velocity</th>
                <th v-if="editable">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, rowIndex) in normalizedRows" :key="`bbb-${row.rowKey}`">
                <td>{{ rowIndex + 1 }}</td>
                <td class="training-player-cell">{{ row.player }}</td>
                <td>{{ row.set }}</td>
                <td v-if="modeKey === 'EV'">{{ trajectoryLabel(row.trajectory) }}</td>
                <td v-if="modeKey === 'LT'">{{ row.distance === null ? '-' : `${formatNumber(row.distance)} ft` }}</td>
                <td v-if="modeKey === 'LT'">{{ hopLabel(row.hop) }}</td>
                <td v-if="modeKey === 'WB'">{{ row.weight === null ? '-' : `${formatNumber(row.weight, 0)} oz` }}</td>
                <td v-if="modeKey !== 'LT'">{{ row.velocity === null ? '-' : `${formatNumber(row.velocity)} mph` }}</td>
                <td v-if="editable">
                  <button type="button" class="training-action" @click.stop="emit('edit-row', row.raw)">Edit</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="activeTabName === 'LEADERS'" class="training-leaders">
          <div class="training-list-card">
            <div class="training-card-title">Leaderboard</div>
            <div v-if="leaders.length === 0" class="training-empty training-empty--small">No leaders available.</div>
            <template v-else>
              <button
                v-for="player in leaders"
                :key="`leader-${player.id}`"
                type="button"
                class="training-leader-row"
                :class="{ 'training-leader-row--active': selectedPlayerId === player.id }"
                @click="selectPlayer(player.id)"
              >
                <span class="training-rank">{{ player.rank }}</span>
                <span class="training-name">{{ player.name }}</span>
                <span class="training-leader-stat">
                  {{ formatNumber(leaderValue(player)) }} {{ leaderUnit }}
                </span>
              </button>
            </template>
          </div>

          <div class="training-table-wrap">
            <table class="training-table">
              <thead>
                <tr>
                  <th>Player</th>
                  <th>{{ modeKey === 'LT' ? 'Throws' : modeKey === 'EV' ? 'Swings' : 'Throws' }}</th>
                  <th>{{ leaderLabel }}</th>
                  <th>{{ averageLabel }}</th>
                  <th v-if="modeKey === 'EV'">LD Avg</th>
                  <th v-if="modeKey === 'EV'">GB Avg</th>
                  <th v-if="modeKey === 'EV'">FB Avg</th>
                  <th v-if="modeKey === 'WB'">Weights</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="player in leaders" :key="`leader-table-${player.id}`" @click="selectPlayer(player.id)">
                  <td class="training-player-cell">{{ player.name }}</td>
                  <td>{{ player.total }}</td>
                  <td>{{ formatNumber(leaderValue(player)) }} {{ leaderUnit }}</td>
                  <td>{{ formatNumber(modeKey === 'LT' ? player.avgDistance : player.avgVelocity) }} {{ leaderUnit }}</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.ldAvg) }} mph</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.gbAvg) }} mph</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.fbAvg) }} mph</td>
                  <td v-if="modeKey === 'WB'">{{ player.weights.length ? player.weights.map((w) => `${formatNumber(w, 0)} oz`).join(', ') : '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="modeKey === 'WB'" class="wb-report-shell">
          <div class="wb-player-picker">
            <button
              type="button"
              class="wb-player-pill"
              :class="{ 'wb-player-pill--active': selectedPlayerId === '' }"
              @click="clearPlayer"
            >
              All
            </button>
            <button
              v-for="player in weightedPlayerReports"
              :key="`wb-player-${player.id}`"
              type="button"
              class="wb-player-pill"
              :class="{ 'wb-player-pill--active': selectedPlayerId === player.id }"
              @click="selectPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="!weightedVisibleReports.length" class="training-empty">No weighted ball throws are available for this player.</div>

          <article v-for="report in weightedVisibleReports" :key="`wb-report-${report.id}`" class="wb-report-card">
            <header class="wb-report-header">
              <div>
                <h3>{{ report.name }}</h3>
                <p>{{ report.subtitle }} - {{ report.totalThrows }} throws</p>
              </div>
              <div class="wb-score-badge">
                <strong>{{ report.developmentScore ?? '--' }}</strong>
                <span>{{ gradeLabel(report.developmentScore) }}</span>
              </div>
            </header>

            <div class="wb-main-grid">
              <div class="wb-curve-stack">
                <section class="wb-chart-card wb-chart-card--curve">
                  <h4>Weighted Ball Velocity Curve</h4>
                  <svg viewBox="0 0 520 220" class="wb-svg" preserveAspectRatio="none">
                    <line :x1="CHART_PADDING.left" :y1="CHART_PADDING.top" :x2="CHART_PADDING.left" :y2="220 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                    <line :x1="CHART_PADDING.left" :y1="220 - CHART_PADDING.bottom" :x2="520 - CHART_PADDING.right" :y2="220 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                    <text x="4" :y="chartY(chartBounds(report.curvePoints, WB_CURVE_KEYS).max, report.curvePoints, WB_CURVE_KEYS, 220) + 4" fill="rgba(255,255,255,0.58)" font-size="12" font-weight="800">{{ chartBounds(report.curvePoints, WB_CURVE_KEYS).max }}</text>
                    <text x="4" :y="chartY(chartBounds(report.curvePoints, WB_CURVE_KEYS).min, report.curvePoints, WB_CURVE_KEYS, 220) + 4" fill="rgba(255,255,255,0.58)" font-size="12" font-weight="800">{{ chartBounds(report.curvePoints, WB_CURVE_KEYS).min }}</text>
                    <polyline v-if="chartHasData(report.curvePoints, ['expected'])" :points="chartPolyline(report.curvePoints, 'expected', WB_CURVE_KEYS, 520, 220)" fill="none" stroke="#F7D774" stroke-width="4" stroke-dasharray="8,7" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline v-if="chartHasData(report.curvePoints, ['actualAvg'])" :points="chartPolyline(report.curvePoints, 'actualAvg', WB_CURVE_KEYS, 520, 220)" fill="none" stroke="#37D67A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                    <polyline v-if="chartHasData(report.curvePoints, ['topVelo'])" :points="chartPolyline(report.curvePoints, 'topVelo', WB_CURVE_KEYS, 520, 220)" fill="none" stroke="#34A7FF" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                    <g v-for="(point, index) in report.curvePoints" :key="`curve-${report.id}-${point.label}`">
                      <text :x="chartX(index, report.curvePoints.length, 520)" y="210" fill="rgba(255,255,255,0.72)" font-size="13" font-weight="900" text-anchor="middle">{{ point.label }}</text>
                      <circle v-if="point.expected !== null" :cx="chartX(index, report.curvePoints.length, 520)" :cy="chartY(point.expected, report.curvePoints, WB_CURVE_KEYS, 220)" r="4.5" fill="#F7D774" />
                      <circle v-if="point.actualAvg !== null" :cx="chartX(index, report.curvePoints.length, 520)" :cy="chartY(point.actualAvg, report.curvePoints, WB_CURVE_KEYS, 220)" r="5" fill="#37D67A" stroke="#0b1322" stroke-width="1.5" />
                      <circle v-if="point.topVelo !== null" :cx="chartX(index, report.curvePoints.length, 520)" :cy="chartY(point.topVelo, report.curvePoints, WB_CURVE_KEYS, 220)" r="5" fill="#34A7FF" stroke="#0b1322" stroke-width="1.5" />
                    </g>
                  </svg>
                  <div class="wb-legend">
                    <span class="wb-legend-avg">Avg</span>
                    <span class="wb-legend-top">Top</span>
                    <span class="wb-legend-expected">Expected</span>
                  </div>
                </section>

                <section class="wb-delta-card">
                  <div class="wb-section-head">
                    <h4>Vs Expected</h4>
                    <span>5 oz session baseline</span>
                  </div>
                  <div class="wb-delta-grid">
                    <div v-for="cell in report.deltaCells" :key="`delta-${report.id}-${cell.weight}`" class="wb-delta-cell">
                      <strong>{{ cell.label }}</strong>
                      <span :class="deltaClass(cell.avgDelta)">Avg {{ formatDelta(cell.avgDelta) }}</span>
                      <span :class="deltaClass(cell.topDelta)">Top {{ formatDelta(cell.topDelta) }}</span>
                    </div>
                  </div>
                </section>
              </div>

              <aside class="wb-report-metrics">
                <div v-for="card in report.reportCards" :key="`${report.id}-${card.label}`" class="wb-report-metric">
                  <span>{{ card.label }}</span>
                  <strong>{{ card.value }}</strong>
                  <small v-if="card.subtext">{{ card.subtext }}</small>
                </div>
              </aside>
            </div>

            <section class="wb-mini-section">
              <h4>Throw-by-Throw Velocity</h4>
              <div class="wb-mini-grid">
                <div v-for="chart in report.miniCharts" :key="`mini-${report.id}-${chart.weight}`" class="wb-chart-card wb-chart-card--mini">
                  <h5>{{ chart.title }}</h5>
                  <div v-if="!chart.points.length" class="wb-mini-empty">No throws recorded.</div>
                  <svg v-else viewBox="0 0 245 132" class="wb-mini-svg" preserveAspectRatio="none">
                    <line :x1="CHART_PADDING.left" :y1="CHART_PADDING.top" :x2="CHART_PADDING.left" :y2="132 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                    <line :x1="CHART_PADDING.left" :y1="132 - CHART_PADDING.bottom" :x2="245 - CHART_PADDING.right" :y2="132 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                    <text x="5" :y="chartY(chartBounds(chart.points, WB_MINI_KEYS).max, chart.points, WB_MINI_KEYS, 132) + 4" fill="rgba(255,255,255,0.55)" font-size="10" font-weight="800">{{ chartBounds(chart.points, WB_MINI_KEYS).max }}</text>
                    <text x="5" :y="chartY(chartBounds(chart.points, WB_MINI_KEYS).min, chart.points, WB_MINI_KEYS, 132) + 4" fill="rgba(255,255,255,0.55)" font-size="10" font-weight="800">{{ chartBounds(chart.points, WB_MINI_KEYS).min }}</text>
                    <line v-if="chart.expected !== null" :x1="CHART_PADDING.left" :y1="chartY(chart.expected, chart.points, WB_MINI_KEYS, 132)" :x2="245 - CHART_PADDING.right" :y2="chartY(chart.expected, chart.points, WB_MINI_KEYS, 132)" stroke="#F7D774" stroke-width="3" stroke-dasharray="7,6" />
                    <polyline v-if="chart.points.length > 1" :points="chartPolyline(chart.points, 'value', WB_MINI_KEYS, 245, 132)" fill="none" stroke="#37D67A" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                    <g v-for="(point, pointIndex) in chart.points" :key="`mini-point-${report.id}-${chart.weight}-${pointIndex}`">
                      <text :x="chartX(pointIndex, chart.points.length, 245)" y="124" fill="rgba(255,255,255,0.72)" font-size="10" font-weight="900" text-anchor="middle">{{ point.label }}</text>
                      <circle :cx="chartX(pointIndex, chart.points.length, 245)" :cy="chartY(point.value, chart.points, WB_MINI_KEYS, 132)" r="5" fill="#37D67A" stroke="#0b1322" stroke-width="1.5" />
                    </g>
                  </svg>
                </div>
              </div>
            </section>

            <section class="wb-feedback-card">
              <h4>Coach Feedback</h4>
              <strong>Velocity Profile: {{ report.profile }}</strong>
              <p>
                {{ report.profile === 'Balanced'
                  ? 'The athlete matched expected velocity across the recorded weights. Underload speed and overload strength are both developing well.'
                  : 'The athlete has a clear weighted-ball profile. Use the best and lowest-scoring weights to guide the next training block.' }}
              </p>
              <div class="wb-feedback-lines">
                <span>Best Weight: {{ report.bestWeight?.label || '--' }}</span>
                <span>Needs Work: {{ report.needsWork?.label || '--' }}</span>
              </div>
              <p class="wb-recommendation">Recommendation: {{ report.recommendation }}</p>
            </section>
          </article>
        </div>

        <div v-else class="training-player-panel">
          <div class="training-player-buttons">
            <button
              type="button"
              class="training-filter"
              :class="{ 'training-filter--active': selectedPlayerId === '' }"
              @click="clearPlayer"
            >
              Team
            </button>
            <button
              v-for="player in playerSummaries"
              :key="`player-filter-${player.id}`"
              type="button"
              class="training-filter"
              :class="{ 'training-filter--active': selectedPlayerId === player.id }"
              @click="selectPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <!-- Player chart — EV: avg velocity by trajectory (bars) · LT: avg distance by hop (x/y line) -->
          <section v-if="modeKey === 'EV' || modeKey === 'LT'" class="training-chart-card">
            <h4 class="training-chart-title">
              {{ modeKey === 'EV' ? 'Avg Exit Velocity by Trajectory' : 'Avg Distance by Hop' }}
              <span>{{ selectedPlayerId ? (playerSummaries.find((p) => p.id === selectedPlayerId)?.name || '') : 'Team' }}</span>
            </h4>

            <!-- EV: vertical bars -->
            <div v-if="modeKey === 'EV'" class="training-bars">
              <div v-for="c in evTrajectoryChart" :key="`bar-${c.label}`" class="training-bar-col">
                <div class="training-bar-val" :style="{ color: c.color }">
                  {{ c.avg != null ? formatNumber(c.avg) : '—' }}<em v-if="c.avg != null">mph</em>
                </div>
                <div class="training-bar-track">
                  <div class="training-bar-fill" :style="{ height: c.barPct + '%', background: c.color }" />
                </div>
                <div class="training-bar-label">{{ c.label }}</div>
                <div class="training-bar-count">{{ c.count }} sw</div>
              </div>
            </div>

            <!-- LT: x = hops (0,1,2,3), y = distance, point = avg distance per hop -->
            <div v-else class="training-lt-chart">
              <div class="training-lt-yaxis">Distance (ft)</div>
              <div class="training-lt-plot">
                <div v-if="!ltChartHasData" class="training-empty">No long toss throws for this selection.</div>
                <svg v-else viewBox="0 0 480 240" preserveAspectRatio="none" class="training-lt-svg">
                  <!-- axes -->
                  <line :x1="CHART_PADDING.left" :y1="CHART_PADDING.top" :x2="CHART_PADDING.left" :y2="240 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                  <line :x1="CHART_PADDING.left" :y1="240 - CHART_PADDING.bottom" :x2="480 - CHART_PADDING.right" :y2="240 - CHART_PADDING.bottom" stroke="rgba(255,255,255,0.22)" stroke-width="1" />
                  <!-- y min / max ticks -->
                  <text x="4" :y="chartY(chartBounds(ltHopCurvePoints, LT_HOP_KEYS).max, ltHopCurvePoints, LT_HOP_KEYS, 240) + 4" fill="rgba(255,255,255,0.55)" font-size="11" font-weight="800">{{ chartBounds(ltHopCurvePoints, LT_HOP_KEYS).max }}</text>
                  <text x="4" :y="chartY(chartBounds(ltHopCurvePoints, LT_HOP_KEYS).min, ltHopCurvePoints, LT_HOP_KEYS, 240) + 4" fill="rgba(255,255,255,0.55)" font-size="11" font-weight="800">{{ chartBounds(ltHopCurvePoints, LT_HOP_KEYS).min }}</text>
                  <!-- distance line across hops -->
                  <polyline v-if="chartHasData(ltHopCurvePoints, LT_HOP_KEYS)" :points="chartPolyline(ltHopCurvePoints, 'value', LT_HOP_KEYS, 480, 240)" fill="none" stroke="#37D67A" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                  <!-- x labels (hops) + points (avg distance) -->
                  <g v-for="(point, index) in ltHopCurvePoints" :key="`lt-hop-${point.label}`">
                    <text :x="chartX(index, ltHopCurvePoints.length, 480)" y="232" fill="rgba(255,255,255,0.75)" font-size="13" font-weight="900" text-anchor="middle">{{ point.label }}</text>
                    <template v-if="point.value !== null">
                      <circle :cx="chartX(index, ltHopCurvePoints.length, 480)" :cy="chartY(point.value, ltHopCurvePoints, LT_HOP_KEYS, 240)" r="5.5" :fill="point.color" stroke="#0b1322" stroke-width="1.5" />
                      <text :x="chartX(index, ltHopCurvePoints.length, 480)" :y="chartY(point.value, ltHopCurvePoints, LT_HOP_KEYS, 240) - 11" fill="#fff" font-size="11" font-weight="800" text-anchor="middle">{{ formatNumber(point.value) }}</text>
                    </template>
                  </g>
                </svg>
              </div>
              <div class="training-lt-xaxis">Hops</div>
            </div>
          </section>

          <div class="training-table-wrap">
            <table class="training-table">
              <thead>
                <tr>
                  <th>Player</th>
                  <th>{{ modeKey === 'LT' ? 'Throws' : modeKey === 'EV' ? 'Swings' : 'Throws' }}</th>
                  <th>{{ leaderLabel }}</th>
                  <th>{{ averageLabel }}</th>
                  <th v-if="modeKey === 'LT'">No Hop Avg</th>
                  <th v-if="modeKey === 'LT'">1 Hop Avg</th>
                  <th v-if="modeKey === 'LT'">2 Hop Avg</th>
                  <th v-if="modeKey === 'LT'">3 Hop Avg</th>
                  <th v-if="modeKey === 'EV'">Line Drive Avg</th>
                  <th v-if="modeKey === 'EV'">Ground Ball Avg</th>
                  <th v-if="modeKey === 'EV'">Fly Ball Avg</th>
                  <th v-if="modeKey === 'WB'">Weight Breakdown</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="player in playerTabRows" :key="`player-row-${player.id}`">
                  <td class="training-player-cell">{{ player.name }}</td>
                  <td>{{ player.total }}</td>
                  <td>{{ formatNumber(leaderValue(player)) }} {{ leaderUnit }}</td>
                  <td>{{ formatNumber(modeKey === 'LT' ? player.avgDistance : player.avgVelocity) }} {{ leaderUnit }}</td>
                  <td v-if="modeKey === 'LT'">{{ formatNumber(player.lt.noHopAvg) }} ft</td>
                  <td v-if="modeKey === 'LT'">{{ formatNumber(player.lt.oneHopAvg) }} ft</td>
                  <td v-if="modeKey === 'LT'">{{ formatNumber(player.lt.twoHopAvg) }} ft</td>
                  <td v-if="modeKey === 'LT'">{{ formatNumber(player.lt.threeHopAvg) }} ft</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.ldAvg) }} mph</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.gbAvg) }} mph</td>
                  <td v-if="modeKey === 'EV'">{{ formatNumber(player.ev.fbAvg) }} mph</td>
                  <td v-if="modeKey === 'WB'" class="training-breakdown">
                    <span v-if="!player.weights.length">-</span>
                    <template v-else>
                      <span v-for="weight in player.weights" :key="`wb-${player.id}-${weight}`">
                        {{ formatNumber(weight, 0) }} oz: {{ formatNumber(player.wb[String(weight)]?.avg) }} avg / {{ formatNumber(player.wb[String(weight)]?.top) }} top
                      </span>
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </section>
</template>

<style scoped>
.training-stats-card {
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 22px;
  background: rgba(6, 10, 26, 0.9);
  box-shadow: 0 20px 55px rgba(0, 0, 0, 0.28);
  color: #fff;
}

.training-tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  padding: 10px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.training-tab {
  border-radius: 14px;
  background: rgba(47, 51, 61, 0.95);
  padding: 12px 14px;
  color: rgba(255, 255, 255, 0.82);
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  transition: 0.18s ease;
}

.training-tab--active {
  background: #ff2d55;
  color: #fff;
  box-shadow: 0 12px 28px rgba(255, 45, 85, 0.28);
}

.training-panel {
  padding: 18px;
}

.training-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.training-eyebrow {
  color: #ff2d55;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.training-header h2 {
  margin-top: 4px;
  color: #fff;
  font-size: 24px;
  font-weight: 950;
  letter-spacing: 0.04em;
}

.training-subject {
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  padding: 9px 13px;
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}

.training-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.training-metric {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.06);
  padding: 14px;
}

.training-metric span {
  display: block;
  color: rgba(255, 255, 255, 0.62);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.training-metric strong {
  display: block;
  margin-top: 7px;
  color: #fff;
  font-size: 26px;
  font-weight: 950;
}

.training-metric small {
  color: rgba(255, 255, 255, 0.65);
  font-size: 13px;
}

.training-filter-row,
.training-player-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 16px;
}

.training-filter {
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  padding: 9px 14px;
  color: rgba(255, 255, 255, 0.82);
  font-size: 12px;
  font-weight: 900;
  text-transform: uppercase;
  transition: 0.18s ease;
}

.training-filter--active {
  border-color: #ff2d55;
  background: #ff2d55;
  color: #fff;
}

.training-table-wrap {
  overflow-x: auto;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(0, 0, 0, 0.22);
}

/* Player-tab chart (EV avg velocity by trajectory · LT avg distance by hop) */
.training-chart-card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(0, 0, 0, 0.22);
  padding: 16px 18px 12px;
  margin-bottom: 14px;
}
.training-chart-title {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.85);
  margin: 0 0 14px;
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.training-chart-title span {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: rgba(255, 255, 255, 0.4);
}
.training-bars {
  display: flex;
  align-items: flex-end;
  gap: 14px;
  min-height: 180px;
}
.training-bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}
.training-bar-val {
  font-size: 16px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}
.training-bar-val em {
  font-style: normal;
  font-size: 10px;
  font-weight: 600;
  opacity: 0.65;
  margin-left: 2px;
}
.training-bar-track {
  width: 100%;
  max-width: 64px;
  height: 130px;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px 8px 4px 4px;
  overflow: hidden;
}
.training-bar-fill {
  width: 100%;
  border-radius: 8px 8px 0 0;
  transition: height 0.4s ease;
  min-height: 3px;
}
.training-bar-label {
  font-size: 11px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.82);
  text-align: center;
}
.training-bar-count {
  font-size: 9px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.35);
}

/* Long Toss x/y line chart (x = hops 0-3, y = distance) */
.training-lt-chart {
  display: grid;
  grid-template-columns: 18px 1fr;
  grid-template-rows: 1fr 18px;
  gap: 4px;
  align-items: center;
}
.training-lt-yaxis {
  grid-row: 1;
  grid-column: 1;
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
}
.training-lt-plot {
  grid-row: 1;
  grid-column: 2;
  min-height: 200px;
}
.training-lt-svg {
  width: 100%;
  height: auto;
  display: block;
}
.training-lt-xaxis {
  grid-row: 2;
  grid-column: 2;
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
}

.training-table {
  width: 100%;
  min-width: 720px;
  border-collapse: collapse;
  font-size: 14px;
}

.training-table th {
  background: #161d3c;
  color: rgba(255, 255, 255, 0.84);
  padding: 13px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 950;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}

.training-table td {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding: 13px 12px;
  color: rgba(255, 255, 255, 0.9);
  white-space: nowrap;
}

.training-table tbody tr:nth-child(odd) {
  background: rgba(255, 255, 255, 0.04);
}

.training-table tbody tr:nth-child(even) {
  background: rgba(255, 255, 255, 0.08);
}

.training-table tbody tr {
  cursor: pointer;
}

.training-player-cell {
  font-weight: 900;
}

.training-action {
  border-radius: 10px;
  background: #ff2d55;
  padding: 8px 13px;
  color: #fff;
  font-size: 12px;
  font-weight: 950;
  text-transform: uppercase;
}

.training-leaders {
  display: grid;
  grid-template-columns: minmax(220px, 0.85fr) minmax(0, 2fr);
  gap: 14px;
}

.training-list-card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.06);
  padding: 12px;
}

.training-card-title {
  margin-bottom: 10px;
  color: rgba(255, 255, 255, 0.7);
  font-size: 11px;
  font-weight: 950;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.training-leader-row {
  display: grid;
  width: 100%;
  grid-template-columns: 34px 1fr auto;
  gap: 10px;
  align-items: center;
  border-radius: 13px;
  padding: 10px;
  color: #fff;
  text-align: left;
}

.training-leader-row:hover,
.training-leader-row--active {
  background: rgba(255, 45, 85, 0.22);
}

.training-rank {
  display: grid;
  height: 30px;
  width: 30px;
  place-items: center;
  border-radius: 999px;
  background: #ff2d55;
  font-weight: 950;
}

.training-name {
  overflow: hidden;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.training-leader-stat {
  color: rgba(255, 255, 255, 0.72);
  font-size: 12px;
  font-weight: 900;
}

.training-breakdown {
  white-space: normal;
}

.training-breakdown span {
  display: block;
}

.training-empty {
  border: 1px dashed rgba(255, 255, 255, 0.18);
  border-radius: 16px;
  padding: 28px;
  color: rgba(255, 255, 255, 0.62);
  text-align: center;
}

.training-empty--small {
  padding: 16px;
}

.wb-report-shell {
  display: grid;
  gap: 18px;
}

.wb-player-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 18px;
  background: rgba(3, 8, 24, 0.72);
  padding: 12px;
}

.wb-player-pill {
  min-width: 118px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.08);
  padding: 13px 18px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 14px;
  font-weight: 950;
  text-align: center;
  transition: 0.18s ease;
}

.wb-player-pill:hover,
.wb-player-pill--active {
  border-color: #ff2d55;
  background: #ff2d55;
  color: #fff;
  box-shadow: 0 14px 30px rgba(255, 45, 85, 0.22);
}

.wb-report-card {
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 22px;
  background:
    linear-gradient(135deg, rgba(13, 18, 38, 0.94), rgba(11, 17, 39, 0.84)),
    rgba(10, 15, 31, 0.94);
  box-shadow: 0 22px 58px rgba(0, 0, 0, 0.28);
  padding: 20px;
}

.wb-report-header {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 16px;
  align-items: start;
  margin-bottom: 18px;
}

.wb-report-header h3 {
  color: #fff;
  font-size: clamp(28px, 3vw, 44px);
  font-weight: 950;
  line-height: 1;
}

.wb-report-header p {
  margin-top: 8px;
  color: rgba(255, 255, 255, 0.64);
  font-size: 17px;
  font-weight: 900;
}

.wb-score-badge {
  min-width: 142px;
  border-radius: 18px;
  background: #ff2d55;
  padding: 18px 20px;
  text-align: center;
  box-shadow: 0 18px 36px rgba(255, 45, 85, 0.24);
}

.wb-score-badge strong {
  display: block;
  color: #fff;
  font-size: 44px;
  font-weight: 950;
  line-height: 0.95;
}

.wb-score-badge span {
  display: block;
  margin-top: 8px;
  color: #fff;
  font-size: 13px;
  font-weight: 950;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.wb-main-grid {
  display: grid;
  grid-template-columns: minmax(360px, 1.45fr) minmax(320px, 0.95fr);
  gap: 18px;
  align-items: start;
}

.wb-curve-stack,
.wb-report-metrics,
.wb-mini-section {
  min-width: 0;
}

.wb-chart-card,
.wb-delta-card,
.wb-report-metric,
.wb-feedback-card {
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.07);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

.wb-chart-card {
  padding: 16px;
}

.wb-chart-card h4,
.wb-mini-section h4,
.wb-feedback-card h4 {
  margin-bottom: 12px;
  color: #fff;
  font-size: 20px;
  font-weight: 950;
}

.wb-chart-card h5 {
  margin-bottom: 10px;
  color: #fff;
  font-size: 16px;
  font-weight: 950;
}

.wb-svg,
.wb-mini-svg {
  display: block;
  width: 100%;
  overflow: visible;
}

.wb-svg {
  height: 260px;
}

.wb-mini-svg {
  height: 150px;
}

.wb-legend {
  display: flex;
  justify-content: center;
  gap: 26px;
  margin-top: 10px;
  font-size: 14px;
  font-weight: 950;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.wb-legend-avg {
  color: #37d67a;
}

.wb-legend-top {
  color: #34a7ff;
}

.wb-legend-expected {
  color: #f7d774;
}

.wb-delta-card {
  margin-top: 14px;
  padding: 16px;
}

.wb-section-head {
  display: flex;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 12px;
}

.wb-section-head h4 {
  color: #fff;
  font-size: 18px;
  font-weight: 950;
}

.wb-section-head span {
  color: rgba(255, 255, 255, 0.58);
  font-size: 12px;
  font-weight: 950;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.wb-delta-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}

.wb-delta-cell {
  border-radius: 14px;
  background: rgba(4, 9, 25, 0.55);
  padding: 12px;
}

.wb-delta-cell strong,
.wb-delta-cell span {
  display: block;
  font-weight: 950;
}

.wb-delta-cell strong {
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  text-transform: uppercase;
}

.wb-delta-cell span {
  margin-top: 6px;
  font-size: 14px;
}

.wb-delta-positive {
  color: #37d67a;
}

.wb-delta-negative {
  color: #ff4b5f;
}

.wb-delta-neutral {
  color: rgba(255, 255, 255, 0.7);
}

.wb-report-metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.wb-report-metric {
  min-height: 104px;
  padding: 16px;
}

.wb-report-metric span {
  display: block;
  color: rgba(255, 255, 255, 0.62);
  font-size: 13px;
  font-weight: 950;
  letter-spacing: 0.07em;
  text-transform: uppercase;
}

.wb-report-metric strong {
  display: block;
  margin-top: 10px;
  color: #fff;
  font-size: clamp(26px, 2.4vw, 38px);
  font-weight: 950;
  line-height: 1;
}

.wb-report-metric small {
  display: block;
  margin-top: 8px;
  color: rgba(255, 255, 255, 0.62);
  font-size: 13px;
  font-weight: 850;
}

.wb-mini-section {
  margin-top: 22px;
}

.wb-mini-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.wb-chart-card--mini {
  min-height: 204px;
}

.wb-mini-empty {
  display: grid;
  min-height: 132px;
  place-items: center;
  color: rgba(255, 255, 255, 0.55);
  font-size: 13px;
  font-weight: 800;
}

.wb-feedback-card {
  margin-top: 22px;
  padding: 18px;
}

.wb-feedback-card h4 {
  color: #ff2d55;
}

.wb-feedback-card strong {
  display: block;
  color: #fff;
  font-size: 18px;
  font-weight: 950;
}

.wb-feedback-card p {
  margin-top: 12px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 16px;
  font-weight: 700;
  line-height: 1.5;
}

.wb-feedback-lines {
  display: grid;
  gap: 4px;
  margin-top: 12px;
}

.wb-feedback-lines span {
  color: #fff;
  font-size: 16px;
  font-weight: 950;
}

.wb-feedback-card .wb-recommendation {
  color: #f7d774;
  font-weight: 950;
}

@media (max-width: 768px) {
  .training-tabs,
  .training-metrics,
  .training-leaders,
  .wb-main-grid,
  .wb-report-header,
  .wb-report-metrics,
  .wb-mini-grid,
  .wb-delta-grid {
    grid-template-columns: 1fr;
  }

  .training-header {
    flex-direction: column;
  }

  .training-subject {
    white-space: normal;
  }

  .wb-report-card {
    padding: 14px;
  }

  .wb-player-pill {
    width: 100%;
  }

  .wb-score-badge {
    width: 100%;
  }
}
</style>
