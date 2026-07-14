<script setup>
/**
 * TabTrajectory.vue — batting "Trajectory" tab.
 * Matches the app (Statics/tableBatting/Trajectory.js): stadium background + navy
 * overlay, left direction filters (ALL / LEFT / MIDDLE / RIGHT), a spray field,
 * and a trajectory table (PLAYER · FB · PF · LD · GB · TOTAL) with brand cells.
 */
import { ref, computed } from 'vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import { useTeamStore } from '@/store/team'
import stadiumBg from '@/assets/img/fungometrics-stadium.png'

const props = defineProps({
  trajectoryData: { type: Object, required: false, default: () => ({}) }, // by_player
  ballData: { type: Array, required: false, default: () => [] },           // ball_x_ball
})

const { team } = useTeamStore()

const FILTERS = [
  { label: 'ALL', dir: null },
  { label: 'LEFT', dir: 'L' },
  { label: 'MIDDLE', dir: 'C' },
  { label: 'RIGHT', dir: 'R' },
]
const activeFilter = ref('ALL')
const selectedPlayer = ref(null)

const identityOf = (b) => {
  const id = String(b.batter_id ?? b.profile?.id ?? '')
  const name = b.batter_name || `${b.profile?.first_name ?? ''} ${b.profile?.last_name ?? ''}`.trim() || 'Unknown'
  return { id, name }
}

const allBalls = computed(() => {
  if (Array.isArray(props.ballData) && props.ballData.length) return props.ballData
  return Object.values(props.trajectoryData || {}).flatMap((v) => (Array.isArray(v) ? v : []))
})

const matchesDir = (b, dir) => {
  if (!dir) return true
  const d = String(b.field_direction ?? '').toUpperCase()
  if (dir === 'L') return d.includes('L')
  if (dir === 'R') return d.includes('R')
  return d.includes('C') && !d.includes('L') && !d.includes('R') // middle
}

const filteredBalls = computed(() => {
  const dir = FILTERS.find((f) => f.label === activeFilter.value)?.dir
  return allBalls.value.filter((b) => {
    if (!matchesDir(b, dir)) return false
    if (selectedPlayer.value && identityOf(b).id !== String(selectedPlayer.value)) return false
    return true
  })
})

const trajBucket = (b) => {
  const t = String(b.type_of_hit ?? '').toUpperCase()
  if (t === 'FB') return 'fb'
  if (t === 'PF') return 'pf'
  if (t === 'LD') return 'ld'
  if (t === 'GB') return 'gb'
  return null // takes / fouls
}

const table = computed(() => {
  const dir = FILTERS.find((f) => f.label === activeFilter.value)?.dir
  const balls = allBalls.value.filter((b) => matchesDir(b, dir))
  const byPlayer = new Map()
  balls.forEach((b) => {
    const { id, name } = identityOf(b)
    const key = id || name
    const cur = byPlayer.get(key) || { id, name, fb: 0, pf: 0, ld: 0, gb: 0, total: 0 }
    const bucket = trajBucket(b)
    if (bucket) cur[bucket] += 1
    cur.total += 1
    byPlayer.set(key, cur)
  })
  const players = [...byPlayer.values()].sort((a, b) => a.name.localeCompare(b.name))
  const t = players.reduce(
    (acc, p) => ({ fb: acc.fb + p.fb, pf: acc.pf + p.pf, ld: acc.ld + p.ld, gb: acc.gb + p.gb, total: acc.total + p.total }),
    { fb: 0, pf: 0, ld: 0, gb: 0, total: 0 },
  )
  return { players, team: { id: null, name: team?.name || 'Team', ...t } }
})

const selectRow = (id) => { selectedPlayer.value = selectedPlayer.value === id ? null : id }
</script>

<template>
  <section class="sbt" :style="{ backgroundImage: `url(${stadiumBg})` }">
    <div class="sbt-overlay" />
    <div class="sbt-inner">
      <div class="sbt-filters">
        <button
          v-for="f in FILTERS"
          :key="f.label"
          class="sbt-filter"
          :class="{ 'sbt-filter--on': activeFilter === f.label }"
          @click="activeFilter = f.label"
        >
          {{ f.label }}
        </button>
      </div>

      <div class="sbt-plot">
        <VelocitySprayField :balls="filteredBalls" mode="spray" :use-field-mark="true" :show-chrome="false" />
      </div>

      <div class="sbt-tablewrap">
        <table class="sbt-table">
          <thead>
            <tr>
              <th class="sbt-th sbt-th--player">PLAYER</th>
              <th class="sbt-th">FB</th>
              <th class="sbt-th">PF</th>
              <th class="sbt-th">LD</th>
              <th class="sbt-th">GB</th>
              <th class="sbt-th">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <tr class="sbt-row" :class="{ 'sbt-row--sel': selectedPlayer === null }" @click="selectedPlayer = null">
              <td class="sbt-td-label">{{ table.team.name }}</td>
              <td class="sbt-cell sbt-cell--fb">{{ table.team.fb }}</td>
              <td class="sbt-cell sbt-cell--pf">{{ table.team.pf }}</td>
              <td class="sbt-cell sbt-cell--ld">{{ table.team.ld }}</td>
              <td class="sbt-cell sbt-cell--gb">{{ table.team.gb }}</td>
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
              <td class="sbt-cell sbt-cell--fb">{{ p.fb }}</td>
              <td class="sbt-cell sbt-cell--pf">{{ p.pf }}</td>
              <td class="sbt-cell sbt-cell--ld">{{ p.ld }}</td>
              <td class="sbt-cell sbt-cell--gb">{{ p.gb }}</td>
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

.sbt-filters { display: flex; flex-wrap: wrap; gap: 8px; }
@media (min-width: 1024px) { .sbt-filters { flex-direction: column; } }
.sbt-filter { flex: 1 1 auto; min-width: 90px; padding: 9px 8px; border-radius: 8px; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; background: rgba(255,255,255,0.95); color: #0a1024; border: 1px solid rgba(255,255,255,0.25); cursor: pointer; transition: 0.15s; }
.sbt-filter:hover { background: #fff; }
.sbt-filter--on { background: #e10600; color: #fff; border-color: #e10600; }

.sbt-plot { display: flex; flex-direction: column; gap: 10px; }

.sbt-tablewrap { overflow-x: auto; border-radius: 12px; }
.sbt-table { width: 100%; border-collapse: separate; border-spacing: 0 0; font-variant-numeric: tabular-nums; }
.sbt-th { background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; padding: 10px 6px; text-align: center; letter-spacing: 0.03em; }
.sbt-th--player { color: #e10600; text-align: left; padding-left: 12px; }
.sbt-row { cursor: pointer; }
.sbt-row:hover .sbt-td-label { background: #232a52; }
.sbt-row--sel { outline: 2px solid #e10600; outline-offset: -2px; }
.sbt-td-label { background: #1a1f35; color: #fff; font-size: 12px; font-weight: 700; padding: 11px 12px; white-space: nowrap; }
.sbt-cell { text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 6px; }
.sbt-cell--fb { background: #2160c4; }
.sbt-cell--pf { background: #e6d08a; color: #000; }
.sbt-cell--ld { background: #d8232a; }
.sbt-cell--gb { background: #16224c; }
.sbt-cell--total { background: rgba(25, 28, 74, 0.55); }
.sbt-row:nth-child(even) .sbt-cell--total { background: rgba(255, 255, 255, 0.08); }
</style>
