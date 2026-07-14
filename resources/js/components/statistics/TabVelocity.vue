<script setup>
/**
 * TabVelocity.vue — batting "Velocity" tab.
 * Matches the app (Statics/tableBatting/Velocity.js): stadium background + navy
 * overlay, left hit-type filters, a spray field, a per-player exit-velocity
 * breakdown (avg by trajectory + MAX EV), and a per-swing velocity list.
 */
import { ref, computed } from 'vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import { useTeamStore } from '@/store/team'
import stadiumBg from '@/assets/img/fungometrics-stadium.png'

const props = defineProps({
  VelocityData: { type: Object, required: false, default: () => ({}) }, // by_player
  ballData: { type: Array, required: false, default: () => [] },         // ball_x_ball
})

const { team } = useTeamStore()

const FILTERS = [
  { label: 'ALL', type: null },
  { label: 'GROUND', type: 'GB' },
  { label: 'POP FLY', type: 'PF' },
  { label: 'FLY BALL', type: 'FB' },
  { label: 'LINE DRIVE', type: 'LD' },
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
  return Object.values(props.VelocityData || {}).flatMap((v) => (Array.isArray(v) ? v : []))
})

const filteredBalls = computed(() => {
  const type = FILTERS.find((f) => f.label === activeFilter.value)?.type
  return allBalls.value.filter((b) => {
    if (type && String(b.type_of_hit ?? '').toUpperCase() !== type) return false
    if (selectedPlayer.value && identityOf(b).id !== String(selectedPlayer.value)) return false
    return true
  })
})

// Per-swing velocity list (respects both filter + selected player).
const swingList = computed(() =>
  [...filteredBalls.value]
    .map((b) => ({ n: (Number(b.sort) || 0) + 1, velo: Number(b.velocity) || 0 }))
    .filter((s) => s.velo > 0)
    .sort((a, b) => a.n - b.n),
)

const avgFor = (balls, type) => {
  const vs = balls.filter((b) => String(b.type_of_hit ?? '').toUpperCase() === type && Number(b.velocity) > 0).map((b) => Number(b.velocity))
  return vs.length ? (vs.reduce((a, v) => a + v, 0) / vs.length).toFixed(1) : '—'
}
const maxFor = (balls) => {
  const vs = balls.map((b) => Number(b.velocity) || 0)
  const m = vs.length ? Math.max(...vs) : 0
  return m > 0 ? m.toFixed(1) : '—'
}

const table = computed(() => {
  const byPlayer = new Map()
  allBalls.value.forEach((b) => {
    const { id, name } = identityOf(b)
    const key = id || name
    if (!byPlayer.has(key)) byPlayer.set(key, { id, name, balls: [] })
    byPlayer.get(key).balls.push(b)
  })
  const players = [...byPlayer.values()]
    .map((p) => ({
      id: p.id,
      name: p.name,
      ld: avgFor(p.balls, 'LD'),
      fb: avgFor(p.balls, 'FB'),
      pf: avgFor(p.balls, 'PF'),
      gb: avgFor(p.balls, 'GB'),
      maxEv: maxFor(p.balls),
    }))
    .sort((a, b) => a.name.localeCompare(b.name))
  const all = allBalls.value
  return {
    players,
    team: { id: null, name: team?.name || 'Team', ld: avgFor(all, 'LD'), fb: avgFor(all, 'FB'), pf: avgFor(all, 'PF'), gb: avgFor(all, 'GB'), maxEv: maxFor(all) },
  }
})

const selectRow = (id) => { selectedPlayer.value = selectedPlayer.value === id ? null : id }
</script>

