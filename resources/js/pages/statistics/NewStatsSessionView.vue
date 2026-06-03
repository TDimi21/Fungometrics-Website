<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/vue'
import Layout from '@/layout/Layout.vue'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from '@/utils/AlertPlugin'
import SessionHeatmapPanel from '@/components/statistics/session/SessionHeatmapPanel.vue'
import SessionVelocityGridPanel from '@/components/statistics/session/SessionVelocityGridPanel.vue'
import { TabBallByBall as BattingAllPitches } from '@/components/statistics'
import { TabBall as BullpenAllPitches, TabVelo as BullpenVelocity } from '@/components/statistics/bullpen'
import {
  BattingTotals,
  BattingPercentage,
  PitchingTotals,
  PitchingPercentage,
  CageLaunchTotal,
  CageLaunchPercentage,
  ExitTotals,
  ExitPercentage,
  LongDistanceTotal,
  LongDistancePercentage,
  WeightedTotals,
  WeightedAverage,
  LiveABHitterBasic,
  LiveABHitterAdvance,
} from '@/components/globalStats/index.js'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const { userData } = useUserStore()

const loading = ref(false)
const teamName = ref('Team')
const playersMap = ref({})

const flatRows = ref([])
const byPlayerRows = ref({})

const totalsPlayers = ref(null)
const totalsTeam = ref(null)
const percentagesPlayers = ref(null)
const percentagesTeam = ref(null)

const cageMetricTabs = ['TOTALS', 'PERCENTAGES', 'AVG EV', 'MAX EV']
const cageAngleTabs = ['LAUNCH ANGLE', 'SPRAY ANGLE']
const selectedCageMetricTab = ref('TOTALS')
const selectedCageAngleTab = ref('LAUNCH ANGLE')
const selectedCagePlayerIds = ref([])
const selectedBattingPlayerIds = ref([])
const selectedEvPlayerIds = ref([])
const selectedBullpenPlayerIds = ref([])
const selectedLongTossPlayerIds = ref([])
const selectedWeightBallPlayerIds = ref([])
const selectedLiveAbPlayerIds = ref([])

const toggleCagePlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedCagePlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedCagePlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedCagePlayerIds.value = [...current, id]
}

const toggleBullpenPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedBullpenPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedBullpenPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedBullpenPlayerIds.value = [...current, id]
}

const toggleBattingPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedBattingPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedBattingPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedBattingPlayerIds.value = [...current, id]
}

const toggleEvPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedEvPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedEvPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedEvPlayerIds.value = [...current, id]
}

const toggleLongTossPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedLongTossPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedLongTossPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedLongTossPlayerIds.value = [...current, id]
}

const toggleWeightBallPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedWeightBallPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedWeightBallPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedWeightBallPlayerIds.value = [...current, id]
}

const toggleLiveAbPlayer = (playerId) => {
  const id = String(playerId)
  const current = selectedLiveAbPlayerIds.value.map(String)
  if (current.includes(id)) {
    selectedLiveAbPlayerIds.value = current.filter((x) => x !== id)
    return
  }
  selectedLiveAbPlayerIds.value = [...current, id]
}

const tabNames = computed(() => {
  if (selectedSession.value === 'P') {
    return ['ALL PITCHES', 'HEATMAP', 'VELO GRID', 'S&M', 'TOTALS', 'PERCENTAGES']
  }
  if (selectedSession.value === 'B') {
    return ['ALL PITCHES', 'SPRAY CHART', 'TOTALS', 'PERCENTAGES']
  }
  if (selectedSession.value === 'C') {
    return ['ALL PITCHES', 'VELO', 'CAGE STATS']
  }
  if (selectedSession.value === 'LT') {
    return ['ALL THROWS', 'HOPS', 'TOTALS', 'PERCENTAGES']
  }
  if (selectedSession.value === 'L') {
    return ['ALL PITCHES', 'VELO', 'TOTALS', 'PERCENTAGES', 'BOX SCORE']
  }
  return ['ALL PITCHES', 'VELO', 'TOTALS', 'PERCENTAGES']
})

const selectedSession = computed(() => String(route.query?.session || '').toUpperCase())
const isBatting = computed(() => selectedSession.value === 'B')
const isBullpen = computed(() => selectedSession.value === 'P')
const endpointBySession = {
  B: 'batting',
  P: 'bullpen',
  C: 'cage',
  EV: 'exitvelocity',
  LT: 'longtoss',
  WB: 'weightball',
  L: 'liveab',
}
const sessionListKeyBySession = {
  B: 'batting',
  P: 'bullpen',
  C: 'cage',
  EV: 'exit_velocity',
  LT: 'long_toss',
  WB: 'weight_ball',
  L: 'live',
}

const endpoint = computed(() => endpointBySession[selectedSession.value] || 'batting')

const pageTitle = computed(() => {
  const map = {
    B: 'Batting Statistics',
    P: 'Bullpen Statistics',
    C: 'Cage Statistics',
    EV: 'Exit Velocity Statistics',
    LT: 'Long Toss Statistics',
    WB: 'Weighted Ball Statistics',
    L: 'Live AB Statistics',
  }
  return map[selectedSession.value] || 'Statistics'
})

const selectedPlayers = computed(() => {
  const raw = String(route.query?.players || '')
  return raw.split(',').map((v) => String(v).trim()).filter(Boolean)
})

const selectedSessionIds = computed(() => {
  const raw = String(route.query?.sessionIds || '')
  return raw.split(',').map((v) => String(v).trim()).filter(Boolean)
})

const teamId = computed(() => String(route.query?.team || ''))
const playerId = computed(() => String(route.query?.playerId || ''))
const isPlayerScope = computed(() => Boolean(playerId.value))
const sinceWhen = computed(() => String(route.query?.since || ''))
const until = computed(() => String(route.query?.until || ''))

const supportsTabsView = computed(() => ['B', 'P', 'C', 'EV', 'LT', 'WB', 'L'].includes(selectedSession.value))
const defaultTabIndex = computed(() => {
  const tab = String(route.query?.tab || '').toUpperCase()
  if (!tab) return 0
  const idx = tabNames.value.findIndex((t) => t.toUpperCase() === tab)
  return idx >= 0 ? idx : 0
})
const supportsSpatial = computed(() => ['B', 'P'].includes(selectedSession.value))
const spatialBaseGrid = computed(() => (isBatting.value ? 80 : 60))
const spatialGridSize = computed(() => (isBatting.value ? 6 : 5))
const spatialMarkMode = computed(() => (isBatting.value ? 'field' : 'pitch'))
const spatialBackground = computed(() => (isBatting.value ? 'field' : 'catcher'))

const totalsComponent = computed(() => {
  if (selectedSession.value === 'B') return BattingTotals
  if (selectedSession.value === 'P') return PitchingTotals
  if (selectedSession.value === 'C') return CageLaunchTotal
  if (selectedSession.value === 'EV') return ExitTotals
  if (selectedSession.value === 'LT') return LongDistanceTotal
  if (selectedSession.value === 'WB') return WeightedTotals
  if (selectedSession.value === 'L') return LiveABHitterBasic
  return null
})

const percentagesComponent = computed(() => {
  if (selectedSession.value === 'B') return BattingPercentage
  if (selectedSession.value === 'P') return PitchingPercentage
  if (selectedSession.value === 'C') return CageLaunchPercentage
  if (selectedSession.value === 'EV') return ExitPercentage
  if (selectedSession.value === 'LT') return LongDistancePercentage
  if (selectedSession.value === 'WB') return WeightedAverage
  if (selectedSession.value === 'L') return LiveABHitterAdvance
  return null
})

const filterPlayerObjectByIds = (playersObj, ids) => {
  if (!playersObj || typeof playersObj !== 'object') return playersObj
  if (!Array.isArray(ids) || ids.length === 0) return playersObj
  const picked = new Set(ids.map(String))
  return Object.fromEntries(
    Object.entries(playersObj).filter(([id]) => picked.has(String(id))),
  )
}

const displayTotalsPlayers = computed(() => {
  if (selectedSession.value === 'P') {
    return filterPlayerObjectByIds(totalsPlayers.value, selectedBullpenPlayerIds.value)
  }
  if (selectedSession.value === 'B') {
    return filterPlayerObjectByIds(totalsPlayers.value, selectedBattingPlayerIds.value)
  }
  if (selectedSession.value === 'EV') {
    const source = hasTotalsData.value ? totalsPlayers.value : evFallbackAggregate.value.totals.players
    return filterPlayerObjectByIds(source, selectedEvPlayerIds.value)
  }
  if (selectedSession.value === 'LT') {
    return filterPlayerObjectByIds(ltRangeAggregate.value.totals.players, selectedLongTossPlayerIds.value)
  }
  if (selectedSession.value === 'WB') {
    return filterPlayerObjectByIds(wbAggregate.value.totals.players, selectedWeightBallPlayerIds.value)
  }
  if (selectedSession.value === 'L') {
    return filterPlayerObjectByIds(totalsPlayers.value, selectedLiveAbPlayerIds.value)
  }
  return totalsPlayers.value
})

const displayPercentagesPlayers = computed(() => {
  if (selectedSession.value === 'P') {
    return filterPlayerObjectByIds(percentagesPlayers.value, selectedBullpenPlayerIds.value)
  }
  if (selectedSession.value === 'B') {
    return filterPlayerObjectByIds(percentagesPlayers.value, selectedBattingPlayerIds.value)
  }
  if (selectedSession.value === 'EV') {
    const source = hasPercentagesData.value ? percentagesPlayers.value : evFallbackAggregate.value.percents.players
    return filterPlayerObjectByIds(source, selectedEvPlayerIds.value)
  }
  if (selectedSession.value === 'LT') {
    return filterPlayerObjectByIds(ltRangeAggregate.value.percents.players, selectedLongTossPlayerIds.value)
  }
  if (selectedSession.value === 'WB') {
    return filterPlayerObjectByIds(wbAggregate.value.averages.players, selectedWeightBallPlayerIds.value)
  }
  if (selectedSession.value === 'L') {
    return filterPlayerObjectByIds(percentagesPlayers.value, selectedLiveAbPlayerIds.value)
  }
  return percentagesPlayers.value
})

const displayTotalsTeam = computed(() => {
  if (selectedSession.value !== 'EV') return totalsTeam.value
  return hasTotalsData.value ? totalsTeam.value : evFallbackAggregate.value.totals.team_totals
})

