<script setup>
import { computed, ref, watch } from 'vue'
import BullpenZoneMap from '@/components/dashboard/BullpenZoneMap.vue'
import { useTeamStore } from '@/store/team'

const props = defineProps({
  VelocityData: {
    type: Object,
    required: false,
    default: () => ({}),
  },
  ballData: {
    type: [Object, Array],
    required: false,
    default: () => [],
  },
})

const { team } = useTeamStore()

const PITCH_FILTERS = [
  { label: 'ALL', key: 'ALL' },
  { label: 'FASTBALL', key: 'FB' },
  { label: 'CURVEBALL', key: 'CB' },
  { label: 'CHANGE-UP', key: 'CH' },
  { label: 'SLIDER', key: 'SL' },
  { label: 'OTHER', key: 'OTHER' },
]

const PITCH_TYPES = [
  { label: 'FB', key: 'FB', tone: 'red' },
  { label: 'CH', key: 'CH', tone: 'blue' },
  { label: 'CV', key: 'CB', tone: 'navy' },
  { label: 'SL', key: 'SL', tone: 'gold' },
  { label: 'OTH', key: 'OTHER', tone: 'slate' },
]

const activeFilter = ref('ALL')
const activePlayerId = ref('team')

const rowsFrom = (value) => {
  if (Array.isArray(value)) return value
  if (!value || typeof value !== 'object') return []
  return Object.values(value).flatMap((entry) => {
    if (Array.isArray(entry)) return entry
    if (entry && typeof entry === 'object') return [entry]
    return []
  })
}

const normalizePitchType = (row) => {
  const raw = String(row?.type_throw ?? row?.type_of_throw ?? row?.pitch_type ?? row?.pitch_name ?? '')
    .trim()
    .toUpperCase()

  if (raw) {
    if (['FB', 'FASTBALL', 'FAST BALL'].includes(raw)) return 'FB'
    if (['CH', 'CHANGEUP', 'CHANGE-UP', 'CHANGE UP'].includes(raw)) return 'CH'
    if (['CB', 'CV', 'CURVEBALL', 'CURVE BALL', 'CURVE'].includes(raw)) return 'CB'
    if (['SL', 'SLIDER'].includes(raw)) return 'SL'
    return 'OTHER'
  }

  const id = Number(row?.type_of_throw_id ?? row?.type_id ?? row?.pitch_type_id ?? 0)
  if (id === 1) return 'FB'
  if (id === 2) return 'CH'
  if (id === 3) return 'SL'
  if (id === 4) return 'CB'
  return 'OTHER'
}

const velocityOf = (row) => {
  const raw = row?.miles_per_hour ?? row?.pitch_velocity ?? row?.velocity ?? row?.velo
  if (raw === null || raw === undefined || raw === '') return null
  const value = Number(raw)
  return Number.isFinite(value) && value > 0 ? value : null
}

const playerIdFromRow = (row) => {
  const id = row?.pitcher_id ?? row?.player_id ?? row?.user_id ?? row?.profile?.id ?? row?.player?.id
  return id === null || id === undefined || id === '' ? null : String(id)
}

const playerNameFromRow = (row, fallback = 'Player') => {
  const explicit = row?.player_name || row?.pitcher_name || row?.name
  if (explicit) return String(explicit)

  const profile = row?.profile || row?.player || row?.pitcher || {}
  const first = profile?.first_name || profile?.name?.first || ''
  const last = profile?.last_name || profile?.name?.last || ''
  const full = `${first} ${last}`.trim()
  return full || fallback
}

const allRows = computed(() => {
  const direct = rowsFrom(props.ballData)
  return direct.length ? direct : rowsFrom(props.VelocityData)
})

const playerRows = computed(() => {
  const groupedEntries = Object.entries(props.VelocityData || {})
    .map(([id, value]) => {
      const rows = rowsFrom(value)
      return {
        id: String(id),
        name: playerNameFromRow(rows[0], `Player ${String(id).slice(0, 6)}`),
        rows,
      }
    })
    .filter((entry) => entry.rows.length > 0)

  if (groupedEntries.length) return groupedEntries

  const grouped = new Map()
  allRows.value.forEach((row) => {
    const id = playerIdFromRow(row)
    if (!id) return
    if (!grouped.has(id)) {
      grouped.set(id, {
        id,
        name: playerNameFromRow(row, `Player ${id.slice(0, 6)}`),
        rows: [],
      })
    }
    grouped.get(id).rows.push(row)
  })
  return [...grouped.values()]
})

const activeBaseRows = computed(() => {
  if (activePlayerId.value === 'team') return allRows.value
  return playerRows.value.find((player) => player.id === activePlayerId.value)?.rows || []
})

const filteredRows = computed(() => {
  const scoped = activeFilter.value === 'ALL'
    ? activeBaseRows.value
    : activeBaseRows.value.filter((row) => normalizePitchType(row) === activeFilter.value)
  return scoped.filter((row) => velocityOf(row) !== null)
})

