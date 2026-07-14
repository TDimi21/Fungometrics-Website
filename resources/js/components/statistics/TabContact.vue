<script setup>
/**
 * TabContact.vue — batting "Contact" tab.
 * Matches the app (Statics/tableBatting/Contact.js): stadium background + navy
 * overlay, left hit-type filters, a spray field (fan diagram with fence-distance
 * arcs + contact dots), and a quality-of-contact table
 * (PLAYER · MISS/FOUL · WEAK · AVG · HARD · TOTAL) with FMTRX brand cell colors.
 */
import { ref, computed } from 'vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import { useTeamStore } from '@/store/team'
import stadiumBg from '@/assets/img/fungometrics-stadium.png'

const props = defineProps({
  contactData: { type: Object, required: false, default: () => ({}) }, // by_player
  ballData: { type: Array, required: false, default: () => [] },        // ball_x_ball
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
  return Object.values(props.contactData || {}).flatMap((v) => (Array.isArray(v) ? v : []))
})

const filteredBalls = computed(() => {
  const type = FILTERS.find((f) => f.label === activeFilter.value)?.type
  return allBalls.value.filter((b) => {
    if (type && String(b.type_of_hit ?? '').toUpperCase() !== type) return false
    if (selectedPlayer.value && identityOf(b).id !== String(selectedPlayer.value)) return false
    return true
  })
})

const isMissFoul = (b) => {
  const q = String(b.quality_of_contact ?? '').toUpperCase()
  const t = String(b.type_of_hit ?? '').toUpperCase()
  return q === 'M' || q === 'MF' || q === 'MISS' || t === 'SM' || t === 'F' || t === 'FOUL'
}

const table = computed(() => {
  const type = FILTERS.find((f) => f.label === activeFilter.value)?.type
  const balls = allBalls.value.filter((b) => !type || String(b.type_of_hit ?? '').toUpperCase() === type)
  const byPlayer = new Map()
  balls.forEach((b) => {
    const { id, name } = identityOf(b)
    const key = id || name
    const cur = byPlayer.get(key) || { id, name, missFoul: 0, weak: 0, avg: 0, hard: 0, total: 0 }
    const q = String(b.quality_of_contact ?? '').toUpperCase()
    const t = String(b.type_of_hit ?? '').toUpperCase()
    if (isMissFoul(b)) cur.missFoul += 1
    else if (t === 'TK' || t === 'TAKE') { /* takes: count toward total only */ }
    else if (q === 'W' || q === 'WEAK') cur.weak += 1
    else if (q === 'A' || q === 'AVG' || q === 'AVERAGE') cur.avg += 1
    else if (q === 'H' || q === 'HARD') cur.hard += 1
    cur.total += 1
    byPlayer.set(key, cur)
  })
  const players = [...byPlayer.values()].sort((a, b) => a.name.localeCompare(b.name))
  const t = players.reduce(
    (acc, p) => ({ missFoul: acc.missFoul + p.missFoul, weak: acc.weak + p.weak, avg: acc.avg + p.avg, hard: acc.hard + p.hard, total: acc.total + p.total }),
    { missFoul: 0, weak: 0, avg: 0, hard: 0, total: 0 },
  )
  return { players, team: { id: null, name: team?.name || 'Team', ...t } }
})

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
          @click="activeFilter = f.label"
        >
          {{ f.label }}
        </button>
      </div>

      <!-- CENTER: spray field -->
      <div class="sbt-plot">
        <VelocitySprayField :balls="filteredBalls" mode="spray" :use-field-mark="true" :show-chrome="false" />
      </div>

      <!-- RIGHT: contact-quality table -->
      <div class="sbt-tablewrap">
        <table class="sbt-table">
          <thead>
            <tr>
              <th class="sbt-th sbt-th--player">PLAYER</th>
              <th class="sbt-th">MISS/FOUL</th>
              <th class="sbt-th">WEAK</th>
              <th class="sbt-th">AVG</th>
              <th class="sbt-th">HARD</th>
              <th class="sbt-th">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <tr class="sbt-row" :class="{ 'sbt-row--sel': selectedPlayer === null }" @click="selectedPlayer = null">
              <td class="sbt-td-label">{{ table.team.name }}</td>
              <td class="sbt-cell sbt-cell--mf">{{ table.team.missFoul }}</td>
              <td class="sbt-cell sbt-cell--weak">{{ table.team.weak }}</td>
              <td class="sbt-cell sbt-cell--avg">{{ table.team.avg }}</td>
              <td class="sbt-cell sbt-cell--hard">{{ table.team.hard }}</td>
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
              <td class="sbt-cell sbt-cell--mf">{{ p.missFoul }}</td>
              <td class="sbt-cell sbt-cell--weak">{{ p.weak }}</td>
              <td class="sbt-cell sbt-cell--avg">{{ p.avg }}</td>
              <td class="sbt-cell sbt-cell--hard">{{ p.hard }}</td>
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
.sbt-th { background: #191c4a; color: #fff; font-size: 10px; font-weight: 800; padding: 10px 5px; text-align: center; letter-spacing: 0.03em; }
.sbt-th--player { color: #e10600; text-align: left; padding-left: 12px; }
.sbt-row { cursor: pointer; }
.sbt-row:hover .sbt-td-label { background: #232a52; }
.sbt-row--sel { outline: 2px solid #e10600; outline-offset: -2px; }
.sbt-td-label { background: #1a1f35; color: #fff; font-size: 12px; font-weight: 700; padding: 11px 12px; white-space: nowrap; }
.sbt-cell { text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding: 11px 5px; }
.sbt-cell--mf { background: #5c6b8a; }
.sbt-cell--weak { background: #2160c4; }
.sbt-cell--avg { background: #e6d08a; color: #000; }
.sbt-cell--hard { background: #d8232a; }
.sbt-cell--total { background: rgba(25, 28, 74, 0.55); }
.sbt-row:nth-child(even) .sbt-cell--total { background: rgba(255, 255, 255, 0.08); }
</style>