const displayPercentagesTeam = computed(() => {
  if (selectedSession.value !== 'EV') return percentagesTeam.value
  return hasPercentagesData.value ? percentagesTeam.value : evFallbackAggregate.value.percents.team_totals
})

const displayTotalsTeamFinal = computed(() => {
  if (selectedSession.value === 'LT') return ltRangeAggregate.value.totals.team_totals
  if (selectedSession.value === 'WB') return wbAggregate.value.totals.team_totals
  return displayTotalsTeam.value
})

const displayPercentagesTeamFinal = computed(() => {
  if (selectedSession.value === 'LT') return ltRangeAggregate.value.percents.team_totals
  if (selectedSession.value === 'WB') return wbAggregate.value.averages.team_totals
  return displayPercentagesTeam.value
})

const genericTableRows = computed(() => {
  return visibleFlatRows.value.map((row, idx) => ({
    idx: idx + 1,
    player: getPlayerName(row),
    type: getTypeLabel(row),
    zone: getZoneLabel(row),
    velo: getVelocityValue(row),
  }))
})

const genericVelocityRows = computed(() => {
  const grouped = {}
  visibleFlatRows.value.forEach((row) => {
    const velo = getVelocityNumber(row)
    if (velo === null) return
    const player = getPlayerName(row)
    if (!grouped[player]) grouped[player] = []
    grouped[player].push(velo)
  })

  return Object.keys(grouped).map((player) => {
    const arr = grouped[player]
    const avg = arr.reduce((a, b) => a + b, 0) / arr.length
    const max = Math.max(...arr)
    return {
      player,
      avg: avg.toFixed(2),
      max: max.toFixed(2),
      count: arr.length,
    }
  })
})

const launchRanges = [
  { label: '0-5', match: (angle) => angle <= 5 },
  { label: '6-10', match: (angle) => angle > 5 && angle <= 10 },
  { label: '11-15', match: (angle) => angle > 10 && angle <= 15 },
  { label: '16-20', match: (angle) => angle > 15 && angle <= 20 },
  { label: '21-25', match: (angle) => angle > 20 && angle <= 25 },
  { label: '26-30', match: (angle) => angle > 25 && angle <= 30 },
  { label: '31-35', match: (angle) => angle > 30 && angle <= 35 },
  { label: '36-40', match: (angle) => angle > 35 && angle <= 40 },
  { label: '41-45', match: (angle) => angle > 40 && angle <= 45 },
  { label: '46-50', match: (angle) => angle > 45 && angle <= 50 },
  { label: '51+', match: (angle) => angle > 50 },
]

const sprayRanges = [
  { label: '<-45', match: (angle) => angle < -45 },
  { label: '-44 TO -31', match: (angle) => angle >= -44 && angle <= -31 },
  { label: '-30 TO -16', match: (angle) => angle >= -30 && angle <= -16 },
  { label: '-15 TO -6', match: (angle) => angle >= -15 && angle <= -6 },
  { label: '-5 TO 5', match: (angle) => angle >= -5 && angle <= 5 },
  { label: '6 TO 15', match: (angle) => angle >= 6 && angle <= 15 },
  { label: '16 TO 30', match: (angle) => angle >= 16 && angle <= 30 },
  { label: '31 TO 44', match: (angle) => angle >= 31 && angle <= 44 },
  { label: '>45', match: (angle) => angle > 45 },
]

const toNumberOrNull = (value) => {
  if (value === null || value === undefined || value === '') return null
  const num = Number(value)
  return Number.isFinite(num) ? num : null
}

const getLaunchAngleValue = (row) =>
  toNumberOrNull(
    row?.launch_angle ?? row?.launchAngle ?? row?.angle ?? row?.launch?.angle ?? null,
  )

const getSprayAngleValue = (row) =>
  toNumberOrNull(
    row?.spray_angle ?? row?.sprayAngle ?? row?.direction ?? row?.spray?.angle ?? null,
  )

const mapToCageRows = (rows) => {
  return rows.map((row, idx) => {
    const ids = getPlayerIdsFromRow(row)
    const playerId = ids.length > 0 ? ids[0] : `unknown-${idx}`
    return {
      playerId,
      playerName: getPlayerName(row),
      launchAngle: getLaunchAngleValue(row),
      sprayAngle: getSprayAngleValue(row),
      velocity: getVelocityNumber(row),
    }
  })
}

const cageRowsAll = computed(() => mapToCageRows(flatRows.value))
const cageRows = computed(() => mapToCageRows(visibleFlatRows.value))

const topPlayerButtons = computed(() => {
  const map = new Map()
  flatRows.value.forEach((row) => {
    const ids = getPlayerIdsFromRow(row)
    const id = ids[0]
    if (!id || map.has(id)) return
    map.set(String(id), getPlayerName(row))
  })
  return [...map.entries()].map(([id, name]) => ({ id, name }))
})

const fallbackSelectedPlayerButtons = computed(() => {
  const picked = Array.isArray(selectedPlayers.value) ? selectedPlayers.value : []
  return picked.map((id) => ({
    id: String(id),
    name: playersMap.value[String(id)] || `Player ${String(id).slice(0, 6)}`,
  }))
})

const availablePlayerButtons = computed(() => {
  return topPlayerButtons.value.length > 0
    ? topPlayerButtons.value
    : fallbackSelectedPlayerButtons.value
})

const visibleByPlayerRows = computed(() => {
  if (selectedSession.value !== 'P') return byPlayerRows.value
  return filterPlayerObjectByIds(byPlayerRows.value, selectedBullpenPlayerIds.value)
})

const getCageRowsForPlayer = (playerId) => {
  return cageRowsAll.value.filter((row) => String(row.playerId) === String(playerId))
}

const formatCageValue = (rows, ranges, metric, angleKey) => {
  const validAngles = rows.filter((r) => r[angleKey] !== null)
  const swings = validAngles.length
  const out = { swings }

  ranges.forEach((range) => {
    const rangeRows = validAngles.filter((r) => range.match(r[angleKey]))
    const count = rangeRows.length

    if (metric === 'TOTALS') {
      out[range.label] = String(count)
      return
    }

    if (metric === 'PERCENTAGES') {
      out[range.label] = swings > 0 ? `${((count / swings) * 100).toFixed(1)}%` : '0%'
      return
    }

    const velocities = rangeRows
      .map((r) => r.velocity)
      .filter((v) => v !== null)

    if (velocities.length === 0) {
      out[range.label] = '0'
      return
    }

    if (metric === 'AVG EV') {
      out[range.label] = (velocities.reduce((a, b) => a + b, 0) / velocities.length).toFixed(1)
      return
    }

    out[range.label] = String(Math.round(Math.max(...velocities)))
  })

  return out
}

const cageTable = computed(() => {
  const isLaunch = selectedCageAngleTab.value === 'LAUNCH ANGLE'
  const ranges = isLaunch ? launchRanges : sprayRanges
  const angleKey = isLaunch ? 'launchAngle' : 'sprayAngle'
  const metric = selectedCageMetricTab.value

  const team = formatCageValue(cageRowsAll.value, ranges, metric, angleKey)
  const players = selectedCagePlayerIds.value
    .map((id) => {
      const player = topPlayerButtons.value.find((p) => String(p.id) === String(id))
      if (!player) return null
      return {
        id: String(id),
        name: player.name,
        stats: formatCageValue(getCageRowsForPlayer(id), ranges, metric, angleKey),
      }
    })
    .filter(Boolean)

  return {
    title: `Cage Session - ${selectedCageAngleTab.value} - ${metric}`,
    ranges: ranges.map((r) => r.label),
    label: isLaunch ? 'SWINGS' : 'PITCHES',
    team,
    players,
    selectedCount: players.length,
  }
})

watch(topPlayerButtons, (players) => {
  const validIds = new Set(
    (players.length > 0 ? players : fallbackSelectedPlayerButtons.value).map((p) => String(p.id)),
  )
  selectedBattingPlayerIds.value = selectedBattingPlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedEvPlayerIds.value = selectedEvPlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedLongTossPlayerIds.value = selectedLongTossPlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedWeightBallPlayerIds.value = selectedWeightBallPlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedLiveAbPlayerIds.value = selectedLiveAbPlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedCagePlayerIds.value = selectedCagePlayerIds.value.filter((id) => validIds.has(String(id)))
  selectedBullpenPlayerIds.value = selectedBullpenPlayerIds.value.filter((id) => validIds.has(String(id)))
})

const inDateRange = (session) => {
  if (!sinceWhen.value || !until.value) return true
  const s = new Date(session?.date || session?.created_at || session?.started_at || session?.started || '')
  if (Number.isNaN(s.getTime())) return true
  const from = new Date(`${sinceWhen.value}T00:00:00`)
  const to = new Date(`${until.value}T23:59:59`)
  return s >= from && s <= to
}

const extractSessionPlayerIds = (session) => {
  const ids = new Set()
  const pushId = (v) => {
    if (v !== null && v !== undefined && v !== '') ids.add(String(v))
  }

  ;(session?.players || []).forEach((p) => pushId(p?.id || p?.user_id))
  ;(session?.pitchers || []).forEach((p) => pushId(p?.id || p?.user_id || p?.pitcher_id))
  ;(session?.batters || []).forEach((p) => pushId(p?.id || p?.user_id || p?.batter_id))
  ;(session?.pitching_practice_lineups || []).forEach((p) => pushId(p?.pitcher_id || p?.user_id || p?.id))
  ;(session?.practice_line_ups || []).forEach((p) => pushId(p?.batter_id || p?.user_id || p?.id))
  ;(session?.lineup || []).forEach((p) => pushId(p?.id || p?.user_id))

  return ids
}

const sessionHasSelectedPlayers = (session) => {
  if (selectedPlayers.value.length === 0) return true
  const ids = extractSessionPlayerIds(session)
  if (ids.size === 0) return true
  return selectedPlayers.value.some((id) => ids.has(String(id)))
}

const addPlayerNames = (players) => {
  if (!players || typeof players !== 'object') return players
  const out = { ...players }
  Object.keys(out).forEach((id) => {
    const label = playersMap.value[id]
    if (label) {
      out[id] = { player: label, ...out[id] }
    }
  })
  return out
}

const mergeByPlayer = (source) => {
  const merged = {}
  if (!source || typeof source !== 'object') return merged

  const toRows = (value) => {
    if (Array.isArray(value)) return value
    if (value && typeof value === 'object') return Object.values(value)
    return []
  }

  Object.keys(source).forEach((playerId) => {
    const rows = toRows(source[playerId])
    if (!merged[playerId]) merged[playerId] = []
    merged[playerId].push(...rows)
  })

  return merged
}

