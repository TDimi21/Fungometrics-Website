<script setup>
import Layout from "../../layout/Layout.vue"
import {usePlayerStore} from "../../store/players";
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import CardTeam from "../../components/CardTeam.vue";
import {defineProps, onMounted, onUpdated, reactive, ref} from "vue";
import router from "../../../router";
import {Dialog, DialogPanel, DialogTitle} from '@headlessui/vue'
import {toast} from "@/utils/AlertPlugin"
import {useAxiosAuth} from '@/composables/axios-auth.js'
import Loader from "../../components/Loader.vue";
import TShirt from '@/components/graphics/TShirt.vue'
import draggable from 'vuedraggable';
import { useLiveABStore } from '@/store/liveAB.js'

const isLoading = reactive({status: true})

const props = defineProps({
  slug: {
    type: String
  }
})

const {axiosPost} = useAxiosAuth()

const {players} = usePlayerStore();
const manageTeamStore = useTeamStore();
const activeTraining = useTrainingStore();
const training = reactive({
  note: '',
});

const teamA = ref(manageTeamStore.team);
const teamB = ref(manageTeamStore.team);

const teams = ref(manageTeamStore.teams)
const dataHitters = ref([...players])
const dataPitchers = ref([...players])
const listOrderPlayerHitters = ref([]);
const listOrderPlayerPitchers = ref([]);
const { resetStatusPlayer } = useLiveABStore()

const isOpenHitters = ref(false)

function closeModalHitters() {
  isOpenHitters.value = false
}

function openModalHitters() {
  isOpenHitters.value = true
}

const isOpenPitchers = ref(false)

function closeModalPitchers() {
  isOpenPitchers.value = false
}

function openModalPitchers() {
  isOpenPitchers.value = true
}


const registerAndRedirect = async () => {
  let teamsRequest = [];
  if (training.note === "" || listOrderPlayerHitters.value.length === 0 || listOrderPlayerPitchers.value.length === 0) {
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You add a note for start training or select one player for start the practice',
    })
  } else {
    try {
      isLoading.status = !isLoading.status;

      let playersA = listOrderPlayerHitters.value.map((item, index) => {
        return {'sort': index, 'id': item.id}
      });
      let playersB = listOrderPlayerPitchers.value.map((item, index) => {
        return {'sort': index, 'id': item.id}
      });

      const data = {
        teams:{'a' : teamA.value.id, 'b': teamB.value.id},
        type: 'L',
        note: training.note,
        players: {'a': playersA, 'b':playersB}
      };


      await axiosPost('coach/trainingab', data).then(async (response) => {

        if (response) {      
          resetStatusPlayer()
          
          localStorage.removeItem('countBall')
          localStorage.removeItem('countStrike')

          isLoading.status = !isLoading.status;
          activeTraining.cleanListPlayer()
          activeTraining.livePitches = 0
          await activeTraining.setDataTraining(response.data.data);
          router.push('/track/live')
        }
      })

    } catch (error) {
      isLoading.status = !isLoading.status;
      console.log(error)
      toast.fire({
        icon: 'error',
        title: 'Error create training',
        text: 'Sorry it is not possible create a training in this moment',
      })
    }
  }

}

const clearPitchersData = ()=>{
 dataPitchers.value =[];
 listOrderPlayerPitchers.value =[];
}

const clearHittersData = ()=>{
  dataHitters.value =[];
  listOrderPlayerHitters.value =[];
}

</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout class="debug">
    <div class="grid grid-flow-row md:grid-cols-3 font-fungo-poppins md:place-items-center">
      <div class="text-center text-fungo-red font-fungo-700 text-lg md:grid-cols-3 md:text-3xl mt-2 md:col-span-3">
        New live AB Practice
      </div>
      <div
        class="max-h-[150px] min-h-[150px] md:max-h-[250px]
        md:min-h-[250px]  md:w-[300px] border rounded bg-white grid
        overflow-y-auto py-2 mt-3 md:ml-12 xl:w-[500px] order-0 md:order-0
">
        <div class="text-fungo-red font-fungo-700 ml-5">Select first team</div>
        <div v-for="(element, index) in teams ">
          <CardTeam :team-item="element" name-input="teamA" v-model:players="dataHitters" v-model:team="teamA"
                    @click="clearHittersData"/>
        </div>
      </div>
      <div
        class="max-h-[150px] min-h-[150px] md:max-h-[250px]
        md:min-h-[250px]  md:w-[300px] border rounded bg-white grid
        overflow-y-auto py-2 mt-3 md:-ml-12 xl:w-[500px] order-1 md:order-2
