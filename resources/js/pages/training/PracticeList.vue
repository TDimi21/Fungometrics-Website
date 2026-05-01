<script setup>
import { storeToRefs } from 'pinia'
import {ref, onMounted, defineProps, onUpdated, reactive} from 'vue'
import { useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { toast } from "@/utils/AlertPlugin"
import { useTeamStore } from '@/store/team.js'
import { SearchIcon } from '@/components/icons'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useUserStore } from "@/store/user";
import { useTrainingStore } from "@/store/training";
import { ArrowHeadRightIcon, ArrowHeadLeftIcon } from '@/components/icons'
import { PracticeTitle, PracticeTable,PracticeTableTrainingMode, PracticeTableTrainingCage } from '@/components/practice'
import { SelectField, InputBase, BigButtonField } from '@/components/form'
import BattingLogoPractice from "@/components/graphics/BattingLogoPractice.vue"
import {Dialog, DialogPanel, DialogTitle} from '@headlessui/vue'

const useTeam = useTeamStore()
const { axiosGet, axiosPost } = useAxiosAuth()
const router = useRouter()
const { userData } = useUserStore();
const activeTraining = useTrainingStore();

const isUpdated = ref(false)
const isOpen = ref(false)
const passFunction = ref(false)
const { team } = storeToRefs(useTeam)
const entriesModel = ref('')
const searchPractice = ref('')
const tableData = ref([])
const pages = ref([])
const entriesOption = ref(
  {
    one: 'Select Entries'
  }
)
const training = reactive({
  note: '',
  modes: ''
});
let mode = ref('');
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
const isLoading = ref(false)
const props = defineProps({
  slug: {
    type: String
  }
})

const getTrainigsByType = async(page = 1) => {
  const typesTraining = [
    { name: "batting", pass: 'B' },
    { name: "bullpen", pass: 'P' },
    { name: "live", pass: 'L' },
    { name: "cage", pass: 'C' },
    { name: "training-mode", pass: 'T' },
  ];
  let type = typesTraining.find(({name})=>name === props.slug).pass;
  const data = {
    team_id: team.value.id,
    type,
    search: searchPractice.value
  }
  try {
    isLoading.value = true
    if(props.slug == "training-mode"){
      let defineEndpointmode = userData.type == 'player' ? `player/sessions/training` : `sessions/all/modes?page=${page}`
      await axiosGet(defineEndpointmode, {
        team: team.value.id
      }).then((response)=> {
        if (response) {
          console.log(response.data.data);
          if (userData.type == 'player') {
            tableData.value =  response.data.data.data.filter(item => item.modes == 'WB' || item.modes == 'LT' || item.modes == 'EV' )
            pages.value = response.data.links
          } else {
            tableData.value =  response.data.data.filter(item => item.mode == 'WB' || item.mode == 'LT' || item.mode == 'EV' )
            pages.value = response.data.meta.links
          }
        }
      })
    }else if(props.slug == 'cage'){
      let defineEndpointCage = userData.type == 'player' ? `player/sessions/cage` : `sessions/all/type?page=${page}`
      await axiosGet(defineEndpointCage, data)
      .then((response) => {
        if (response) {
          if (userData.type == 'player') {
            tableData.value = response.data.data.data.filter(item => item.type == type)
            pages.value = response.data.links
          } else {
            tableData.value = response.data.data
            pages.value = response.data.meta.links
          }
        }
      })
    } else {
      let defineEndpoint = userData.type == 'player' ? `player/sessions/${type == 'B' ? 'batting' : 'bullpen'}` : `sessions/all/type?page=${page}`
      await axiosGet(defineEndpoint, data)
      .then(async(response) => {
        if (response) {
          if (userData.type == 'player') {
            tableData.value = response.data.data.data.filter(item => item.type == type)
            pages.value = response.data.links
          } else {
            tableData.value = response.data.data
            pages.value = response.data.meta.links
			  console.log("data to find missing logo", response.data.data);
          }
        }
      })
    }

  } catch (error) {
    console.log(error);
    tableData.value = []
  } finally {
    isLoading.value = false
  }
}

const getPaginate = (pageNumber) => {
  if ( !pageNumber.includes('Prev') && !pageNumber.includes('Next')) {
    getTrainigsByType(pageNumber)
  }
}

