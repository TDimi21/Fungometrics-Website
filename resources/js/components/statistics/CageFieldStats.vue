<script setup>
import { computed, ref } from 'vue'
import VelocitySprayField from '@/components/dashboard/VelocitySprayField.vue'
import stadiumBg from '@/assets/img/fungometrics-stadium.webp'

const props = defineProps({
  balls: { type: Array, default: () => [] },
  teamName: { type: String, default: 'Team' },
  mode: {
    type: String,
    default: 'contact',
    validator: (value) => ['contact', 'trajectory', 'velocity'].includes(value),
  },
})

const selectedFilter = ref('ALL')
const selectedPlayer = ref(null)

const hitFilters = [
  { label: 'ALL', value: 'ALL' },
  { label: 'GROUND', value: 'GB' },
  { label: 'POP FLY', value: 'PF' },
  { label: 'FLY BALL', value: 'FB' },
  { label: 'LINE DRIVE', value: 'LD' },
]
const directionFilters = [
  { label: 'ALL', value: 'ALL' },
  { label: 'LEFT', value: 'LEFT' },
  { label: 'MIDDLE', value: 'MIDDLE' },
  { label: 'RIGHT', value: 'RIGHT' },
]
const filters = computed(() =>
  props.mode === 'trajectory' ? directionFilters : hitFilters,
)

const trajectory = (ball) => {
  const stored = String(ball.type_of_hit ?? ball.trajectory ?? '').toUpperCase()
  if (['GB', 'LD', 'FB', 'PF'].includes(stored)) return stored
  const launch = Number(ball.launch_angle)
  if (!Number.isFinite(launch)) return ''
  if (launch >= 45) return 'PF'
  if (launch >= 25) return 'FB'
  if (launch >= 8) return 'LD'
  return 'GB'
}

const normalizedBalls = computed(() =>
  (Array.isArray(props.balls) ? props.balls : []).map((ball) => ({
    ...ball,
    playerId: String(ball.user_id ?? ball.batter_id ?? ball.profile?.user_id ?? ''),
    playerName:
      `${ball.profile?.first_name ?? ''} ${ball.profile?.last_name ?? ''}`.trim() ||
      ball.batter_name ||
      'Player',
    velocity: Number(ball.launch_angle_velocity ?? ball.velocity ?? 0),
    launch_angle: Number(ball.launch_angle ?? 0),
    spray_angle: Number(ball.spray_angle ?? 0),
    distance_travel: Number(ball.distance_travel ?? 0),
    trajectory: trajectory(ball),
  })),
)

const matchesDirection = (ball, filter) => {
  if (filter === 'ALL') return true
  if (filter === 'LEFT') return ball.spray_angle < -15
  if (filter === 'RIGHT') return ball.spray_angle > 15
  return ball.spray_angle >= -15 && ball.spray_angle <= 15
}

const filteredBalls = computed(() =>
  normalizedBalls.value.filter((ball) => {
    const filterMatch = props.mode === 'trajectory'
      ? matchesDirection(ball, selectedFilter.value)
      : selectedFilter.value === 'ALL' || ball.trajectory === selectedFilter.value
    const playerMatch =
      !selectedPlayer.value || ball.playerId === String(selectedPlayer.value)
    return filterMatch && playerMatch
  }),
)

