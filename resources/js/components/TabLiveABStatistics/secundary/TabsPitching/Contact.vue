<script setup>
import { ref, onMounted } from 'vue'
import { PrintCatcherData } from '@/components/shared'
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
  "Other", "sl", "cv", "ch", "fb", "total", "player"
])
const buttonsGroup = ref([
  { text: 'Ground ball', typeHit: 'GB' },
  { text: 'Line drive', typeHit: 'LD' },
  { text: 'Fly ball', typeHit: 'FB' },
  { text: 'Foul', typeHit: 'F' },
  { text: 'Swing / Miss', typeHit: 'SM' },
  { text: 'Take', typeHit: 'TK' },
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
    toRecorring = props.tableData['pitcher-contact'].team_totals['TOTAL-PITCH-LOCATION']
    toRecorring.forEach(item => {
      if (item.trajectory === type) {
        coordinates.value.push(item.pitch_mark)
      }

      if (type == 'All') {
        coordinates.value.push(item.pitch_mark)
      }
    })

  } else {
    toRecorring = props.tableData['pitcher-contact'].players[activeRow.value]

    toRecorring['TOTAL-PITCH-LOCATION'].forEach(item => {
      if (item.trajectory === type) {
        coordinates.value.push(item.pitch_mark)
      }

      if (type == 'All') {
        coordinates.value.push(item.pitch_mark)
      }
    })
  }
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player['TOTAL-PITCH-LOCATION'].forEach(element => {
    coordinates.value.push(element.pitch_mark)
  })
}

const getALlPitchMark = () => {
  let toRecorring = props.tableData['pitcher-contact'].team_totals['TOTAL-PITCH-LOCATION']

  if (toRecorring != 0) {
    toRecorring.forEach(element => {
      coordinates.value.push(element.pitch_mark)
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
        <PrintCatcherData :ballCoordinates="coordinates"/>
      </div>

      <div class="pitch-table col-span-3 px-5 py-4">
        <table class="w-full space-y-6 text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="bg-white pb-4">
              <th class="ball-header orange"></th>
              <th class="ball-header middle"></th>
              <th class="ball-header left"></th>
              <th class="ball-header purple"></th>
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
              <td>{{ props.tableData['pitcher-contact'].team_totals.OTHER }}</td>
              <td>{{ props.tableData['pitcher-contact'].team_totals.SL }}</td>
              <td>{{ props.tableData['pitcher-contact'].team_totals.CB }}</td>
              <td>{{ props.tableData['pitcher-contact'].team_totals.CH }}</td>
              <td>{{ props.tableData['pitcher-contact'].team_totals.FB }}</td>
              <td>{{ props.tableData['pitcher-contact'].team_totals.TOTAL }}</td>
              <td>Team</td>
            </tr>
            <tr
              v-for="(item, id) in props.tableData['pitcher-contact'].players"
              :key="id"
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              @click=" filterPointsByRowTable(item, id) "
              :class=" {'active-row text-white opacity-60' : activeRow == id } "
              :id="id"
            >
              <td>{{ item.OTHER }}</td>
              <td>{{ item.SL }}</td>
              <td>{{ item.CB }}</td>
              <td>{{ item.CH }}</td>
              <td>{{ item.FB }}</td>
              <td>{{ item.TOTAL }}</td>
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
.ball-header.orange {
  background-image: url("@/assets/img/training/ball-orange.svg");
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