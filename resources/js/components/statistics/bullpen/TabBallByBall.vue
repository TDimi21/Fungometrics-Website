<script setup>
import { ref } from 'vue'
import { TableEdit } from '@/components/icons'
import DefaultImg from '@/assets/img/noavatar.png'
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
              <img v-if="heading.is_sort" src="@/assets/img/icons/sort-solid.svg" alt="sort data" class="w-3">
            </span>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in tableData" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
          <td>
            {{ item.sort + 1 }}
          </td>
          <td class="w-[100px] lg:w-[300px] lg:max-w-[400px]">
            <div class="grid grid-cols-2 place-items-center w-[200px] lg:w-auto">
              <img
                :src="item.profile.picture ? item.profile.picture : DefaultImg"
                :alt="`Photo of ${item.profile.first_name}`"
                class="w-[70px] h-[70px] rounded-full border-[5px] border-fungo-gray"
              >
              <p class="">
                {{ item.profile.first_name }} {{ item.profile.last_name }}
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
