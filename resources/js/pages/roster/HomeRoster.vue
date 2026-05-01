<script setup>
import Layout from '@/layout/Layout.vue'
import { InputBase, BigButtonField, InutTel, LabelField } from '@/components/form'
import { SearchIcon, ArrowHeadRightIcon, ArrowRightIcon } from '@/components/icons'
import {ref, onMounted, reactive} from 'vue'
import { CoachTable, PlayerTable, ModalSearchPlayer, ModalSearchCoach } from '@/components/roster'
import { useUserStore } from '@/store/user'
import {useTeamStore} from "@/store/team";
import { usePlayerStore } from '@/store/players.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { toast } from "@/utils/AlertPlugin"
import Loader from "@/components/Loader.vue";
import axios from "axios";
import { storeToRefs } from 'pinia'

const { axiosGet, axiosDelete } = useAxiosAuth()
const {userData} = useUserStore();
const {team } = useTeamStore();
const playerStore = usePlayerStore()

const { players } = storeToRefs(playerStore)

const pages = ref([])
const api_url = process.env.API_ENDPOINT;
const isLoading = reactive({status: true})
const token = JSON.parse(localStorage.getItem('auth')).token

const searchCoach = ref('')
const coachesDataDefault = ref([])
const searchPlayer = ref('')
const playersDataDefault = ref([])

//Constantes tablas
const isLoadingCoach = ref(false)
const isLoadingPlayer = ref(false)
const tableDataPlayers = ref([])
const playerLinks = ref([])
const tableDataCoaches = ref([])

//Constantes para modals
const isOpenModalCoach = ref(false)
const isOpenModalPlayer = ref(false)
const isAddPlayer = ref(false)
const isAddCoach = ref(false)

let dataCoach = reactive({
  firstName: '',
  lastName: '',
  mobileNumber: '',
})

let dataPlayer = reactive({
  firstName: '',
  lastName: '',
  mobileNumber: '',
})

const changeBool = ()=>{
  isOpenModalPlayer.value = !isOpenModalPlayer.value
  getPlayerByTeam()
}

const changeBoolCoach = ()=>{
  isOpenModalCoach.value = !isOpenModalCoach.value
}

const getCoachesByRoster = async(page = 1) => {
  const data = {}
  try {
    isLoadingPlayer.value = true
    await axiosGet(`coach/roster/coaches`, data)
      .then((response) => {
        if (response) {
          tableDataCoaches.value = response.data.data
          coachesDataDefault.value = tableDataCoaches.value
          // pages.value = response.data.meta.links
        }
      })
    tableDataCoaches.value.splice(0, 0, userData)
  } catch (error) {
    tableDataCoaches.value.splice(0, 0, userData)
    // await toast.fire({
    //   icon: 'error',
    //   title: 'Error get data',
    //   text: 'Yo can try with a different type of user',
    // })
  } finally {
    isLoadingPlayer.value = false
  }
}

const getPlayerByTeam = async(page = 1) => {
  const data = {}
  try {
    isLoadingPlayer.value = true
    await axiosGet(`coach/teams/${team.id}`, data)
      .then((response) => {
        if (response) {
          tableDataPlayers.value = response.data.data
          playersDataDefault.value = tableDataPlayers.value
          playerLinks.value = response.data.links
          playerStore.setPlayers(tableDataPlayers.value)
        }
      })

  } catch (error) {
    // await toast.fire({
    //   icon: 'error',
    //   title: 'Error get data',
    //   text: 'Yo can try with a different type of user',
    // })
  } finally {
    isLoadingPlayer.value = false
  }
}

const searchCoahByName = async () =>{
  if(searchCoach.value.length == 0){
    tableDataCoaches.value = coachesDataDefault.value;
  }else if(searchCoach.value.length >= 1 && searchCoach.value.length <= 2){
    await toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: 'Please enter at least three letters to perform the search',
    })
  }else{
    const newArray = ref([])
    coachesDataDefault.value.forEach(element => {
      if(element.name.full.toLowerCase().includes(searchCoach.value.toLowerCase())){
        newArray.value.push(element)
      }
    });

    if(newArray.value.length > 0){
      tableDataCoaches.value = newArray.value
    }else{
      searchCoach.value = ""
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Not  Coach Found',
      })
    }
  }
}