const gotoCreateTraining = ()=>{
  if (!passFunction.value) {
    if(userData.type == 'player'){
      passFunction.value = true
      openModal()
    }else{
      passFunction.value = true
      console.log(props.slug);
      activeTraining.cleanListPlayer()
      switch (props.slug) {
        case "live":
          router.replace({ name: 'create.ab', params: { 'slug':
            'LiveAB' } })
          break;
        case 'training':
          if (userData.type == "player") {
            router.push('/track/' + props.slug)
          } else {
            router.replace({ name: 'create.training', params: { 'slug': props.slug } })
          }
          break;
        case 'training-mode':
          router.replace({ name: 'create.trainingMode', params: { 'slug':
              props.slug } })
          break;
        case "cage":
          router.replace({ name: 'create.trainingCage'})
          break;
        default:
          if (userData.type == "player") {
            router.push('/track/' + props.slug)
            activeTraining.setDataTraining(response.data.data);
          } else {
            router.replace({ name: 'create.training', params: { 'slug': props.slug } })
          }
          break;
      }
    }
  }
}

const registerAndRedirect = async () => {
  if(training.note === ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You add a note for start training or select one player for start the practice',
    })
  }else{
    try {
      isLoading.status = !isLoading.status;
      const typesTraining = [
        { name: "batting", pass: 'B' },
        { name: "bullpen", pass: 'P' },
        { name: "live", pass: 'L' },
        { name: "cage", pass: 'C' },
        { name: "training", pass: 'T' },
        { name: "training-mode", pass: 'T' },
      ];
      let type = typesTraining.find(({name})=>name === props.slug).pass;
      const data = ref({})
      if(type == "cage"){
        data.value = {
          team: '',
          type,
          note: training.note,
          cage: {
            "height": cageHeight.value,
            "width": cageWidth.value,
            "length": cageLength.value
          },
          players: [
            {
              "id": userData.id,
              "sort": "1",
            }
          ]
        };
      }else if (type == 'T'){
        switch (training.modes) {
          case "Long Toss":
              mode.value = "LT";
            break;
          case "Exit Velocity":
              mode.value = "EV";
            break;
          case 'Weighted Ball':
              mode.value = "WB";
            break;
          default:
              mode.value = 'LT';
            break;
        }
        data.value = {
          team: null,
          type,
          modes: mode.value,
          note: training.note,
          players: [
            { "id": userData.id, "sort": "1" }
          ]
        }
      }else{
        data.value = {
          team: '',
          type,
          note: training.note,
          players: [
            {
              "id": userData.id,
              "sort": "1",
            }
          ]
        };
      }

      await axiosPost('training',data.value).then(async (response)=>{
        if(response){
          isLoading.status = !isLoading.status;
          await activeTraining.setDataTraining(response.data.data);
          if(type == "C"){
            router.push({
              name: "track.trainingCage",
              params: {
                cageHeight: cageHeight.value.ft,
                lengthCage: cageLength.value.ft,
                widthCage: cageWidth.value.ft,
              }
            });
          } else if (type == 'T'){
              let data = {
                "balls": 0,
                "bxs": 0,
                "set": 1
              }
              activeTraining.countThrowArray[userData.id] = data
              router.push(`/track/training-mode/${mode.value}`)
          } else {
            router.push('/track/' + props.slug)
          }
        }
      })
    }catch (error){
      console.log(error);
      toast.fire({
        icon: 'error',
        title: 'Error create training',
        text: 'Sorry it is not possible create a training in this moment',
      })
    }
  }
}

const updateList = () => {
  getTrainigsByType()
}

const search = () => {
  getTrainigsByType();
}

function closeModal() {
  isOpen.value = false
  passFunction.value = false
}

function openModal() {
  isOpen.value = true
}

onMounted(() => {
  getTrainigsByType()
})

onUpdated(()=>{
  getTrainigsByType()
})
</script>

