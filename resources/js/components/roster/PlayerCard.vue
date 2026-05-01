<script setup>
import {defineProps} from 'vue'
import { toast } from "@/utils/AlertPlugin"
import axios from "axios";
import { storeToRefs } from 'pinia'
import {useTeamStore} from "@/store/team";
import { usePlayerStore } from '@/store/players.js'
import {useRouter} from "vue-router"

const {team} = useTeamStore();
const playerStore = usePlayerStore()
const { players } = storeToRefs(playerStore)
const api_url = process.env.API_ENDPOINT;
const token = JSON.parse(localStorage.getItem('auth')).token
const router = useRouter()
const props = defineProps({
  data: {
    type: Array,
    required: true,
    default: []
  },
  isLoad: {
    type: Boolean,
    required: true,
    default: false,
  }
})

const submitAddPlayer = async (player) => {
  if(player.name.first == "" || player.name.last == "" || player.phone == "" ||
      player.name.first == undefined || player.name.last == undefined || player.phone == undefined ){
    console.log(player);
    console.log(team.id);
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields',
    })
  }else{
    console.log(player);
    console.log(team.id);
    console.log(player.phone);
    let dataForm = new FormData();
    dataForm.append('phone', player.phone)
    dataForm.append('team', team.id)
    dataForm.append('name[first]', player.name.first)
    dataForm.append('name[last]', player.name.last)
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
      getRosterPlayers()
      players.value.push(playerToSetInStore)
      await router.replace("/dashboard")

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
    }})
  }
}
</script>

<template>

  <div class="grid grid-cols-1  w-[100%] items-center px-[2%] gap-5 overflow-y-auto min-h-[50%] max-h-[50%]">
    <div v-if="isLoad">
      <h1 class="text-fungo-darkblue text-3xl text-center">Loading data...</h1>
    </div>
    <div v-else-if="!data.length > 0">
      <h1 class="text-fungo-darkblue text-3xl text-center">No found data</h1>
    </div>
    <div class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1 lg:pl-4 rounded-xl space-x-1 lg:space-x-0 gap-2 lg:gap-24"
      v-for="(item, index) in data" :key="item.id">
      <div class="container-image">
        <template v-if="item.avatar != null">
          <img :src="item.avatar" alt="" class="circle-img">
        </template>
        <img v-else src="../../assets/img/layout/logofungo-nav.png" alt="Avatar Player" class="circle-img">
        <!-- <img alt="Avatar Player" class="circle-img" src="item.avatar ?? '../../assets/img/layout/logofungo-nav.png'"> -->
      </div>
      <div class="w-[150px] max-w-[150px] lg:w-[450px] lg:max-w-[450px] flex flex-col lg:flex-row">
        <div class="flex flex-col text-[14px] lg:text-[16px] items-start">
          <text class="font-fungo-700 text-fungo-blue2 text-[14px] lg:text-[16px]">Player</text>
          <text class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]">{{ item.name.full ?? "Text"}}</text>
          <text class="font-fungo-700 text-fungo-blue2 text-[14px] lg:text-[16px]">Teams</text>
          <text class="font-fungo-700 text-fungo-darkblue text-[14px] lg:text-[16px]">
            <div v-for="(team,index) in item.actual_team">
              {{team.name}}
            </div>
<!--            {{ item.actual_team ??-->
<!--          team.name }}-->
          </text>
        </div>
        <div class="w-[100%] max-w-[100%] h-[2px] max-h-[2px] lg:h-[100%] lg:max-h-[100%] lg:w-[2px] mx-0 lg:mx-4 lg:max-w-[2px] bg-fungo-gray3"></div>
        <div class="flex flex-col text-[14px] lg:text-[16px] items-start">
            <text class="font-fungo-700 text-fungo-blue2 text-[14px] lg:text-[16px]">Age</text>
            <text class="text-fungo-darkblue font-fungo-400 text-[14px] lg:text-[16px]">{{ item.born.age ?? "00"}}</text>
            <text class="font-fungo-700 text-fungo-blue2 text-[14px] lg:text-[16px]">Mobile number</text>
            <text class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px]">{{ item.phone ?? "+ (0) 0000 - 0000"}}</text>
          </div>
      </div>
      <div class="w-[50px] max-w-[50px] lg:w-[200px] lg:max-w-[200px] flex justify-end pr-5 items-center">
        <input type="radio" name="choose_coach" :id="`choose_${item.id}`"
          class="appearance-none checked:bg-green-500 autofill:bg-green-500
          text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8"
          @click="submitAddPlayer(item)">
      </div>
    </div>
  </div>
</template>
<style scoped>

.container-image{
  @apply w-[100%] max-w-[100%] flex justify-center items-center h-[200px] lg:h-[100%] lg:max-h-[100%] lg:w-[110px] lg:max-w-[110px] border-fungo-gray3
}

.circle-img{
  @apply w-[75px] max-w-[75px] lg:w-[110px] lg:max-w-[110px] h-[75px] lg:h-[100%] object-center object-fill mx-auto rounded-full border-8
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
