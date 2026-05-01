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
  { name: 'Pitch Type', colspan: 5 },
  { name: 'Trajectory', colspan: 6 },
]
const tableHeadings = [
  "player", "Pitches", "balls", "strikes", "FB",
  "CH", "CV", "SL", "Other", "GB", "LD", "Fly",
  "Foul", "S/M", "Take"
]
</script>
<template>
  <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      Pitching Session - PERCENTAGES
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
              <td class="text-center">{{ props.team.pitches ?? '?' }}</td>
              <td class="text-center">{{ props.team.ball ?? '?' }}</td>
              <td class="text-center">{{ props.team.strike ?? '?' }}</td>
              <td class="text-center">{{ props.team.FB ?? '?' }}</td>
              <td class="text-center">{{ props.team.CH ?? '?' }}</td>
              <td class="text-center">{{ props.team.CV ?? '?' }}</td>
              <td class="text-center">{{ props.team.SL ?? '?' }}</td>
              <td class="text-center">{{ props.team.OTHER ?? '?' }}</td>
              <td class="text-center">{{ props.team.GB ?? '?' }}</td>
              <td class="text-center">{{ props.team.LD ?? '?' }}</td>
              <td class="text-center">{{ props.team.FLY ?? '?' }}</td>
              <td class="text-center">{{ props.team.FOUL ?? '?' }}</td>
              <td class="text-center">{{ props.team["S/M"] ?? '?' }}</td>
              <td class="text-center">{{ props.team.TAKE ?? '?' }}</td>
            </tr>
            <tr v-if="props.players == null">
              <td colspan="16" class="text-fungo-darkblue text-3xl text-center">No found data</td>
            </tr>
            <tr v-else v-for="(item, index) in props.players">
              <td class="text-center">
                {{ item.player ?? '?' }}
                <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
              </td>
              <td class="text-center">{{ item.pitches ?? '?' }}</td>
              <td class="text-center">{{ item.ball ?? '?' }}</td>
              <td class="text-center">{{ item.strike ?? '?' }}</td>
              <td class="text-center">{{ item.FB ?? '?' }}</td>
              <td class="text-center">{{ item.CH ?? '?' }}</td>
              <td class="text-center">{{ item.CV ?? '?' }}</td>
              <td class="text-center">{{ item.SL ?? '?' }}</td>
              <td class="text-center">{{ item.OTHER ?? '?' }}</td>
              <td class="text-center">{{ item.GB ?? '?' }}</td>
              <td class="text-center">{{ item.LD ?? '?' }}</td>
              <td class="text-center">{{ item.FLY ?? '?' }}</td>
              <td class="text-center">{{ item.FOUL ?? '?' }}</td>
              <td class="text-center">{{ item["S/M"] ?? '?' }}</td>
              <td class="text-center">{{ item.TAKE ?? '?' }}</td>
            </tr>
          </tbody>

        </table>
    </section>
  </div>
</template>
