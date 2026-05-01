<script setup>
import { ref, onMounted } from 'vue'
import { PrintFieldData } from '@/components/shared'
import { useGetPlayerAb } from '@/composables/useGetPlayerAb.js'

const props = defineProps({
  tableData: {
    type: Object,
    required: false,
    default: {}
  }
})

const { getPlayerInfo } = useGetPlayerAb()

const tableHeadings = ref([
  "fb", "sw/miss", "foul", "ld", "gb", "total", "player"
])
const buttonsGroup = ref([
  { text: 'All', typeHit: 'All' },
  { text: 'Left', typeHit: 'L' },
  { text: 'Middle', typeHit: 'C' },
  { text: 'Right', typeHit: 'R' },
])

const currentIndex = ref(0)
let coordinates = ref([])
const activeRow = ref('1')


const filterPointsByFirstRowTable = () => {
  coordinates.value = []
  activeRow.value = '1'
  getALlPitchMark()
}

const filterByTrajectory = (index, type) => {
  let toRecorring
  coordinates.value = []
  currentIndex.value = index

  if (activeRow.value == '1') {
    toRecorring = props.tableData['hitter-trajectory'].team_totals['SPRAY-PITCH-LOCATION']
    toRecorring.forEach(item => {
      if (item.field_direction !== null && item.field_direction.includes(type)) {
        coordinates.value.push({point: item.field_mark, feature: item.type_of_hit})
      }

      if (type == 'All') {
        coordinates.value.push({point: item.field_mark, feature: item.type_of_hit})
      }
    })

  } else {
    toRecorring = props.tableData['hitter-trajectory'].players[activeRow.value]

    toRecorring['SPRAY-PITCH-LOCATION'].forEach(item => {
      if (item.field_direction !== null && item.field_direction.includes(type)) {
        coordinates.value.push({point: item.field_mark, feature: item.type_of_hit})
      }

      if (type == 'All') {
        coordinates.value.push({point: item.field_mark, feature: item.type_of_hit})
      }
    })
  }
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['SPRAY-PITCH-LOCATION'].forEach(element => {
    coordinates.value.push({point: element.field_mark, feature: element.type_of_hit })
  })
}


const getALlPitchMark = () => {
  let toRecorring = props.tableData['hitter-trajectory'].team_totals['SPRAY-PITCH-LOCATION']

  if (toRecorring != 0) {
    toRecorring.forEach(element => {
      coordinates.value.push({point: element.field_mark, feature: element.type_of_hit })
    });
  }
}

onMounted(() => {
  getALlPitchMark()
})

</script>
<template>
  <section class="mt-5">
    <div class="grid grid-cols-3 bg-fungo-lightblue divide-x divide-[#000] text-center py-2">
      <div class="col-span-2">
        <p>Pitch Heat Map</p>
      </div>
      <div>
        directional breakdown
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-7 lg:gap-x-8 mt-10 gap-y-8">
      <div class="grid lg:grid-cols-1 bg-white rounded-[10px] h-min button-group px-7 py-9 gap-y-3">
        <p class="text-fungo-red">Select</p>
        <button
          v-for="(button, index) in buttonsGroup"
          :key="index"
          class="rounded-[5px] border border-fungo-darkblue py-1"
          @click="filterByTrajectory(index, button.typeHit)"
          :class="{'bg-fungo-red text-white border-fungo-red' : currentIndex === index}"
        >
          {{ button.text }}
        </button>
      </div>
      <div class="col-span-3 lg:col-span-1 xl:col-span-3 px-5">
        <PrintFieldData :fieldCoordinates="coordinates" typeOfCondition="trajectory"/>
      </div>

      <div class="pitch-table col-span-3 px-5 py-4">
        <table class="w-full space-y-6 text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="bg-white pb-4">
              <th class="ball-header purple"></th>
              <th class="ball-header white"></th>
              <th class="ball-header left"></th>
              <th class="ball-header middle"></th>
              <th class="ball-header right"></th>
            </tr>
            <tr>
              <th class="bg-white h-[10px]"></th>
            </tr>
            <tr class="divide-x divide-[#000]">
              <th v-for="(heading, index) in tableHeadings" :key="index"
                class="py-3 px-2 font-fungo-500 uppercase w-min">
                {{ heading }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              :class=" {'active-row text-white opacity-60' : activeRow == '1' } "
              @click="filterPointsByFirstRowTable"
            >
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-FB'] }}</td>
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-SM'] }}</td>
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-FOUL'] }}</td>
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-LD'] }}</td>
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-GB'] }}</td>
              <td>{{ props.tableData['hitter-trajectory'].team_totals['TOTAL-SWINGS'] }}</td>
              <td>Team</td>
            </tr>
            <tr
              v-for="(item, id) in props.tableData['hitter-trajectory'].players"
              :key="id"
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              @click=" filterPointsByRowTable(item, id) "
              :class=" {'active-row text-white opacity-60' : activeRow == id } "
              :id="id"
            >
              <td>{{ item['TOTAL-FB'] }}</td>
              <td>{{ item['TOTAL-SM'] }}</td>
              <td>{{ item['TOTAL-FOUL'] }}</td>
              <td>{{ item['TOTAL-LD'] }}</td>
              <td>{{ item['TOTAL-GB'] }}</td>
              <td>{{ item['TOTAL-SWINGS'] }}</td>
              <td>{{ getPlayerInfo(id).name.first }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
<style scoped>
.pitch-table {
  @apply rounded-[20px] bg-white;
  box-shadow: 0px 154.341px 216.189px #B9C9F3;
}
.button-group {
  box-shadow: 0px 154.341px 216.189px #B9C9F3;
}
.ball-header {
  background-repeat: no-repeat;
  background-size: contain;
  height: 25px;
  width: 25px;
  background-position: center;
}
.ball-header.left {
  background-image: url("@/assets/img/training/balltraining-green.svg");
}
.ball-header.middle {
  background-image: url("@/assets/img/training/balltraining.svg");
}
.ball-header.right {
  background-image: url("@/assets/img/training/balltraining-blue.svg");
}
.ball-header.white {
  background-image: url("@/assets/img/training/ball-white.svg");
}
.ball-header.purple {
  background-image: url("@/assets/img/training/ball-purple.svg");
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
.active-row {
  background-color: #0096C7 !important;
}
</style>