<template>
  <section class="sbt" :style="{ backgroundImage: `url(${stadiumBg})` }">
    <div class="sbt-overlay" />
    <div class="sbt-inner sbt-inner--velo">
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

      <div class="sbt-right">
        <!-- Per-player exit velocity breakdown -->
        <div class="sbt-tablewrap">
          <table class="sbt-table">
            <thead>
              <tr>
                <th class="sbt-th sbt-th--player">PLAYER</th>
                <th class="sbt-th">LD</th>
                <th class="sbt-th">FB</th>
                <th class="sbt-th">PF</th>
                <th class="sbt-th">GB</th>
                <th class="sbt-th">MAX EV</th>
              </tr>
            </thead>
            <tbody>
              <tr class="sbt-row" :class="{ 'sbt-row--sel': selectedPlayer === null }" @click="selectedPlayer = null">
                <td class="sbt-td-label">{{ table.team.name }}</td>
                <td class="sbt-cell sbt-cell--ld">{{ table.team.ld }}</td>
                <td class="sbt-cell sbt-cell--fb">{{ table.team.fb }}</td>
                <td class="sbt-cell sbt-cell--pf">{{ table.team.pf }}</td>
                <td class="sbt-cell sbt-cell--gb">{{ table.team.gb }}</td>
                <td class="sbt-cell sbt-cell--max">{{ table.team.maxEv }}</td>
              </tr>
              <tr
                v-for="p in table.players"
                :key="p.id || p.name"
                class="sbt-row"
                :class="{ 'sbt-row--sel': selectedPlayer === p.id }"
                @click="selectRow(p.id)"
              >
                <td class="sbt-td-label">{{ p.name }}</td>
                <td class="sbt-cell sbt-cell--ld">{{ p.ld }}</td>
                <td class="sbt-cell sbt-cell--fb">{{ p.fb }}</td>
                <td class="sbt-cell sbt-cell--pf">{{ p.pf }}</td>
                <td class="sbt-cell sbt-cell--gb">{{ p.gb }}</td>
                <td class="sbt-cell sbt-cell--max">{{ p.maxEv }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Per-swing velocity list -->
        <div class="sbt-list">
          <div class="sbt-list-head">
            <span>SWING #</span><span>VELOCITY</span>
          </div>
          <div class="sbt-list-body">
            <div v-for="s in swingList" :key="s.n" class="sbt-list-row">
              <span>{{ s.n }}</span><span class="sbt-list-velo">{{ s.velo.toFixed(1) }} <em>mph</em></span>
            </div>
            <div v-if="!swingList.length" class="sbt-list-empty">No recorded exit velocities</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.sbt { position: relative; border-radius: 16px; overflow: hidden; background-size: cover; background-position: center; }
.sbt-overlay { position: absolute; inset: 0; background: rgba(26, 31, 53, 0.86); }
.sbt-inner { position: relative; display: grid; grid-template-columns: 1fr; gap: 18px; padding: 18px; align-items: start; }
@media (min-width: 1024px) { .sbt-inner--velo { grid-template-columns: 150px minmax(0, 1fr) minmax(0, 1.25fr); gap: 22px; } }

.sbt-filters { display: flex; flex-wrap: wrap; gap: 8px; }
@media (min-width: 1024px) { .sbt-filters { flex-direction: column; } }
.sbt-filter { flex: 1 1 auto; min-width: 90px; padding: 9px 8px; border-radius: 8px; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; background: rgba(255,255,255,0.95); color: #0a1024; border: 1px solid rgba(255,255,255,0.25); cursor: pointer; transition: 0.15s; }
.sbt-filter:hover { background: #fff; }
.sbt-filter--on { background: #e10600; color: #fff; border-color: #e10600; }

.sbt-plot { display: flex; flex-direction: column; gap: 10px; }
.sbt-right { display: flex; flex-direction: column; gap: 14px; }

.sbt-tablewrap { overflow-x: auto; border-radius: 12px; }
.sbt-table { width: 100%; border-collapse: separate; border-spacing: 0 0; font-variant-numeric: tabular-nums; }
.sbt-th { background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; padding: 10px 5px; text-align: center; letter-spacing: 0.03em; }
.sbt-th--player { color: #e10600; text-align: left; padding-left: 12px; }
.sbt-row { cursor: pointer; }
.sbt-row:hover .sbt-td-label { background: #232a52; }
.sbt-row--sel { outline: 2px solid #e10600; outline-offset: -2px; }
.sbt-td-label { background: #1a1f35; color: #fff; font-size: 12px; font-weight: 700; padding: 11px 12px; white-space: nowrap; }
.sbt-cell { text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 5px; }
.sbt-cell--ld { background: #d8232a; }
.sbt-cell--fb { background: #2160c4; }
.sbt-cell--pf { background: #e6d08a; color: #000; }
.sbt-cell--gb { background: #16224c; }
.sbt-cell--max { background: #1f7a44; }

/* Swing velocity list */
.sbt-list { border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); background: rgba(10,16,32,0.6); }
.sbt-list-head { display: grid; grid-template-columns: 1fr 1fr; background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; padding: 9px 14px; letter-spacing: 0.04em; }
.sbt-list-body { max-height: 260px; overflow-y: auto; }
.sbt-list-row { display: grid; grid-template-columns: 1fr 1fr; padding: 8px 14px; font-size: 12px; color: #e2e8f0; font-variant-numeric: tabular-nums; }
.sbt-list-row:nth-child(odd) { background: rgba(255,255,255,0.03); }
.sbt-list-velo { font-weight: 800; color: #fff; }
.sbt-list-velo em { font-style: normal; font-size: 10px; font-weight: 600; opacity: 0.6; }
.sbt-list-empty { padding: 18px; text-align: center; color: rgba(255,255,255,0.4); font-size: 12px; }
</style>
