<script setup>
import {reactive, ref} from "vue";
import axios from "axios";
import {useRouter} from "vue-router"
import {coachLevels, states} from '../../utils'
import {toast} from "../../utils/AlertPlugin";
import {useUserStore} from "../../store/user"
import {useTeamStore} from "../../store/team"
import {useAuthStore} from "../../store/auth";

import {BigButtonField, InputImage, InutTel, LabelField, PasswordField, SelectField} from "../../components/form"
import BannerLeftRegister from "../../components/register/BannerLeftRegister.vue";
import BannerCoach from "../../assets/img/register/banner-coach.jpg";
import InputBase from "../../components/form/InputBase.vue";
import Loader from "../../components/Loader.vue";

const {isLogged,setToken} = useAuthStore();

const props = defineProps({
  user: Object
})
const emits = defineEmits(['update']);

const imgPath = ref(BannerCoach);
const divContainer = ref('');
const coach = reactive({
  teamLogo: '',
  teamName: '',
  firstName: props.user?.profile?.first_name,
  lastName: props.user?.profile?.last_name,
  email: '',
  mobileNumber: props.user?.phone,
  password: '',
  confirmPassword: '',
  levels: '',
  city: '',
  state: '',
  zipCode: ''
});

const router = useRouter()
const api_url = process.env.API_ENDPOINT;
const isLoading = reactive({status: true})
const userStore = useUserStore();
const teamStore = useTeamStore();

const submitCoach = async () => {
  isLoading.status =!isLoading.status;

  let dataForm = new FormData();
  dataForm.append('email', coach.email.toLowerCase())
  dataForm.append('password', coach.password)
  dataForm.append('phone', coach.mobileNumber)
  dataForm.append('zip', coach.zipCode)
  dataForm.append('state', coach.state,)
  dataForm.append('city', coach.city,)
  dataForm.append('team', coach.teamName)
  dataForm.append('team', coach.teamName)
  dataForm.append('profile[name][first]', coach.firstName)
  dataForm.append('profile[name][last]', coach.lastName)
  dataForm.append('profile[level]', coach.levels)

  if(props.user)
  {
    emits('update', dataForm);
    isLoading.status = false;
    return ;
  }

  const imageTemp = coach.teamLogo.files[0];
  dataForm.append('logo', imageTemp)
  await axios.post(api_url+'coach/register', dataForm
    ).then(async function (response) {
    //   await userStore.setData(response.data.data.user);
    //   await teamStore.setTeam(response.data.data.team);
    //   setToken(response.data.data.token);
    // isLogged.status = !isLogged.status;

    isLoading.status =!isLoading.status;
      toast.fire({
        icon: 'success',
        title: 'Coach Register',
        text: response.data.message,
      })

      await router.replace("/login/coach")
    }).catch(async function (error){
      console.log(error)
    if (error.response.data.code === '001V' || error.response.status === 422 ) {
      await toast.fire({
        icon: 'warning',
        title: 'Coach Warning !!!',
        text: error.response.data.message,
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


</script>
<template>
  <Loader v-show="!isLoading.status"/>
  <div class="flex flex-row h-screen overflow-y-auto flex-nowrap lg:overflow-hidden font-fungo-poppins">
    <BannerLeftRegister :background-image="imgPath" title="Coach"/>
    <section class="w-full md:w-[65%]">
      <RouterLink to="/" class="absolute right-6 top-6" ><img alt="Icon close view"
                                                            src="@/assets/img/register/cancel.svg">
      </RouterLink>
      <form class="w-full h-full" @submit.prevent="submitCoach">
        <div ref="divContainer"></div>
        <!-- header form -->
        <div class="form-header">
          <div  v-if="!props.user" class="w-[75%] flex flex-col items-center">
            <div class="flex flex-col w-1/2">
              <InputImage v-model="coach.teamLogo" label="Team logo"/>
				
            </div>
            <div class="flex flex-col w-1/2 md:w-1/">
              <LabelField :required="true" class="mt-8 mb-5 text-fungo-darkblue" text="Team Name"/>
              <InputBase v-model="coach.teamName"/>
            </div>
          </div>
          <div v-else>
            <img alt="Fungo's logo" src="@/assets/img/login/assteslogin/logo-fungo.png">
          </div>
        </div>
        <!-- end header form -->

        <!-- body form -->
        <div class="form-body">

          <!-- first row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="First name"/>
              <InputBase v-model="coach.firstName"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Last name"/>
              <InputBase v-model="coach.lastName"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="E-Mail adress"/>
              <InputBase v-model="coach.email" inputType="email"/>
            </div>
          </div>

          <!-- second row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Mobile number"/>
              <InutTel v-model="coach.mobileNumber" inputType="tel"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Password"/>
              <PasswordField v-model="coach.password"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Confirm password"/>
              <PasswordField v-model="coach.confirmPassword"/>
            </div>
          </div>

          <!-- third row -->
          <div class="flex flex-col justify-between lg:flex-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Level"/>
              <SelectField v-model="coach.levels" :options="coachLevels"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="City"/>
              <InputBase v-model="coach.city"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="State"/>
              <SelectField v-model="coach.state" :options="states"/>
            </div>
          </div>

          <!-- fourth row -->
          <div class="flex flex-col justify-between space-y-4 lg:flex-row lg:space-y-0">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-5 text-fungo-darkblue" text="Zip code"/>
              <InputBase v-model="coach.zipCode"/>
            </div>
            <div class="justify-end box-input-col">
              <BigButtonField color="red" label="Register" type="submit"/>
            </div>
          </div>
        </div>
        <!-- end body form -->
      </form>
    </section>
  </div>
</template>

<style scoped>
.form-header {
  @apply bg-[#F7F8F9] h-[43%] flex flex-col justify-center items-center;
}

.form-body {
  @apply bg-[#E7EAEE] h-full md:h-[70%] lg:h-[57%] px-20 py-12 2xl:px-28 2xl:pt-10 2xl:pb-20 flex flex-col justify-between;
}

.box-input-col {
  @apply flex flex-col w-full lg:w-[31%];
}

.loading {
  border: 16px solid #f3f3f3; /* Light grey */
  border-top: 16px solid #3498db; /* Blue */
  border-radius: 50%;
  width: 120px;
  height: 120px;
  animation: spin 2s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}


</style>
