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
    <div class="edit-profile-page w-full px-4 md:px-8 py-6 md:py-10">
      <div class="max-w-6xl mx-auto practice-shell p-5 md:p-8">
      <h1 class="text-white text-3xl md:text-[42px] text-center mb-7 font-fungo-700 tracking-wide">Edit Profile</h1>

      <section class="profile-card">
        <div class="profile-card-header">
          <h2 class="profile-card-title">Team</h2>
          <button class="profile-header-link" @click="router.push({ name: 'manage.team' })">
            Create new team
            <ArrowHeadRightIcon color="002060"/>
          </button>
        </div>

        <div class="profile-card-body">
          <div class="image-holder">
            <InputImage v-model="teamData.logo" label="Team logo"/>
          </div>

          <div class="inputs-grid team-grid">
            <div>
              <LabelField :required="true" text="Team Name"/>
              <InputBase v-model="teamData.name"/>
            </div>
            <div>
              <LabelField :required="true" text="State"/>
              <SelectField v-model="teamData.state" :options="states"/>
            </div>
            <div>
              <LabelField :required="true" text="Zip code"/>
              <InputBase v-model="teamData.zip"/>
            </div>
          </div>

          <div class="action-row">
            <button class="btn-edit-profile" type="submit" @click="submitEditTeam">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">Update Team</span>
              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
            </button>
          </div>
        </div>
      </section>

      <section class="profile-card mt-6">
        <div class="profile-card-header">
          <h2 class="profile-card-title">Coach</h2>
          <RouterLink to="/change-password" class="profile-header-link">
            Create new password
            <ArrowHeadRightIcon color="002060"/>
          </RouterLink>
        </div>

        <div class="profile-card-body">
          <div class="image-holder">
            <InputImage label="Picture coach" v-model="coach.avatar" inputClasses="h-52"/>
          </div>

          <div class="inputs-grid coach-grid">
            <div>
              <LabelField text="First name" :required="true"/>
              <InputBase v-model="coach.firstName" />
            </div>
            <div>
              <LabelField text="Last name" :required="true"/>
              <InputBase v-model="coach.lastName" />
            </div>
            <div>
              <LabelField text="E-Mail address" :required="true"/>
              <InputBase v-model="coach.email" inputType="email" :enableInput="true"/>
            </div>
            <div>
              <LabelField text="Mobile number" :required="true"/>
              <InutTel v-model="coach.mobileNumber"/>
            </div>
            <div>
              <LabelField :required="true" text="City"/>
              <InputBase v-model="coach.city"/>
            </div>
            <div>
              <LabelField :required="true" text="State"/>
              <SelectField v-model="coach.state" :options="states"/>
            </div>
          </div>

          <div class="action-row">
            <button class="btn-edit-profile" type="submit" @click="submitEditCoach">
              <img alt="button register coach" class="w-6 h-6 md:w-8 md:h-8 mx-2 md:mx-0" src="../../assets/img/login/assteslogin/ballbutton.svg">
              <span class="mx-2">Update Coach</span>
              <div class="text-white mx-2 animate-bounce-r"><ArrowRightIcon color="ffffff" w="50" h="50"/></div>
            </button>
          </div>
        </div>
      </section>
      </div>
    </div>
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
  @apply grid place-items-center grid-flow-col flex-row w-[260px] lg:w-[320px] rounded-t-[30px] rounded-r-[10px] rounded-b-[10px] rounded-l-[30px]
    px-2 py-2 text-base lg:text-[18px] bg-fungo-red text-white hover:bg-fungo-red-hover font-fungo-700 tracking-wide
}

.edit-profile-page {
  background: #060b14;
}

.practice-shell {
  background: rgba(10, 16, 32, 0.58);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  box-shadow: 0 18px 45px rgba(0, 0, 0, 0.28);
}

.profile-card {
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 1rem;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.72);
}

.profile-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  background: rgba(10, 16, 32, 0.9);
  border-bottom: 1px solid rgba(255, 255, 255, 0.14);
}

.profile-card-title {
  color: #ffffff;
  font-size: 1.35rem;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.profile-header-link {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  color: #002060;
  font-weight: 900;
  font-size: 0.95rem;
}

.profile-card-body {
  padding: 1rem;
  background: rgba(15, 23, 42, 0.45);
}

.image-holder {
  max-width: 420px;
  margin: 0 auto 1rem auto;
}

.inputs-grid {
  display: grid;
  gap: 0.9rem;
}

.team-grid {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.coach-grid {
  grid-template-columns: repeat(1, minmax(0, 1fr));
}

.action-row {
  margin-top: 1.1rem;
  display: flex;
  justify-content: center;
}

.edit-profile-page :deep(label),
.edit-profile-page :deep(input),
.edit-profile-page :deep(select),
.edit-profile-page :deep(option),
.edit-profile-page :deep(span),
.edit-profile-page :deep(h1),
.edit-profile-page :deep(h2),
.edit-profile-page :deep(h3),
.edit-profile-page :deep(button),
.edit-profile-page :deep(a) {
  font-weight: 800;
}

.edit-profile-page :deep(.profile-card-body label) {
  color: #e2e8f0;
  font-size: 0.95rem;
  margin-bottom: 0.35rem;
}

.edit-profile-page :deep(.profile-card-body input),
.edit-profile-page :deep(.profile-card-body select) {
  width: 100% !important;
  min-width: 0;
  height: 2.7rem;
  padding: 0.5rem 0.7rem;
  border-radius: 0.55rem;
  border: 1px solid rgba(255, 255, 255, 0.22) !important;
  background: rgba(10, 16, 32, 0.9) !important;
  color: #f8fafc !important;
  font-size: 0.95rem;
}

.edit-profile-page :deep(.profile-card-body input:focus),
.edit-profile-page :deep(.profile-card-body select:focus) {
  outline: none;
  border-color: rgba(192, 0, 0, 0.65) !important;
  box-shadow: 0 0 0 2px rgba(192, 0, 0, 0.12);
}

.edit-profile-page :deep(.profile-card-body .arrow-position) {
  top: 6px;
  right: 8px;
}

.edit-profile-page :deep(.image-input-label) {
  color: #ffffff !important;
  font-weight: 900 !important;
}

.edit-profile-page :deep(.image-edit-btn) {
  background: #002060 !important;
}

.edit-profile-page :deep(.image-preview-panel) {
  background: rgba(10, 16, 32, 0.95) !important;
  border-color: rgba(255, 255, 255, 0.22) !important;
}

.edit-profile-page :deep(.profile-card-body .input-tel-decorator) {
  height: calc(2.7rem - 2px);
}

@media (min-width: 768px) {
  .profile-card-header {
    padding: 0.9rem 1.6rem;
  }

  .profile-card-body {
    padding: 1.4rem 1.6rem 1.6rem;
  }

  .team-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .coach-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .profile-card-title {
    font-size: 1.35rem;
  }

  .profile-card-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
