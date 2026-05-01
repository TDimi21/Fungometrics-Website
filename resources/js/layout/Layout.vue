<script setup>
import { ref, reactive, onMounted }  from 'vue'
import {useUserStore} from "../store/user";
import {useTeamStore} from "../store/team";
import NavSidebar from './NavigationSidebar.vue'
import {confirm} from "../utils/AlertPlugin";import {toast} from "@/utils/AlertPlugin"
import { InputBase, BigButtonField, InutTel, LabelField } from '@/components/form'
import { SendMsgModal } from '@/components/shared'
import {ArrowLeftIcon, ArrowRightIcon} from '@/components/icons/'
import router from "../../router";
import { usePlayerStore } from '@/store/players.js'
import { useTrainingStore } from '@/store/training.js'
import { storeToRefs } from 'pinia'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  DialogDescription,
  TransitionRoot,
  TransitionChild
} from '@headlessui/vue'
import { TableCancel } from '@/components/icons'
import Loader from "../components/Loader.vue";
import axios from "axios";
import { useAxiosAuth } from '@/composables/axios-auth.js'

const { axiosGet } = useAxiosAuth()
const playerStore = usePlayerStore()
const trainingStore = useTrainingStore()
const { players, setPlayers } = storeToRefs(playerStore)
const { isShowMsgModal } = storeToRefs(trainingStore)

const isOpen = ref(false)
const isChange = ref(false)
const api_url = process.env.API_ENDPOINT;
const isLoading = reactive({status: true})
const token = JSON.parse(localStorage.getItem('auth')).token
const temporalTeams = ref([])
let playersOfTeam = ref([])
function closeModal() {
  isOpen.value = false
}
function openModal() {
  isOpen.value = true
}
const {userData} = useUserStore();
const {team, teams, setTeam } = useTeamStore();
let player = reactive({
  type: [],
  heightFt: 0,
  heightInch: 0,
  firstName: '',
  lastName: '',
  born: '',
  email: '',
  password: '',
  confirmPassword: '',
  mobileNumber: '',
})
const userName = ref(userData.name ? userData.name.full : "")
const typeUser = ref(userData.type)
let hasSidebar = reactive({ active: true })
const toggleSidebar = () => hasSidebar.active ? hasSidebar.active = false
  : hasSidebar.active = true
const logout=()=>{
  confirm.fire().then((result)=>{
    if(result.isConfirmed){
      localStorage.clear();
      location.reload();
    }
  })
}

const getTeamsWithPalyers = async() => {
  if (userData.type == "coach") {
    try {
      isLoading.value = true
      const { data } = await axiosGet('coach/teams')
      temporalTeams.value = data.data
    } catch (error) {
      console.log(error);
    } finally {
      isLoading.value = false
    }
  }
}

const submitAddPlayer = async () => {
  isLoading.status =!isLoading.status;
  if(player.mobileNumber == "" || player.firstName == "" || player.lastName == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    dataForm.append('phone', player.mobileNumber)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', player.firstName)
    dataForm.append('name[last]', player.lastName)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    console.log("paso");
    await axios.post(api_url+'coach/add/players', dataForm, config).then(async function (response) {

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

      players.value.push(playerToSetInStore)

      toast.fire({
        icon: 'success',
        title: 'Player Register',
        text: response.data.message,
      })
      isLoading.status =!isLoading.status;
      isOpen.value = false
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
          title: 'Team Warning !!!',
          text: errorMessage,
        })
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Team Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
      }
      console.log("paso x4");
      isLoading.status =!isLoading.status;
    })
  }
}
const changeTeam = async (info) => {
  isLoading.status =!isLoading.status;
  for (const item of temporalTeams.value) {
    if(item.id_team == info.id){
      await setTeam(info);
      players.value = item.players
      // await setPlayers(item.players);
      isChange.value = false
      isLoading.status =!isLoading.status;
      router.go(router.currentRoute)
    }
  }
}

