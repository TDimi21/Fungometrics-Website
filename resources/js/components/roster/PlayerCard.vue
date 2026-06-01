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
const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
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
  <div class="flex flex-col gap-3 w-full">
    <div v-if="isLoad" class="text-app-muted text-center py-8">Searching…</div>
    <div v-else-if="!data.length" class="text-app-muted text-center py-8">No players found</div>

    <div
      v-else
      v-for="item in data"
      :key="item.id"
      class="flex items-center gap-4 bg-app-card border border-white/10 rounded-xl px-4 py-3
             hover:border-app-red/40 transition group"
    >
      <!-- Avatar -->
      <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-white/10 flex-shrink-0 bg-app-navy flex items-center justify-center">
        <img v-if="item.avatar" :src="item.avatar" alt="" class="w-full h-full object-cover" />
        <span v-else class="text-white/60 font-fungo-700 text-sm">
          {{ (item.name?.first?.[0] ?? '') + (item.name?.last?.[0] ?? '') }}
        </span>
      </div>

      <!-- Info -->
      <div class="flex-1 min-w-0">
        <p class="text-white font-fungo-700 text-sm truncate">{{ item.name?.full ?? '—' }}</p>
        <div class="flex flex-wrap gap-x-3 mt-0.5">
          <span class="text-app-muted text-xs">{{ item.phone ?? '—' }}</span>
          <span v-if="item.born?.age" class="text-app-muted text-xs">Age {{ item.born.age }}</span>
          <span v-for="t in item.actual_team" :key="t.name" class="text-app-blue text-xs">{{ t.name }}</span>
        </div>
      </div>

      <!-- Add button -->
      <button
        @click="submitAddPlayer(item)"
        class="flex-shrink-0 flex items-center gap-1.5 bg-app-red/10 hover:bg-app-red text-app-red hover:text-white
               text-xs font-fungo-700 px-3 py-1.5 rounded-xl border border-app-red/30 hover:border-app-red transition"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add
      </button>
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
