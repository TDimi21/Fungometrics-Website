<script setup>
import { ref, computed } from 'vue'
import { TableEdit } from '@/components/icons'
import {useTrainingStore} from "../../../../store/training";
import { useRouter, useRoute } from 'vue-router'
import defaultIMg from '@/assets/img/noavatar.png'
import { useLiveABStore } from '@/store/liveAB.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { storeToRefs } from 'pinia'

const router = useRouter()
const route = useRoute()
const training = useTrainingStore();
const { setStatusPlayers, resetStatusPlayer } = useLiveABStore()
const { axiosGet } = useAxiosAuth()

const props = defineProps({
  tableData: {
    type: [Array, Object],
    required: true,
    default: []
  },
  isLoading: {
    type: Boolean,
    required: false
  }
})

const tableHeadings = ref([
  { title: "pitch #", is_sort: true, filter: 'sort'},
  { title: "pitcher", is_sort: true, filter: 'pitching.profile.first_name' },
  { title: "HITTERS", is_sort: true, filter: 'batting.profile.first_name'},
  { title: "COUNT", is_sort: true, filter: 'count_b_s'},
  { title: "PITCH TYPE", is_sort: true, filter: 'pitching.type_throw'},
  { title: "b/s", is_sort: true, filter: 'batting.zone'},
  { title: "Q.C", is_sort: true, filter: 'batting.quality_of_contact'},
  { title: "TOTAL BASES", is_sort: true, filter: 'bases'},
  { title: "TRAJ.", is_sort: true, filter: 'pitching.trajectory'},
  { title: "DIRECTION", is_sort: true, filter: 'batting.field_direction'},
  { title: "PITCH VELOCITY", is_sort: true, filter: 'pitching.miles_per_hour'},
  { title: "EXIT VELOCITY", is_sort: true, filter: 'batting.velocity'},
  { title: "edit", is_sort: false, filter: ''}
])

const { trainingActive } = storeToRefs(training)

const editData = (data) => {
  // console.log('ss', data.batting);
  // console.log('ddd', trainingActive.value.players);
  // return
  let newBatters = []
  let newPitchers = []
  trainingActive.value.players.batters.forEach(item => {
    newBatters.push(item.player)
  })
  trainingActive.value.players.pitchers.forEach(item => {
    newPitchers.push(item.player)
  })

  trainingActive.value.players.batters = newBatters
  trainingActive.value.players.pitchers = newPitchers
	console.log("batters", trainingActive.value.players.batters)
	console.log("pitdchers", trainingActive.value.players.pitchers)

  /* hacer un foeach del nodo players > batters y hacer un push extrayendo el player */

  
    resetStatusPlayer()


    let playersStats = props.tableData

    training.cleanListPlayer()

    const idCount = {}
    const matchingElements = [];

    playersStats.forEach(obj => {
      const batterId = obj.batting.batter_id;
      const pitcherId = obj.pitching.pitcher_id;
      const idPair = `${batterId}-${pitcherId}`;

      if (idCount[idPair]) {
        idCount[idPair]++;
      } else {
        idCount[idPair] = 1;
      }

      matchingElements.push(obj)
    });

    matchingElements.forEach(obj => {
        const batterId = obj.batting.batter_id;
        const pitcherId = obj.pitching.pitcher_id;
        const idPair = `${batterId}-${pitcherId}`;
        const count = idCount[idPair];
        setStatusPlayers( pitcherId, batterId, count)
    });

    /* save data to edit */
    trainingActive.value.bases = data.bases
    trainingActive.value.count_b_s = data.count_b_s,
    trainingActive.value.batting_result_id = data.batting_result_id,
    trainingActive.value.is_ball = data.is_ball,
    trainingActive.value.is_hit = data.is_hit,
    trainingActive.value.is_strike = data.is_strike,
    trainingActive.value.pitching_result_id = data.pitching_result_id,
    trainingActive.value.id = data.id,
    trainingActive.value.sort = data.sort,
    trainingActive.value.turn = data.turn,
    trainingActive.value.turn_ball = data.turn_ball,
    trainingActive.value.turn_is_over = data.turn_is_over,
    trainingActive.value.turn_pitches = data.turn_pitches,
    trainingActive.value.turn_strike = data.turn_strike

    trainingActive.value.battersEdit = { ...data.batting }
    trainingActive.value.pitchersEdit = { ...data.pitching }

    
    localStorage.removeItem('countBall')
    localStorage.removeItem('countStrike')

    router.push('/track/live')
  // })
}