const pitchListRows = computed(() => {
  return filteredRows.value.map((row, index) => ({
    id: `${playerIdFromRow(row) || 'team'}-${row?.id || row?.sort || index}`,
    number: Number.isFinite(Number(row?.sort)) ? Number(row.sort) + 1 : index + 1,
    pitch: normalizePitchType(row),
    velocity: velocityOf(row),
  }))
})

const averageVelocity = computed(() => {
  if (!filteredRows.value.length) return '0.0'
  const values = filteredRows.value.map(velocityOf).filter((value) => value !== null)
  if (!values.length) return '0.0'
  return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(1)
})

const maxVelocity = computed(() => {
  const values = filteredRows.value.map(velocityOf).filter((value) => value !== null)
  if (!values.length) return '0.0'
  return Math.max(...values).toFixed(1)
})

const averageFor = (rows, pitchType) => {
  const values = rows
    .filter((row) => normalizePitchType(row) === pitchType)
    .map(velocityOf)
    .filter((value) => value !== null)

  if (!values.length) return '-'
  return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(1)
}

const maxFastballFor = (rows) => {
  const values = rows
    .filter((row) => normalizePitchType(row) === 'FB')
    .map(velocityOf)
    .filter((value) => value !== null)

  if (!values.length) return '-'
  return Math.max(...values).toFixed(1)
}

const tableRows = computed(() => {
  const rows = [
    {
      id: 'team',
      name: team?.name || 'Team Total',
      rows: allRows.value,
      isTeam: true,
    },
    ...playerRows.value,
  ]

  return rows.map((entry) => ({
    ...entry,
    values: Object.fromEntries(PITCH_TYPES.map((type) => [type.key, averageFor(entry.rows, type.key)])),
    maxFb: maxFastballFor(entry.rows),
  }))
})

const activeSubject = computed(() => {
  if (activePlayerId.value === 'team') return team?.name || 'Team Total'
  return playerRows.value.find((player) => player.id === activePlayerId.value)?.name || 'Player'
})

const setActivePlayer = (id) => {
  activePlayerId.value = id
}

watch(
  () => props.VelocityData,
  () => {
    if (activePlayerId.value !== 'team' && !playerRows.value.some((player) => player.id === activePlayerId.value)) {
      activePlayerId.value = 'team'
    }
  },
  { deep: true },
)
</script>

<template>
  <section class="bullpen-panel">
    <div class="bullpen-header">
      <div>
        <p class="bullpen-eyebrow">Velocity Breakdown</p>
        <h3 class="bullpen-title">Velocity</h3>
        <p class="bullpen-subtitle">{{ activeSubject }} · {{ filteredRows.length }} velocity readings shown</p>
      </div>
      <div class="metric-row">
        <div class="bullpen-metric">
          <span>{{ averageVelocity }}</span>
          <small>Avg mph</small>
        </div>
        <div class="bullpen-metric">
          <span>{{ maxVelocity }}</span>
          <small>Top mph</small>
        </div>
      </div>
    </div>

    <div class="bullpen-grid">
      <aside class="filter-card">
        <p class="filter-label">Select Pitch</p>
        <button
          v-for="filter in PITCH_FILTERS"
          :key="filter.key"
          type="button"
          class="filter-button"
          :class="{ active: activeFilter === filter.key }"
          @click="activeFilter = filter.key"
        >
          {{ filter.label }}
        </button>
      </aside>

      <div class="zone-card">
        <BullpenZoneMap :pitches="filteredRows" mode="grid" />
      </div>

      <div class="velocity-stack">
        <div class="table-card">
          <div class="table-title-row">
            <div>
              <p class="bullpen-eyebrow">Pitch List</p>
              <h4>Velocities</h4>
            </div>
            <span>{{ activeFilter === 'ALL' ? 'All pitches' : PITCH_FILTERS.find((f) => f.key === activeFilter)?.label }}</span>
          </div>

          <div class="bullpen-scroll pitch-list-scroll">
            <table class="bullpen-table compact-table">
              <thead>
                <tr>
                  <th>Pitch #</th>
                  <th>Pitch</th>
                  <th>Velocity</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="pitchListRows.length === 0">
                  <td colspan="3" class="empty-cell">No velocity data found.</td>
                </tr>
                <template v-else>
                  <tr v-for="row in pitchListRows" :key="row.id">
                    <td>{{ row.number }}</td>
                    <td>{{ row.pitch }}</td>
                    <td>{{ row.velocity.toFixed(1) }} mph</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div class="table-card">
          <div class="table-title-row">
            <div>
              <p class="bullpen-eyebrow">Average Velocity</p>
              <h4>Pitch Type Table</h4>
            </div>
            <span>Click a row to filter the grid</span>
          </div>

          <div class="bullpen-scroll">
            <table class="bullpen-table">
              <thead>
                <tr>
                  <th>Player</th>
                  <th v-for="type in PITCH_TYPES" :key="type.key" :class="`tone-${type.tone}`">
                    {{ type.label }}
                  </th>
                  <th>Max FB</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="allRows.length === 0">
                  <td :colspan="PITCH_TYPES.length + 2" class="empty-cell">No bullpen velocity rows found.</td>
                </tr>
                <template v-else>
                  <tr
                    v-for="row in tableRows"
                    :key="row.id"
                    class="click-row"
                    :class="{ selected: activePlayerId === row.id }"
                    @click="setActivePlayer(row.id)"
                  >
                    <td class="player-cell">{{ row.name }}</td>
                    <td v-for="type in PITCH_TYPES" :key="`${row.id}-${type.key}`">{{ row.values[type.key] }}</td>
                    <td>{{ row.maxFb }}</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.bullpen-panel {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  background: linear-gradient(180deg, rgba(12, 18, 38, 0.96), rgba(7, 11, 24, 0.96));
  padding: 18px;
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.3);
}

