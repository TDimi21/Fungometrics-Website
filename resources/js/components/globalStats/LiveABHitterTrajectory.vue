<script setup>
import {ref} from 'vue'
import TableSesionsTotalAB from './reusables/DataTableStadisticsAB.vue'
import { PrintFieldData } from '@/components/shared'

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
    "TOTAL-SWINGS",
    "TOTAL-SM",
    "TOTAL-FOUL",
    "TOTAL-LD",
    "TOTAL-GB",
    "TOTAL-FB",
  ],
  team: [
    "TOTAL-SWINGS",
    "TOTAL-SM",
    "TOTAL-FOUL",
    "TOTAL-LD",
    "TOTAL-GB",
    "TOTAL-FB",
  ]
})

const tableHeadings = [
  "player", 'SWINGS',"SW/Miss", 'FOUL', 'LD', 'GB', 'FB',
]

const filter = ref('A')

const points = ref([])

const lastSelected = ref({})

const updatePoints = (item)=> {
  points.value = []
  for (const iterator of item['SPRAY-PITCH-LOCATION']) {
    if(filter.value != 'A'){
      if(iterator.field_direction != null){
        if(iterator.field_direction[0] == filter.value){
          points.value.push({
            point: iterator.field_mark,
            feature: iterator.type_of_hit
          })
        }
      }
    } else{
      points.value.push({
        point: iterator.field_mark,
        feature: iterator.type_of_hit
      })
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
      LiveAB Session - HITTER - TRAJECTORY
    </h1>
    <div class="grid grid-cols-1 lg:grid-cols-2">
      <div class="col-auto flex justify-center">
        <div class="mx-2 text-center flex-col">
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'A'? 'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('A')">All</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'L'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('L')">Left</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'C'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('C')">Middle</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'R'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('R')">Right</button>
        </div>
        <PrintFieldData
        class="flex-col"
        :fieldCoordinates="points"
        typeOfCondition="trajectory"
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
