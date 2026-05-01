<script setup>
import { onMounted, onUpdated, ref } from 'vue'
import { TableEdit } from '@/components/icons'
import { useRouter } from 'vue-router'
import { isObject } from '@vue/shared';
import {useTrainingStore} from "../../store/training";

const training = useTrainingStore()
defineProps({
  headings: {
    type: Array,
    required: true,
  },
  tableData: {
    type: Array,
    required: true
  },
  tableDataTeam: {
    type: Array,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  isSorteable : {
    type: Boolean,
    default: false,
  },
  actionable: {
    type: Boolean,
    default: false,
  },
  have_team: {
    type: Boolean,
    default: false,
  },
  activeRow: {
    type: Number,
    required: false
  },
})
const router = useRouter()

console.log(training.selectedRowID);
</script>

<template>
  <section class="mt-[3px] lg:mt-[3px] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="uppercase divide-x divide-[#000]">
          <th
            v-for="(heading, index) in headings"
            :key="index"
            :class="isSorteable ? 'grid-flow-row py-3 px-2 font-fungo-500 cursor-pointer ': 'py-3 px-2 font-fungo-500'"
            v-on:click="$emit('click-header', heading)">
            <div class="flex justify-center">
              {{ heading }} <img v-if="isSorteable" src="@/assets/img/icons/sort-solid.svg" alt="sort data" class="w-3 ml-2">
            </div>
          </th>
          <th v-if="actionable">ACTION</th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <template v-else>
          <tr v-if="have_team" v-for="(item, index) in tableDataTeam"  v-on:click="$emit('clicked-data', {item, index})" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative">
            <td class="min-w-[10px] max-w-[150px]" :class="activeRow == index ? 'bg-fungo-blue3' : ''" v-for="obj , key in item" v-html="obj">
            </td>
          </tr>
          <tr v-for="(item, index) in tableData"  v-on:click="$emit('clicked-data', {item, index, 'player': 'player'})" class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative" :class="activeRow == index+1 ? '!bg-fungo-blue3' : ''">
            <template v-for="obj , key in item">
              
              <td class="min-w-[10px] max-w-[90px]" 
                v-if="'idPlayer' !== key"
              v-html="obj"></td>
            </template>
            <td class="w min-w-[10px] max-w-[90px] flex justify-items-center" v-if="actionable">
              <TableEdit class=" cursor-pointer" v-on:click="$emit('edit-event', item )"/>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </section>
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

/* .active-row {
  background-color: #0096C7 !important;
} */

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

.select-row {
  @apply bg-fungo-lightblue-hover !important;
}
</style>
