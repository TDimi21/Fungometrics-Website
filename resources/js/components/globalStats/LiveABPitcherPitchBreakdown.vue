<script setup>
import {ref} from 'vue'
import TableSesionsTotalAB from './reusables/DataTableStadisticsAB.vue'
import { PrintCatcherData } from '@/components/shared'

const props = defineProps({
  team: {
    type: Object,
    default: () => {}
  },
  player: {
    type: Object,
    default: () => {}
  }
})

const keys = ref({
  player: [
    'player',
    "TOTAL",
    "FB %",
    "CH %",
    "CB %",
    "SL %",
    "OTHER %",
  ],
  team: [
    "TOTAL",
    "FB %",
    "CH %",
    "CB %",
    "SL %",
    "OTHER %",
  ]
})

const tableHeadings = [
  "player", "totals", 'FB', 'CH', 'CB', 'SL', 'OTHER'
]

const filter = ref('A')

const points = ref([])

const lastSelected = ref({})

const updatePoints = (item)=> {
  points.value = []
  for (const iterator of item['TOTAL-PITCH-LOCATION']) {
    if(filter.value != 'A'){
      if(iterator.type_throw == filter.value){
        points.value.push(iterator.pitch_mark)
      }
    } else{
      points.value.push(iterator.pitch_mark)
    }
  }

  lastSelected.value = item
}

const changeFilter = (value) => {
  filter.value = value
  updatePoints(lastSelected.value)
}

</script>
<template>
    <div class="px-[10%] md:px-[5%] py-3">
    <h1 class="text-fungo-red text-2xl text-center mt-9 mb-6 font-fungo-700">
      LiveAB Session - PITCHER - PITCH - BREAKDOWN
    </h1>
    <div class="grid grid-cols-1 lg:grid-cols-2">
      <div class="col-auto flex justify-center">
        <div class="mx-2 text-center flex-col">
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'A'? 'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('A')">All</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'FB'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('FB')">Fast Ball</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'CB'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('CB')">Curve Ball</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'CH'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('CH')">Change Up</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'SL'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('SL')">Slider</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'OTHER'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('OTHER')">Other</button>
        </div>
        <PrintCatcherData
        class="flex-col"
        :ballCoordinates="points"
        />
      </div>
      <div class="col-auto">
        <TableSesionsTotalAB :data="{
        players: props.player,
        team: props.team
        }"
        :headings="tableHeadings"
        :keyTeam="keys.team"
        :keyPlayer="keys.player"
        :isSelected="true"
        :selectedRow="lastSelected"
        v-on:selectedPlayer="updatePoints($event)"
        />
      </div>
    </div>
  </div>
</template>