">

        <div class="text-fungo-red font-fungo-700 ml-5">Select second team</div>
        <div v-for="(element, index) in teams ">
          <CardTeam :team-item="element" name-input="teamB" v-model:players="dataPitchers" v-model:team="teamB"
                    @click="clearPitchersData" />
        </div>
      </div>
      <div class="hidden md:block w-[75%] md:w-[50%] grow-0 order-2 md:order-1">
        <img alt="back batting"
             src="../../assets/img/training/liveabbglogo.svg">
      </div>
      <div class="grid grid-flow-row place-items-center mt-5 md:mr-12 order-3 md:order-3 md:col-span-3">
        <div>
          <div class="grid grid-cols-2 mt-5">
            <div class="col-span-2 my-3">Select players:</div>
            <button
              class=" rounded-xl rounded-l-3xl border bg-fungo-red text-white xl:w-[200px] mx-auto xl:w-[300px] xl:mr-2"
              @click="openModalHitters">
              <div class="flex min-w-[80px]  order-1">
                <div class="m-1 p-1 order-0 xl:flex xl:justify-start xl:w-full">
                  <img
                    class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px]"
                    src="../../assets/img/login/assteslogin/ballbutton.png">
                </div>
                <div class="grid content-center items-center mx-8 xl:text-[18px]">Batters</div>
              </div>
            </button>
            <button
              class=" rounded-xl rounded-r-3xl border bg-fungo-red text-white xl:w-[200px] mx-auto xl:w-[300px] xl:ml-2"
              @click="openModalPitchers">
              <div class="flex  min-w-[80px]  order-0">
                <div class="m-1 p-1 order-1 xl:flex xl:justify-end xl:w-full ">
                  <img
                    class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px] xl:ml-[75px]"
                    src="../../assets/img/login/assteslogin/ballbutton.png">
                </div>
                <div class="grid content-center items-center mx-8 xl:text-[18px]">Pitchers</div>
              </div>
            </button>
          </div>
        </div>
        <div class="mt-3">
          Notes
          <FormKit v-model="training.note" type="textarea"/>
        </div>
        <div class="grid mt-5 ">
          <button
            class=" rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white xl:w-[200px] mx-auto xl:w-[300px]"
            @click="registerAndRedirect">
            <div
              class="flex  min-w-[80px] lg:max-w-[250px]">
              <div class="m-1 p-1"><img
                class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px]"
                src="../../assets/img/login/assteslogin/ballbutton.png"></div>
              <div class="grid content-center items-center mx-8 xl:text-[18px]">Start Practice</div>
            </div>
          </button>
        </div>

      </div>


    </div>
    <div class="md:col-span-3 mt-8">
      <section class="px-[10%] md:px-[5%]  lg:mt-[140px] overflow-x-auto grid place-items-center">
        <table class="w-fit border-separate space-y-6 text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
          <tr class="divide-x divide-[#000]">
            <th>
              Team A
            </th>
            <th>
              Team B
            </th>
            <th>
              lineup A
            </th>
            <th>
              lineup B
            </th>
            <th>
              Note
            </th>
          </tr>

          </thead>

          <tbody>


          <tr class="bg-white border-l border-fungo-lightblue ">

            <td class="w-[140px] max-w-[140px] ">
              <img :src="teamA.logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
              <div class="font-fungo-700">
              {{ teamA.name }}
            </div>

            </td>

            <td class="w-[140px] max-w-[140px]">
              <img :src="teamB.logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
              <div class="font-fungo-700">
                {{ teamB.name }}
              </div>

            </td>

            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">
              <span v-for="(player, playerIndex) in listOrderPlayerHitters" :key="playerIndex">
                {{
                  listOrderPlayerHitters.length === (playerIndex + 1) ? player.name.full : player.name.full + ', '
                }}
              </span>

                <!-- tooltip player -->
                <span class="tooltip">
                <label v-for="(player, playerIndex) in listOrderPlayerHitters" :key="playerIndex">
                  {{
                    listOrderPlayerHitters.length === (playerIndex + 1) ? player.name.full : player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
                <!-- end tooltip player -->
              </p>
            </td>
            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">
              <span v-for="(player, playerIndex) in listOrderPlayerPitchers" :key="playerIndex">
                {{
                  listOrderPlayerPitchers.length === (playerIndex + 1) ? player.name.full : player.name.full + ', '
                }}
              </span>

                <!-- tooltip player -->
                <span class="tooltip">
                <label v-for="(player, playerIndex) in listOrderPlayerPitchers" :key="playerIndex">
                  {{
                    listOrderPlayerPitchers.length === (playerIndex + 1) ? player.name.full : player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
                <!-- end tooltip player -->
              </p>
            </td>
            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">{{ training.note }}</p>
              <!-- tooltip note -->
              <span class="tooltip w-[300px] max-w-[300px]">
              {{ training.note }}
            </span>
              <!-- end tooltip note -->
            </td>

          </tr>
          </tbody>
        </table>
      </section>
    </div>
    <Dialog :open="isOpenHitters" class="relative z-50" @close="closeModalHitters">
      <div class="fixed inset-0 grid place-items-center min-h-[70%] max-h-[70%] p-4">
        <div class="fixed inset-0 overflow-y-auto">
          <!-- Container to center the panel -->
          <div class="grid w-[95%]  md:w-[65%] mx-auto mt-10">
            <DialogPanel class="rounded bg-white border p-2 drop-shadow-xl">
              <DialogTitle
                class="text-xl text-fungo-red text-center font-fungo-700 my-5">
                Set players and order to training
              </DialogTitle>
              <div
                class="absolute right-2 top-2 md:right-6 md:top-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]"
                @click="closeModalHitters"><img
                alt="Icon close view"
                src="../../assets/img/register/cancel.svg">
              </div>
              <div class="grid grid-cols-2 p-2 gap-2">
                <div class="border-r-4 p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">List of players</div>

                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="dataHitters" group="people">
                    <template #item="{ element }">
                      <t-shirt :player="element"/>
                    </template>
                  </draggable>
                </div>
                <div class="p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">Order training</div>
                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="listOrderPlayerHitters" group="people">
                    <template #item="{ element }">
                      <t-shirt :player="element"/>
                    </template>
                  </draggable>
                </div>
              </div>
            </DialogPanel>
          </div>
        </div>
      </div>
    </Dialog>
    <Dialog :open="isOpenPitchers" class="relative z-50" @close="closeModalPitchers">
      <div class="fixed inset-0 grid place-items-center min-h-[70%] max-h-[70%] p-4">
        <div class="fixed inset-0 overflow-y-auto">
          <!-- Container to center the panel -->
          <div class="grid w-[95%]  md:w-[65%] mx-auto mt-10">
            <DialogPanel class="rounded bg-white border p-2 drop-shadow-xl">
              <DialogTitle
                class="text-xl text-fungo-red text-center font-fungo-700 my-5">
                Set players and order to training
              </DialogTitle>
              <div
                class="absolute right-2 top-2 md:right-6 md:top-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]"
                @click="closeModalPitchers"><img
                alt="Icon close view"
                src="../../assets/img/register/cancel.svg">
              </div>
              <div class="grid grid-cols-2 p-2 gap-2">
                <div class="border-r-4 p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">List of players</div>

                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="dataPitchers" group="people">
                    <template #item="{ element }">
                      <t-shirt :player="element"/>
                    </template>
                  </draggable>
                </div>
                <div class="p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">Order training</div>
                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="listOrderPlayerPitchers" group="people">
                    <template #item="{ element }">
                      <t-shirt :player="element"/>
                    </template>
                  </draggable>
                </div>
              </div>
            </DialogPanel>
          </div>
        </div>
      </div>
    </Dialog>
  </Layout>
</template>
<style scoped>
.dash-table-container {
  position: relative;
  left: 0;
}

.box-input-col {
  @apply flex flex-col w-[100%];
}

.dash-body {
  @apply h-full  w-full  flex flex-col justify-between;
}

.capitalize {
  text-transform: capitalize;
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
  background: #e41111;
  border: 0px none #ffffff;
  border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
  background: #ffffff;
}

::-webkit-scrollbar-thumb:active {
  background: #000000;
}

::-webkit-scrollbar-track {
  background: #666666;
  border: 22px solid #918383;
  border-radius: 4px;
}

::-webkit-scrollbar-track:hover {
  background: #e41111;
}

::-webkit-scrollbar-track:active {
  background: #333333;
}

::-webkit-scrollbar-corner {
  background: transparent;
}

.drop-zone {
  @apply bg-white
}

.tooltip {
  @apply absolute hidden group-hover:flex -left-5 -top-2 -translate-y-[60%] w-max px-2 py-1 bg-fungo-darkblue rounded-lg text-center text-white text-sm after:content-[''] after:absolute after:left-1/2 after:top-[100%] after:-translate-x-1/2 after:border-8 after:border-x-transparent after:border-b-transparent after:border-t-fungo-darkblue
}

table {
  border-spacing: 0 10px;
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
</style>
