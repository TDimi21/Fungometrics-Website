<script setup>
import { computed } from 'vue'

const props = defineProps({
  players: {
    type: Object,
    default: () => {}
  },
  team: {
    type: Object,
    default: () => {}
  },
})

const normalizedPlayers = computed(() => {
  if (!props.players || typeof props.players !== 'object') return []
  return Object.values(props.players)
})

const weightCols = computed(() => {
  const keys = new Set()
  const addKeysFromObj = (obj) => {
    if (!obj || typeof obj !== 'object') return
    Object.keys(obj).forEach((k) => {
      if (k === 'player' || k === 'throws') return
      const n = Number(k)
      if (Number.isFinite(n)) keys.add(String(n))
    })
  }

  addKeysFromObj(props.team)
  normalizedPlayers.value.forEach((p) => addKeysFromObj(p))

  return [...keys].sort((a, b) => Number(a) - Number(b))
})
</script>
<template>
  <div class="batting-table-wrap">
    <h1 class="batting-table-title">Weighted Balls - Average Velocity</h1>
    <section class="batting-table-scroll">
      <table class="batting-stat-table">
        <thead>
          <tr class="primary-head capitalize">
            <th colspan="2">Location</th>
            <th :colspan="Math.max(weightCols.length, 1)">Weight (oz)</th>
          </tr>
          <tr class="column-head">
            <th class="uppercase">Player</th>
            <th class="uppercase">Throws</th>
            <th v-for="key in weightCols" :key="key" class="uppercase">{{ key }}</th>
            <th v-if="weightCols.length === 0" class="uppercase">-</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.team != null" class="team-row">
            <td>Total Players</td>
            <td>{{ props.team.throws ?? '?' }}</td>
            <td v-for="key in weightCols" :key="`team-${key}`">{{ props.team[key] ?? 0 }}</td>
            <td v-if="weightCols.length === 0">-</td>
          </tr>
          <tr v-if="normalizedPlayers.length === 0" class="no-data-row">
            <td :colspan="weightCols.length + 2" class="text-center">No found data</td>
          </tr>
          <tr v-else v-for="(item, index) in normalizedPlayers" :key="index" class="data-row">
            <td>{{ item.player ?? '?' }}</td>
            <td>{{ item.throws ?? '?' }}</td>
            <td v-for="key in weightCols" :key="`player-${index}-${key}`">{{ item[key] ?? 0 }}</td>
            <td v-if="weightCols.length === 0">-</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

<style scoped>
.batting-table-wrap {
  padding: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  background: rgba(10, 16, 32, 0.8);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.28);
}

.batting-table-title {
  color: #f8fafc;
  font-size: 1.05rem;
  text-align: center;
  margin: 0 0 0.9rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.batting-table-scroll {
  overflow-x: auto;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.85rem;
  background: rgba(2, 8, 23, 0.65);
}

.batting-stat-table {
  width: 100%;
  min-width: 760px;
  border-collapse: separate;
  border-spacing: 0;
}

.batting-stat-table thead th {
  padding: 0.7rem 0.55rem;
  color: #e2e8f0;
  font-size: 0.7rem;
  font-weight: 900;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
}

.batting-stat-table .primary-head th {
  background: rgba(30, 41, 59, 0.95);
  color: #93c5fd;
  font-size: 0.68rem;
}

.batting-stat-table .column-head th {
  background: rgba(15, 23, 42, 0.95);
}

.batting-stat-table tbody td {
  padding: 0.62rem 0.55rem;
  text-align: center;
  color: #e5e7eb;
  font-size: 0.78rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.batting-stat-table thead th:first-child,
.batting-stat-table tbody td:first-child {
  text-align: left;
  padding-left: 0.85rem;
  font-weight: 800;
}

.batting-stat-table .team-row td {
  background: rgba(192, 0, 0, 0.16);
  color: #fee2e2;
  font-weight: 900;
}

.batting-stat-table .data-row:nth-child(odd) td {
  background: rgba(255, 255, 255, 0.03);
}

.batting-stat-table .data-row:nth-child(even) td {
  background: rgba(148, 163, 184, 0.05);
}

.batting-stat-table .data-row:hover td {
  background: rgba(59, 130, 246, 0.12);
}

.batting-stat-table .no-data-row td {
  padding: 1.2rem;
  color: rgba(248, 250, 252, 0.55);
  font-size: 0.95rem;
  background: rgba(255, 255, 255, 0.02);
}
</style>
