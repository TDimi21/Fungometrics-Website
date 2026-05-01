<script setup>
import { ref } from 'vue'
import { TableEdit } from '@/components/icons'
import {useTrainingStore} from "../../store/training";
import router from "../../../router";
import DefaultImg from '@/assets/img/noavatar.png'

const training = useTrainingStore();

const props = defineProps({
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

const tableHeadings = ref([
  { title: "pitch #", is_sort: true, filter: 'sort'},
  { title: "player", is_sort: true, filter: 'profile.first_name' },
  { title: "q.c.", is_sort: true, filter: 'quality_of_contact'},
  { title: "traj.", is_sort: true, filter: 'type_of_hit'},
  { title: "b/s", is_sort: true, filter: 'zone'},
  { title: "dir", is_sort: true, filter: 'field_direction'},
  { title: "velo", is_sort: true, filter: 'velocity'},
  { title: "edit", is_sort: false, filter: ''}
])

const editData = (player) => {
  let editData = {
    'id': player.practice_id,
    'players': [{
      'id': player.batter_id,
      'name': {
        'first': player.profile.first_name,
        'last': player.profile.last_name,
        'full': player.profile.first_name + ' ' + player.profile.last_name
      },
      'picture': player.profile.picture
    }],
    ...player
  }

  training.setDataTraining(editData)

  router.push({
    path: '/track/batting'
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
        <tr v-else-if="!props.tableData.length > 0" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in props.tableData" :key="index" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
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
            {{ item.quality_of_contact == 'N' ? '-' : item.quality_of_contact }}
          </td>
          <td>
            {{ item.type_of_hit }}
          </td>
          <td>
            {{ item.zone }}
          </td>
          <td>
            {{ item.field_direction }}
          </td>
          <td>
            {{ item.velocity == 0 ? '0.0': item.velocity}}
          </td>
          <td>
            <button
              v-on:click="editData(item)"
              class="rounded-full hover:bg-fungo-gray2 p-2 transition-[background-color] ease-in duration-200"
            >
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
