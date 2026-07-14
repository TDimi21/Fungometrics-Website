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
      row?.velo ??
      null,
  )

const getDistance = (row) => toNumber(row?.distance ?? row?.dist ?? row?.throw_distance ?? row?.feet ?? null)

const getWeight = (row) => toNumber(row?.weight ?? row?.ball_weight ?? row?.weight_oz ?? row?.oz ?? null)

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
  const hop = toNumber(row?.hop ?? row?.hops ?? row?.player_hop ?? row?.hop_count ?? row?.number_of_hops ?? 0)
  if (hop === null) return null
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
      <div class="training-header">
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
        <div class="training-metrics">
          <div v-for="card in metricCards" :key="card.label" class="training-metric">
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}<small v-if="card.unit"> {{ card.unit }}</small></strong>
          </div>
        </div>

        <div v-if="activeTabName !== 'BALL BY BALL'" class="training-filter-row">
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

@media (max-width: 768px) {
  .training-tabs,
  .training-metrics,
  .training-leaders {
    grid-template-columns: 1fr;
  }

  .training-header {
    flex-direction: column;
  }

  .training-subject {
    white-space: normal;
  }
}
</style>
