<script setup>
import Layout from "../../layout/Layout.vue"
import {useUserStore} from "../../store/user";
import {usePlayerStore} from "../../store/players";
import {useTeamStore} from "../../store/team";
import {useTrainingStore} from "../../store/training";
import CardTeam from "../../components/CardTeam.vue";
import {defineProps, reactive, ref, onMounted, onUpdated} from "vue";
import router from "../../../router";
import {Dialog, DialogPanel, DialogTitle} from '@headlessui/vue'
import { toast } from "@/utils/AlertPlugin"
import { useAxiosAuth } from '@/composables/axios-auth.js'
import Loader from "../../components/Loader.vue";
import {computed} from 'vue'
import TShirt from '@/components/graphics/TShirt.vue'
import draggable from 'vuedraggable';

const isLoading = reactive({status: true})

const slug = ref('cage')

let mode = ref('');

const { axiosPost } = useAxiosAuth()

const {players} = usePlayerStore();
const {team, teams} = useTeamStore();
const activeTraining = useTrainingStore();
const training = reactive({
  note: '',
});

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

const registerAndRedirect = async () => {
    if(training.note === "" || listOrderPlayer.value.length === 0 && training.modes == ''){
      toast.fire({
        icon: 'warning',
        title: 'Validation !!!',
        text: 'You add a note for start training or select one player for start the practice',
      })
    }else{
      try {
        isLoading.status = !isLoading.status;

        let type = "C";
        const data = {
          team: activeTeam.value,
          type,
          cage: {
            "height": cageHeight.value,
            "width": cageWidth.value,
            "length": cageLength.value
          },
          note: training.note,
          players:listOrderPlayer.value.map((item,index)=>{
            return {'sort': index, 'id':item.id}
          })
        };


        await axiosPost('training',data).then(async (response)=>{

          if(response){
            isLoading.status = !isLoading.status;
            activeTraining.cleanListPlayer()
            activeTraining.setCountBallsTraining(0)
            await activeTraining.setDataTraining(response.data.data);
            router.push({
              name: "track.trainingCage",
              params: {
                cageHeight: cageHeight.value.ft,
                lengthCage: cageLength.value.ft,
                widthCage: cageWidth.value.ft,
              }
            });
          }
        })

      }catch (error){
        isLoading.status = !isLoading.status;
         toast.fire({
          icon: 'error',
          title: 'Error create training',
          text: 'Sorry it is not possible create a training in this moment',
        })
      }

    }


  }
const listPlayers = ref([...players])
const listOrderPlayer = ref([]);
const activeTeam = ref(team.id);

const isOpen = ref(false)

function closeModal() {
  isOpen.value = false
}

function openModal() {
  isOpen.value = true
}

const clearPlayersData = ()=>{
  listPlayers.value =[];
  listOrderPlayer.value =[];
}

