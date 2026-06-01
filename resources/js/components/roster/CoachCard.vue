<script setup>
import {defineProps} from 'vue'
import { toast } from "@/utils/AlertPlugin"
import axios from "axios";
import {useTeamStore} from "@/store/team";
import {useRouter} from "vue-router"

const {team} = useTeamStore();
const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
const token = JSON.parse(localStorage.getItem('auth')).token
const router = useRouter()

const props = defineProps({
  data: {
    type: Array,
    required: true,
    default: []
  },
  isLoading: {
    type: Boolean,
    required: true,
    default: false,
  },
})

// const coach = reactive({
//   teamLogo: '',
//   teamName: '',
//   firstName: '',
//   lastName: '',
//   email: '',
//   mobileNumber: '',
//   password: '',
//   confirmPassword: '',
//   levels: '',
//   city: '',
//   state: '',
//   zipCode
// })

const submitAddCoach = async (coach) => {
  if(coach.profile != null){
    if(coach.profile.first_name == "" || coach.profile.last_name == "" || coach.phone == ""){
      toast.fire({
        icon: 'warning',
        title: 'Validation !!!',
        text: 'You must complete all the fields',
      })
    }else{
      let dataForm = new FormData();
      dataForm.append('phone', coach.phone)
      dataForm.append('team', team.id)
      dataForm.append('name[first]', coach.profile.first_name)
      dataForm.append('name[last]', coach.profile.last_name)
      const config = {
        headers: { Authorization: `Bearer ${token}` }
      };
      await axios.post(api_url+'coach/add/coaches', dataForm, config).then(async function (response) {
        toast.fire({
          icon: 'success',
          title: 'Coach Register',
          text: response.data.message,
        })

        await router.replace("/roster")
        router.go("/roster")
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
      })
    }
  }else{
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: `This coach don't have a profile`,
    })
  }
}
</script>

<template>

  <!-- <div class="flex flex-col lg:flex-row  w-[100%] items-center mb-5 py-5 px-[2%] gap-7"> -->
  <div class="flex flex-col gap-3 w-full">
    <div v-if="isLoading">
      <p class="text-app-muted text-center py-8">Loading…</p>
    </div>
    <div v-else-if="!data.length > 0">
      <p class="text-app-muted text-center py-8">No coaches found</p>
    </div>

    <div
      v-else
      v-for="(item, index) in data"
      :key="item.id"
      class="flex items-center gap-4 bg-app-card border border-white/10 rounded-xl px-4 py-3
             hover:border-app-blue/40 transition"
    >
      <!-- Avatar -->
      <div class="w-12 h-12 rounded-full overflow-hidden ring-2 ring-white/10 flex-shrink-0 bg-app-navy flex items-center justify-center">
        <img v-if="item.avatar" :src="item.avatar" alt="" class="w-full h-full object-cover" />
        <span v-else class="text-white/60 font-fungo-700 text-sm">
          {{ item.profile ? (item.profile.first_name?.[0] ?? '') + (item.profile.last_name?.[0] ?? '') : (item.name?.full?.[0] ?? '?') }}
        </span>
      </div>

      <!-- Info -->
      <div class="flex-1 min-w-0">
        <p class="text-white font-fungo-700 text-sm truncate">
          {{ item.profile ? `${item.profile.first_name} ${item.profile.last_name}` : (item.name?.full ?? '—') }}
        </p>
        <p class="text-app-muted text-xs mt-0.5">{{ item.phone ?? '—' }}</p>
      </div>

      <!-- Add button -->
      <button
        @click="submitAddCoach(item)"
        class="flex-shrink-0 flex items-center gap-1.5 bg-app-blue/10 hover:bg-app-blue text-app-blue hover:text-white
               text-xs font-fungo-700 px-3 py-1.5 rounded-xl border border-app-blue/30 hover:border-app-blue transition"
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
