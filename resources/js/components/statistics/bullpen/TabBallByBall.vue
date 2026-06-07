<script setup>
import { ref } from 'vue'
import { TableEdit } from '@/components/icons'
import DefaultImg from '@/assets/img/login/assteslogin/updatedlogo.png'
import {useTrainingStore} from "../../../store/training";
import router from "../../../../router";
import { useAxiosAuth } from '@/composables/axios-auth.js'

const training = useTrainingStore();

const { axiosGet } = useAxiosAuth()

defineProps({
  tableData: {
    type: Object,
    required: false,
    default: {}
  },
  isLoading: {
    type: Boolean,
    required: true
  }
})

// const tableHeadings = ref([
//   "pitch #", "player", "pitch", "traj.", "b/s", "velo", "edit"
// ])

const tableHeadings = ref([
  { title: "pitch #", is_sort: true, filter: 'sort'},
  { title: "player", is_sort: true, filter: 'profile.first_name' },
  { title: "pitch", is_sort: true, filter: 'type_throw'},
  { title: "traj.", is_sort: true, filter: 'trajectory'},
  { title: "b/s", is_sort: true, filter: 'zone'},
  { title: "velo", is_sort: true, filter: 'miles_per_hour'},
  { title: "edit", is_sort: false, filter: ''}
])


const editData = (player) => {
  let editData = {
    'id': player.practice_id,
    'players': [{
      'id': player.pitcher_id,
      'name': {
        'first': player.profile.first_name,
        'last': player.profile.last_name,
        'full': player.profile.first_name + ' ' + player.profile.last_name
      }
    }],
    ...player
  }

  axiosGet('statistics/'+player.practice_id+'/bullpen/').then((response)=>{
    let playersStats = response.data.data.by_player

    training.setDataTraining(editData)
    training.cleanListPlayer()
      for (const key in playersStats) {
        if (Object.hasOwnProperty.call(playersStats, key)) {
          const element = playersStats[key];
          training.addPLayerInfo(key, {
            'pitch': element.length,
          })
        }
      }

      router.push({
        path: '/track/bullpen'
      })
  })

}

const getPlayerPicture = (item) => {
  return item?.profile?.picture || item?.player?.picture || DefaultImg
}

const getPlayerFirstName = (item) => {
  return item?.profile?.first_name || item?.player?.first_name || item?.pitcher_name || 'Player'
}

const getPlayerLastName = (item) => {
  return item?.profile?.last_name || item?.player?.last_name || ''
}

const getPlayerDisplayName = (item) => {
  const first = getPlayerFirstName(item)
  const last = getPlayerLastName(item)
  const full = `${first} ${last}`.trim()
  return full || 'Player'
}
</script>

<template>
  <div class="stat-table-wrap">
    <section class="stat-table-scroll">
    <table class="stat-table w-full border-separate space-y-6">

      <thead>
        <tr>
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 px-2 md:px-0 uppercase w-min"
            @click="$emit('sortData', heading.filter)"
          >
            <span role="button" class="flex flex-row justify-evenly items-center cursor-pointer">
              <label>{{ heading.title }}</label>
              <img v-if="heading.is_sort" src="@/assets/img/icons/sort-solid.svg" alt="sort data" class="w-3">
            </span>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0" class="w-full">
          <td colspan="9" class="text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in tableData" :key="index" class="relative">
          <td>
            {{ (item?.sort ?? index) + 1 }}
          </td>
          <td class="w-[100px] lg:w-[300px] lg:max-w-[400px]">
            <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
              <img
                :src="getPlayerPicture(item)"
                :alt="`Photo of ${getPlayerFirstName(item)}`"
                class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
              >
              <p class="">
                {{ getPlayerDisplayName(item) }}
              </p>
            </div>
          </td>
          <td>
            {{ item.type_throw }}
          </td>
          <td>
            {{ item.trajectory }}
          </td>
          <td>
            {{ item.zone }}
          </td>
          <td>
            {{ (item.miles_per_hour).toFixed(2) }}
          </td>
          <td>
            <button
              class="rounded-full hover:bg-white/15 p-2 transition-[background-color] ease-in duration-200"
            v-on:click="editData(item)">
              <TableEdit />
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
  </div>
</template>

<style scoped>
.stat-table-wrap {
  padding: 1rem;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 1rem;
  background: rgba(10, 16, 32, 0.8);
  box-shadow: 0 14px 36px rgba(0, 0, 0, 0.28);
}

.stat-table-scroll {
  overflow-x: auto;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 0.85rem;
  background: rgba(2, 8, 23, 0.65);
}

.stat-table {
  border-spacing: 0 10px;
  color: #e2e8f0;
}

.stat-table thead th {
  background: rgba(15, 23, 42, 0.95);
  color: #e2e8f0;
  font-weight: 900;
  letter-spacing: 0.06em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
}

.stat-table tbody tr td {
  @apply text-center py-4 px-1 2xl:px-5;
  color: #e5e7eb;
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
  border-right: 1px solid rgba(255, 255, 255, 0.06);
}

.stat-table tbody tr::after {
  content: '';
  position: absolute;
  left: -1px;
  top: 0;
  height: 100%;
  width: 3px;
  background-color: rgba(192, 0, 0, 0.55);
}

.stat-table tbody tr:nth-child(odd) {
  background: rgba(255, 255, 255, 0.03);
}

.stat-table tbody tr:nth-child(even) {
  background: rgba(148, 163, 184, 0.05);
}

.stat-table tbody tr:hover {
  background: rgba(59, 130, 246, 0.12);
}

.stat-table tbody tr:nth-child(even)::after {
  background-color: rgba(148, 163, 184, 0.35);
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