</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <Layout class="debug">
    <div class="grid grid-flow-row md:grid-cols-3 font-fungo-poppins md:place-items-center">
      <div class="capitalize text-center text-fungo-red font-fungo-700 text-lg md:grid-cols-3 md:text-3xl mt-2 md:col-span-3">
        New {{ slug.replace('-', ' ') }} Practice
      </div>
      <div
        class="max-h-[150px] min-h-[150px] md:max-h-[300px]
        md:min-h-[250px]  md:w-[300px] border rounded bg-white grid
        overflow-y-auto py-2 mt-3 md:ml-8 xl:w-[500px]">
        <div class="text-fungo-red font-fungo-700 ml-5">Select team</div>
        <div v-for="(element, index) in teams ">
          <CardTeam :team-item="element" name-input="teamActive" v-model:players="listPlayers" v-model:team="team"
                    @click="clearPlayersData"/>
        </div>
      </div>
      <div class="hidden md:block w-[75%]">
        <img alt="back batting"
             src="../../assets/img/training/battingbglogo.svg"></div>
      <div class="grid grid-flow-row place-items-center mt-5 md:mr-12">
        <div>
          <div class="grid mt-5">
            <button
              class=" rounded-xl rounded-l-3xl border bg-fungo-red text-white xl:w-[200px] mx-auto xl:w-[300px]"
              @click="openModal">
              <div
                class="flex  min-w-[80px] lg:max-w-[250px]">
                <div class="m-1 p-1"><img
                  class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px]"
                  src="../../assets/img/login/assteslogin/ballbutton.png"></div>
                <div class="grid content-center items-center mx-8 xl:text-[18px]">Select Players</div>
              </div>
            </button>
          </div>
        </div>
        <div class="mt-2">
          Notes
          <FormKit type="textarea" v-model="training.note"/>
        </div>
        <div class="grid mt-2">
          <button
            class=" rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white xl:w-[200px] mx-auto xl:w-[300px]"
            @click="registerAndRedirect">
            <div
              class="flex  min-w-[80px] lg:max-w-[250px]">
              <div class="m-1 p-1"><img
                class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px]" src="../../assets/img/login/assteslogin/ballbutton.png"></div>
              <div class="grid content-center items-center mx-8 xl:text-[18px]">Start Practice</div>
            </div>
          </button>
        </div>

      </div>
    </div>
    <div class="text-fungo-red font-fungo-700 font-fungo-poppins text-center my-4 text-xl " >A "Standard Cage" is 14 * 14 * 65</div>
    <div class="bg-white grid gap-4 grid-cols-1 justify-center md:grid-cols-2 xl:grid-cols-3 p-4">
     <div class="flex flex-col items-center">
        <div>
          Cage width
        </div>
        <div class="flex flex-row gap-1">
          <div>
            <button @click="cageWidth.ft--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageWidth.ft" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">feet</span>
            </span>
            <button @click="cageWidth.ft++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
          <div>
            <button @click="cageWidth.inch--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageWidth.inch" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">inch</span>
            </span>
            <button @click="cageWidth.inch++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
        </div>
      </div>
      <div class="flex flex-col items-center">
        <div>
          Cage height
        </div>
        <div class="flex flex-row gap-1">
          <div>
            <button @click="cageHeight.ft--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageHeight.ft" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">feet</span>
            </span>
            <button @click="cageHeight.ft++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
          <div>
            <button @click="cageHeight.inch--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageHeight.inch" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">inch</span>
            </span>
            <button @click="cageHeight.inch++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
        </div>
      </div>
      <div class="flex flex-col items-center md:col-span-2 xl:col-span-1">
        <div>
          Cage length
        </div>
        <div class="flex flex-row gap-1">
          <div>
            <button @click="cageLength.ft--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageLength.ft" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">feet</span>
            </span>
            <button @click="cageLength.ft++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
          <div>
            <button @click="cageLength.inch--" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">-</button>
            <span class="relative border border-fungo-darkblue rounded-md mx-2 p-1">
              <input v-model="cageLength.inch" type="text" class="w-24 h-[30px] border-none bg-transparent focus:border-current focus:ring-0">
              <span class="absolute left-16 top-[3px] text-gray-400">inch</span>
            </span>
            <button @click="cageLength.inch++" class="bg-gray-100 border-2 border-red py-[2px] px-[10px] rounded-md hover:bg-fungo-lightblue">+</button>
          </div>
        </div>
      </div>
    </div>
    <div class="md:col-span-3 mt-8">
      <section class="px-[10%] md:px-[5%]  lg:mt-[140px] overflow-x-auto grid place-items-center">
        <table class="w-fit border-separate space-y-6 text-fungo-darkblue">

          <thead class="bg-fungo-lightblue">
          <tr class="divide-x divide-[#000]">
            <th>
              logo
            </th>
            <th>
              name
            </th>
            <th>
              lineup
            </th>
            <th>
              note
            </th>
          </tr>

          </thead>

          <tbody>


          <tr  class="bg-white border-l border-fungo-lightblue ">

            <td class="w-[140px] max-w-[140px]">
              <img :src="team.logo" alt="" class="w-16 h-full object-center object-cover mx-auto rounded-full">
            </td>

            <td class="w-[200px] max-w-[200px] font-fungo-700">
              {{ team.name }}
            </td>

            <td class="w-[270px] max-w-[270px] group relative">
              <p class="truncate">
              <span v-for="(player, playerIndex) in listOrderPlayer" :key="playerIndex">
                {{
                  listOrderPlayer.length === (playerIndex + 1) ? player.name.full : player.name.full + ', '
                }}
              </span>

                <!-- tooltip player -->
                <span class="tooltip">
                <label v-for="(player, playerIndex) in listOrderPlayer" :key="playerIndex">
                  {{
                    listOrderPlayer.length === (playerIndex + 1) ? player.name.full : player.name.full + ',‍‍‍‍‍ㅤ'
                  }}
                </label>
              </span>
                <!-- end tooltip player -->
              </p>
            </td>
            <td class="w-[200px] max-w-[200px] group relative">
              <p class="truncate">{{ training.note }}</p>
              <span class="tooltip w-[300px] max-w-[300px]">
                {{ training.note }}
              </span>
            </td>
          </tr>
          </tbody>
        </table>
      </section>
    </div>
    <Dialog :open="isOpen" class="relative z-50" @close="closeModal">
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
                @click="closeModal"><img
                alt="Icon close view"
                src="../../assets/img/register/cancel.svg">
              </div>
              <div class="grid grid-cols-2 p-2 gap-2">
                <div class="border-r-4 p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">List of players</div>

                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="listPlayers" group="people">
                    <template #item="{ element }">
                      <t-shirt :player="element"/>
                    </template>
                  </draggable>

                </div>
                <div class="p-2 drop-zone">
                  <div class="font-fungo-800 text-fungo-blue2">Order training</div>
                  
                  <draggable class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 min-h-[100px]" :list="listOrderPlayer" group="people">
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
</style>
