<script setup>
import { ref, onMounted } from 'vue'
import { PrintFieldData } from '@/components/shared'
import { useTeamStore } from "@/store/team"

const props = defineProps({
  contactData: {
    type: Object,
    required: false,
    default: {}
  }
})

const { team } = useTeamStore()

let coordinates = ref([])
const activeRow = ref('1')
const teamTotalSwings = ref(0)

const tableHeadings = ref([
  "miss foul", "weak", "average", "hard", "swings", "player"
])
const buttonsGroup = ref([
  { text: 'All', typeHit: 'All' },
  { text: 'Ground', typeHit: 'GB' },
  { text: 'Pop fly', typeHit: 'PF' },
  { text: 'Fly ball', typeHit: 'FB' },
  { text: 'Line drive', typeHit: 'LD' }
])

const currentIndex = ref(0)

const filterRowByContact = (allContact, contactQuality) => {
  return allContact.filter(item => item.quality_of_contact?.includes(contactQuality)).length
}

const filterPointsByRowTable = (player, id) => {
  coordinates.value = []
  activeRow.value = id
  player.forEach(element => {
    coordinates.value.push({point: element.field_mark, feature: element.quality_of_contact})
  })
}


const contactAllLocationCount = (allContact, contactQuality) => {
  let counter = 0

  Object.values(allContact).forEach(contact => {
    contact.forEach(item => {
      if (item.quality_of_contact?.includes(contactQuality)) {
        counter++
      }
    })
  })

  return counter
}

const filterPointsByFirstRowTable = () => {
  coordinates.value = []
  activeRow.value = '1'
  teamTotalSwings.value = 0

  Object.values(props.contactData).forEach(item => {
    teamTotalSwings.value += item.length
    
    item.forEach(location => {
      coordinates.value.push({point: location.field_mark, feature: location.quality_of_contact})
    })
  })
}

const filterByTrajectory = (index, type) => {
  coordinates.value = []
  currentIndex.value = index

  Object.values(props.contactData).forEach(player => {

    player.forEach( track => {
      if (activeRow.value == 1) {
        if(track.type_of_hit === type && type !== 'All'){
          coordinates.value.push({point: track.field_mark, feature: track.quality_of_contact})
        } else if(type === 'All') {
          coordinates.value.push({point: track.field_mark, feature: track.quality_of_contact})
        }
      }

      if (activeRow.value == track.batter_id) {
        if(track.type_of_hit === type && type !== 'All'){
          coordinates.value.push({point: track.field_mark, feature: track.quality_of_contact})
        } else if(type === 'All') {
          coordinates.value.push({point: track.field_mark, feature: track.quality_of_contact})
        }
      }

    })
  })
}

onMounted(() => {
  Object.values(props.contactData).forEach(item => {
    teamTotalSwings.value += item.length
    
    item.forEach(location => {
      coordinates.value.push({point: location.field_mark, feature: location.quality_of_contact})
    })
  })
})
</script>
<template>
  <section class="mt-5">
    <div class="grid grid-cols-3 bg-fungo-lightblue divide-x divide-[#000] text-center py-2 uppercase">
      <div class="col-span-2">
        <p>SPRAY CHART  </p>
      </div>
      <div>
        QUALITY OF CONTACT BREAKDOWN
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
        <PrintFieldData
          :fieldCoordinates="coordinates"
          typeOfCondition="qtyContact"
        />
      </div>

      <div class="pitch-table col-span-3 px-5 py-4">
        <table class="w-full space-y-6 text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
            <tr class="bg-white pb-4">
              <th class="ball-header foul"></th>
              <th class="ball-header weack"></th>
              <th class="ball-header average"></th>
              <th class="ball-header hard"></th>
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
              <td>
                {{ contactAllLocationCount(props.contactData, 'MF') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.contactData, 'W') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.contactData, 'A') }}
              </td>
              <td>
                {{ contactAllLocationCount(props.contactData, 'H') }}
              </td>
              <td>
                {{ teamTotalSwings }}
              </td>
              <td>
                {{ team.name }}
              </td>
            </tr>
            <tr
              v-for="(contact, id) in props.contactData"
              :key="id"
              class="bg-white even:bg-fungo-gray4 relative cursor-pointer"
              :class=" {'active-row text-white opacity-60' : activeRow == id } "
              @click="filterPointsByRowTable(contact, id)"
            >
              <td> {{ filterRowByContact(contact, 'MF') }} </td>
              <td> {{ filterRowByContact(contact, 'W') }} </td>
              <td> {{ filterRowByContact(contact, 'A') }} </td>
              <td> {{ filterRowByContact(contact, 'H') }} </td>
              <td> {{ contact.length }}</td>
              <td> {{ contact[0].profile.first_name }} </td>
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