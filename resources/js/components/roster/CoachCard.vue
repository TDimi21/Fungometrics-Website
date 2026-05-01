<script setup>
import {defineProps} from 'vue'
import { toast } from "@/utils/AlertPlugin"
import axios from "axios";
import {useTeamStore} from "@/store/team";
import {useRouter} from "vue-router"

const {team} = useTeamStore();
const api_url = process.env.API_ENDPOINT;
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
  <div class="grid grid-cols-1 w-[100%] items-center px-[2%] gap-5 overflow-y-auto min-h-[50%] max-h-[50%]"
    :class="!data.length > 0 ? 'grid-rows-1' : 'grid-rows-2 lg:grid-cols-2'">
    <div v-if="isLoading">
      <h1 class="text-fungo-darkblue text-3xl text-center col-span-2 row-span-2">Loading data...</h1>
    </div>
    <div v-else-if="!data.length > 0">
      <h1 class="text-fungo-darkblue text-3xl text-center col-span-2 row-span-2">No found data</h1>
    </div>
    <div class="bg-fungo-gray4 border-2 border-fungo-gray3 flex flex-row w-[100%] md:w-[100%] md:max-w-[100%] py-5 pl-1 lg:pl-4 rounded-xl"
      v-for="(item, index) in data" :key="item.id">
      <div class="w-[200px] max-w-[200px] lg:w-[100px] lg:max-w-[100px] border-8 border-fungo-gray3 rounded-full">
        <img alt="Avatar Coach" class="w-16 h-full object-center object-cover mx-auto rounded-full" src="../../assets/img/login/assteslogin/ballbutton.svg">
      </div>
      <div class="w-[250px] max-w-[250px] pl-2 lg:pl-5">
        <div class="flex flex-col text-[12px] lg:text-[14px] items-start">
          <text class="font-fungo-700 text-fungo-blue2 pb-2">Coach</text>
          <!-- <text class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]">{{  item.name.full ?? "Example"}}</text> -->
          <text class="text-fungo-darkblue font-fungo-800 text-[14px] lg:text-[16px]">
            {{ item.profile == null ? item.name == null ? "Text Example" : item.name.full : `${item.profile.first_name} ${item.profile.last_name}` }}
          </text>
          <text class="font-fungo-400 text-fungo-darkblue text-[14px] lg:text-[16px] pt-2">{{ item.phone ?? "+ (000) 0000 - 0000"}}</text>
        </div>
      </div>
      <div class="w-[150px] max-w-[150px] lg:w-[200px] lg:max-w-[200px] flex justify-center items-center">
        <input type="radio" name="choose_coach" :id="`choose_${item.id}`"
          class="appearance-none checked:bg-green-500 autofill:bg-green-500
          text-green-500 indeterminate:bg-fungo-gray6 default:ring-2 valid:border-fungo-darkblue h-8 w-8"
          @click="submitAddCoach(item)">
      </div>
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