onMounted(() => {
  getTeamsWithPalyers()
  if (screen.width < 1024) hasSidebar.active = false
})
</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <div>
    <nav class="bg-white border-b border-gray-200 fixed z-30 w-full duration-500" :class="hasSidebar.active ? 'pl-0 lg:pl-64'
     : 'pl-0'">
      <div class="flex items-center justify-between h-20">

        <div class="flex items-center justify-start">
          <button class="w-20 h-20" :class="userData.type == 'player' ? 'bg-fungo-darkblue-player' : 'bg-fungo-dark-gray'" @click="toggleSidebar">
            <img src="../assets/img/layout/btn-menu.svg" alt="Toogle menu button" class="mx-auto">
          </button>
          <div
            v-if="userData.type === 'coach'"
            class="text-fungo-darkblue text-xs lg:text-base"
          >
            <button
              @click="openModal"
              type="button"
              class="ml-4 lg:ml-14 hover:text-fungo-darkblue-hover"
            >
              ADD PLAYERS
            </button>
            <button
              @click="isChange = true"
              type="button"
              class="ml-4 lg:ml-14 hover:text-fungo-darkblue-hover"
            >
            {{team.name}} (change)
            </button>
          </div>
          <div
            v-if="userData.type === 'player'"
            class="text-fungo-darkblue text-xs lg:text-base"
          >
            <button
              type="button"
              class="ml-4 lg:ml-14 hover:text-fungo-darkblue-hover"
            >
            <RouterLink to="/change-password">CHANGE PASSWORD</RouterLink>
            </button>
          </div>
        </div>

        <div class="flex items-center h-full divide-x divide-[#D3D3D3]">
          <img src="../assets/img/layout/logofungo-nav.png" alt="Logo fungo white" width="100" height="65" class="pr-3 hidden md:block">
          <div class="flex flex-col px-2 lg:px-5 h-min">
            <p class="text-fungo-darkblue font-fungo-700 text-[7px] lg:text-base">Welcome {{ typeUser }}!</p>
            <p class="text-fungo-darkblue font-fungo-300 text-[7px] lg:text-base">{{ userName }}</p>
          </div>
          <div class="flex items-center justify-center px-2 lg:px-5 h-full">
            <RouterLink :to="userData.type === 'player' ? '/profile-player' : '/profile'" method="post" as="button" type="button"
                        class="bg-fungo-lightblue rounded-lg lg:p-1.5 p-0.5">
              <img src="../assets/img/icons/i-edit.svg" alt="" class="w-[3em] lg:w-[2em]"  >
            </RouterLink>
          </div>
          <div class="hidden md:grid grid-cols-1 px-2 lg:px-5 h-full content-center justify-items-center">
            <img src="../assets/img/layout/crown.png" alt="" width="30" height="30" v-if="typeUser === 'coach'">
            <img src="../assets/img/login/assteslogin/ballbutton.png" alt="Ball image" width="30" height="30">
          </div>
          <div
            @click="logout"
            class="btn-logout"
          >
            <span class=""><strong>Logout</strong> </span>
            <img src="../assets/img/icons/i-cancel_red.svg" alt="Image circle red with x for close session" class="">
          </div>
        </div>

      </div>
    </nav>

    <div class="flex overflow-hidden bg-white pt-16">

      <aside
        class="fixed z-30 h-full top-0 left-0 flex flex-shrink-0 flex-col transition-width duration-500 bg-fungo-darkblue w-64"
             :class="hasSidebar.active ? 'translate-x-0' : '-translate-x-full'">
        <div class="relative flex-1 flex flex-col min-h-0 pt-0">
          <div class="flex-1 flex flex-col overflow-y-auto">
            <div class="mt-8 pb-6 relative">
              <button
                @click="toggleSidebar"
                class="absolute right-3 top-0 lg:hidden"
              >
                <TableCancel />
              </button>
              <RouterLink :to="userData.type == 'player' ? '/player-dashboard' : '/dashboard'"
                class="grid items-center cursor-pointer" method="post" as="button" type="button">
                <img src="../assets/img/login/assteslogin/logo-fungo.png" alt="Main fungo logo" width="130" height="126"
                   class="mx-auto">
              </RouterLink>
              <div class="absolute w-full h-[3px] bg-gradient-to-r from-[#023E8A] to-[#E10600] bottom-0"></div>
            </div>
            <NavSidebar :collapse="hasSidebar.active" />
          </div>
        </div>
      </aside>

      <div class="h-full w-full relative overflow-y-auto transition-[margin] duration-500" :class="hasSidebar.active
      ? 'ml-0 lg:ml-64' : 'ml-0'">
        <main class="min-h-[94vh] pt-6 pb-24 px-4 overflow-hidden bg-fungo-gray2" v-if="userData.type === 'coach'">
          <slot />
        </main>
        <main class="min-h-[94vh] px-0 overflow-hidden bg-fungo-gray2" v-if="userData.type === 'player'">
          <slot />
        </main>
      </div>

    </div>
  </div>
  <div v-if="isOpen">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%] max-h-[50%]
        lg:min-h-[40%] lg:max-h-[40%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
        <div>
          <div class="flex flex-row w-[100%] items-center mb-3 px-4 ">
            <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Add player</h1>
            <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isOpen = false">
              <img alt="Icon close view" src="../assets/img/register/cancel.svg">
            </div>
          </div>
          <div class="bg-fungo-gray2 flex flex-row w-[100%] items-center mb-5 py-10 px-[3%]">
            <form action="" name="add-player" class="flex flex-col lg:flex-row flex-wrap items-center space-x-0 lg:space-x-3 w-[95%] lg:w-[100%]">
              <div class="mb-2">
                <div>
                  <LabelField text="First name" :required="true"/>
                  <InputBase v-model="player.firstName" />
                </div>
              </div>
              <div class="mb-2">
                <div>
                  <LabelField text="Last name" :required="true"/>
                  <InputBase v-model="player.lastName" />
                </div>
              </div>
              <div class="mb-2">
                <div>
                  <LabelField text="Mobile number" :required="true"/>
                  <InutTel v-model="player.mobileNumber" inputType="tel" />
                </div>
              </div>
            </form>
          </div>
          <div class="flex flex-row justify-center">
            <div class="justify-center">
              <button
                class="grid place-items-center grid-flow-col flex-row rounded-button-right w-[200px] lg:w-[250px]
                  px-2 py-1 text-xl md:text-[12px] lg:text-[16px] bg-fungo-red text-white hover:bg-fungo-red-hover"
                type="submit" @click="submitAddPlayer">
                <img alt="button register coach" class="w-4 h-4 md:w-6 md:h-6 mx-2 md:mx-0" src="../assets/img/login/assteslogin/ballbutton.svg">
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
  <div v-if="isChange">
    <div class="fixed inset-0 z-50 flex justify-center items-center">
      <div class="flex flex-col max-w-5xl rounded-lg shadow-xl overflow-y-auto bg-white border pt-2 pb-4 drop-shadow-xl min-h-[50%] max-h-[50%]
        lg:min-h-[40%] lg:max-h-[40%] w-[85%] md:w-[100%] ml-3 lg:ml-0">
        <div>
          <div class="flex flex-row w-[100%] items-center mb-1 px-4 ">
            <h1 class="text-lg lg:text-2xl text-fungo-red font-fungo-700 my-5">Change team</h1>
            <div class="absolute right-2 md:right-6 cursor-pointer w-[24px] h-[24px] md:w-[32px] md:h-[32px]" @click="isChange = false">
              <img alt="Icon close view" src="../assets/img/register/cancel.svg">
            </div>
          </div>
          <div class="flex flex-row w-[100%] items-center mb-2 py-4 px-[2%]">
            <div class="grid grid-rows-2 grid-cols-1 w-[100%] items-center gap-4 overflow-y-auto min-h-[50%] max-h-[50%]">
              <div class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row justify-between w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1
                lg:pl-4 rounded-xl">
                <div class="w-[100px] max-w-[100px]">
                  <img alt="Team logo" class="h-full object-center object-cover mx-auto" :src="team.logo">
                </div>
                <div class="w-[400px] max-w-[400px] pl-2 lg:pl-5">
                  <div class="flex flex-col text-[12px] lg:text-[14px] items-start">
                    <text class="font-fungo-700 text-fungo-blue2 pb-2">Team</text>
                    <text class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]">{{ team.name ?? "item.number_in_shirt"}}</text>
                    <text class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px] pt-2">
                      <span class="font-fungo-700 text-fungo-darkblue text-[14px] lg:text-[16px]">Coach:</span>
                      {{ userData.name.full ?? "+ (503) 7851 - 7268"}}
                    </text>
                  </div>
                </div>
                <div class="w-[100px] max-w-[100px] flex justify-center items-center">
                  <input type="radio" name="choose_team" :id="`choose_${team.id}`" checked
                    class="appearance-none checked:bg-green-500 autofill:bg-green-500
                    text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8">
                  </div>
              </div>
              <template v-for="(item, index) in teams" :key="item.id">
                <div v-if="item.id != team.id" class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row justify-between w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1
                  lg:pl-4 rounded-xl">
                  <div class="w-[100px] max-w-[100px]">
                    <img alt="Team logo" class="h-full object-center object-cover mx-auto" :src="item.logo">
                  </div>
                  <div class="w-[400px] max-w-[400px] pl-2 lg:pl-5">
                    <div class="flex flex-col text-[12px] lg:text-[14px] items-start">
                      <text class="font-fungo-700 text-fungo-blue2 pb-2">Team</text>
                      <text class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]">{{ item.name ?? "item.number_in_shirt"}}</text>
                      <text class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px] pt-2">
                        <span class="font-fungo-700 text-fungo-darkblue text-[14px] lg:text-[16px]">Coach:</span>
                        {{ userData.name.full ?? "+ (503) 7851 - 7268"}}
                      </text>
                    </div>
                  </div>
                  <div class="w-[100px] max-w-[100px] flex justify-center items-center">
                    <input type="radio" name="choose_team" :id="`choose_${item.id}`"
                      class="appearance-none checked:bg-green-500 autofill:bg-green-500
                      text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8" @click="changeTeam(item)">
                    </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="opacity-70 fixed inset-0 z-40 bg-fungo-darkblue"></div>
  </div>

  <SendMsgModal v-if="isShowMsgModal"/>
</template>
<style lang="css" scoped>
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

.size-icon {
  @apply w-2 h-2
}

.btn-logout {
  @apply text-fungo-darkblue hover:text-fungo-darkblue-hover grid grid-cols-1 items-center content-center
  justify-items-center h-full px-2 lg:px-5 cursor-pointer text-xs lg:text-lg;
}
</style>
