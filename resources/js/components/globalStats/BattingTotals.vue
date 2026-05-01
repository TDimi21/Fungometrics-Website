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
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Batting Session - TOTALS
    </h1>
    <section class="px-[10%] md:px-[5%] mt-4 overflow-x-auto">
      <table class="w-full border-separate space-y-6 text-fungo-darkblue">
        <thead class="bg-fungo-lightblue">
          <template v-if="primaryHeaders.length > 0">
            <tr class="divide-x divide-[#000] capitalize">
              <th
                v-for="head in primaryHeaders" :key="head.name"
                :colspan="head.colspan"
              >
                {{ head.name }}
              </th>
            </tr>
          </template>
          <tr class="divide-x divide-[#000]">
            <th
              v-for="(heading, index) in tableHeadings"
              :key="index"
              class="py-3 font-fungo-500 uppercase"
            >
              {{ heading }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="props.team != null">
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
          <tr v-if="props.players == null">
            <td colspan="16" class="text-fungo-darkblue text-3xl text-center">No found data</td>
          </tr>
          <tr v-else v-for="(item, index) in props.players">
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
table{
  border-spacing: 0 10px;
}
table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after{
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}
table tbody tr:nth-child(even)::after{
  background-color: #DADADA;
}
/* progress bar */
progress.in-proress::-webkit-progress-value {
  background: #FFB457;
}
progress.completed::-webkit-progress-value {
  background: #35A800;
}
progress::-webkit-progress-bar {
  background: #DBDFF1;
}
/* end progress bar */

.tooltip {
  @apply absolute hidden group-hover:flex -left-5 -top-2 -translate-y-[60%] w-max px-2 py-1 bg-fungo-darkblue rounded-lg text-center text-white text-sm after:content-[''] after:absolute after:left-1/2 after:top-[100%] after:-translate-x-1/2 after:border-8 after:border-x-transparent after:border-b-transparent after:border-t-fungo-darkblue
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
  @apply bg-fungo-darkblue-hover rounded-md;
}

::-webkit-scrollbar-thumb:active {
  @apply bg-fungo-darkblue;
}
::-webkit-scrollbar-track {
  border: 22px solid #918383;
  @apply bg-fungo-dark-gray rounded-md;
}
::-webkit-scrollbar-corner {
  background: transparent;
}
</style>
