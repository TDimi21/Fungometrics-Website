<script setup>
import { ref } from 'vue'
import { TableStats, TableCancel, TableStart } from '@/components/icons'
import { useRouter } from 'vue-router'
import {useTrainingStore} from "../../store/training";
import {Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot} from '@headlessui/vue'
import { Modal } from '@/components/shared'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import {toast} from "@/utils/AlertPlugin"

const emit = defineEmits(["updateList"]);

const { axiosDelete } = useAxiosAuth()

const practiceToDelte = ref(null)
const isOpenModal = ref(false)

const activeTraining = useTrainingStore()

const {axiosGet} = useAxiosAuth()

defineProps({
  tableData: {
    type: Object,
    required: true
  },
  teamData: {
    type: Object,
    required: true
  },
  isLoading: {
    type: Boolean,
    required: true
  },
  typeUser: {
    type: String,
    required: false,
    default: 'c'
  }
})

const tableHeadings = ref([
  "ID", "TEAM LOGO", "TEAM NAME", "BATTERS", "NOTES", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])
const tableHeadingsPlayer = ref([
  "ID", "TEAM / PLAYER", "NOTES", "STATUS/CREATED AT", "START/RESUME", "STATS", "DELETE"
])

const isOpen = ref(false)
const router = useRouter()

const cageWidth = ref({
  ft: 14,
  inch: 0
})
const cageHeight = ref({
  ft: 14,
  inch: 0
})
const cageLength = ref({
  ft: 65,
  inch: 0
})

const resumePractice = async (players) => {
  const newData = await axiosGet('training/'+ players.id)
  let data = {
    id: players.id,
    is_completed: players.is_completed,
    players: newData.data.data.players,
    mode: players.mode,
    note: players.note,
    start: players.start,
    team: players.team,
    type: players.type
  }
  console.log(cageHeight.value.ft);
  await activeTraining.setDataTraining(data)
  const params = ref({})
  if (newData.data.data.cage_data == "" || newData.data.data.cage_data == undefined) {
    params.value = {
      cageHeight: cageHeight.value.ft,
      lengthCage: cageLength.value.ft,
      widthCage: cageWidth.value.ft,
    }
  } else {
    params.value = {
      cageHeight: newData.data.data.cage_data.height.ft,
      lengthCage: newData.data.data.cage_data.length.ft,
      widthCage: newData.data.data.cage_data.width.ft,
    }
  }


  axiosGet('statistics/'+players.id+'/cage').then((response)=>{
    let playersStats = response.data.data.by_player
    activeTraining.cleanListPlayer()
    for (const key in playersStats) {
      if (Object.hasOwnProperty.call(playersStats, key)) {
        const element = playersStats[key];
        activeTraining.addPLayerInfo(key, {
          'balls': element.length,
        })
      }
    }
    router.push({
      name: "track.trainingCage",
      params: params.value
    });
  }).catch(function(error) {
    if (error.response.status == 404) {
      activeTraining.cleanListPlayer()
      router.push({
        name: "track.trainingCage",
        params: params.value
      });
    }
  });
}

const deleteTeam = (id) => {
  isOpenModal.value = true
  practiceToDelte.value = id
}

const confirmDelete = async() => {
  try {
    await axiosDelete('training/', practiceToDelte.value).then(async(response) => {
      if (response.data) {

        await toast.fire({
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

</script>

<template>
  <section class="px-[10%] md:px-[5%] mt-[250px] lg:mt-[140px] overflow-x-auto">
    <table class="w-full border-separate space-y-6 text-fungo-darkblue">

      <thead class="bg-fungo-lightblue">
        <tr class="divide-x divide-[#000]">
          <template v-if="typeUser == 'c'">
              <th
              v-for="(heading, index) in tableHeadings"
              :key="index"
              class="py-3 font-fungo-500"
            >
              {{ heading }}
            </th>
          </template>
          <template v-else>
              <th
              v-for="(heading, index) in tableHeadingsPlayer"
              :key="index"
              class="py-3 font-fungo-500"
            >
              {{ heading }}
            </th>
          </template>
        </tr>
      </thead>

      <tbody>
        <tr v-if="isLoading" class="w-full">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">Loading data...</td>
        </tr>
        <tr v-else-if="!tableData.length > 0">
          <td colspan="9" class="text-fungo-darkblue text-3xl text-center">No found data</td>
        </tr>
        <template v-else-if="tableData.length > 0 && typeUser == 'c'">
          <tr v-for="(item, index) in tableData"
            :key="index"
            class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
          >
            <td>{{ Number.parseInt(index, 10) + 1 }}</td>

            <td class="w-[140px] max-w-[140px]">
              <img :src="item.team.logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
            </td>

            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ item.team.name }}
            </td>

            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">
                <span v-for="(player, playerIndex) in item.lineup" :key="playerIndex">
                  {{
                    item.lineup.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ', '
                  }}
                </span>

                <!-- tooltip player -->
                <span class="tooltip">
                  <label v-for="(player, playerIndex) in item.lineup" :key="playerIndex">
                    {{
                      item.lineup.length === (playerIndex + 1) ? player.player.name.full : player.player.name.full + ',‍‍‍‍‍ㅤ'
                    }}
                  </label>
                </span>
                <!-- end tooltip player -->
              </p>
            </td>

            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">{{ item.note }}</p>
              <!-- tooltip note -->
              <span class="tooltip w-[300px] max-w-[300px]">
                {{ item.note }}
              </span>
              <!-- end tooltip note -->
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
              <button v-if="!item.is_completed" @click="resumePractice(item)">
                <TableStart />
              </button>
            </td>

            <td class="w-[80px] max-w[80px]">
              <button
                @click="router.push({ name: 'training.statsCage', params: { 'idPractice': item.id, 'mode': item.mode, 'isComplete' : item.is_completed } })"
                class="hover:bg-fungo-gray3 rounded-full p-2"
              >
                <TableStats />

              </button>
            </td>

            <td class="w-[80px] max-w[80px]">
              <button  @click="deleteTeam(item.id)">
                <TableCancel />
              </button>
            </td>

          </tr>
        </template>

        <template v-else-if="tableData.length > 0 && typeUser == 'p'">
          <tr
            v-for="(item, index) in tableData"
            :key="index"
            class="bg-white even:bg-fungo-gray4 border-l border-fungo-lightblue relative"
          >
            <td>{{ Number.parseInt(index, 10) + 1 }}</td>

            <td class="w-[300px] max-w-[3000px] flex flex-row items-center">
              <img src="../../assets/img/layout/logofungo-nav.png" alt="" class="w-20 h-full object-center object-cover mx-auto rounded-full">
              <div class="flex flex-col text-[16px] items-start">
                <template v-if="item.team !== null">
                  <h1><span class="text-[16px] font-fungo-700">Team: </span>{{ item.team.name }} </h1>
                </template>
                <template v-else>
                  <h1 class="text-[18px] font-fungo-700">Personal practice</h1>
                </template>
              </div>
            </td>

            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">{{ item.note }}</p>
              <!-- tooltip note -->
              <span class="tooltip w-[300px] max-w-[300px]">
                {{ item.note }}
              </span>
              <!-- end tooltip note -->
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
                @click.prevent="resumePractice(item)"
                :class="{'hidden' : item.is_completed}"
              >
                <TableStart />
              </button>
            </td>

            <td class="w-[80px] max-w[80px]">
              <button
              @click="router.push({ name: 'training.statsCage', params: { 'idPractice': item.id, 'mode': item.mode, 'isComplete' : item.is_completed } })"
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
        </template>
      </tbody>
    </table>
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
  </section>
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
