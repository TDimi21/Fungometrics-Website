<script setup>
import { ref, onMounted } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'
import { useRouter } from 'vue-router'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"
import { useTrainingStore } from "@/store/training";
import { storeToRefs } from 'pinia'
import { useLiveABStore } from '@/store/liveAB.js'

const props = defineProps({
  tableData: {
    type: Object,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  typeTrainig: {
    type: String,
    required: true
  }
})

const emit = defineEmits(["updateList"]);
const trainingStore = useTrainingStore()

const { axiosDelete, axiosGet } = useAxiosAuth()
const activeTraining = useTrainingStore()
const { livePitches } = storeToRefs(trainingStore)
const { setStatusPlayers, resetStatusPlayer } = useLiveABStore()

const practiceToDelte = ref(null)
const isOpenModal = ref(false)
// const newActiveLiveAB = ref()

const tableHeadings = ref([
  "ID", "PITCHER / NAME TEAM", "pitchers", "batter / NAME TEAM", "batters", "status", "START/RESUME", "STATS", "DELETE"
])

const deleteTeam = (id) => {
  isOpenModal.value = true
  practiceToDelte.value = id
}

const confirmDelete = async() => {
  try {
    await axiosDelete('training/', practiceToDelte.value).then(async(response) => {
      if (response.data) {

        toast.fire({
          icon: 'success',
          title: 'Practice deleted',
          text: 'Practice successfully deleted',
        })

        emit('updateList')
      }
    })
  } catch (error) {
    await toast.fire({
      icon: 'warning',
      title: 'Practice not deleted',
      text: 'Could not remove practice',
    })
  }

  isOpenModal.value = false

}

const router = useRouter()

// const resumenPractice = async(practiceId) => {

//   let newActiveLiveAB = props.tableData.find(item => item.id == practiceId)

//   let battings = []
//   let pitchers = []

//   newActiveLiveAB.players.batters.forEach(batter => {
//     batter.player.sort = batter.sort
//     battings.push(batter.player)
//   })

//   newActiveLiveAB.players.pitchers.forEach(pitcher => {
//     pitcher.player.sort = pitcher.sort
//     pitchers.push(pitcher.player)
//   })

//   router.push('/track/live')
//   newActiveLiveAB.players['batters'] = await battings
//   newActiveLiveAB.players['pitchers'] = await pitchers


//   await activeTraining.setDataTraining(newActiveLiveAB);
// }
const resumenPractice = (training) => {
  let newActiveTraining = training
  let playersBatters = []
  let playersPitchers = []
  // console.log(livePitches);
  // console.log('dd', newActiveTraining);
  axiosGet('statistics/'+training.id+'/liveab').then((response)=>{
    resetStatusPlayer()

    let playersStats = response.data.data.ball_x_ball
    livePitches.value = response.data.data.count
    // return
    newActiveTraining.players.batters.forEach(player => {
      playersBatters.push(player.player)
    });
    newActiveTraining.players.pitchers.forEach(player => {
      playersPitchers.push(player.player)
    });

    activeTraining.cleanListPlayer()

    const idCount = {}
    const matchingElements = [];

    playersStats.forEach(obj => {
      const batterId = obj.batting.batter_id;
      const pitcherId = obj.pitching.pitcher_id;
      const idPair = `${batterId}-${pitcherId}`;

      if (idCount[idPair]) {
        idCount[idPair]++;
      } else {
        idCount[idPair] = 1;
      }

      matchingElements.push(obj)
    });

    matchingElements.forEach(obj => {
        const batterId = obj.batting.batter_id;
        const pitcherId = obj.pitching.pitcher_id;
        const idPair = `${batterId}-${pitcherId}`;
        const count = idCount[idPair];
        setStatusPlayers( pitcherId, batterId, count)
    });

    /* remove posible counter state of ball and strile */
    localStorage.removeItem('countBall')
    localStorage.removeItem('countStrike')

    newActiveTraining.players['batters'] = playersBatters
    newActiveTraining.players['pitchers'] = playersPitchers
    // delete newActiveTraining.lineup
    activeTraining.setDataTraining(newActiveTraining);
    router.push('/track/live')
  })
}

const statsLiveAB = (practiceId) => {
  let newActiveLiveAB = props.tableData.find(item => item.id == practiceId)

  activeTraining.setDataTraining(newActiveLiveAB);
  router.push({ name: 'training.statsLiveAB', params: { 'id': newActiveLiveAB.id } })
}
</script>

<template>
  <section class="px-[10%] md:px-[5%] mt-[250px] lg:mt-[140px] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <th
            v-for="(heading, index) in tableHeadings"
            :key="index"
            class="py-3 font-fungo-500"
          >
            {{ heading }}
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="props.isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!props.tableData.length > 0">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <tr
          v-else
          v-for="(item, index) in props.tableData"
          :key="index"
          class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
        >
          <td>{{ Number.parseInt(index, 10) + 1 }}</td>

          <td class="w-[140px] max-w-[140px] flex flex-col">
            <img :src="item.teams[0].logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
            <p>{{ item.teams[0].name }}</p>
          </td>

          <td class="w-[270px] max-w-[270px] group relative">
            <p class="truncate">
              <span v-for="(player, playerIndex) in item.players.pitchers" :key="playerIndex">
                {{
                  item.players.pitchers.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ', '
                }}
              </span>

              <span class="tooltip">
                <label v-for="(player, playerIndex) in item.players.pitchers" :key="playerIndex">
                  {{
                    item.players.pitchers === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
            </p>
          </td>

          <td class="w-[140px] max-w-[140px] flex flex-col">
            <img :src="item.teams[1].logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
            <p>{{ item.teams[1].name }}</p>
          </td>

          <td class="w-[270px] max-w-[270px] group relative">
            <p class="truncate">
              <span v-for="(player, playerIndex) in item.players.batters" :key="playerIndex">
                {{
                  item.players.batters.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ', '
                }}
              </span>

              <span class="tooltip">
                <label v-for="(player, playerIndex) in item.players.batters" :key="playerIndex">
                  {{
                    item.players.batters === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
            </p>
          </td>


          <td class="w-[200px] max-w-[200px]">
            <progress
              max="100"
              :value="item.is_completed ? 100 : 50"
              class="rounded overflow-hidden h-[7px]"
              :class="{ 'in-proress' : !item.is_completed, 'completed' : item.is_completed }"
            >
            </progress>
          </td>

          <td class="w-[150px] max-w-[150px]">
            <button
              @click.prevent="resumenPractice(item)"
              :class="{'hidden' : item.is_completed}"
            >
              <TableStart />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button
              @click.prevent="statsLiveAB(item.id)"
              class="hover:bg-fungo-gray3 rounded-full p-2"
            >
              <TableStats />
            </button>
          </td>

          <td class="w-[80px] max-w[80px]">
            <button
              @click="deleteTeam(item.id)"
            >
              <TableCancel />
            </button>
          </td>

        </tr>
      </tbody>
    </table>
  </section>

  <Modal
    modalTitle="Confirm delete"
    :isOpen="isOpenModal"
  >
    <template #content>
      <div>
        <p>Are you sure to delete this training?</p>
      </div>
    </template>
    <template #actions>
        <div class="flex justify-between items-center w-90% mx-auto">
          <button
            @click="confirmDelete"
            class="bg-red-500 text-white px-4 py-1 rounded-md"
          >
            Yes, delete
          </button>

          <button
            @click=" isOpenModal = false"
            class="bg-fungo-lightblue px-4 py-1 rounded-md"
          >
            Cancel
          </button>

        </div>
      </template>
  </Modal>
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
</style>
