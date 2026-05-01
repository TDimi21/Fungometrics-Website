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

const tableHeadings = [
  "player", "swing", "strikes",  "balls",  "weak",
  "average", "hard", "gb", "ld", "fb", "pf", "MISS/FOUL",
  "TAKE", "LEFT", "MIDDLE", "RIGHT"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Batting Session - PERCENTAGES
    </h1>
    <section class="px-[10%] md:px-[5%] mt-4 overflow-x-auto">
      <table class="w-full border-separate space-y-6 text-fungo-darkblue">
        <thead class="bg-fungo-lightblue">
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
