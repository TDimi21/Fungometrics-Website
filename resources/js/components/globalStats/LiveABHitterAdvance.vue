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

const columns = [
  { label: 'HITTER', key: 'player' },
  { label: 'AB', key: 'AB' },
  { label: 'PA', key: 'PA' },
  { label: 'H', key: 'H' },
  { label: 'PA/BB', key: 'PA_BB' },
  { label: 'BB/K', key: 'BB_K' },
  { label: 'C%', key: 'C_PERCENTS' },
  { label: 'XBH', key: 'XBH' },
  { label: 'PS/PA', key: 'PS_PA' },
  { label: '2S AVG', key: '2SAVG' },
  { label: '6+', key: '6+' },
  { label: '6+ %', key: '6+ %' },
  { label: 'HARDHIT %', key: 'HARDHIT' },
  { label: 'LD%', key: 'LDP' },
  { label: 'GB%', key: 'GBP' },
  { label: 'FLY%', key: 'FLYP' },
  { label: 'BABIP%', key: 'BABIP' },
]

const rows = computed(() => {
  if (!props.players || typeof props.players !== 'object') return []
  return Object.values(props.players)
})
</script>

<template>
  <div class="batting-table-wrap">
    <h1 class="batting-table-title">Live AB - Hitter Advanced</h1>
    <section class="batting-table-scroll">
      <table class="batting-stat-table">
        <thead>
          <tr class="column-head">
            <th
              v-for="col in columns"
              :key="`liveab-adv-head-${col.key}`"
              class="uppercase"
            >
              {{ col.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.team" class="team-row">
            <td>TEAM TOTAL</td>
            <td v-for="col in columns.slice(1)" :key="`liveab-adv-team-${col.key}`">
              {{ props.team[col.key] ?? 0 }}
            </td>
          </tr>

          <tr v-if="rows.length === 0" class="no-data-row">
            <td :colspan="columns.length" class="text-center">No found data</td>
          </tr>

          <tr
            v-else
            v-for="(item, idx) in rows"
            :key="`liveab-adv-row-${idx}`"
            class="data-row"
          >
            <td>{{ item.player ?? '?' }}</td>
            <td v-for="col in columns.slice(1)" :key="`liveab-adv-${idx}-${col.key}`">
              {{ item[col.key] ?? 0 }}
            </td>
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
  min-width: 1080px;
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