const playerGroups = computed(() => {
  const base = props.mode === 'trajectory'
    ? normalizedBalls.value.filter((ball) => matchesDirection(ball, selectedFilter.value))
    : normalizedBalls.value.filter(
      (ball) => selectedFilter.value === 'ALL' || ball.trajectory === selectedFilter.value,
    )
  const groups = new Map()
  base.forEach((ball) => {
    const key = ball.playerId || ball.playerName
    if (!groups.has(key)) {
      groups.set(key, { id: ball.playerId, name: ball.playerName, balls: [] })
    }
    groups.get(key).balls.push(ball)
  })
  return [...groups.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const trajectoryCounts = (balls) => {
  const result = { fb: 0, pf: 0, ld: 0, gb: 0, total: balls.length }
  balls.forEach((ball) => {
    const key = String(ball.trajectory || '').toLowerCase()
    if (Object.hasOwn(result, key)) result[key] += 1
  })
  return result
}

const velocityCounts = (balls) => {
  const buckets = { under60: 0, sixty: 0, seventy: 0, eighty: 0, ninety: 0 }
  const velocities = balls.map((ball) => ball.velocity).filter((value) => value > 0)
  velocities.forEach((velocity) => {
    if (velocity < 60) buckets.under60 += 1
    else if (velocity < 70) buckets.sixty += 1
    else if (velocity < 80) buckets.seventy += 1
    else if (velocity < 90) buckets.eighty += 1
    else buckets.ninety += 1
  })
  const total = velocities.length
  const format = (count) => total ? `${Math.round((count / total) * 100)}% (${count})` : '0% (0)'
  return {
    under60: format(buckets.under60),
    sixty: format(buckets.sixty),
    seventy: format(buckets.seventy),
    eighty: format(buckets.eighty),
    ninety: format(buckets.ninety),
    max: total ? Math.max(...velocities) : 0,
  }
}

const rows = computed(() => {
  const make = props.mode === 'velocity' ? velocityCounts : trajectoryCounts
  const teamBalls = playerGroups.value.flatMap((group) => group.balls)
  return [
    { id: null, name: props.teamName || 'Team', ...make(teamBalls) },
    ...playerGroups.value.map((group) => ({
      id: group.id,
      name: group.name,
      ...make(group.balls),
    })),
  ]
})

const selectPlayer = (id) => {
  selectedPlayer.value = selectedPlayer.value === id ? null : id
}
</script>

<template>
  <section class="cfs" :style="{ backgroundImage: `url(${stadiumBg})` }">
    <div class="cfs-overlay" />
    <div class="cfs-grid">
      <aside class="cfs-filters">
        <button
          v-for="filter in filters"
          :key="filter.value"
          type="button"
          class="cfs-filter"
          :class="{ 'cfs-filter--active': selectedFilter === filter.value }"
          @click="selectedFilter = filter.value"
        >
          {{ filter.label }}
        </button>
      </aside>

      <div class="cfs-field">
        <VelocitySprayField
          :balls="filteredBalls"
          :mode="mode === 'velocity' ? 'heatmap' : 'spray'"
          :use-field-mark="false"
          :show-chrome="false"
          :trajectory-lines="mode !== 'velocity'"
        />
      </div>

      <div class="cfs-table-wrap">
        <table v-if="mode !== 'velocity'" class="cfs-table">
          <thead>
            <tr>
              <th>Player</th><th>FB</th><th>PF</th><th>LD</th><th>GB</th><th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id || 'team'"
              :class="{ 'cfs-row--selected': selectedPlayer === row.id }"
              @click="selectPlayer(row.id)"
            >
              <td>{{ row.name }}</td>
              <td class="cfs-fb">{{ row.fb }}</td>
              <td class="cfs-pf">{{ row.pf }}</td>
              <td class="cfs-ld">{{ row.ld }}</td>
              <td class="cfs-gb">{{ row.gb }}</td>
              <td>{{ row.total }}</td>
            </tr>
          </tbody>
        </table>

        <table v-else class="cfs-table cfs-table--velocity">
          <thead>
            <tr>
              <th>Player</th><th>&lt;60</th><th>60–69</th><th>70–79</th>
              <th>80–89</th><th>90+</th><th>Max EV</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id || 'team'"
              :class="{ 'cfs-row--selected': selectedPlayer === row.id }"
              @click="selectPlayer(row.id)"
            >
              <td>{{ row.name }}</td>
              <td>{{ row.under60 }}</td><td>{{ row.sixty }}</td>
              <td>{{ row.seventy }}</td><td>{{ row.eighty }}</td>
              <td>{{ row.ninety }}</td>
              <td class="cfs-max">{{ row.max ? `${row.max} mph` : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<style scoped>
.cfs { position: relative; min-height: 540px; overflow: hidden; border-radius: 14px; background-position: center; background-size: cover; }
.cfs-overlay { position: absolute; inset: 0; background: rgba(8, 14, 32, .84); }
.cfs-grid { position: relative; display: grid; grid-template-columns: 140px minmax(320px, .95fr) minmax(430px, 1.15fr); gap: 18px; align-items: start; padding: 22px; }
.cfs-filters { display: flex; flex-direction: column; gap: 10px; padding-top: 46px; }
.cfs-filter { min-height: 48px; border: 1px solid rgba(255,255,255,.2); border-radius: 9px; background: #fff; color: #0a1024; font-size: 12px; font-weight: 900; }
.cfs-filter--active { border-color: #ff2b4a; background: #ff2b4a; color: #fff; }
.cfs-field { min-width: 0; padding-top: 10px; }
.cfs-table-wrap { overflow-x: auto; border-radius: 10px; }
.cfs-table { width: 100%; border-collapse: collapse; color: #fff; font-variant-numeric: tabular-nums; }
.cfs-table th { height: 58px; padding: 8px 10px; background: #171d4a; font-size: 12px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
.cfs-table td { height: 60px; padding: 9px 12px; text-align: center; font-size: 13px; font-weight: 800; white-space: nowrap; }
.cfs-table td:first-child, .cfs-table th:first-child { text-align: left; }
.cfs-table tbody tr { cursor: pointer; background: rgba(20,27,74,.94); }
.cfs-table tbody tr:nth-child(even) { background: rgba(49,56,96,.94); }
.cfs-table tbody tr:hover, .cfs-row--selected { outline: 2px solid #ff2b4a; outline-offset: -2px; }
.cfs-fb { background: #2868c8; }
.cfs-pf { background: #e8d48e; color: #111; }
.cfs-ld { background: #dc252b; }
.cfs-gb { background: #17234d; }
.cfs-max { color: #fff; font-size: 15px !important; }
@media (max-width: 1180px) {
  .cfs-grid { grid-template-columns: 130px minmax(300px, 1fr); }
  .cfs-table-wrap { grid-column: 1 / -1; }
  .cfs-filters { padding-top: 16px; }
}
@media (max-width: 720px) {
  .cfs-grid { grid-template-columns: 1fr; padding: 12px; }
  .cfs-filters { flex-direction: row; overflow-x: auto; padding: 0; }
  .cfs-filter { min-width: 110px; }
  .cfs-field, .cfs-table-wrap { grid-column: auto; }
}
</style>
