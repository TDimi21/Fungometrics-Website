<script setup>
import Layout from '@/layout/Layout.vue'
import {
  SelectField,
  InputBase,
  InputImage,
  InutTel,
  LabelField
} from '@/components/form'
import { ArrowRightIcon, ArrowHeadRightIcon, ArrowDownIcon } from '@/components/icons'
import {reactive} from 'vue'
import { useUserStore } from '@/store/user'
import {useTeamStore} from "@/store/team";
import Loader from "@/components/Loader.vue";
import {useRouter} from "vue-router"
import {states} from '../../utils'
import {toast} from "../../utils/AlertPlugin";

const {userData} = useUserStore();
const {team, setTeam } = useTeamStore();
const isLoading = reactive({status: true})
const router = useRouter()
const token = JSON.parse(localStorage.getItem('auth')).token
const api_url = process.env.API_ENDPOINT;
const coach = reactive({
  firstName: userData.name.first,
  lastName: userData.name.last,
  email: userData.email,
  mobileNumber: userData.phone,
  // levels: userData.level,
  city: userData.city,
  avatar: team.logo ?? '../../assets/img/layout/logofungo-nav.png',
  state: userData.state,
  zipCode: userData.zip,
});
const teamData = reactive({
  name: team.name,
  zip: team.zip,
  state: team.state,
  logo: team.logo ?? '../../assets/img/layout/logofungo-nav.png',
  city: ''
});

const submitEditTeam = async () => {
  isLoading.status =!isLoading.status;
  const imageTemp = teamData.logo.files[0] ?? team.logo
  if(teamData.name == "" || teamData.zip == "" || teamData.state == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields of team',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    if(imageTemp == undefined || imageTemp == team.logo){
      dataForm.append('logo', team.logo)
    }else{
      dataForm.append('logo', imageTemp)
    }

    dataForm.append('name', teamData.name)
    dataForm.append('state', teamData.state,)

    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/edit/teams/'+team.id, dataForm, config).then(async function (response) {
      let tempResponse = response.data.data
      let teamToSetInStore = {
        "id": tempResponse.id,
        "name": tempResponse.name,
        "logo": tempResponse.logo,
        "zip": tempResponse.zip,
        "state": tempResponse.state,
        "created_at": tempResponse.created_at,
        "updated_at": tempResponse.updated_at,
        "num_players": team.num_players
      }
      await setTeam(teamToSetInStore);
      toast.fire({
        icon: 'success',
        title: 'Team Update',
        text: response.data.message,
      })
      isLoading.status =!isLoading.status;
      router.replace("/")
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
          title: 'Team Warning !!!',
          text: errorMessage,
        })
        isLoading.status =!isLoading.status;
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Team Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
        isLoading.status =!isLoading.status;
      }
    })
  }
}

const submitEditCoach = async () => {
  isLoading.status =!isLoading.status;
  const imageTemp = coach.avatar.files[0] ?? team.logo
  if(coach.firstName == "" || coach.lastName == "" || coach.email == "" || coach.mobileNumber == ""){
    toast.fire({
      icon: 'warning',
      title: 'Validation !!!',
      text: 'You must complete all the fields of coach',
    })
    isLoading.status =!isLoading.status;
  }else{
    let dataForm = new FormData();
    if(imageTemp == undefined || imageTemp == team.logo){
      dataForm.append('picture', team.logo)
    }else{
      dataForm.append('picture', imageTemp)
    }

    dataForm.append('first_name', coach.firstName)
    dataForm.append('last_name', coach.lastName)
    dataForm.append('email', coach.email)
    dataForm.append('phone', coach.mobileNumber)
    dataForm.append('state', coach.state,)
    dataForm.append('city', coach.city,)
    const config = {
      headers: { Authorization: `Bearer ${token}` }
    };
    await axios.post(api_url+'coach/edit/', dataForm, config).then(async function (response) {
      let tempResponse = response.data.data
      userData.name.first = coach.firstName
      userData.name.last = coach.lastName
      userData.name.full = `${coach.firstName} ${coach.lastName}`
      userData.avatar = tempResponse.profile.picture
      userData.phone = coach.mobileNumber
      userData.state = coach.state
      userData.zip = coach.zipCode
      userData.city = coach.city

      toast.fire({
        icon: 'success',
        title: 'Coach Update',
        text: response.data.message,
      })
      isLoading.status =!isLoading.status;
      router.replace("/")
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
        isLoading.status =!isLoading.status;
      } else {
        await toast.fire({
          icon: 'error',
          title: 'Team Error !!!',
          text: "strike 3 is out, have a internal problem, " +error.response.data.message,
        })
        isLoading.status =!isLoading.status;
      }
    })
  }
}
</script>

