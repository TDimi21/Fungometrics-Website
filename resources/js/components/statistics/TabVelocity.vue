<script setup>
import { ref, onMounted, computed } from 'vue'
import { useTeamStore } from "@/store/team"

const props = defineProps({
  VelocityData: {
    type: Object,
    required: false,
    default: {}
  }
})

const { team } = useTeamStore()

const playerVelocities = ref([])

const tableOneHeadings = ref([
  'SWING #', 'VELOCITY'
])

const tableTwoHeadings = ref([
  'ld', 'fb', 'pf', 'GB', 'max ev', 'player'
])

const buttonsGroup = ref([
  { text: 'All', typeHit: 'All' },
  { text: 'Ground', typeHit: 'GB' },
  { text: 'Pop fly', typeHit: 'PF' },
  { text: 'Fly ball', typeHit: 'FB' },
  { text: 'Line drive', typeHit: 'LD' }
])

const currentIndex = ref(0)
const activeRow = ref('1')

const filterBytrajecotry = (index, typeHit) => {
  playerVelocities.value = []
  currentIndex.value = index

  Object.values(props.VelocityData).forEach(player => {

    player.forEach(track => {
      if (activeRow.value == 1) {
        if (track.type_of_hit === typeHit && typeHit !== 'All') {
          playerVelocities.value.push(track)
        } else if (typeHit === 'All') {
          playerVelocities.value.push(track)
        }
      }

      if (activeRow.value == track.batter_id) {
        if (track.type_of_hit === typeHit && typeHit !== 'All') {
          playerVelocities.value.push(track)
        } else if (typeHit === 'All') {
          playerVelocities.value.push(track)
        }
      }
    })
  })

  if (playerVelocities.value.length > 0) {
    playerVelocities.value.sort((a, b) => a.sort - b.sort)
  }
}

const filterRowByTrajectory = computed(() => (allContact, trajectory) => {
  let velocities = 0
  let counter = 0

  allContact.filter(item => {
    if (item.type_of_hit.includes(trajectory)) {
      if (item.velocity != 0) {
        velocities += item.velocity
        counter++
      }
    }
  })
  if (counter != 0) {
    velocities = velocities / counter
  }

  return (velocities).toFixed(2)
})

const getMaxTrajectory = (allContact) => {
  let max = 0
  allContact.forEach(element => {
    if (max < element.velocity) {
      max = element.velocity
    }
  })

  return (max).toFixed(2)
}

const getMaxTrajectoryOfTeam = () => {
  let max = 0
  Object.values(props.VelocityData).forEach(item => {
    item.forEach(element => {
      if (max < element.velocity) {
        max = element.velocity
      }
    })
  })

  return (max).toFixed(2)
}

const filterByFirstRowTable = () => {
  activeRow.value = '1'
  playerVelocities.value = []

  Object.values(props.VelocityData).forEach(item => {
    item.forEach(player => {
      playerVelocities.value.push(player)
    })
  })
}

const contactAllLocationCount = computed(() => (allContact, trajectory) => {
  let velocities = 0
  let counter = 0

  Object.values(allContact).forEach(contact => {
    contact.filter(item => {
      if (item.type_of_hit === trajectory) {
        if (item.velocity != 0) {
          velocities += item.velocity
          counter++
        }
      }
    })
  })

  if (counter != 0) {
    velocities = velocities / counter
  }

  return velocities.toFixed(2)
})

const filterByPlayer = (player, id) => {
  activeRow.value = id
  playerVelocities.value = player
}

onMounted(() => {
  Object.values(props.VelocityData).forEach(item => {
    item.forEach(player => {
      playerVelocities.value.push(player)
    })
  })

  if (playerVelocities.value.length > 0) {
    playerVelocities.value.sort((a, b) => a.sort - b.sort)
  }
})
</script>
<template>
  <section class="mt-5">
    <div class="grid grid-cols-3 bg-fungo-lightblue divide-x divide-[#000] text-center py-2 uppercase">
      <div class="col-span-2">
        <p>LIST OF VELOCITIES</p>
      </div>
      <div>
        VELOCITY BREAKDOWN
      </div>
    </div>

    <div class="grid grid-cols-1 mt-10 lg:grid-cols-2 xl:grid-cols-7 lg:gap-x-8 gap-y-8">
      <div class="grid lg:grid-cols-1 bg-white rounded-[10px] h-min button-group px-7 py-9 gap-y-3">
        <p class="text-fungo-red">Select</p>
        <button v-for="(button, index) in buttonsGroup" :key="index"
          class="rounded-[5px] border border-fungo-darkblue py-1" @click="filterBytrajecotry(index, button.typeHit)"
          :class="{ 'bg-fungo-red text-white border-fungo-red': currentIndex === index }">
          {{ button.text }}
        </button>
      </div>
      <div class="col-span-3 px-5 py-4 pitch-table">
        <table class="w-full border-collapse text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="divide-x divide-[#000]">
              <th v-for="(heading, index) in tableOneHeadings" :key="index"
                class="px-2 py-3 uppercase font-fungo-500 w-min">
                {{ heading }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(velocity, id) in playerVelocities" :key="id" class="relative bg-white even:bg-fungo-gray4">
              <td>{{ velocity.sort + 1 }}</td>
              <td>{{ velocity.velocity }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="col-span-3 px-5 py-4 pitch-table">
        <table class="w-full border-collapse text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="divide-x divide-[#000]">
              <th v-for="(heading, index) in tableTwoHeadings" :key="index"
                class="px-2 py-3 uppercase font-fungo-500 w-min">
                {{ heading }}
              </th>
            </tr>
          </thead>

          <tbody>
            <tr class="relative bg-white cursor-pointer even:bg-fungo-gray4"
              :class="{ 'active-row text-white opacity-60': activeRow == '1' }" @click="filterByFirstRowTable">
              <td>
                {{ contactAllLocationCount(props.VelocityData, 'LD') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.VelocityData, 'FB') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.VelocityData, 'PF') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.VelocityData, 'GB') }}
              </td>
              <td>
                {{ getMaxTrajectoryOfTeam() }}
              </td>
              <td>
                {{ team.name }}
              </td>
            </tr>
            <tr v-for="(velocity, id) in VelocityData" :key="id"
              class="relative bg-white cursor-pointer even:bg-fungo-gray4"
              :class="{ 'active-row text-white opacity-60': activeRow == id }" @click="filterByPlayer(velocity, id)">
              <td>{{ filterRowByTrajectory(velocity, 'LD') }}</td>
              <td>{{ filterRowByTrajectory(velocity, 'FB') }}</td>
              <td>{{ filterRowByTrajectory(velocity, 'PF') }}</td>
              <td>{{ filterRowByTrajectory(velocity, 'GB') }}</td>
              <td>{{ getMaxTrajectory(velocity) }}</td>
              <td>{{ velocity[0].profile.first_name }}</td>
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
