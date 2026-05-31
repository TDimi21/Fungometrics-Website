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

const getPlayerPicture = (item) => {
  return item?.profile?.picture || item?.player?.picture || DefaultImg
}

const getPlayerFirstName = (item) => {
  return item?.profile?.first_name || item?.player?.first_name || item?.batter_name || 'Player'
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
        <tr v-else-if="!props.tableData.length > 0" class="w-full">
          <td colspan="9" class="text-3xl text-center">There is no data</td>
        </tr>
        <tr v-else v-for="(item, index) in props.tableData" :key="index" class="relative">
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
            {{ item.quality_of_contact == 'N' ? '-' : item.quality_of_contact }}
          </td>
          <td>
            {{ (['H','A','W'].includes(item.quality_of_contact) && item.type_of_hit === 'TK') ? '—' : item.type_of_hit }}
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
              class="rounded-full hover:bg-white/15 p-2 transition-[background-color] ease-in duration-200"
            >
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