.bullpen-header,
.table-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
}

.bullpen-eyebrow {
  color: #ff2d55;
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.bullpen-title {
  margin-top: 4px;
  font-size: clamp(20px, 3vw, 30px);
  font-weight: 900;
  text-transform: uppercase;
}

.bullpen-subtitle,
.table-title-row span {
  color: rgba(255, 255, 255, 0.58);
  font-size: 13px;
  font-weight: 700;
}

.metric-row {
  display: flex;
  gap: 10px;
}

.bullpen-metric {
  min-width: 112px;
  border: 1px solid rgba(255, 45, 85, 0.35);
  border-radius: 16px;
  background: rgba(255, 45, 85, 0.12);
  padding: 10px 14px;
  text-align: center;
}

.bullpen-metric span {
  display: block;
  font-size: 24px;
  font-weight: 900;
}

.bullpen-metric small {
  color: rgba(255, 255, 255, 0.68);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.bullpen-grid {
  display: grid;
  grid-template-columns: minmax(150px, 190px) minmax(280px, 0.75fr) minmax(460px, 1.45fr);
  gap: 16px;
  align-items: stretch;
}

.filter-card,
.zone-card,
.table-card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(4, 9, 22, 0.78);
}

.filter-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 10px;
  padding: 14px;
}

.filter-label {
  color: rgba(255, 255, 255, 0.62);
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.filter-button {
  min-height: 44px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 10px;
  background: #ffffff;
  color: #050816;
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.02em;
  transition: transform 0.16s ease, background 0.16s ease, color 0.16s ease;
}

.filter-button:hover {
  transform: translateY(-1px);
}

.filter-button.active {
  border-color: #ff2d55;
  background: #ff2d55;
  color: #ffffff;
  box-shadow: 0 12px 26px rgba(255, 45, 85, 0.24);
}

.zone-card {
  display: grid;
  place-items: center;
  padding: 14px;
}

.velocity-stack {
  display: grid;
  gap: 16px;
  min-width: 0;
}

.table-card {
  min-width: 0;
  padding: 14px;
}

.table-title-row h4 {
  color: #fff;
  font-size: 18px;
  font-weight: 900;
  text-transform: uppercase;
}

.bullpen-scroll {
  overflow-x: auto;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.pitch-list-scroll {
  max-height: 240px;
  overflow-y: auto;
}

.bullpen-table {
  width: 100%;
  min-width: 640px;
  border-collapse: collapse;
  color: #fff;
}

.compact-table {
  min-width: 420px;
}

.bullpen-table th {
  background: #171d46;
  padding: 14px 12px;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.bullpen-table td {
  padding: 14px 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  text-align: center;
  font-size: 15px;
  font-weight: 800;
}

.player-cell,
.bullpen-table th:first-child {
  text-align: left;
}

.click-row,
.compact-table tbody tr {
  background: rgba(31, 40, 82, 0.68);
}

.click-row:nth-child(even),
.compact-table tbody tr:nth-child(even) {
  background: rgba(53, 60, 111, 0.68);
}

.click-row {
  cursor: pointer;
}

.click-row.selected {
  outline: 2px solid rgba(255, 45, 85, 0.72);
  outline-offset: -2px;
}

.tone-red { background: #d8232a !important; }
.tone-blue { background: #2160c4 !important; }
.tone-navy { background: #16224c !important; }
.tone-gold { background: #e6d08a !important; color: #060b14 !important; }
.tone-slate { background: #5c6b8a !important; }

.empty-cell {
  color: rgba(255, 255, 255, 0.58);
  padding: 28px 12px;
  text-align: center !important;
}

@media (max-width: 1100px) {
  .bullpen-grid {
    grid-template-columns: 1fr;
  }

  .filter-card {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .filter-label {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .bullpen-panel {
    padding: 12px;
  }

  .bullpen-header {
    align-items: stretch;
    flex-direction: column;
  }

  .metric-row {
    flex-direction: column;
  }

  .filter-card {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