const searchPlayerByName = async () =>{
  if(searchPlayer.value.length == 0){
    tableDataPlayers.value = playersDataDefault.value;
  }else if(searchPlayer.value.length >= 1 && searchPlayer.value.length <= 2){
    await toast.fire({
      icon: 'error',
      title: 'Error get data',
      text: 'Please enter at least three letters to perform the search',
    })
  }else{
    const newArray = ref([])
    playersDataDefault.value.forEach(element => {
      if(element.name.full.toLowerCase().includes(searchPlayer.value.toLowerCase())){
        newArray.value.push(element)
      }
    });

    if(newArray.value.length > 0){
      tableDataPlayers.value = newArray.value
    }else{
      searchPlayer.value = ""
      await toast.fire({
        icon: 'error',
        title: 'Error get data',
        text: 'Not  Player Found',
      })
    }
  }
}

const submitAddPlayer = async () => {
  isLoading.status =!isLoading.status;
  if(dataPlayer.mobileNumber == "" || dataPlayer.firstName == "" || dataPlayer.lastName == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    dataForm.append('phone', dataPlayer.mobileNumber)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', dataPlayer.firstName)
    dataForm.append('name[last]', dataPlayer.lastName)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/add/players', dataForm, config).then(async function (response) {
      console.log(response.data);

      let tempResponse = response.data.data
      let playerToSetInStore = {
        "id": tempResponse.id,
        "name": {
          "first": tempResponse.profile.first_name,
          "last": tempResponse.profile.last_name,
          "full": tempResponse.profile.first_name + tempResponse.profile.last_name
        },
        "avatar":"https://fungometrics.s3.amazonaws.com/logo.png",
        "body":{
          "ft":null,
          "inch":null,
          "weight":null,
          "full_height":"'”",
          "weight_data":" lb"
        },
        "born": {
          "date":null,
          "age":0
        },
        "number_in_shirt":null,
        "throw_side":null,
        "hit_side":null,
        "positions":[]
      }

      toast.fire({
        icon: 'success',
        title: 'Player Register',
        text: response.data.message,
      })
      /* reload data */
      getPlayerByTeam()
      players.value.push(playerToSetInStore)
      dataPlayer.firstName = '',
      dataPlayer.lastName = '',
      dataPlayer.mobileNumber = '',
      isLoading.status =!isLoading.status;
      isAddPlayer.value = false
      router.go(router.currentRoute)
    }).catch(async function (error){
      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        const errorsObject = error.response.data.data.errors
        let errorMessage = ''
        let isAllow = false
        for (const [key, value] of Object.entries(errorsObject)) {
          if(!isAllow){
            isAllow = true
            errorMessage = value
          }
        }
        await toast.fire({
          icon: 'warning',
          title: 'Player Warning !!!',
          text: errorMessage,
        })
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Player Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      isLoading.status =!isLoading.status;
    })
  }
}

const submitAddCoach = async () => {
  isLoading.status =!isLoading.status;
  if(dataCoach.mobileNumber == "" || dataCoach.firstName == "" || dataCoach.lastName == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    dataForm.append('phone', dataCoach.mobileNumber)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', dataCoach.firstName)
    dataForm.append('name[last]', dataCoach.lastName)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/add/coaches', dataForm, config).then(async function (response) {
      toast.fire({
        icon: 'success',
        title: 'Coach Register',
        text: response.data.message,
      })
      dataCoach.firstName = '',
      dataCoach.lastName = '',
      dataCoach.mobileNumber = '',
      isLoading.status =!isLoading.status;
      isAddCoach.value = false
      getCoachesByRoster()
    }).catch(async function (error){
      console.log(error.response);
      if (error.response.data.code === '001V' || error.response.status === 422 ) {
        const errorsObject = error.response.data.data.errors
        let errorMessage = ''
        let isAllow = false
        for (const [key, value] of Object.entries(errorsObject)) {
          if(!isAllow){
            isAllow = true
            errorMessage = value
          }
        }
        await toast.fire({
          icon: 'warning',
          title: 'Coach Warning !!!',
          text: errorMessage,
        })
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Coach Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      isLoading.status =!isLoading.status;
    })
  }
}

onMounted(() => {
  getPlayerByTeam()
  getCoachesByRoster()
})

