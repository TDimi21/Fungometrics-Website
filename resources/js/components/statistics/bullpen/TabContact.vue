<script setup>
import { ref, onMounted } from 'vue'
import { PrintCatcherBullpen } from '@/components/shared'
import { useTeamStore } from "@/store/team";

const props = defineProps({
  breakdownData: {
    type: Object,
    required: false,
    default: {}
  }
})

const { team } = useTeamStore();

const tableHeadings = ref([
  "other", "sl", "cv", "ch", "fb", "total", "player"
])
const buttonsGroup = ref([
  { text: 'Ground ball', typeHit: 'GB' },
  { text: 'Line drive', typeHit: 'LD' },
  { text: 'Fly ball', typeHit: 'FB' },
  { text: 'Foul', typeHit: 'F' },
  { text: 'Swing / Miss', typeHit: 'SM' },
  { text: 'Take', typeHit: 'TK' }
])

const currentIndex = ref(0)
let coordinates = ref([])
const activeRow = ref('1')
const teamTotalSwings = ref(0)

const getPercentByPitch = (allLocation, typeThrow) => {
  return allLocation.filter(item => item.type_throw?.includes(typeThrow)).length
}

const getPercentByAllPitch = (allContact, trajectory) => {
  let counter = 0
  Object.values(allContact).forEach(contact => {
    contact.forEach(item => {
      if (item.type_throw?.includes(trajectory)) {
        counter++
      }
    })
  })

  return counter
}

const filterByTrajectory = (index, type) => {
  coordinates.value = []
  currentIndex.value = index

  Object.values(props.breakdownData).forEach(player => {
    player.forEach( track => {
      if (activeRow.value == 1) {
        if(track.trajectory === type){
          // coordinates.value.push(track.pitch_mark)
          coordinates.value.push({point: track.pitch_mark, feature: track.type_throw})
        } else if(type === 'All') {
          // coordinates.value.push(track.pitch_mark)
          coordinates.value.push({point: track.pitch_mark, feature: track.type_throw})
        }
      }

      if (activeRow.value == track.pitcher_id) {
        if(track.trajectory === type){
          // coordinates.value.push(track.pitch_mark)
          coordinates.value.push({point: track.pitch_mark, feature: track.type_throw})
        } else if(type === 'All') {
          // coordinates.value.push(track.pitch_mark)
          coordinates.value.push({point: track.pitch_mark, feature: track.type_throw})
        }
      }
    })
  })
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player.forEach(element => {
    // coordinates.value.push(element.pitch_mark)
    coordinates.value.push({point: element.pitch_mark, feature: element.type_throw})
  })
}

const filterPointsByFirstRowTable = () => {
  coordinates.value = []
  activeRow.value = '1'
  teamTotalSwings.value = 0
  
  Object.values(props.breakdownData).forEach(item => {
    teamTotalSwings.value += item.length
    
    item.forEach(location => {
      // coordinates.value.push(location.pitch_mark)
      coordinates.value.push({point: location.pitch_mark, feature: location.type_throw})
    })
  })
}


onMounted(() => {
  Object.values(props.breakdownData).forEach(item => {
    teamTotalSwings.value += item.length
    
    item.forEach(location => {
      // coordinates.value.push(location.pitch_mark)
      coordinates.value.push({point: location.pitch_mark, feature: location.type_throw})
    })
  })
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
        <PrintCatcherBullpen :ballCoordinates="coordinates" typeOfCondition="bullpenColor"/>
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
              <td >
                {{ getPercentByAllPitch(props.breakdownData, 'OTHER') }}
              </td>
              <td>
                {{ getPercentByAllPitch(props.breakdownData, 'SL') }}
              </td>
              <td>
                {{ getPercentByAllPitch(props.breakdownData, 'CB') }}
              </td>
              <td>
                {{ getPercentByAllPitch(props.breakdownData, 'CH') }}
              </td>
              <td>
                {{ getPercentByAllPitch(props.breakdownData, 'FB') }}
              </td>
              <td>
                {{ teamTotalSwings }}
              </td>
              <td>
                {{ team.name }}
              </td>
            </tr>
            <tr
              v-for="(item, id) in props.breakdownData"
              :key="id"
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              @click=" filterPointsByRowTable(item, id) "
              :class=" {'active-row text-white opacity-60' : activeRow == id } "
              :id="id"
            >
              <td >
                {{ getPercentByPitch(item, 'OTHER') }}
              </td>
              <td>
               {{ getPercentByPitch(item, 'SL') }}
              </td>
              <td>
                {{ getPercentByPitch(item, 'CB') }}
              </td>
              <td>
                {{ getPercentByPitch(item, 'CH') }}
              </td>
              <td>
                {{ getPercentByPitch(item, 'FB') }}
              </td>
              <td>
                {{ item.length }}
              </td>
              <td>
                {{ item[0].profile.first_name }}
              </td>
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