const showTotalBasesValue = (item) => {
  let valueToShow = ''
  if(item.bases == 7 && item.pitching.trajectory != 'F' && item.count_b_s.split('-')[1] == 2) {
    valueToShow = 'K'
  } else if(item.count_b_s.split('-')[1] == 2 && item.pitching.zone == 'B' && item.pitching.trajectory == 'SM'){
    valueToShow = 'K'
  } else if (item.count_b_s.split('-')[1] == 2 && item.pitching.zone == 'S' && item.pitching.trajectory == 'TK'){
    valueToShow = 'K'
  } else if (item.bases === 4) {
    valueToShow = 'BB'
  } else if (item.bases == 6) {
    valueToShow = 'HBP'
  } else if (item.bases === 5) {
    valueToShow = 'HR'
  } else if (item.bases === 8){
    valueToShow = 'Out/E'
  } else if(item.bases == 7 && item.pitching.trajectory == 'F'){
    valueToShow = '-'
  } else {
    valueToShow = item.bases === 0 ? '-' : item.bases + 'B'
  }

  return valueToShow
}
</script>
<template>
  <section class="mt-4 overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 px-2 md:px-0 font-fungo-500 uppercase w-min"
            @click="$emit('sortData', heading.filter)"
          >
            <span role="button" class="flex flex-row justify-evenly items-center cursor-pointer">
              <label>{{ heading.title }}</label>
              <img v-if="heading.is_sort" src="@/assets/img/icons/sort-solid.svg" alt="sort data" class="w-2">
            </span>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="props.isLoading" class="w-full">
          <td colspan="13" class="w-full text-center py-5">
            <div class="animate-pulse grid grid-cols-4 gap-x-2">
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
              <div class="h-3 bg-slate-300 rounded-lg"></div>
            </div>
          </td>
        </tr>
        <tr v-else-if="props.tableData == null || props.tableData == undefined" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in props.tableData" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
          <td>
            {{ item.sort + 1 }}
          </td>
          <td class="w-[100px] lg:w-[250px] lg:max-w-[250px]">
            <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
              <img
                :src="item.pitching.profile.picture ? item.pitching.profile.picture : defaultIMg"
                :alt="`Photo of ${item.pitching.profile.first_name}`"
                class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
              >
              <p class="">
                {{ item.pitching.profile.first_name }} {{ item.pitching.profile.last_name }}
              </p>
            </div>
          </td>
          <td class="w-[100px] lg:w-[250px] lg:max-w-[250px]">
            <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
              <img
                :src="item.batting.profile.picture ? item.batting.profile.picture : defaultIMg"
                :alt="`Photo of ${item.batting.profile.first_name}`"
                class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
              >
              <p class="">
                {{ item.batting.profile.first_name }} {{ item.batting.profile.last_name }}
              </p>
            </div>
          </td>
          <td class="min-w-[100px]">
            {{ item.count_b_s }}
          </td>
          <td class="min-w-[100px]">{{ item.pitching.type_throw == 'CB' ? 'CV' : item.pitching.type_throw == 'N' ? '-' : item.pitching.type_throw }}</td>
          <td class="min-w-[100px]">{{ item.batting.zone == 'S' ? 'Strike' : 'Ball'  }}</td>
          <td class="min-w-[100px]">{{ item.batting.quality_of_contact === 'N' ? '-' :  item.batting.quality_of_contact }}</td>
          <td class="min-w-[100px]">{{ showTotalBasesValue(item) }}</td>
          <td class="min-w-[100px]">{{ item.pitching.trajectory }}</td>
          <td class="min-w-[100px]">{{ item.batting.field_direction ? item.batting.field_direction : '-' }}</td>
          <td class="min-w-[100px]">{{ item.pitching.miles_per_hour !== 0 ? item.pitching.miles_per_hour : '-' }}</td>
          <td class="min-w-[100px]">{{ item.batting.velocity !== 0 ? item.batting.velocity : '-' }}</td>
          <td class="flex min-w-[100px] justify-center justify-items-center">
            <button
              class="rounded-full hover:bg-fungo-gray2 p-2 transition-[background-color] ease-in duration-200"
              v-on:click="editData(item)">
              <TableEdit />
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

<style scoped>
table {
  border-spacing: 0 10px;
}

table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
}

table tbody tr::after {
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: #ADE8F4;
}

table tbody tr:nth-child(even)::after {
  background-color: #DADADA;
}
</style>