const deleteCoach = (deleteItem) =>{
  if(Object.keys(deleteItem).length > 0){
    axiosDelete(`coach/remove/coach/`, deleteItem.id).then((response)=>{
      console.log(response.data)
      toast.fire({
        icon: 'success',
        title: 'removed coach',
        text: 'coach already removed',
      })
      tableDataCoaches.value = []
      getCoachesByRoster()
    }).catch((error)=>{
      isOpenDelteModal = false
      toast.fire({
        icon: 'error',
        title: 'Not removed coach',
        text: 'Sorry it is not possible remove this information',
      })
    })
  }
}

const updateTable = (item) => {
  getPlayerByTeam()
}
</script>

<template>
    <Layout>
      <Loader v-show="!isLoading.status"/>
        <h1 class="text-fungo-red text-2xl md:text-[40px] text-center mt-9 mb-6 font-fungo-700">Roster</h1>
        <section class="bg-fungo-gray4 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%]">
            <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
                <div class="w-full lg:w-[30%] mt-6 mb-6 text-center">
                    <h1 class="text-fungo-darkblue text-lg md:text-[30px] font-fungo-700">Coach</h1>
                </div>
                <div class="w-[100%] lg:w-[75%] justify-center flex lg:justify-end">
                    <BigButtonField color="dark" label="Create coach" @click="isAddCoach = true"/>
                </div>
            </div>
        </section>
        <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[140px] lg:mt-[80px]">
            <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
              <button class="w-full lg:w-[40%] mt-6 mb-6" @click="isOpenModalCoach = true">
                <h1 class="text-fungo-blue2 text-base md:text-[15px] font-fungo-700 flex items-center cursor-pointer">Choose from existing coach <ArrowHeadRightIcon color="0077B6"/> </h1>
              </button>
              <div class="w-[100%] lg:w-[75%] flex justify-end">
                <div class="w-full lg:w-[60%] ml-2">
                  <div class="w-full lg:w-[100%] ml-2">
                    <div class="flex flex-row flex-nowrap items-center space-x-3">
                      <label for="search" class="block">Search</label>
                      <InputBase v-model="searchCoach" inputType="search" placeholder="Search by name" class="inline-flex w-[100%]"/>
                      <button @click="searchCoahByName"
                        class="bg-fungo-red inline-flex rounded-lg w-[20%] max-w-[50px] h-10 items-center justify-center">
                        <SearchIcon />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </section>
        <CoachTable :tableData="tableDataCoaches" :isLoading="isLoadingCoach" :idTeam="team.id" v-on:remove-item="deleteCoach($event)"/>
        <section class="bg-fungo-gray4 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[160px] lg:mt-[80px]">
            <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
                <div class="w-full lg:w-[30%] mt-6 mb-6 text-center">
                    <h1 class="text-fungo-darkblue text-lg md:text-[30px] font-fungo-700">Player</h1>
                </div>
                <div class="w-[100%] lg:w-[75%] justify-center flex lg:justify-end pb-[3%] lg:pb-[0%]">
                    <BigButtonField color="dark" label="Create player" @click="isAddPlayer = true"/>
                </div>
            </div>
        </section>
        <section class="bg-fungo-gray3 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[300px] lg:mt-[160px]">
            <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
                <div class="w-full lg:w-[40%] mt-6 mb-6" @click="isOpenModalPlayer = true">
                    <h1 class="text-fungo-blue2 text-base md:text-[15px] font-fungo-700 flex items-center cursor-pointer">Choose from existing player <ArrowHeadRightIcon color="0077B6"/> </h1>
                </div>
                <div class="w-[100%] lg:w-[75%] flex justify-end">
                  <div class="w-full lg:w-[60%] ml-2">
                    <div class="w-full lg:w-[100%] ml-2">
                      <div class="flex flex-row flex-nowrap items-center space-x-3">
                        <label for="search" class="block">Search</label>
                        <InputBase v-model="searchPlayer" inputType="search" placeholder="Search by name" class="inline-flex w-[100%]"/>
                        <button @click="searchPlayerByName"
                          class="bg-fungo-red inline-flex rounded-lg w-[20%] max-w-[50px] h-10 items-center justify-center">
                          <SearchIcon />
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </section>
        <PlayerTable :tableData="tableDataPlayers" :isLoading="isLoadingPlayer" :idTeam="team.id" :playerLinks="playerLinks" v-on:update-table="updateTable($event)"/>

        <div v-if="isOpenModalCoach">
          <ModalSearchCoach :isOpen="isOpenModalCoach" @closeModal="changeBoolCoach"></ModalSearchCoach>
          <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
        </div>
        <div v-if="isOpenModalPlayer">
          <ModalSearchPlayer :isOpen="isOpenModalPlayer" @closeModal="changeBool()"></ModalSearchPlayer>
          <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
        </div>
        <div v-if="isAddPlayer">
          <div class="fixed inset-0 z-50 flex justify-center items-center">
            <div class="modal-container">
              <div>
                <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
                  <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Create player</h1>
                  <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isAddPlayer = false">
                    <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
                  </div>
                </div>
                <div class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-10 px-[3%]">
                  <form action="" name="add-player" class="flex flex-col lg:flex-row flex-wrap items-center space-x-0 lg:space-x-3 w-[95%] lg:w-[100%]">
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="First name" :required="true"/>
                        <InputBase v-model="dataPlayer.firstName" />
                      </div>
                    </div>
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="Last name" :required="true"/>
                        <InputBase v-model="dataPlayer.lastName" />
                      </div>
                    </div>
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="Mobile number" :required="true"/>
                        <InutTel v-model="dataPlayer.mobileNumber"/>
                      </div>
                    </div>
                  </form>
                </div>
                <div class="flex flex-row justify-center">
                  <div class="justify-center">
                    <button class="btn-modal"
                      type="submit" @click="submitAddPlayer">
                      <img alt="button register coach" class="w-4 h-4 md:w-6 md:h-6 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
                      <span class="mx-2">Add</span>
                      <div class="text-white mx-2 animate-bounce-r">
                        <ArrowRightIcon color="ffffff" w="50" h="50"/>
                      </div>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
        </div>
        <div v-if="isAddCoach">
          <div class="fixed inset-0 z-50 flex justify-center items-center">
            <div class="modal-container">
              <div>
                <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
                  <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Create coach</h1>
                  <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isAddCoach = false">
                    <img alt="Icon close view" src="../../assets/img/register/cancel.svg">
                  </div>
                </div>
                <div class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-10 px-[3%]">
                  <form action="" name="add-player" class="flex flex-col lg:flex-row flex-wrap items-center space-x-0 lg:space-x-3 w-[95%] lg:w-[100%]">
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="First name" :required="true"/>
                        <InputBase v-model="dataCoach.firstName" />
                      </div>
                    </div>
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="Last name" :required="true"/>
                        <InputBase v-model="dataCoach.lastName" />
                      </div>
                    </div>
                    <div class="flex flex-row justify-between mb-2">
                      <div>
                        <LabelField text="Mobile number" :required="true"/>
                        <InutTel v-model="dataCoach.mobileNumber"/>
                      </div>
                    </div>
                  </form>
                </div>
                <div class="flex flex-row justify-center">
                  <div class="justify-center">
                    <button class="btn-modal"
                      type="submit" @click="submitAddCoach">
                      <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
                      <span class="mx-2">Add</span>
                      <div class="text-white mx-2 animate-bounce-r">
                        <ArrowRightIcon color="ffffff" w="50" h="50"/>
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

<style scoped>

.modal-container{
  @apply flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%] max-h-[50%]
    lg:min-h-[40%] lg:max-h-[40%] w-[85%] md:w-[100%] ml-3 lg:ml-0
}

.btn-modal{
  @apply grid place-items-center grid-flow-col flex-row rounded-button-right w-[250px] lg:w-[300px]
    px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover
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

@keyframes bounce {
  0%, 100% {
    transform: translateX(-25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-r {
  animation: bounce 1s infinite;
}

@keyframes bouncel {
  0%, 100% {
    transform: translateX(25%);
    animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
  }
  50% {
    transform: none;
    animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
  }
}

.animate-bounce-l {
  animation: bouncel 1s infinite;
}

.rounded-button-right {
  border-radius: 30px 10px 10px 30px;
}

.rounded-button-left {
  border-radius: 10px 30px 30px 10px;
}
</style>