const normalizeFlatRows = (rows) => {
  return (Array.isArray(rows) ? rows : []).map((item, index) => ({
    ...item,
    sort: Number.isFinite(Number(item?.sort)) ? Number(item.sort) : index,
  }))
}

const getPlayerIdsFromRow = (row) => {
  return [
    row?.batter_id,
    row?.pitcher_id,
    row?.player_id,
    row?.user_id,
    row?.profile?.id,
    row?.profile?.user_id,
    row?.player?.id,
    row?.batter?.id,
    row?.pitcher?.id,
  ]
    .filter((v) => v !== undefined && v !== null && v !== '')
    .map((v) => String(v))
}

const rowMatchesSelectedPlayers = (row) => {
  if (selectedPlayers.value.length === 0) return true
  const rowIds = getPlayerIdsFromRow(row)
  if (rowIds.length === 0) return true
  const picked = new Set(selectedPlayers.value.map(String))
  return rowIds.some((id) => picked.has(id))
}

const rowMatchesPlayerFilter = (row) => {
  if (selectedSession.value === 'B') {
    if (selectedBattingPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedBattingPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'EV') {
    if (selectedEvPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedEvPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'C') {
    if (selectedCagePlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedCagePlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'P') {
    if (selectedBullpenPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedBullpenPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'LT') {
    if (selectedLongTossPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedLongTossPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'WB') {
    if (selectedWeightBallPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedWeightBallPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  if (selectedSession.value === 'L') {
    if (selectedLiveAbPlayerIds.value.length === 0) return true
    const rowIds = getPlayerIdsFromRow(row)
    if (rowIds.length === 0) return false
    const picked = new Set(selectedLiveAbPlayerIds.value.map(String))
    return rowIds.some((id) => picked.has(String(id)))
  }

  return true
}

const visibleFlatRows = computed(() => {
  return flatRows.value.filter(rowMatchesPlayerFilter)
})

const getPlayerName = (row) => {
  const explicit = row?.player_name || row?.batter_name || row?.pitcher_name || row?.name
  if (explicit) return String(explicit)

  const p = row?.profile || row?.player || row?.batter || row?.pitcher || {}
  const first = p?.first_name || p?.name?.first || ''
  const last = p?.last_name || p?.name?.last || ''
  const full = `${first} ${last}`.trim()
  if (full) return full

  const id = row?.batter_id || row?.pitcher_id || row?.player_id || row?.user_id || p?.id
  return id ? `Player ${id}` : 'Player'
}

const getTypeLabel = (row) => {
  return (
    row?.type_of_hit ||
    row?.type_throw ||
    row?.trajectory ||
    row?.quality_of_contact ||
    row?.result ||
    row?.zone ||
    '-'
  )
}

const getZoneLabel = (row) => {
  return row?.zone || row?.pitch_location || row?.field_direction || row?.capture_zone || '-'
}

const getVelocityNumber = (row) => {
  const raw =
    row?.velocity ??
    row?.miles_per_hour ??
    row?.exit_velocity ??
    row?.launch_angle_velocity ??
    row?.weighted_velocity ??
    null
  if (raw === null || raw === undefined || raw === '') return null
  const num = Number(raw)
  return Number.isFinite(num) ? num : null
}

const getVelocityValue = (row) => {
  const num = getVelocityNumber(row)
  return num === null ? '-' : num.toFixed(2)
}

const toRowsArray = (value) => {
  if (Array.isArray(value)) return value
  if (value && typeof value === 'object') return Object.values(value)
  return []
}

const normalizeEvType = (row) => {
  const raw = String(row?.trajectory ?? row?.type_of_hit ?? '-').trim().toUpperCase()
  if (raw === 'GB' || raw === 'GROUNDBALL') return 'GB'
  if (raw === 'LD' || raw === 'LINEDRIVE') return 'LD'
  if (raw === 'FB' || raw === 'FLY' || raw === 'FLYBALL') return 'FLY'
  return raw || '-'
}

const evAllPitchesRows = computed(() => {
  if (selectedSession.value !== 'EV') return []
  return visibleFlatRows.value.map((row, idx) => ({
    idx: idx + 1,
    player: getPlayerName(row),
    type: normalizeEvType(row),
    velo: getVelocityValue(row),
    set: row?.set ?? '-',
    sort: row?.sort ?? '-',
  }))
})

const evVelocityByTypeRows = computed(() => {
  if (selectedSession.value !== 'EV') return []
  const buckets = {
    GB: [],
    LD: [],
    FLY: [],
  }

  visibleFlatRows.value.forEach((row) => {
    const t = normalizeEvType(row)
    const v = getVelocityNumber(row)
    if (v === null) return
    if (!buckets[t]) buckets[t] = []
    buckets[t].push(v)
  })

  return Object.entries(buckets)
    .map(([type, values]) => {
      if (!values.length) {
        return { type, swings: 0, avg: '-', max: '-' }
      }
      const avg = values.reduce((a, b) => a + b, 0) / values.length
      const max = Math.max(...values)
      return {
        type,
        swings: values.length,
        avg: avg.toFixed(2),
        max: max.toFixed(2),
      }
    })
    .filter((r) => ['GB', 'LD', 'FLY'].includes(r.type))
})

const normalizeLongTossHop = (row) => {
  const hop = Number(row?.hop)
  if (!Number.isFinite(hop)) return 'Unknown'
  if (hop <= 0) return 'No Hops'
  if (hop === 1) return '1 Hop'
  if (hop === 2) return '2 Hop'
  if (hop === 3) return '3 Hop'
  return `${hop} Hops`
}

const getDistanceNumber = (row) => {
  const num = Number(row?.distance)
  return Number.isFinite(num) ? num : null
}

const longTossAllThrowsRows = computed(() => {
  if (selectedSession.value !== 'LT') return []
  return visibleFlatRows.value.map((row, idx) => ({
    idx: idx + 1,
    player: getPlayerName(row),
    hop: normalizeLongTossHop(row),
    distance: getDistanceNumber(row),
    set: row?.set ?? '-',
    sort: row?.sort ?? '-',
  }))
})

const longTossHopsRows = computed(() => {
  if (selectedSession.value !== 'LT') return []

  const labels = ['No Hops', '1 Hop', '2 Hop', '3 Hop']
  const buckets = {
    'No Hops': [],
    '1 Hop': [],
    '2 Hop': [],
    '3 Hop': [],
  }

  visibleFlatRows.value.forEach((row) => {
    const label = normalizeLongTossHop(row)
    const distance = getDistanceNumber(row)
    if (distance === null) return
    if (!buckets[label]) buckets[label] = []
    buckets[label].push(distance)
  })

  return labels.map((label) => {
    const values = buckets[label] || []
    if (!values.length) return { hop: label, throws: 0, avgDistance: '-', maxDistance: '-' }
    const avg = values.reduce((a, b) => a + b, 0) / values.length
    return {
      hop: label,
      throws: values.length,
      avgDistance: avg.toFixed(2),
      maxDistance: Math.max(...values).toFixed(2),
    }
  })
})

const ltDistanceRanges = [
  { label: '0-40', min: 0, max: 40 },
  { label: '41-80', min: 41, max: 80 },
  { label: '81-120', min: 81, max: 120 },
  { label: '121-160', min: 121, max: 160 },
  { label: '161-200', min: 161, max: 200 },
  { label: '201-240', min: 201, max: 240 },
  { label: '241-280', min: 241, max: 280 },
  { label: '281-320', min: 281, max: 320 },
  { label: '321-360', min: 321, max: 360 },
  { label: '361-400', min: 361, max: 400 },
  { label: '401-440', min: 401, max: 440 },
  { label: '441-480', min: 441, max: 480 },
  { label: '481-500', min: 481, max: 500 },
]

const buildLongTossRangeAggregateFromRows = (rows) => {
  const items = Array.isArray(rows) ? rows : []
  const teamTotals = { throws: items.length }
  const playersTotals = {}

  ltDistanceRanges.forEach((r) => {
    teamTotals[r.label] = 0
  })

  items.forEach((row) => {
    const ids = getPlayerIdsFromRow(row)
    const playerId = ids[0] ? String(ids[0]) : null
    const distance = getDistanceNumber(row)

    if (playerId && !playersTotals[playerId]) {
      playersTotals[playerId] = {
        player: playersMap.value[playerId] || getPlayerName(row),
        throws: 0,
      }
      ltDistanceRanges.forEach((r) => {
        playersTotals[playerId][r.label] = 0
      })
    }

    if (playerId) {
      playersTotals[playerId].throws += 1
    }

    if (distance === null) return

    ltDistanceRanges.forEach((r) => {
      const inRange = distance >= r.min && distance <= r.max
      if (!inRange) return
      teamTotals[r.label] += 1
      if (playerId) playersTotals[playerId][r.label] += 1
    })
  })

  const safePct = (num, den) => (den > 0 ? Number(((num / den) * 100).toFixed(2)) : 0)

  const teamPercents = { throws: teamTotals.throws }
  ltDistanceRanges.forEach((r) => {
    teamPercents[r.label] = safePct(teamTotals[r.label], teamTotals.throws)
  })

  const playersPercents = Object.fromEntries(
    Object.entries(playersTotals).map(([playerId, stats]) => {
      const out = {
        player: stats.player,
        throws: stats.throws,
      }
      ltDistanceRanges.forEach((r) => {
        out[r.label] = safePct(stats[r.label], stats.throws)
      })
      return [playerId, out]
    }),
  )

  return {
    totals: {
      team_totals: teamTotals,
      players: playersTotals,
    },
    percents: {
      team_totals: teamPercents,
      players: playersPercents,
    },
  }
}

const ltRangeAggregate = computed(() => buildLongTossRangeAggregateFromRows(flatRows.value))

// ─── Box Score (Live AB) ──────────────────────────────────────────────────────
const boxScoreView = ref('bat') // 'bat' | 'pit'

function _bsFormatAVG(h, ab) {
  if (!ab) return '.000'
  const v = h / ab
  return v >= 1 ? '1.000' : ('.' + String(Math.round(v * 1000)).padStart(3, '0'))
}
function _bsFormatERA(er, outs) {
  if (!outs) return '0.00'
  return (er * 27 / outs).toFixed(2)
}
function _bsOutsToIP(outs) {
  return `${Math.floor(outs / 3)}.${outs % 3}`
}
const _FIP_C = 3.10
const _LG_HR_FB = 0.105
function _calcFIP(hr, bb, k, outs) {
  if (!outs) return '-'
  return ((13 * hr + 3 * bb - 2 * k) / (outs / 3) + _FIP_C).toFixed(2)
}
function _calcXFIP(fo, bb, k, outs) {
  if (!outs) return '-'
  const expHR = fo * _LG_HR_FB
  return ((13 * expHR + 3 * bb - 2 * k) / (outs / 3) + _FIP_C).toFixed(2)
}
function _calcOBP(h, bb, hbp, ab, sf) {
  return _bsFormatAVG(h + bb + hbp, ab + bb + hbp + sf)
}
function _calcSLG(h, doubles, triples, hr, ab) {
  return _bsFormatAVG(h + doubles + 2 * triples + 3 * hr, ab)
}
function _calcOPS(h, bb, hbp, ab, sf, doubles, triples, hr) {
  const obpD = ab + bb + hbp + sf
  const obpN = obpD > 0 ? (h + bb + hbp) / obpD : 0
  const tb = h + doubles + 2 * triples + 3 * hr
  const slgN = ab > 0 ? tb / ab : 0
  return (obpN + slgN).toFixed(3)
}
function _calcISO(doubles, triples, hr, ab) {
  return _bsFormatAVG(doubles + 2 * triples + 3 * hr, ab)
}
function _decodeOutcome(totalBases) {
  const s = String(totalBases ?? '').trim()
  if (!s || s === '-' || s === '0') return null
  const u = s.toUpperCase()
  if (s === '1' || u === '1B') return '1B'
  if (s === '2' || u === '2B') return '2B'
  if (s === '3' || u === '3B') return '3B'
  if (s === '4' || u === 'BB') return 'BB'
  if (s === '5' || u === 'HR') return 'HR'
  if (s === '6' || u === 'HBP') return 'HBP'
  if (s === '7' || u === 'K') return 'K'
  if (s === '8' || u === 'OUT' || u === 'OUT/E') return 'OUT'
  return null
}
function _normalizePitch(ball) {
  const pitchingData = ball.pitching || {}
  const battingData = ball.batting || {}
  const pitcherProfile = pitchingData.profile || {}
  const batterProfile = battingData.profile || {}
  const pitcher_id = String(pitcherProfile.id ?? ball.pitcher_id ?? '')
  const batter_id = String(batterProfile.id ?? ball.batter_id ?? '')
  const total_bases = ball.bases ?? battingData.bases ?? ball.total_bases ?? ''
  const contact_trajectory = battingData.type_of_hit || battingData.trajectory || ball.contact_trajectory || ''
  const turn_is_over = ball.turn?.is_over ?? ball.turn_is_over ?? false
  const mkName = (profile, fallback) => {
    if (fallback && fallback !== `#${fallback}`) return fallback
    const full = profile.full || profile.full_name
    if (full) return full
    return `${profile.first_name || ''} ${profile.last_name || ''}`.trim() || ''
  }
  return {
    ...ball,
    pitcher_id,
    batter_id,
    pitcher_name: mkName(pitcherProfile, ball.pitcher_name),
    batter_name: mkName(batterProfile, ball.batter_name),
    contact_trajectory,
    total_bases,
    turn_is_over,
  }
}
function _isTerminalPitch(pitch) {
  if (pitch.turn_is_over) return true
  if (_decodeOutcome(pitch.total_bases) !== null) return true
  const traj = String(pitch.contact_trajectory || '').toUpperCase().trim()
  return traj === 'GB' || traj === 'FLY' || traj === 'LD' || traj === 'HBP'
}
function _abbrevName(name) {
  const parts = String(name || '').trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 9)
  return `${parts[0][0]}. ${parts.slice(1).join(' ')}`.substring(0, 11)
}

const boxScore = computed(() => {
  if (selectedSession.value !== 'L') return { batters: [], pitchers: [] }
  const batterMap = {}
  const pitcherMap = {}
  const pitches = visibleFlatRows.value.map(_normalizePitch)

  pitches.forEach((pitch) => {
    const bid = pitch.batter_id
    const pid = pitch.pitcher_id
    const terminal = _isTerminalPitch(pitch)
    const outcome = terminal ? _decodeOutcome(pitch.total_bases) : null
    const traj = String(pitch.contact_trajectory || '').toUpperCase().trim()

    if (bid && !batterMap[bid]) {
      batterMap[bid] = {
        id: bid, name: pitch.batter_name || playersMap.value[bid] || `#${bid}`, number: '',
        pos: pitch.batter_position || pitch.position || '',
        pa: 0, r: 0, h: 0, rbi: 0, doubles: 0, triples: 0, hr: 0,
        bb: 0, k: 0, hbp: 0, sh: 0, sf: 0, sb: 0, cs: 0,
      }
    }
    if (pid && !pitcherMap[pid]) {
      pitcherMap[pid] = {
        id: pid, name: pitch.pitcher_name || playersMap.value[pid] || `#${pid}`, number: '',
        dec: '',
        outs: 0, h: 0, r: 0, er: 0, bb: 0, k: 0, wp: 0, bk: 0, hp: 0, bf: 0,
        doubles: 0, triples: 0, hr: 0, xbh: 0, fo: 0, go: 0, gdp: 0,
      }
    }
    if (!terminal) return

    if (bid) {
      const b = batterMap[bid]
      b.pa++
      if (outcome === '1B') { b.h++ }
      else if (outcome === '2B') { b.h++; b.doubles++ }
      else if (outcome === '3B') { b.h++; b.triples++ }
      else if (outcome === 'HR') { b.h++; b.hr++ }
      else if (outcome === 'BB') { b.bb++ }
      else if (outcome === 'HBP') { b.hbp++ }
      else if (outcome === 'K') { b.k++ }
      else if (traj === 'HBP') { b.hbp++ }
    }
    if (pid) {
      const p = pitcherMap[pid]
      p.bf++
      if (outcome === '1B') { p.h++ }
      else if (outcome === '2B') { p.h++; p.doubles++; p.xbh++ }
      else if (outcome === '3B') { p.h++; p.triples++; p.xbh++ }
      else if (outcome === 'HR') { p.h++; p.hr++; p.xbh++ }
      if (outcome === 'BB' || (!outcome && traj === 'BB')) p.bb++
      if (outcome === 'HBP' || traj === 'HBP') p.hp++
      if (outcome === 'K') { p.k++; p.outs++ }
      const isHitOrReach = ['1B', '2B', '3B', 'HR', 'BB', 'HBP'].includes(outcome) || traj === 'HBP'
      if (!isHitOrReach && outcome !== 'K') {
        p.outs++
        if (traj === 'GB') { p.go++ }
        else if (traj === 'FLY' || traj === 'LD') { p.fo++ }
      }
    }
  })

  const batters = Object.values(batterMap).map((b) => {
    const ab = Math.max(0, b.pa - b.bb - b.hbp - b.sf - b.sh)
    const lob = Math.max(0, (b.h + b.bb + b.hbp) - b.r - b.rbi)
    return {
      ...b, ab, lob,
      avg: _bsFormatAVG(b.h, ab),
      obp: _calcOBP(b.h, b.bb, b.hbp, ab, b.sf),
      slg: _calcSLG(b.h, b.doubles, b.triples, b.hr, ab),
      ops: _calcOPS(b.h, b.bb, b.hbp, ab, b.sf, b.doubles, b.triples, b.hr),
      iso: _calcISO(b.doubles, b.triples, b.hr, ab),
    }
  })

  const pitchers = Object.values(pitcherMap).map((p) => ({
    ...p,
    ip: _bsOutsToIP(p.outs),
    era: _bsFormatERA(p.er, p.outs),
    fip: _calcFIP(p.hr, p.bb, p.k, p.outs),
    xfip: _calcXFIP(p.fo, p.bb, p.k, p.outs),
  }))

  return { batters, pitchers }
})

const BS_BAT_ROWS = [
  ['POS', 'pos'], ['#', 'number'],
  ['AB', 'ab'], ['R', 'r'], ['H', 'h'], ['RBI', 'rbi'],
  ['2B', 'doubles'], ['3B', 'triples'], ['HR', 'hr'],
  ['BB', 'bb'], ['K', 'k'], ['HBP', 'hbp'], ['SH', 'sh'], ['SF', 'sf'],
  ['LOB', 'lob'], ['SB', 'sb'], ['CS', 'cs'],
  ['AVG', 'avg'], ['OBP', 'obp'], ['SLG', 'slg'], ['OPS', 'ops'], ['ISO', 'iso'],
]
const BS_PIT_ROWS = [
  ['#', 'number'], ['DEC', 'dec'],
  ['IP', 'ip'], ['H', 'h'], ['R', 'r'], ['ER', 'er'], ['BB', 'bb'], ['K', 'k'],
  ['WP', 'wp'], ['BK', 'bk'], ['HP', 'hp'], ['BF', 'bf'],
  ['2B', 'doubles'], ['3B', 'triples'], ['HR', 'hr'], ['XBH', 'xbh'],
  ['FO', 'fo'], ['GO', 'go'], ['GDP', 'gdp'],
  ['ERA', 'era'], ['FIP', 'fip'], ['xFIP', 'xfip'],
]

const boxScoreRows = computed(() => boxScoreView.value === 'bat' ? BS_BAT_ROWS : BS_PIT_ROWS)
const boxScorePlayers = computed(() => boxScoreView.value === 'bat' ? boxScore.value.batters : boxScore.value.pitchers)
const boxScoreTeamRow = computed(() => {
  const isBat = boxScoreView.value === 'bat'
  const players = isBat ? boxScore.value.batters : boxScore.value.pitchers
  if (!players.length) return {}
  const sum = (key) => players.reduce((a, p) => a + (Number(p[key]) || 0), 0)
  if (isBat) {
    const tAB = sum('ab'), tH = sum('h'), tBB = sum('bb'), tHBP = sum('hbp'), tSF = sum('sf')
    const t2B = sum('doubles'), t3B = sum('triples'), tHR = sum('hr')
    return {
      name: 'TEAM', pos: '-', number: '-',
      ab: tAB, r: sum('r'), h: tH, rbi: sum('rbi'),
      doubles: t2B, triples: t3B, hr: tHR,
      bb: tBB, k: sum('k'), hbp: tHBP, sh: sum('sh'), sf: tSF,
      lob: sum('lob'), sb: sum('sb'), cs: sum('cs'),
      avg: '---',
      obp: _calcOBP(tH, tBB, tHBP, tAB, tSF),
      slg: _calcSLG(tH, t2B, t3B, tHR, tAB),
      ops: _calcOPS(tH, tBB, tHBP, tAB, tSF, t2B, t3B, tHR),
      iso: _calcISO(t2B, t3B, tHR, tAB),
    }
  } else {
    const totalOuts = players.reduce((a, p) => a + (p.outs || 0), 0)
    return {
      name: 'TEAM', number: '-', dec: '-',
      outs: totalOuts,
      h: sum('h'), r: sum('r'), er: sum('er'), bb: sum('bb'), k: sum('k'),
      wp: sum('wp'), bk: sum('bk'), hp: sum('hp'), bf: sum('bf'),
      doubles: sum('doubles'), triples: sum('triples'), hr: sum('hr'), xbh: sum('xbh'),
      fo: sum('fo'), go: sum('go'), gdp: sum('gdp'),
      ip: _bsOutsToIP(totalOuts),
      era: _bsFormatERA(sum('er'), totalOuts),
      fip: _calcFIP(sum('hr'), sum('bb'), sum('k'), totalOuts),
      xfip: _calcXFIP(sum('fo'), sum('bb'), sum('k'), totalOuts),
    }
  }
})

const getWeightNumber = (row) => {
  const raw = row?.weight ?? row?.ball_weight ?? row?.weight_oz ?? row?.oz ?? null
  const num = Number(raw)
  return Number.isFinite(num) ? num : null
}

const wbAllPitchesRows = computed(() => {
  if (selectedSession.value !== 'WB') return []
  return visibleFlatRows.value.map((row, idx) => ({
    idx: idx + 1,
    player: getPlayerName(row),
    weight: getWeightNumber(row),
    velo: getVelocityValue(row),
    set: row?.set ?? '-',
    sort: row?.sort ?? '-',
  }))
})

const wbVeloByWeightRows = computed(() => {
  if (selectedSession.value !== 'WB') return []
  const buckets = {}
  visibleFlatRows.value.forEach((row) => {
    const weight = getWeightNumber(row)
    const velo = getVelocityNumber(row)
    if (weight === null || velo === null) return
    const key = String(weight)
    if (!buckets[key]) buckets[key] = []
    buckets[key].push(velo)
  })

  return Object.keys(buckets)
    .sort((a, b) => Number(a) - Number(b))
    .map((weight) => {
      const arr = buckets[weight]
      const avg = arr.reduce((a, b) => a + b, 0) / arr.length
      const max = Math.max(...arr)
      return {
        weight,
        topVelo: max.toFixed(1),
        avgVelo: avg.toFixed(1),
        throws: arr.length,
      }
    })
})

const buildWeightedAggregateFromRows = (rows) => {
  const items = Array.isArray(rows) ? rows : []
  const teamTotals = { throws: items.length }
  const teamVelosByWeight = {}
  const playersTotals = {}
  const playersVelosByWeight = {}

  items.forEach((row) => {
    const ids = getPlayerIdsFromRow(row)
    const playerId = ids[0] ? String(ids[0]) : null
    const weightNum = getWeightNumber(row)
    const weightKey = weightNum === null ? null : String(weightNum)
    const velo = getVelocityNumber(row)

    if (playerId && !playersTotals[playerId]) {
      playersTotals[playerId] = {
        player: playersMap.value[playerId] || getPlayerName(row),
        throws: 0,
      }
      playersVelosByWeight[playerId] = {}
    }

    if (playerId) {
      playersTotals[playerId].throws += 1
    }

    if (!weightKey) return

    if (!teamTotals[weightKey]) teamTotals[weightKey] = 0
    teamTotals[weightKey] += 1

    if (!teamVelosByWeight[weightKey]) teamVelosByWeight[weightKey] = []
    if (velo !== null) teamVelosByWeight[weightKey].push(velo)

    if (playerId) {
      if (!playersTotals[playerId][weightKey]) playersTotals[playerId][weightKey] = 0
      playersTotals[playerId][weightKey] += 1
      if (!playersVelosByWeight[playerId][weightKey]) playersVelosByWeight[playerId][weightKey] = []
      if (velo !== null) playersVelosByWeight[playerId][weightKey].push(velo)
    }
  })

  const weightKeys = Object.keys(teamTotals)
    .filter((key) => key !== 'throws' && Number(teamTotals[key]) > 0)
    .sort((a, b) => Number(a) - Number(b))

  const totalsTeam = Object.fromEntries([
    ['throws', teamTotals.throws],
    ...weightKeys.map((k) => [k, teamTotals[k]]),
  ])

  const totalsPlayers = Object.fromEntries(
    Object.entries(playersTotals).map(([playerId, stats]) => [
      playerId,
      Object.fromEntries([
        ['player', stats.player],
        ['throws', stats.throws],
        ...weightKeys.map((k) => [k, stats[k] || 0]),
      ]),
    ]),
  )

  const averagesTeam = Object.fromEntries([
    ['throws', teamTotals.throws],
    ...weightKeys.map((k) => {
      const arr = teamVelosByWeight[k] || []
      const avg = arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0
      return [k, Number(avg.toFixed(2))]
    }),
  ])

  const averagesPlayers = Object.fromEntries(
    Object.entries(totalsPlayers).map(([playerId, totals]) => [
      playerId,
      Object.fromEntries([
        ['player', totals.player],
        ['throws', totals.throws],
        ...weightKeys.map((k) => {
          const arr = playersVelosByWeight[playerId]?.[k] || []
          const avg = arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0
          return [k, Number(avg.toFixed(2))]
        }),
      ]),
    ]),
  )

  return {
    totals: { team_totals: totalsTeam, players: totalsPlayers },
    averages: { team_totals: averagesTeam, players: averagesPlayers },
    weightKeys,
  }
}

const wbAggregate = computed(() => buildWeightedAggregateFromRows(visibleFlatRows.value))

const hasPlayersData = (playersObj) => {
  return !!(playersObj && typeof playersObj === 'object' && Object.keys(playersObj).length > 0)
}

const hasTotalsData = computed(() => {
  return !!(totalsTeam.value && typeof totalsTeam.value === 'object' && Object.keys(totalsTeam.value).length > 0)
    || hasPlayersData(totalsPlayers.value)
})

const hasPercentagesData = computed(() => {
  return !!(percentagesTeam.value && typeof percentagesTeam.value === 'object' && Object.keys(percentagesTeam.value).length > 0)
    || hasPlayersData(percentagesPlayers.value)
})

const normalizeEvTrajectory = (row) => {
  const raw = String(row?.trajectory ?? row?.type_of_hit ?? '').trim().toUpperCase()
  if (!raw) return null
  if (raw === 'GB' || raw === 'GROUNDBALL') return 'GB'
  if (raw === 'LD' || raw === 'LINEDRIVE') return 'LD'
  if (raw === 'FLY' || raw === 'FB' || raw === 'FLYBALL') return 'FLY'
  return null
}

const buildEvAggregateFromRows = (rows) => {
  const items = Array.isArray(rows) ? rows : []

  const teamTotals = { swings: 0, GB: 0, LD: 0, FLY: 0 }
  const playersTotals = {}

  items.forEach((row) => {
    const ids = getPlayerIdsFromRow(row)
    const playerId = ids[0] ? String(ids[0]) : null
    const playerName = getPlayerName(row)
    const traj = normalizeEvTrajectory(row)

    teamTotals.swings += 1
    if (traj) teamTotals[traj] += 1

    if (!playerId) return
    if (!playersTotals[playerId]) {
      playersTotals[playerId] = {
        player: playersMap.value[playerId] || playerName,
        swings: 0,
        GB: 0,
        LD: 0,
        FLY: 0,
      }
    }
    playersTotals[playerId].swings += 1
    if (traj) playersTotals[playerId][traj] += 1
  })

  const safePct = (num, den) => (den > 0 ? Number(((num / den) * 100).toFixed(2)) : 0)

  const teamPercents = {
    swings: teamTotals.swings,
    GB: safePct(teamTotals.GB, teamTotals.swings),
    LD: safePct(teamTotals.LD, teamTotals.swings),
    FLY: safePct(teamTotals.FLY, teamTotals.swings),
  }

  const playersPercents = Object.fromEntries(
    Object.entries(playersTotals).map(([playerId, stats]) => [
      playerId,
      {
        player: stats.player,
        swings: stats.swings,
        GB: safePct(stats.GB, stats.swings),
        LD: safePct(stats.LD, stats.swings),
        FLY: safePct(stats.FLY, stats.swings),
      },
    ]),
  )

  return {
    totals: {
      team_totals: teamTotals,
      players: playersTotals,
    },
    percents: {
      team_totals: teamPercents,
      players: playersPercents,
    },
  }
}

const evFallbackAggregate = computed(() => buildEvAggregateFromRows(flatRows.value))

const buildOptionsFromSession = () => {
  const key = selectedSession.value
  if (key === 'P') return { P: [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28] }
  if (key === 'C') return { C: [29, 30, 31, 32, 33, 34] }
  if (key === 'EV') return { EV: [35, 36, 37, 38] }
  if (key === 'LT') return { LT: [39, 40, 41, 42, 43, 44] }
  if (key === 'WB') return { WB: [45, 46, 47] }
  if (key === 'L') return { L: [48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58] }
  return { B: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9] }
}

const loadTeamPlayers = async () => {
  if (isPlayerScope.value) {
    playersMap.value = {
      [playerId.value]: String(route.query?.playerName || userData?.name?.full || userData?.name || 'Player'),
    }
    return
  }

  const res = await axiosGet(`coach/teams/${teamId.value}`)
  const players = Array.isArray(res?.data?.data) ? res.data.data : []
  const map = {}
  players.forEach((p) => {
    map[String(p.id)] = p?.name?.full || `${p?.name?.first || ''} ${p?.name?.last || ''}`.trim() || 'Player'
  })
  playersMap.value = map
}

const loadAggregateTotalsAndPercentages = async () => {
  const payload = {
    dates: [sinceWhen.value, until.value],
    players: selectedPlayers.value,
    options: buildOptionsFromSession(),
  }

  const endpoint = isPlayerScope.value
    ? `result/statistics/player/${playerId.value}`
    : `result/statistics/${teamId.value}`

  const res = await axiosGet(endpoint, payload)
  const data = res?.data?.data || {}
  const sectionKeyMap = {
    B: 'batting',
    P: 'bullpen',
    C: 'cage',
    EV: 'exit_velocity',
    LT: 'long_toss',
    WB: 'weight_ball',
    L: 'live',
  }
  const section = data?.[sectionKeyMap[selectedSession.value]]

  if (!section) return

  const keyMap = {
    B: { totals: 'totals', perc: 'percents' },
    P: { totals: 'totals', perc: 'percents' },
    C: { totals: 'launch-angle-totals', perc: 'launch-angle-percents' },
    EV: { totals: 'totals', perc: 'percents' },
    LT: { totals: 'totals-distances', perc: 'percents-distances' },
    WB: { totals: 'totals', perc: 'average-velocity' },
    L: { totals: 'hitter-basic', perc: 'hitter-advance' },
  }

  const sessionKeys = keyMap[selectedSession.value]
  if (!sessionKeys) return

  const totalsRaw = section?.[sessionKeys.totals]
  const percRaw = section?.[sessionKeys.perc]

  totalsPlayers.value = addPlayerNames(totalsRaw?.players || null)
  totalsTeam.value = totalsRaw?.team_totals || null
  percentagesPlayers.value = addPlayerNames(percRaw?.players || null)
  percentagesTeam.value = percRaw?.team_totals || null
}

const loadAllPitchesAndVeloData = async () => {
  let list = []

  if (isPlayerScope.value) {
    if (selectedSession.value === 'B') {
      const res = await axiosGet('player/sessions/batting')
      list = res?.data?.data?.data || []
    } else if (selectedSession.value === 'P') {
      const res = await axiosGet('player/sessions/bullpen')
      list = res?.data?.data?.data || []
    } else if (selectedSession.value === 'C') {
      const res = await axiosGet('player/sessions/cage')
      list = res?.data?.data?.data || []
    } else {
      const res = await axiosGet('player/sessions/training')
      const training = res?.data?.data?.data || []
      const mode = String(selectedSession.value).toUpperCase()
      list = training.filter((s) => String(s?.modes || s?.mode || '').toUpperCase() === mode)
    }
  } else {
    const sessionsRes = await axiosGet(`coach/sessions/lasts/${teamId.value}`)
    const sessionsData = sessionsRes?.data?.data || sessionsRes?.data || {}

    const key = sessionListKeyBySession[selectedSession.value]
    list = sessionsData?.[key] || []

    if (selectedSession.value === 'EV' && (!Array.isArray(list) || list.length === 0)) {
      const trainingFallback =
        sessionsData?.training ||
        sessionsData?.trainings ||
        sessionsData?.all_training ||
        []

      if (Array.isArray(trainingFallback) && trainingFallback.length > 0) {
        list = trainingFallback.filter((s) => {
          const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
          return ['EV', 'EXIT_VELOCITY', 'EXITVELOCITY'].includes(mode)
        })
      }
    }

    if (selectedSession.value === 'LT' && (!Array.isArray(list) || list.length === 0)) {
      const trainingFallback =
        sessionsData?.training ||
        sessionsData?.trainings ||
        sessionsData?.all_training ||
        []

      if (Array.isArray(trainingFallback) && trainingFallback.length > 0) {
        list = trainingFallback.filter((s) => {
          const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
          return ['LT', 'LONG_TOSS', 'LONGTOSS'].includes(mode)
        })
      }
    }

    if (selectedSession.value === 'WB' && (!Array.isArray(list) || list.length === 0)) {
      const trainingFallback =
        sessionsData?.training ||
        sessionsData?.trainings ||
        sessionsData?.all_training ||
        []

      if (Array.isArray(trainingFallback) && trainingFallback.length > 0) {
        list = trainingFallback.filter((s) => {
          const mode = String(s?.mode ?? s?.modes ?? '').toUpperCase()
          return ['WB', 'WEIGHT_BALL', 'WEIGHTBALL'].includes(mode)
        })
      }
    }
  }

  const filtered = (Array.isArray(list) ? list : [])
    .filter((session) => inDateRange(session))
    .filter((session) => sessionHasSelectedPlayers(session))
    .filter((session) => {
      if (selectedSessionIds.value.length === 0) return true
      return selectedSessionIds.value.includes(String(session?.id || ''))
    })

  const ids = [...new Set(filtered.map((s) => String(s?.id || '')).filter(Boolean))]

  const mergedRows = []
  const mergedByPlayer = {}

  await Promise.all(
    ids.map(async (id) => {
      try {
        const res = await axiosGet(`statistics/${id}/${endpoint.value}`)
        const data = res?.data?.data || {}

        const rows =
          data?.ball_x_ball ||
          data?.ball_by_ball ||
          data?.ball_by_ball_results ||
          data?.pitches ||
          data?.results ||
          data?.swings ||
          data?.throws ||
          []
        mergedRows.push(...toRowsArray(rows))

        const byPlayer = mergeByPlayer(data?.by_player || {})
        Object.keys(byPlayer).forEach((playerId) => {
          if (!mergedByPlayer[playerId]) mergedByPlayer[playerId] = []
          mergedByPlayer[playerId].push(...byPlayer[playerId])
        })
      } catch (e) {
        // ignore bad session payloads
      }
    }),
  )

  flatRows.value = normalizeFlatRows(mergedRows).filter(rowMatchesSelectedPlayers)
  byPlayerRows.value = mergedByPlayer
}

const boot = async () => {
  if ((!teamId.value && !isPlayerScope.value) || !supportsTabsView.value) {
    toast.fire({ icon: 'warning', title: 'Validation', text: 'This New Stats view needs a valid session type.' })
    return
  }

  loading.value = true
  try {
    await loadTeamPlayers()
    await Promise.all([
      loadAggregateTotalsAndPercentages(),
      loadAllPitchesAndVeloData(),
    ])
  } catch (error) {
    toast.fire({ icon: 'error', title: 'Error', text: error?.message || 'Failed to load statistics.' })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  teamName.value = String(route.query?.teamName || route.query?.playerName || 'Team')
  boot()
})
</script>

<template>
  <Layout>
    <div class="min-h-screen bg-[#060b14] text-white px-4 py-6 lg:px-8 lg:py-8">
      <div class="mx-auto max-w-7xl">
        <h1 class="text-center text-3xl md:text-4xl font-black tracking-wide text-[#ff2d55] mb-3">{{ pageTitle }}</h1>
        <p class="text-center text-white/60 mb-6">
          {{ teamName }} · {{ sinceWhen }} to {{ until }}
        </p>

        <div v-if="loading" class="flex justify-center py-10">
          <div class="w-12 h-12 border-4 border-red-500 border-b-transparent rounded-full animate-spin"></div>
        </div>

        <TabGroup v-else :default-index="defaultTabIndex">
          <div v-if="selectedSession === 'B'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedBattingPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedBattingPlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`bat-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedBattingPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleBattingPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'EV'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedEvPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedEvPlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`ev-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedEvPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleEvPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'LT'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedLongTossPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedLongTossPlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`lt-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedLongTossPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleLongTossPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'WB'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedWeightBallPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedWeightBallPlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`wb-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedWeightBallPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleWeightBallPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'L'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedLiveAbPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedLiveAbPlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`l-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedLiveAbPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleLiveAbPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'P'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedBullpenPlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedBullpenPlayerIds = []"
            >
              TEAM TOTAL
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`bp-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedBullpenPlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleBullpenPlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <div v-if="selectedSession === 'C'" class="mb-4 flex flex-wrap justify-center gap-2">
            <button
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedCagePlayerIds.length === 0 ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="selectedCagePlayerIds = []"
            >
              ALL PLAYERS
            </button>
            <button
              v-for="player in availablePlayerButtons"
              :key="`top-player-${player.id}`"
              class="rounded-full border px-3 py-1.5 text-xs md:text-sm font-bold transition"
              :class="selectedCagePlayerIds.includes(player.id) ? 'border-[#ff2d55] bg-[#ff2d55]/20 text-white' : 'border-white/20 bg-white/5 text-white/80 hover:bg-white/10'"
              @click="toggleCagePlayer(player.id)"
            >
              {{ player.name }}
            </button>
          </div>

          <TabList v-if="selectedSession === 'C'" class="mb-5 rounded-xl bg-white/10 p-2">
            <div class="mb-2 flex items-center justify-center gap-2">
              <Tab as="template" v-slot="{ selected }">
                <button
                  class="rounded-lg px-6 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  ALL PITCHES
                </button>
              </Tab>
              <Tab as="template" v-slot="{ selected }">
                <button
                  class="rounded-lg px-6 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  VELO
                </button>
              </Tab>
            </div>
            <div class="flex items-center justify-center">
              <Tab as="template" v-slot="{ selected }">
                <button
                  class="rounded-lg px-6 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  CAGE STATS
                </button>
              </Tab>
            </div>
          </TabList>

          <TabList v-else-if="selectedSession === 'B'" class="mb-5 rounded-xl bg-white/10 p-2">
            <div class="flex flex-wrap items-center justify-center gap-2">
              <Tab
                v-for="name in tabNames"
                :key="name"
                as="template"
                v-slot="{ selected }"
              >
                <button
                  class="rounded-lg px-5 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  {{ name }}
                </button>
              </Tab>
            </div>
          </TabList>

          <TabList v-else-if="selectedSession === 'EV'" class="mb-5 rounded-xl bg-white/10 p-2">
            <div class="flex flex-wrap items-center justify-center gap-2">
              <Tab
                v-for="name in tabNames"
                :key="name"
                as="template"
                v-slot="{ selected }"
              >
                <button
                  class="rounded-lg px-5 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  {{ name }}
                </button>
              </Tab>
            </div>
          </TabList>

          <TabList v-else-if="selectedSession === 'P'" class="mb-5 rounded-xl bg-white/10 p-2">
            <div class="flex flex-wrap items-center justify-center gap-2">
              <Tab
                v-for="name in tabNames"
                :key="name"
                as="template"
                v-slot="{ selected }"
              >
                <button
                  class="rounded-lg px-5 py-2 text-sm md:text-base font-black tracking-wide transition"
                  :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
                >
                  {{ name }}
                </button>
              </Tab>
            </div>
          </TabList>

          <TabList v-else class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-5 rounded-xl bg-white/10 p-2">
            <Tab
              v-for="name in tabNames"
              :key="name"
              as="template"
              v-slot="{ selected }"
            >
              <button
                class="rounded-lg py-2 text-sm md:text-base font-black tracking-wide transition"
                :class="selected ? 'bg-[#ff2d55] text-white' : 'bg-white/5 text-white/80 hover:bg-white/10'"
              >
                {{ name }}
              </button>
            </Tab>
          </TabList>

          <TabPanels>
            <TabPanel v-for="name in tabNames" :key="`panel-${name}`">
              <template v-if="name === 'ALL PITCHES' || name === 'ALL THROWS'">
                <BattingAllPitches
                  v-if="isBatting"
                  :isLoading="false"
                  :tableData="visibleFlatRows"
                />
                <BullpenAllPitches
                  v-else-if="isBullpen"
                  :isLoading="false"
                  :tableData="visibleFlatRows"
                />
                <div v-else-if="selectedSession === 'EV'" class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-left">Velo</th>
                        <th class="px-3 py-2 text-left">Set</th>
                        <th class="px-3 py-2 text-left">Swing</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="evAllPitchesRows.length === 0">
                        <td colspan="6" class="px-3 py-5 text-center text-white/60">No swing-level rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in evAllPitchesRows"
                        :key="`ev-r-${row.idx}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.idx }}</td>
                        <td class="px-3 py-2">{{ row.player }}</td>
                        <td class="px-3 py-2">{{ row.type }}</td>
                        <td class="px-3 py-2">{{ row.velo }}</td>
                        <td class="px-3 py-2">{{ row.set }}</td>
                        <td class="px-3 py-2">{{ row.sort }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else-if="selectedSession === 'LT'" class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-left">Hop Type</th>
                        <th class="px-3 py-2 text-left">Distance</th>
                        <th class="px-3 py-2 text-left">Set</th>
                        <th class="px-3 py-2 text-left">Throw</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="longTossAllThrowsRows.length === 0">
                        <td colspan="6" class="px-3 py-5 text-center text-white/60">No throw-level rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in longTossAllThrowsRows"
                        :key="`lt-r-${row.idx}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.idx }}</td>
                        <td class="px-3 py-2">{{ row.player }}</td>
                        <td class="px-3 py-2">{{ row.hop }}</td>
                        <td class="px-3 py-2">{{ row.distance ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.set }}</td>
                        <td class="px-3 py-2">{{ row.sort }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else-if="selectedSession === 'WB'" class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-left">Weight (oz)</th>
                        <th class="px-3 py-2 text-left">Velo</th>
                        <th class="px-3 py-2 text-left">Set</th>
                        <th class="px-3 py-2 text-left">Throw</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="wbAllPitchesRows.length === 0">
                        <td colspan="6" class="px-3 py-5 text-center text-white/60">No throw-level rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in wbAllPitchesRows"
                        :key="`wb-r-${row.idx}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.idx }}</td>
                        <td class="px-3 py-2">{{ row.player }}</td>
                        <td class="px-3 py-2">{{ row.weight ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.velo }}</td>
                        <td class="px-3 py-2">{{ row.set }}</td>
                        <td class="px-3 py-2">{{ row.sort }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else-if="selectedSession === 'L'" class="rounded-2xl border border-white/10 bg-[#0a1020]/90 p-4 shadow-[0_14px_36px_rgba(0,0,0,0.28)]">
                  <h3 class="text-center text-white text-base md:text-lg font-black uppercase tracking-[0.08em] mb-3">Live AB - All Pitches</h3>
                  <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#020817]/70">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="bg-[#0f172a] text-[#e2e8f0] uppercase text-xs tracking-wider">
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">#</th>
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Player</th>
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Type</th>
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Zone</th>
                          <th class="px-3 py-2 text-left border-b border-white/10">Velo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="genericTableRows.length === 0">
                          <td colspan="5" class="px-3 py-6 text-center text-white/60">No pitch-level rows found.</td>
                        </tr>
                        <tr
                          v-for="(row, rowIndex) in genericTableRows"
                          :key="`l-r-${row.idx}`"
                          class="border-b border-white/10"
                          :class="rowIndex % 2 === 0 ? 'bg-white/[0.03]' : 'bg-slate-400/[0.05]'"
                        >
                          <td class="px-3 py-2 border-r border-white/10">{{ row.idx }}</td>
                          <td class="px-3 py-2 border-r border-white/10 font-semibold">{{ row.player }}</td>
                          <td class="px-3 py-2 border-r border-white/10">{{ row.type }}</td>
                          <td class="px-3 py-2 border-r border-white/10">{{ row.zone }}</td>
                          <td class="px-3 py-2">{{ row.velo }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-left">Type</th>
                        <th class="px-3 py-2 text-left">Zone</th>
                        <th class="px-3 py-2 text-left">Velo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="genericTableRows.length === 0">
                        <td colspan="5" class="px-3 py-5 text-center text-white/60">No pitch-level rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in genericTableRows"
                        :key="`r-${row.idx}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.idx }}</td>
                        <td class="px-3 py-2">{{ row.player }}</td>
                        <td class="px-3 py-2">{{ row.type }}</td>
                        <td class="px-3 py-2">{{ row.zone }}</td>
                        <td class="px-3 py-2">{{ row.velo }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <template v-else-if="name === 'SPRAY CHART'">
                <SessionHeatmapPanel
                  v-if="isBatting"
                  :rows="visibleFlatRows"
                  :base-grid="spatialBaseGrid"
                  :grid-size="spatialGridSize"
                  :mark-mode="spatialMarkMode"
                  :background="spatialBackground"
                  filter-options="batting-spray"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">Spray chart is only available for batting sessions.</div>
              </template>

              <template v-else-if="name === 'VELO' || name === 'HOPS'">
                <BullpenVelocity
                  v-if="isBullpen"
                  :VelocityData="visibleByPlayerRows"
                />
                <div v-else-if="selectedSession === 'EV'" class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">Swing Type</th>
                        <th class="px-3 py-2 text-left">Swings</th>
                        <th class="px-3 py-2 text-left">Avg Velo</th>
                        <th class="px-3 py-2 text-left">Max Velo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="evVelocityByTypeRows.length === 0">
                        <td colspan="4" class="px-3 py-5 text-center text-white/60">No velocity rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in evVelocityByTypeRows"
                        :key="`ev-v-${row.type}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.type }}</td>
                        <td class="px-3 py-2">{{ row.swings }}</td>
                        <td class="px-3 py-2">{{ row.avg }}</td>
                        <td class="px-3 py-2">{{ row.max }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else-if="selectedSession === 'LT'" class="rounded-2xl border border-white/10 bg-white/10 p-4">
                  <div class="overflow-hidden rounded-xl border border-white/10 bg-black/20">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="bg-[#ff2d55] text-white uppercase text-xs tracking-wider">
                          <th class="px-4 py-3 text-center">HOPS</th>
                          <th class="px-4 py-3 text-center">MAX DISTANCE</th>
                          <th class="px-4 py-3 text-center">AVG DISTANCE</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="longTossHopsRows.length === 0">
                          <td colspan="3" class="px-3 py-8 text-center text-white/60">No hops rows found.</td>
                        </tr>
                        <tr
                          v-for="(row, rowIndex) in longTossHopsRows"
                          :key="`lt-h-${row.hop}`"
                          class="border-b border-white/10"
                          :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                        >
                          <td class="px-4 py-5 text-center text-xl text-white">{{ row.hop }}</td>
                          <td class="px-4 py-5 text-center text-xl text-white">{{ row.maxDistance !== '-' ? `${row.maxDistance} ft` : '-' }}</td>
                          <td class="px-4 py-5 text-center text-xl text-white">{{ row.avgDistance !== '-' ? `${row.avgDistance} ft` : '-' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div v-if="longTossHopsRows.length > 0" class="mt-4 border-t border-white/10 pt-4 text-center text-white/60 text-2xl">
                    Total throws: {{ longTossAllThrowsRows.length }}
                  </div>
                </div>
                <div v-else-if="selectedSession === 'WB'" class="rounded-2xl border border-white/10 bg-white/10 p-4">
                  <div class="overflow-hidden rounded-xl border border-white/10 bg-black/20">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="bg-[#ff2d55] text-white uppercase text-xs tracking-wider">
                          <th class="px-4 py-3 text-center">WEIGHT (oz)</th>
                          <th class="px-4 py-3 text-center">TOP VELO</th>
                          <th class="px-4 py-3 text-center">AVG VELO</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="wbVeloByWeightRows.length === 0">
                          <td colspan="3" class="px-3 py-8 text-center text-white/60">No velocity rows found.</td>
                        </tr>
                        <tr
                          v-for="(row, rowIndex) in wbVeloByWeightRows"
                          :key="`wb-v-${row.weight}`"
                          class="border-b border-white/10"
                          :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                        >
                          <td class="px-4 py-5 text-center text-xl text-white">{{ row.weight }}</td>
                          <td class="px-4 py-5 text-center text-xl text-white">{{ `${row.topVelo} mph` }}</td>
                          <td class="px-4 py-5 text-center text-xl text-white">{{ `${row.avgVelo} mph` }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <div v-if="wbVeloByWeightRows.length > 0" class="mt-4 border-t border-white/10 pt-4 text-center text-white/60 text-2xl space-y-1">
                    <div>Total weights analyzed: {{ wbVeloByWeightRows.length }}</div>
                    <div>Total throws: {{ wbAllPitchesRows.length }}</div>
                  </div>
                </div>
                <div v-else-if="selectedSession === 'L'" class="rounded-2xl border border-white/10 bg-[#0a1020]/90 p-4 shadow-[0_14px_36px_rgba(0,0,0,0.28)]">
                  <h3 class="text-center text-white text-base md:text-lg font-black uppercase tracking-[0.08em] mb-3">Live AB - Velocity</h3>
                  <div class="overflow-x-auto rounded-xl border border-white/10 bg-[#020817]/70">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="bg-[#0f172a] text-[#e2e8f0] uppercase text-xs tracking-wider">
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Player</th>
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Samples</th>
                          <th class="px-3 py-2 text-left border-b border-white/10 border-r border-white/10">Avg Velo</th>
                          <th class="px-3 py-2 text-left border-b border-white/10">Max Velo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="genericVelocityRows.length === 0">
                          <td colspan="4" class="px-3 py-6 text-center text-white/60">No velocity rows found.</td>
                        </tr>
                        <tr
                          v-for="(row, rowIndex) in genericVelocityRows"
                          :key="`l-v-${row.player}`"
                          class="border-b border-white/10"
                          :class="rowIndex % 2 === 0 ? 'bg-white/[0.03]' : 'bg-slate-400/[0.05]'"
                        >
                          <td class="px-3 py-2 border-r border-white/10 font-semibold">{{ row.player }}</td>
                          <td class="px-3 py-2 border-r border-white/10">{{ row.count }}</td>
                          <td class="px-3 py-2 border-r border-white/10">{{ row.avg }}</td>
                          <td class="px-3 py-2">{{ row.max }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-4 overflow-x-auto">
                  <table class="min-w-full text-sm">
                    <thead>
                      <tr class="bg-white/10 text-white/80 uppercase text-xs tracking-wider">
                        <th class="px-3 py-2 text-left">Player</th>
                        <th class="px-3 py-2 text-left">Samples</th>
                        <th class="px-3 py-2 text-left">Avg Velo</th>
                        <th class="px-3 py-2 text-left">Max Velo</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="genericVelocityRows.length === 0">
                        <td colspan="4" class="px-3 py-5 text-center text-white/60">No velocity rows found.</td>
                      </tr>
                      <tr
                        v-for="(row, rowIndex) in genericVelocityRows"
                        :key="`v-${row.player}`"
                        class="border-b border-white/10"
                        :class="rowIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                      >
                        <td class="px-3 py-2">{{ row.player }}</td>
                        <td class="px-3 py-2">{{ row.count }}</td>
                        <td class="px-3 py-2">{{ row.avg }}</td>
                        <td class="px-3 py-2">{{ row.max }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>

              <template v-else-if="name === 'HEATMAP'">
                <SessionHeatmapPanel
                  v-if="supportsSpatial"
                  :rows="visibleFlatRows"
                  :base-grid="spatialBaseGrid"
                  :grid-size="spatialGridSize"
                  :mark-mode="spatialMarkMode"
                  :background="spatialBackground"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">Heatmap is only available for batting and bullpen sessions.</div>
              </template>

              <template v-else-if="name === 'S&M'">
                <SessionHeatmapPanel
                  v-if="isBullpen"
                  :rows="visibleFlatRows"
                  :base-grid="spatialBaseGrid"
                  :grid-size="spatialGridSize"
                  :mark-mode="spatialMarkMode"
                  :background="spatialBackground"
                  filter-mode="sm"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">S&amp;M heatmap is only available for bullpen sessions.</div>
              </template>

              <template v-else-if="name === 'VELO GRID'">
                <SessionVelocityGridPanel
                  v-if="supportsSpatial"
                  :rows="visibleFlatRows"
                  :base-grid="spatialBaseGrid"
                  :grid-size="spatialGridSize"
                  :mark-mode="spatialMarkMode"
                  :background="spatialBackground"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">Velocity grid is only available for batting and bullpen sessions.</div>
              </template>

              <template v-else-if="name === 'TOTALS'">
                <component
                  v-if="totalsComponent"
                  :is="totalsComponent"
                  :players="displayTotalsPlayers"
                  :team="displayTotalsTeamFinal"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">Totals view is not available for this session type.</div>
              </template>

              <template v-else-if="name === 'CAGE STATS'">
                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 md:p-5">
                  <div class="mb-4 flex flex-wrap gap-2 justify-center">
                    <button
                      v-for="tab in cageMetricTabs"
                      :key="`metric-${tab}`"
                      class="rounded-lg px-4 py-2 text-xs md:text-sm font-black tracking-wide transition"
                      :class="selectedCageMetricTab === tab ? 'bg-[#ff2d55] text-white' : 'bg-white/10 text-white/80 hover:bg-white/15'"
                      @click="selectedCageMetricTab = tab"
                    >
                      {{ tab }}
                    </button>
                  </div>

                  <div class="mb-4 flex flex-wrap gap-2 justify-center">
                    <button
                      v-for="tab in cageAngleTabs"
                      :key="`angle-${tab}`"
                      class="rounded-lg px-4 py-2 text-xs md:text-sm font-black tracking-wide transition"
                      :class="selectedCageAngleTab === tab ? 'bg-[#ff2d55] text-white' : 'bg-white/10 text-white/80 hover:bg-white/15'"
                      @click="selectedCageAngleTab = tab"
                    >
                      {{ tab }}
                    </button>
                  </div>

                  <div class="mb-5 flex flex-wrap gap-2 justify-center">
                    <div class="text-white/70 text-xs md:text-sm font-bold">
                      Filter: {{ cageTable.selectedCount > 0 ? `${cageTable.selectedCount} PLAYERS` : 'ALL PLAYERS' }}
                    </div>
                  </div>

                  <h3 class="text-center text-lg md:text-2xl text-[#ff2d55] font-black mb-4">{{ cageTable.title }}</h3>

                  <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="min-w-full text-sm">
                      <thead>
                        <tr class="bg-white/10 text-white/90 uppercase text-xs tracking-wider">
                          <th class="px-3 py-2 text-left">Category</th>
                          <th class="px-3 py-2 text-left">Team Total</th>
                          <th
                            v-for="player in cageTable.players"
                            :key="`header-${player.id}`"
                            class="px-3 py-2 text-left"
                          >
                            {{ player.name }}
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr class="border-b border-white/10 bg-white/5">
                          <td class="px-3 py-2 font-bold">{{ cageTable.label }}</td>
                          <td class="px-3 py-2">{{ cageTable.team.swings }}</td>
                          <td
                            v-for="player in cageTable.players"
                            :key="`swings-${player.id}`"
                            class="px-3 py-2"
                          >
                            {{ player.stats.swings }}
                          </td>
                        </tr>
                        <tr
                          v-for="(range, rangeIndex) in cageTable.ranges"
                          :key="`range-${range}`"
                          class="border-b border-white/10"
                          :class="rangeIndex % 2 === 0 ? 'bg-white/5' : 'bg-white/10'"
                        >
                          <td class="px-3 py-2">{{ range }}</td>
                          <td class="px-3 py-2">{{ cageTable.team[range] }}</td>
                          <td
                            v-for="player in cageTable.players"
                            :key="`range-${range}-${player.id}`"
                            class="px-3 py-2"
                          >
                            {{ player.stats[range] }}
                          </td>
                        </tr>
                        <tr v-if="cageRowsAll.length === 0">
                          <td colspan="16" class="px-3 py-6 text-center text-white/60">No found data</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>

              <template v-else-if="name === 'BOX SCORE'">
                <div class="rounded-2xl border border-white/10 bg-[#0a1020]/90 p-4 shadow-[0_14px_36px_rgba(0,0,0,0.28)]">
                  <!-- Toggle -->
                  <div class="flex gap-2 justify-center mb-4">
                    <button
                      class="rounded-lg px-5 py-2 text-sm font-black tracking-wide transition"
                      :class="boxScoreView === 'bat' ? 'bg-[#ff2d55] text-white' : 'bg-white/10 text-white/80 hover:bg-white/15'"
                      @click="boxScoreView = 'bat'"
                    >⚾ BATTING</button>
                    <button
                      class="rounded-lg px-5 py-2 text-sm font-black tracking-wide transition"
                      :class="boxScoreView === 'pit' ? 'bg-[#ff2d55] text-white' : 'bg-white/10 text-white/80 hover:bg-white/15'"
                      @click="boxScoreView = 'pit'"
                    >🥎 PITCHING</button>
                  </div>

                  <div v-if="boxScorePlayers.length === 0" class="py-10 text-center text-white/60">
                    No {{ boxScoreView === 'bat' ? 'batting' : 'pitching' }} data found for selected sessions.
                  </div>

                  <!-- Transposed table: rows = stats, cols = players + TEAM -->
                  <div v-else class="overflow-x-auto rounded-xl border border-white/10 bg-[#020817]/70">
                    <table class="text-sm border-collapse min-w-full">
                      <thead>
                        <tr class="bg-[#0f172a]">
                          <th class="sticky left-0 z-10 bg-[#0f172a] px-3 py-2 text-left text-xs font-black tracking-widest text-[#ff2d55] border-b border-white/10 min-w-[70px]">STAT</th>
                          <th
                            v-for="player in boxScorePlayers"
                            :key="`bs-hdr-${player.id}`"
                            class="px-3 py-2 text-center text-xs font-bold text-white/80 border-b border-white/10 min-w-[80px]"
                          >
                            <div class="font-black text-white leading-tight">{{ _abbrevName(player.name) }}</div>
                            <div v-if="player.number" class="text-white/50 text-[10px]">#{{ player.number }}</div>
                          </th>
                          <th class="px-3 py-2 text-center text-xs font-black text-[#ff2d55] border-b border-l border-white/20 min-w-[70px]">TEAM</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="([label, key], ri) in boxScoreRows"
                          :key="`bs-row-${key}`"
                          class="border-b border-white/10"
                          :class="[
                            ri % 2 === 0 ? 'bg-white/5' : 'bg-white/10',
                            key === 'ops' ? 'bg-[#ff2d55]/20 font-bold' : '',
                          ]"
                        >
                          <td class="sticky left-0 z-10 px-3 py-2 font-black text-xs tracking-wider uppercase border-r border-white/10"
                              :class="ri % 2 === 0 ? 'bg-[#0e1628] text-white/70' : 'bg-[#0b1120] text-white/70'">
                            {{ label }}
                          </td>
                          <td
                            v-for="player in boxScorePlayers"
                            :key="`bs-${key}-${player.id}`"
                            class="px-3 py-2 text-center text-white"
                            :class="key === 'ops' ? 'text-[#ff2d55] font-black' : ''"
                          >{{ player[key] ?? '-' }}</td>
                          <td class="px-3 py-2 text-center font-bold border-l border-white/20"
                              :class="key === 'ops' ? 'text-[#ff2d55] font-black' : 'text-white'">
                            {{ boxScoreTeamRow[key] ?? '-' }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </template>

              <template v-else>
                <component
                  v-if="percentagesComponent"
                  :is="percentagesComponent"
                  :players="displayPercentagesPlayers"
                  :team="displayPercentagesTeamFinal"
                />
                <div v-else class="rounded-2xl border border-white/10 bg-white/10 p-5 text-white/70">Percentages view is not available for this session type.</div>
              </template>
            </TabPanel>
          </TabPanels>
        </TabGroup>
      </div>
    </div>
  </Layout>
</template>
