<script setup>
import { ref } from 'vue';
import TableSesionsTotal from './reusables/Table.vue'
const data = ref([])

const props = defineProps({
  players: {
    type: Object,
    default: () => {}
  },
  team: {
    type: Object,
    default: () => {}
  },
  playersTOP: {
    type: Object,
    default: () => {}
  },
  teamTOP: {
    type: Object,
    default: () => {}
  }
})

const tableHeadings = [
  'player', 'Pitches', 'FB', 'CH', 'CB', 'SL', 'Other'
]
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2">
    <div>
      <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
        Pitching Session - Average Velocity - Breakdown
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
              <td class="text-center">{{ props.team.pitches ?? '?' }}</td>
              <td class="text-center">{{ props.team.FB ?? '?' }}</td>
              <td class="text-center">{{ props.team.CH ?? '?' }}</td>
              <td class="text-center">{{ props.team.CB ?? '?' }}</td>
              <td class="text-center">{{ props.team.SL ?? '?' }}</td>
              <td class="text-center">{{ props.team.OTHER ?? '?' }}</td>
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
              <td class="text-center">{{ item.FB ?? '?' }}</td>
              <td class="text-center">{{ item.CH ?? '?' }}</td>
              <td class="text-center">{{ item.CB ?? '?' }}</td>
              <td class="text-center">{{ item.SL ?? '?' }}</td>
              <td class="text-center">{{ item.OTHER ?? '?' }}</td>
            </tr>
          </tbody>

        </table>
      </section>
    </div>
    <div>
      <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
        Pitching Session - Top Velocity - Breakdown
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
            <tr v-if="props.teamTOP != null">
              <td class="text-center">
                Total Players
                <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
              </td>
              <td class="text-center">{{ props.teamTOP.pitches ?? '?' }}</td>
              <td class="text-center">{{ props.teamTOP.FB ?? '?' }}</td>
              <td class="text-center">{{ props.teamTOP.CH ?? '?' }}</td>
              <td class="text-center">{{ props.teamTOP.CB ?? '?' }}</td>
              <td class="text-center">{{ props.teamTOP.SL ?? '?' }}</td>
              <td class="text-center">{{ props.teamTOP.OTHER ?? '?' }}</td>
              </tr>
            <tr v-if="props.playersTOP == null">
              <td colspan="16" class="text-fungo-darkblue text-3xl text-center">No found data</td>
            </tr>
            <tr v-else v-for="(item, index) in props.playersTOP">
              <td class="text-center">
                {{ item.player ?? '?' }}
                <!-- <img :src="item.player.avatar" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full"/> -->
              </td>
              <td class="text-center">{{ item.pitches ?? '?' }}</td>
              <td class="text-center">{{ item.FB ?? '?' }}</td>
              <td class="text-center">{{ item.CH ?? '?' }}</td>
              <td class="text-center">{{ item.CB ?? '?' }}</td>
              <td class="text-center">{{ item.SL ?? '?' }}</td>
              <td class="text-center">{{ item.OTHER ?? '?' }}</td>
            </tr>
          </tbody>

        </table>
      </section>
    </div>
  </div>
</template>