<template>
  <Layout>
    <Loader v-show="!isLoading.status"/>
    <h1 class="text-fungo-red text-2xl md:text-[40px] text-center mt-9 mb-6 font-fungo-700">Edit Profile</h1>
    <section class="bg-fungo-gray2 w-full h-auto">
      <section class="bg-fungo-gray4 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[1%]">
        <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
          <div class="w-full lg:w-[15%] my-2 md:my-6 text-center">
            <h1 class="text-fungo-darkblue text-lg md:text-[30px] font-fungo-700">Team</h1>
          </div>
          <button class="w-[100%] lg:w-[82%] flex justify-center lg:justify-end my-2 md:my-6">
            <h1 @click="router.push({ name: 'manage.team' })" class="text-fungo-blue2 text-base md:text-[16px] font-fungo-700 flex items-center">
              Create new team <ArrowHeadRightIcon color="0077B6"/> </h1>
          </button>
        </div>
      </section>
      <section class="w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[110px] lg:mt-[80px]">
        <div class="w-full h-auto px-4 my-3 md:my-9 pt-[2 %]">
          <div class="flex flex-col justify-center items-center">
            <div class="flex flex-col w-full lg:w-1/4">
              <InputImage v-model="teamData.logo" label="Team logo"/>
            </div>
          </div>
          <div class="mt-5 w-[100%] flex justify-between flex-col md:flex-row">
            <div class="box-input-col">
              <LabelField :required="true" text="Team Name"/>
              <InputBase v-model="teamData.name"/>
            </div>
            <div class="box-input-col">
              <div>
                <LabelField :required="true" text="State"/>
                <SelectField v-model="teamData.state" :options="states"/>
              </div>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" text="Zip code"/>
              <InputBase v-model="teamData.zip"/>
            </div>
          </div>
          <div class="w-[100%] flex justify-center px-4 my-4 md:my-9">
            <button class="btn-edit-profile rounded-button-right" type="submit" @click="submitEditTeam">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">Update</span>
              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
            </button>
          </div>
        </div>
      </section>
    </section>
    <section class="w-full min-h-[1050px] md:min-h-[750px] mt-[800px] lg:mt-[630px]">
      <section class="bg-fungo-gray4 w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[1%]">
        <div class="flex flex-col items-center lg:flex-row space-y-6 lg:space-y-0 lg:space-x-3">
          <div class="w-full lg:w-[15%] my-2 md:my-6 text-center">
            <h1 class="text-fungo-darkblue text-lg md:text-[30px] font-fungo-700">Coach</h1>
          </div>
          <button class="w-[100%] lg:w-[82%] flex justify-center lg:justify-end my-2 md:my-6">
            <RouterLink to="/change-password">
              <h1 class="text-fungo-blue2 text-base md:text-[16px] font-fungo-700 flex items-center">Create new password <ArrowHeadRightIcon color="0077B6"/> </h1>
            </RouterLink>
          </button>
        </div>
      </section>
      <section class="w-full h-auto lg:h-[80px] absolute left-0 px-[10%] md:px-[5%] mt-[140px] lg:mt-[80px]">
        <div class="w-full h-auto px-4 my-9 pt-[2%]">
          <div class="flex flex-col justify-center items-center">
            <div class="flex flex-col w-full lg:w-1/4">
              <InputImage label="Picture coach"  v-model="coach.avatar" inputClasses="h-52"/>
            </div>
          </div>
          <div class="form-body">
            <div class="flex flex-col md:flex-row justify-between w-full">
              <div class="box-input-col">
                <LabelField text="First name" :required="true"/>
                <InputBase v-model="coach.firstName" />
              </div>
              <div class="box-input-col">
                <LabelField text="Last name" :required="true"/>
                <InputBase v-model="coach.lastName" />
              </div>
              <div class="box-input-col">
                <LabelField text="E-Mail address" :required="true"/>
                <InputBase v-model="coach.email" inputType="email" :enableInput="true"/>
              </div>
              <div class="box-input-col">
                <LabelField text="Mobile number" :required="true"/>
                <InutTel v-model="coach.mobileNumber"/>
              </div>
            </div>

            <div class="flex flex-col md:flex-row justify-between w-full my-2">
              <div class="box-input-col">
                <LabelField :required="true" text="City"/>
                <InputBase v-model="coach.city"/>
              </div>
              <div class="box-input-col">
                <div>
                  <LabelField :required="true" text="State"/>
                  <SelectField v-model="coach.state" :options="states"/>
                </div>
              </div>
            </div>
            <div class="w-[100%] flex justify-center px-4 my-6">
              <button class="btn-edit-profile rounded-button-right" type="submit" @click="submitEditCoach">
                <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
                <span class="mx-2">Update</span>
                <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
              </button>
            </div>
          </div>
        </div>
      </section>
    </section>
  </Layout>
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

.btn-edit-profile{
  @apply grid place-items-center grid-flow-col flex-row w-[250px] lg:w-[300px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    px-2 py-1 text-xl md:text-[16px] lg:text-[20px] bg-fungo-red text-white hover:bg-fungo-red-hover
}

.form-body {
  @apply bg-[#E7EAEE] h-[57%] px-1 md:px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col w-full;
}

.box-input-col {
  @apply flex flex-col w-full md:w-[22%];
}
</style>
