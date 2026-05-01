<script setup>
import {ref} from 'vue'
import TableSesionsTotalAB from './reusables/DataTableStadisticsAB.vue'

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
    "MAX-FB",
    "FB",
    "CH",
    "CB",
    'SL',
    'OTHER'
  ],
  team: [
    "MAX-FB",
    "FB",
    "CH",
    "CB",
    'SL',
    'OTHER'
  ]
})

const tableHeadings = [
  "player", 'MAX FB', 'FB', 'CH', 'CB', 'SL', 'OTHER'
]

const filter = ref('A')

const points = ref([])

const lastSelected = ref({})

const updatePoints = (item)=> {
  points.value = []
  let number = 0
  for (const iterator of item['TOTAL-PITCH-LOCATION']) {
    if(filter.value != 'A'){
      if(iterator.type_throw == filter.value){
        points.value.push({
          number: number,
          velocity: iterator.miles_per_hour,
          position: iterator.zone
        })
        number++
      }
    } else{
      points.value.push({
        number: number,
        velocity: iterator.miles_per_hour,
        position: iterator.zone
      })
      number++
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
      LiveAB Session - PITCHER - VELOCITY
    </h1>
    <div class="grid grid-cols-1 md:grid-cols-2">
      <div class="col-auto flex">
        <div class="mx-2 text-center flex-col">
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'A'? 'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('A')">All</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'FB'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('FB')">Fast Ball</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'CB'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('CB')">Curve Ball</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'CH'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('CH')">Change Up</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'SL'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('SL')">Slider</button>
          <button class="block w-32 px-4 rounded-lg py-2 border border-black mt-2" :class="filter == 'OTHER'?'bg-fungo-red text-white':''" type="button" v-on:click="changeFilter('OTHER')">Other</button>
          <div class="bg-white rounded-lg mt-2 text-left p-4">
            <div class="flex justify-between">
              <div>Strike</div>
              <div class="bg-red-500 mt-[2px] ml-2 w-4 h-4 rounded-full"></div>
            </div>
            <div class="flex justify-between">
              <div>Ball</div>
              <div class="bg-blue-500 mt-[2px] ml-2 w-4 h-4 rounded-full"></div>
            </div>
          </div>
        </div>
        <section class="px-[10%] md:px-[5%] mt-4 overflow-x-hidden overflow-y-scroll h-[500px]">
          <table class="w-full border-collapse space-y-6 text-fungo-darkblue">
            <thead class="bg-fungo-lightblue">
              <tr class="divide-x divide-[#000]">
                <th class="p-3 font-fungo-500 uppercase">
                  PITCH #
                </th>
                <th class="p-3 font-fungo-500 uppercase">
                  Velocity
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="Object.values(points).length == 0">
                <td colspan="16" class="text-fungo-darkblue text-sm text-center">No selected data</td>
              </tr>
              <tr v-else v-for="(item, index) in points">
                <td class="cursor-pointer text-center">
                  {{ item.number+1 ?? '?'}}
                </td>
                <td class="cursor-pointer text-center" :class="{
                  'bg-red-500 text-white': item.position == 'S',
                  'bg-red-500 text-white': item.position == 'B',
                  'bg-transparent': item.position == '',
                }">
                  {{ item.velocity ?? '?'}}
                </td>
              </tr>
            </tbody>
          </table>
        </section>
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
