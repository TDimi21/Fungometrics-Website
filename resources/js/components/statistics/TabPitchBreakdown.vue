<script setup>
/**
 * TabPitchBreakdown.vue — batting "Pitch Breakdown" tab.
 * Matches the app (Statics/tableBatting/Pitch.js): stadium background + navy
 * overlay, left hit-type filters, a strike-zone scatter colored by outcome
 * (In Play / Foul / Swing & Miss / Take), and a directional table
 * (PLAYER · LEFT · MIDDLE · RIGHT · TOTAL) with FMTRX brand cell colors.
 */
import { ref, computed } from 'vue'
import StatsStrikeZonePlot from '@/components/statistics/StatsStrikeZonePlot.vue'
import { useTeamStore } from '@/store/team'
import stadiumBg from '@/assets/img/fungometrics-stadium.webp'

const props = defineProps({
  breakdownData: { type: Object, required: false, default: () => ({}) }, // by_player
  ballData: { type: Array, required: false, default: () => [] },          // ball_x_ball
})

const { team } = useTeamStore()

const FILTERS = [
  { label: 'ALL', type: null },
  { label: 'GROUND BALL', type: 'GB' },
  { label: 'POP FLY', type: 'PF' },
  { label: 'FLY BALL', type: 'FB' },
  { label: 'LINE DRIVE', type: 'LD' },
]
const activeFilter = ref('ALL')
const selectedPlayer = ref(null) // batter id, or null for team/all

const LEGEND = [
  { color: '#2ECC71', label: 'In Play' },
  { color: '#F1C40F', label: 'Foul' },
  { color: '#E74C3C', label: 'Swing & Miss' },
  { color: '#3498DB', label: 'Take' },
]

// Outcome color for a pitch dot (mirrors app pitchResultColor).
const pitchResultColor = (b) => {
  const t = String(b.type_of_hit ?? '').toUpperCase()
  const q = String(b.quality_of_contact ?? '').toUpperCase()
  if (t === 'TK' || t === 'TAKE') return '#3498DB'
  if (q === 'M' || q === 'MF' || t === 'SM') return '#E74C3C'
  if (q === 'F' || t === 'F' || t === 'FOUL') return '#F1C40F'
  return '#2ECC71'
}

const identityOf = (b) => {
  const id = String(b.batter_id ?? b.profile?.id ?? '')
  const name = b.batter_name || `${b.profile?.first_name ?? ''} ${b.profile?.last_name ?? ''}`.trim() || 'Unknown'
  return { id, name }
}

// All batting balls (prefer flat ball_x_ball; fall back to the grouped map).
const allBalls = computed(() => {
  if (Array.isArray(props.ballData) && props.ballData.length) return props.ballData
  return Object.values(props.breakdownData || {}).flatMap((v) => (Array.isArray(v) ? v : []))
})

const filteredBalls = computed(() => {
  const type = FILTERS.find((f) => f.label === activeFilter.value)?.type
  return allBalls.value.filter((b) => {
    if (type && String(b.type_of_hit ?? '').toUpperCase() !== type) return false
    if (selectedPlayer.value && identityOf(b).id !== String(selectedPlayer.value)) return false
    return true
  })
})

// Directional aggregation (LEFT / MIDDLE / RIGHT) per player + team totals.
const dirOf = (b) => {
  const d = String(b.field_direction ?? '').toUpperCase()
  if (d.includes('L')) return 'left'
  if (d.includes('R')) return 'right'
  if (d.includes('C')) return 'mid'
  return null
}

const table = computed(() => {
  const type = FILTERS.find((f) => f.label === activeFilter.value)?.type
  // Table respects the hit-type filter but not the player row selection.
  const balls = allBalls.value.filter((b) => !type || String(b.type_of_hit ?? '').toUpperCase() === type)
  const byPlayer = new Map()
  balls.forEach((b) => {
    const { id, name } = identityOf(b)
    const key = id || name
    const cur = byPlayer.get(key) || { id, name, left: 0, mid: 0, right: 0, total: 0 }
    const d = dirOf(b)
    if (d) cur[d] += 1
    cur.total += 1
    byPlayer.set(key, cur)
  })
  const players = [...byPlayer.values()].sort((a, b) => a.name.localeCompare(b.name))
  const teamRow = players.reduce(
    (acc, p) => ({ left: acc.left + p.left, mid: acc.mid + p.mid, right: acc.right + p.right, total: acc.total + p.total }),
    { left: 0, mid: 0, right: 0, total: 0 },
  )
  return { players, team: { id: null, name: team?.name || 'Team', ...teamRow } }
})

const setFilter = (label) => { activeFilter.value = label }
const selectRow = (id) => { selectedPlayer.value = selectedPlayer.value === id ? null : id }
</script>

