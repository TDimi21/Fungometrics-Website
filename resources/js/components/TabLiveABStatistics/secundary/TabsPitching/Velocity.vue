<script setup>
import { ref, onMounted } from 'vue'
import { useTeamStore } from "@/store/team"

const props = defineProps({
  tableData: {
    type: Object,
    required: false,
    default: {}
  }
})

const { team } = useTeamStore()

const playerVelocities = ref([])

const tableOneHeadings = ref([
  'Pitch #', 'VELOCITY'
])

const tableTwoHeadings = ref([
  "Other", "sl", "cv", "ch", "fb", "total", "player"
])

const buttonsGroup = ref([
  { text: 'All', typeHit: 'All' },
  { text: 'Fast ball', typeHit: 'FB' },
  { text: 'Curve ball', typeHit: 'CB' },
  { text: 'Change up', typeHit: 'CH' },
  { text: 'Slider', typeHit: 'SL' },
  { text: 'OTHER', typeHit: 'OTHER' },
])

const currentIndex = ref(0)
const activeRow = ref('1')

const filterBytrajecotry = (index, typeHit) => {
  playerVelocities.value = []
  currentIndex.value = index

  Object.values(props.tableData).forEach(player => {

    player.forEach(track => {
        if (track.pitching.type_throw === typeHit && typeHit !== 'All') {
          playerVelocities.value.push(track)
        } else if (typeHit === 'All') {
          playerVelocities.value.push(track)
        }
    })
  })
}

const filterRowByVelocity = (allContact, trajectory) => {
  let counter = allContact.filter(item => item.pitching.type_throw.includes(trajectory)).length

  return counter
}

const getMaxTrajectoryOfTeam = () => {
  let max = 0

  Object.values(props.tableData).forEach(item => {
    max += item.length
  })

  return max
}

const filterByFirstRowTable = () => {
  activeRow.value = '1'
  playerVelocities.value = []

  Object.values(props.tableData).forEach(item => {
    item.forEach(player => {
      playerVelocities.value.push(player)
    })
  })
}

const contactAllLocationCount = (allContact, trajectory) => {
  let counter = 0

  Object.values(allContact).forEach(contact => {
    contact.forEach(item => {
      if (item.pitching.type_throw.includes(trajectory)) {
        counter++
      }
    })
  })

  return counter
}

const filterByPlayer = (player, id) => {
  activeRow.value = id
  playerVelocities.value = player.filter(item => item.pitching.miles_per_hour !== 0)
}

onMounted(() => {
  Object.values(props.tableData).forEach(item => {
    item.forEach(player => {
      if (player.pitching.miles_per_hour !== 0) playerVelocities.value.push(player)
    })
  })
})
</script>
<template>
  <section class="mt-5">
    <div class="grid grid-cols-3 bg-fungo-lightblue divide-x divide-[#000] text-center py-2 uppercase">
      <div class="col-span-2">
        <p>List of Velocities</p>
      </div>
      <div>
        VELOCITY BREAKDOWN
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-7 lg:gap-x-8 mt-10 gap-y-8">
      <div class="grid lg:grid-cols-1 bg-white rounded-[10px] h-min button-group px-7 py-9 gap-y-3">
        <div class="flex flex-col space-y-3 mb-4">
          <article class="flex row border-l-4 border-fungo-blue3 items-center space-x-2 bg-fungo-gray2 w-full py-1 rounded-md justify-center">
            <span>Strike</span>
            <img src="@/assets/img/training/ball-red.svg" alt="ball blue">
          </article>

          <article class="flex row border-l-4 border-fungo-dark-gray items-center space-x-2 bg-fungo-gray2 w-full py-1 rounded-md justify-center">
            <span>Ball</span>
            <img src="@/assets/img/training/balltraining-blue.svg" alt="ball blue">
          </article>
        </div>
        <p class="text-fungo-red">Select</p>
        <button v-for="(button, index) in buttonsGroup" :key="index"
          class="rounded-[5px] border border-fungo-darkblue py-1" @click="filterBytrajecotry(index, button.typeHit)"
          :class="{ 'bg-fungo-red text-white border-fungo-red': currentIndex === index }">
          {{ button.text }}
        </button>
      </div>
      <div class="pitch-table col-span-3 px-5 py-4">
        <table class="w-full border-collapse text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="divide-x divide-[#000]">
              <th v-for="(heading, index) in tableOneHeadings" :key="index"
                class="py-3 px-2 font-fungo-500 uppercase w-min">
                {{ heading }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(velocity, id) in playerVelocities"
              :key="id"
              class="bg-white even:bg-fungo-gray4 relative"
              :class="[velocity.is_strike ? 'text-red-500' : 'text-fungo-blue2']"
            >
              <td>{{ velocity.sort + 1 }}</td>
              <td>{{ velocity.pitching.miles_per_hour }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pitch-table col-span-3 px-5 py-4">
        <table class="w-full border-collapse text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="divide-x divide-[#000]">
              <th v-for="(heading, index) in tableTwoHeadings" :key="index"
                class="py-3 px-2 font-fungo-500 uppercase w-min">
                {{ heading }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              :class=" {'active-row text-white opacity-60' : activeRow == '1' } "
              @click="filterByFirstRowTable"
            >
              <td >
                {{ contactAllLocationCount(props.tableData, 'OTHER') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.tableData, 'SL') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.tableData, 'CB') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.tableData, 'CH') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.tableData, 'FB') }}
              </td>
              <td>
                {{ getMaxTrajectoryOfTeam() }}
              </td>
              <td>
                {{ team.name }}
              </td>
            </tr>
            <tr v-for="(velocity, id) in tableData" :key="id"
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              :id="id"
              :class="{ 'active-row text-white opacity-60': activeRow == id }"
              @click="filterByPlayer(velocity, id)">
              <td>{{ filterRowByVelocity(velocity, 'OTHER') }}</td>
              <td>{{ filterRowByVelocity(velocity, 'SL') }}</td>
              <td>{{ filterRowByVelocity(velocity, 'CB') }}</td>
              <td>{{ filterRowByVelocity(velocity, 'CH') }}</td>
              <td>{{ filterRowByVelocity(velocity, 'FB') }}</td>
              <td>{{ velocity.length }}</td>
              <td>{{ velocity[0].pitching.profile.first_name }}</td>
              
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

.ball-header.foul {
  background-image: url("../../assets/img/login/assteslogin/ballbutton.svg");
}

.ball-header.weack {
  background-image: url("../../assets/img/training/balltraining-green.svg");
}

.ball-header.average {
  background-image: url("../../assets/img/training/balltraining.svg");
}

.ball-header.hard {
  background-image: url("../../assets/img/training/balltraining-blue.svg");
}

.active-row {
  background-color: #0096C7 !important;
}
</style>