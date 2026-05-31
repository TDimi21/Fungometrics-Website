<script setup>
import { ref } from 'vue'
import TableSesionsTotal from './reusables/Table.vue'

const props = defineProps({
  players: {
    type: Object,
    default: () => {}
  },
  team: {
    type: Object,
    default: () => {}
  }
})

const primaryHeaders = [
  { name: 'Location', colspan: 4 },
  { name: 'quality of contact', colspan: 4 },
  { name: 'trajectory', colspan: 4 },
  { name: 'Direction', colspan: 4 },
]

const tableHeadings = [
  "player", "swing", "balls", "strikes", "weak",
  "average", "hard", "gb", "ld", "fb", "pf", "MISS/FOUL",
  "TAKE", "LEFT", "MIDDLE", "RIGHT"
]
</script>
<template>
  <div class="batting-table-wrap">
    <h1 class="batting-table-title">
      Batting Session - TOTALS
    </h1>
    <section class="batting-table-scroll">
      <table class="batting-stat-table">
        <thead>
          <template v-if="primaryHeaders.length > 0">
            <tr class="primary-head capitalize">
              <th
                v-for="head in primaryHeaders" :key="head.name"
                :colspan="head.colspan"
              >
                {{ head.name }}
              </th>
            </tr>
          </template>
          <tr class="column-head">
            <th
              v-for="(heading, index) in tableHeadings"
              :key="index"
              class="uppercase"
            >
              {{ heading }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.team != null" class="team-row">
            <td class="text-center">
              Total Players
              <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
            </td>
            <td class="text-center">{{ props.team.swings ?? '?'}}</td>
            <td class="text-center">{{ props.team.STRIKE ?? '?' }}</td>
            <td class="text-center">{{ props.team.BALL ?? '?' }}</td>
            <td class="text-center">{{ props.team.WEAK ?? '?' }}</td>
            <td class="text-center">{{ props.team.AVERAGE ?? '?' }}</td>
            <td class="text-center">{{ props.team.HARD ?? '?' }}</td>
            <td class="text-center">{{ props.team.GB ?? '?' }}</td>
            <td class="text-center">{{ props.team.LD ?? '?' }}</td>
            <td class="text-center">{{ props.team.FB ?? '?' }}</td>
            <td class="text-center">{{ props.team.PF ?? '?' }}</td>
            <td class="text-center">{{ props.team.SM ?? '?' }}</td>
            <td class="text-center">{{ props.team.TAKE ?? '?' }}</td>
            <td class="text-center">{{ props.team.LEFT ?? '?' }}</td>
            <td class="text-center">{{ props.team.MIDDLE ?? '?' }}</td>
            <td class="text-center">{{ props.team.RIGHT ?? '?' }}</td>
          </tr>
          <tr v-if="props.players == null" class="no-data-row">
            <td colspan="16" class="text-center">No found data</td>
          </tr>
          <tr v-else v-for="(item, index) in props.players" class="data-row">
            <td class="text-center">
              {{ item.player ?? '?' }}
              <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
            </td>
            <td class="text-center">{{ item.swings ?? '?'}}</td>
            <td class="text-center">{{ item.STRIKE ?? '?' }}</td>
            <td class="text-center">{{ item.BALL ?? '?' }}</td>
            <td class="text-center">{{ item.WEAK ?? '?' }}</td>
            <td class="text-center">{{ item.AVERAGE ?? '?' }}</td>
            <td class="text-center">{{ item.HARD ?? '?' }}</td>
            <td class="text-center">{{ item.GB ?? '?' }}</td>
            <td class="text-center">{{ item.LD ?? '?' }}</td>
            <td class="text-center">{{ item.FB ?? '?' }}</td>
            <td class="text-center">{{ item.PF ?? '?' }}</td>
            <td class="text-center">{{ item.SM ?? '?' }}</td>
            <td class="text-center">{{ item.TAKE ?? '?' }}</td>
            <td class="text-center">{{ item.LEFT ?? '?' }}</td>
            <td class="text-center">{{ item.MIDDLE ?? '?' }}</td>
            <td class="text-center">{{ item.RIGHT ?? '?' }}</td>
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

::-webkit-scrollbar {
  width: 4px;
  height: 4px;
}
::-webkit-scrollbar-button {
  width: 0px;
  height: 0px;
}
::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 8px;
}

::-webkit-scrollbar-thumb:active {
  background: #1e293b;
}
::-webkit-scrollbar-track {
  background: rgba(15, 23, 42, 0.85);
  border-radius: 8px;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