<template>
  <section class="sbt" :style="{ backgroundImage: `url(${stadiumBg})` }">
    <div class="sbt-overlay" />
    <div class="sbt-inner">
      <!-- LEFT: hit-type filters -->
      <div class="sbt-filters">
        <button
          v-for="f in FILTERS"
          :key="f.label"
          class="sbt-filter"
          :class="{ 'sbt-filter--on': activeFilter === f.label }"
          @click="setFilter(f.label)"
        >
          {{ f.label }}
        </button>
      </div>

      <!-- CENTER: strike-zone plot -->
      <div class="sbt-plot">
        <div class="sbt-legend">
          <span v-for="l in LEGEND" :key="l.label" class="sbt-leg">
            <span class="sbt-leg-dot" :style="{ background: l.color }" />{{ l.label }}
          </span>
        </div>
        <StatsStrikeZonePlot :balls="filteredBalls" :color-of="pitchResultColor" mark-key="pitch_mark" />
      </div>

      <!-- RIGHT: directional table -->
      <div class="sbt-tablewrap">
        <table class="sbt-table">
          <thead>
            <tr>
              <th class="sbt-th sbt-th--player">PLAYER</th>
              <th class="sbt-th">LEFT</th>
              <th class="sbt-th">MIDDLE</th>
              <th class="sbt-th">RIGHT</th>
              <th class="sbt-th">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <tr class="sbt-row" :class="{ 'sbt-row--sel': selectedPlayer === null }" @click="selectedPlayer = null">
              <td class="sbt-td-label">{{ table.team.name }}</td>
              <td class="sbt-cell sbt-cell--left">{{ table.team.left }}</td>
              <td class="sbt-cell sbt-cell--mid">{{ table.team.mid }}</td>
              <td class="sbt-cell sbt-cell--right">{{ table.team.right }}</td>
              <td class="sbt-cell sbt-cell--total">{{ table.team.total }}</td>
            </tr>
            <tr
              v-for="p in table.players"
              :key="p.id || p.name"
              class="sbt-row"
              :class="{ 'sbt-row--sel': selectedPlayer === p.id }"
              @click="selectRow(p.id)"
            >
              <td class="sbt-td-label">{{ p.name }}</td>
              <td class="sbt-cell sbt-cell--left">{{ p.left }}</td>
              <td class="sbt-cell sbt-cell--mid">{{ p.mid }}</td>
              <td class="sbt-cell sbt-cell--right">{{ p.right }}</td>
              <td class="sbt-cell sbt-cell--total">{{ p.total }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sbt { position: relative; border-radius: 16px; overflow: hidden; background-size: cover; background-position: center; }
.sbt-overlay { position: absolute; inset: 0; background: rgba(26, 31, 53, 0.86); }
.sbt-inner { position: relative; display: grid; grid-template-columns: 1fr; gap: 18px; padding: 18px; align-items: center; }
@media (min-width: 1024px) { .sbt-inner { grid-template-columns: 150px minmax(0, 1fr) minmax(0, 1.15fr); gap: 22px; } }

/* Filters */
.sbt-filters { display: flex; flex-wrap: wrap; gap: 8px; }
@media (min-width: 1024px) { .sbt-filters { flex-direction: column; } }
.sbt-filter { flex: 1 1 auto; min-width: 90px; padding: 9px 8px; border-radius: 8px; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; color: #fff; background: rgba(255,255,255,0.95); color: #0a1024; border: 1px solid rgba(255,255,255,0.25); cursor: pointer; transition: 0.15s; }
.sbt-filter:hover { background: #fff; }
.sbt-filter--on { background: #e10600; color: #fff; border-color: #e10600; }

/* Plot */
.sbt-plot { display: flex; flex-direction: column; gap: 10px; }
.sbt-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }
.sbt-leg { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.88); }
.sbt-leg-dot { width: 10px; height: 10px; border-radius: 50%; }

/* Table */
.sbt-tablewrap { overflow-x: auto; border-radius: 12px; }
.sbt-table { width: 100%; border-collapse: separate; border-spacing: 0 0; font-variant-numeric: tabular-nums; }
.sbt-th { background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; padding: 10px 6px; text-align: center; letter-spacing: 0.04em; }
.sbt-th--player { color: #e10600; text-align: left; padding-left: 12px; }
.sbt-row { cursor: pointer; }
.sbt-row:hover .sbt-td-label { background: #232a52; }
.sbt-row--sel { outline: 2px solid #e10600; outline-offset: -2px; }
.sbt-td-label { background: #1a1f35; color: #fff; font-size: 12px; font-weight: 700; padding: 11px 12px; white-space: nowrap; }
.sbt-cell { text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 6px; }
.sbt-cell--left { background: #2160c4; }
.sbt-cell--mid { background: #e6d08a; color: #000; }
.sbt-cell--right { background: #16224c; }
.sbt-cell--total { background: rgba(25, 28, 74, 0.55); }
.sbt-row:nth-child(even) .sbt-cell--total { background: rgba(255, 255, 255, 0.08); }
</style>