<template>
  <Layout>

    <PracticeTitle v-if="props.slug !== 'bullpen'" class="capitalize" :title="props.slug.replace('-', ' ') + ' Practice'" />
    <PracticeTitle v-else class="capitalize" :title="props.slug.replace('-', ' ') + ' and Pitcher Practice'" />

    <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%]">

      <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
        <div class="w-max">
          <BattingLogoPractice class="h-[80px] w-[80px] hidden lg:block" />
        </div>
        <div class="w-full lg:w-[50%]">
          <form
            @submit.prevent="search"
            name="search-practice"
            class="flex flex-row flex-nowrap items-center space-x-3"
          >
            <label for="search" class="block">Search</label>
            <InputBase v-model="searchPractice" inputType="search" class="inline-flex w-[65%]" required="false" placeholder="Search by player name"/>
            <button type="submit" role="submit" class="bg-fungo-red inline-flex rounded-lg w-10 h-10 items-center justify-center">
              <SearchIcon />
            </button>
          </form>
        </div>
        <div class="w-[100%] lg:w-[50%] flex justify-end">
          <BigButtonField color="dark" label="New practice" @click="gotoCreateTraining"/>
        </div>
      </div>
    </section>
    <div v-if="props.slug == 'training-mode'">
      <PracticeTableTrainingMode :tableData="tableData" :teamData="team" :isLoading="isLoading" :typeUser="userData.type == 'player' ? 'p' : 'c'" @updateList="updateList"/>
    </div>
    <div v-else-if="props.slug == 'cage'">
      <PracticeTableTrainingCage :tableData="tableData" :teamData="team" :isLoading="isLoading" :typeUser="userData.type == 'player' ? 'p' : 'c'" @updateList="updateList"/>
    </div>
    <PracticeTable v-else :tableData="tableData" :teamData="team" :isLoading="isLoading" :typeUser="userData.type == 'player' ? 'p' : 'c'" @updateList="updateList"/>

    <div class="pagination flex justify-end items-center px-[10%] md:px-[5%] mt-12" v-if="!tableData.length == 0">
      <button
        v-for="(page, index) in pages"
        :key="index"
        class="bg-white border border-fungo-darkblue w-[40px] h-[40px]"
        :class="{ 'bg-fungo-lightblue' : page.active }"
        @click=" getPaginate(page.label) "
      >
        <span
          v-if="page.label.includes('Prev')"
          class="flex justify-center items-center"
        >
          <ArrowHeadLeftIcon classes="w-[30px] h-[30px]"/>
        </span>

        <span
          v-else-if="page.label.includes('Next')"
          class="flex justify-center items-center"
        >
          <ArrowHeadRightIcon classes="w-[30px] h-[30px]"/>
        </span>

        <span v-else>
          {{ Number.parseInt(index, 10) }}
        </span>
      </button>
    </div>

    <div v-if="isOpen">
      <div class="fixed inset-0 z-50 flex justify-center items-center">
        <div class="modal-container">
          <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
            <!-- <PracticeTitle v-if="props.slug !== 'bullpen'" class="capitalize" :title="props.slug.replace('-', ' ') + ' Practice'" /> -->
            <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Create new {{props.slug.replace('-', ' ')}} session</h1>
            <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="closeModal()">
              <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
            </div>
          </div>
          <div class="bg-fungo-gray2 py-10 px-[3%]">
            <div class="grid grid-cols-5 gap-2 lg:gap-1 truncate">
              <div class="text-fungo-darkblue col-span-3 text-[16px] flex flex-row items-center justify-center space-x-3">
                <h1 class="text-[24px] mx-5">Notes</h1>
                <FormKit type="textarea" v-model="training.note"/>
                <div v-if="props.slug === 'training-mode'">
                  <h1>Mode</h1>
                  <FormKit type="select" v-model="training.modes" :options="['Long Toss', 'Exit Velocity', 'Weighted Ball']"/>
                </div>
              </div>
              <div class="grid mt-5 col-span-2">
                <button class=" rounded-xl rounded-l-3xl border bg-fungo-darkblue text-white xl:w-[200px] mx-auto xl:w-[300px]"
                  @click="registerAndRedirect">
                  <div class="flex  min-w-[80px] lg:max-w-[250px]">
                    <div class="m-1 p-1">
                      <img class="w-[20px] h-[20px] xl:w-[35px] xl:h-[35px]" src="../../assets/img/login/assteslogin/ballbutton.png">
                    </div>
                    <div class="grid content-center items-center mx-8 xl:text-[18px]">Start Practice</div>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
    </div>
  </Layout>
</template>
<style lang="css" scoped>
.pagination button:first-child {
  border-radius: 10px 0 0 10px;
}
.pagination button:last-child {
  border-radius: 0 10px 10px 0;
}

.modal-container{
  @apply flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 drop-shadow-xl min-h-[30%] max-h-[30%]
    lg:min-h-[30%] lg:max-h-[30%] w-[85%] md:w-[100%] ml-3 lg:ml-0
}
</style>
