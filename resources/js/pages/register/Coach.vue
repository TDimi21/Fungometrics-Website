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
const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
const isLoading = reactive({status: true})
const userStore = useUserStore();
const teamStore = useTeamStore();

const submitCoach = async () => {
  if (!props.user && coach.password !== coach.confirmPassword) {
    toast.fire({
      icon: 'warning',
      title: 'Validation',
      text: 'The passwords are not the same',
    })
    return;
  }

  isLoading.status =!isLoading.status;

  let dataForm = new FormData();
  dataForm.append('email', coach.email.toLowerCase())
  dataForm.append('password', coach.password)
  dataForm.append('phone', coach.mobileNumber)
  dataForm.append('zip', coach.zipCode)
  dataForm.append('state', coach.state,)
  dataForm.append('city', coach.city,)
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

  // InputImage now emits the selected File directly via v-model.
  const imageTemp = coach.teamLogo instanceof File ? coach.teamLogo : '';
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
  <div class="flex flex-row h-screen overflow-hidden flex-nowrap font-fungo-poppins">
    <BannerLeftRegister :background-image="imgPath" title="Coach"/>
    <section class="relative w-full md:w-[65%] h-screen overflow-y-auto">
      <RouterLink to="/" class="absolute right-6 top-6 z-20" ><img alt="Icon close view"
                                                            src="@/assets/img/register/cancel.svg">
      </RouterLink>
      <form class="flex flex-col w-full min-h-full" @submit.prevent="submitCoach">
        <div ref="divContainer"></div>
        <!-- header form: team logo + team name -->
        <div class="form-header">
          <template v-if="!props.user">
            <div class="flex flex-col w-2/3 sm:w-1/2 lg:w-1/4">
              <InputImage v-model="coach.teamLogo" label="Team logo" inputClasses="h-40"/>
            </div>
            <div class="flex flex-col w-full sm:w-3/4 lg:w-2/5 lg:ml-11">
              <LabelField :required="true" class="mb-3 text-fungo-darkblue" text="Team Name"/>
              <InputBase v-model="coach.teamName" inputClasses="w-full"/>
            </div>
          </template>
          <div v-else class="flex justify-center w-full">
            <img class="h-36 object-contain" alt="Fungo's logo" src="@/assets/img/login/assteslogin/logo-fungo.png">
          </div>
        </div>
        <!-- end header form -->

        <!-- body form -->
        <div class="form-body">

          <!-- first row -->
          <div class="form-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="First name"/>
              <InputBase v-model="coach.firstName" inputClasses="w-full"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Last name"/>
              <InputBase v-model="coach.lastName" inputClasses="w-full"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="E-Mail address"/>
              <InputBase v-model="coach.email" inputType="email" inputClasses="w-full"/>
            </div>
          </div>

          <!-- second row -->
          <div class="form-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Mobile number"/>
              <InutTel v-model="coach.mobileNumber" inputType="tel"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Password"/>
              <PasswordField v-model="coach.password"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Confirm password"/>
              <PasswordField v-model="coach.confirmPassword"/>
            </div>
          </div>

          <!-- third row -->
          <div class="form-row">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Level"/>
              <SelectField v-model="coach.levels" :options="coachLevels"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="City"/>
              <InputBase v-model="coach.city" inputClasses="w-full"/>
            </div>
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="State"/>
              <SelectField v-model="coach.state" :options="states"/>
            </div>
          </div>

          <!-- fourth row -->
          <div class="form-row sm:items-end">
            <div class="box-input-col">
              <LabelField :required="true" class="mb-2 text-fungo-darkblue" text="Zip code"/>
              <InputBase v-model="coach.zipCode" inputClasses="w-full"/>
            </div>
            <div class="box-input-col sm:flex-row sm:justify-end sm:items-end">
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
  @apply bg-[#F7F8F9] flex flex-col sm:flex-row justify-center items-center gap-6 px-8 py-10 lg:py-12;
}

.form-body {
  @apply bg-[#E7EAEE] flex-1 flex flex-col justify-center gap-6 lg:gap-8 px-6 py-10 md:px-12 lg:px-16 2xl:px-28;
}

.form-row {
  @apply flex flex-col gap-6 justify-between sm:flex-row lg:gap-8;
}

.box-input-col {
  @apply flex flex-col w-full sm:w-[31%];
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
