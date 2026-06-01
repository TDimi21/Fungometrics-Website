<script setup>
import {reactive} from 'vue'
import {BigButtonField, InputBase, LabelField, PasswordField, SwitchField} from '../../components/form'
import axios from "axios";
import {toast} from "../../utils/AlertPlugin";
import Loader from "../../components/Loader.vue";
import router from "../../../router";
import {useAuthStore} from "../../store/auth";
import {useUserStore} from "../../store/user"
import {useTeamStore} from "../../store/team"
import {usePlayerStore} from "../../store/players"
const props = defineProps({
  backgroundImage: {
    type: String,
    required: true
  },

  title: {
    type: String,
    required: true,
  }
})

const {isLogged,setToken} = useAuthStore();
const userStore = useUserStore();
const teamStore = useTeamStore();
const playerStore = usePlayerStore();
const isLoading = reactive({status: true})
const formData = reactive({user: '', password: '', remember: false})

const getTeamIdCandidates = (teamLike) => {
  const ids = [teamLike?.id_team, teamLike?.id]
    .filter(Boolean)
    .map((v) => String(v))
  return [...new Set(ids)]
}

const getSessionCountForTeam = async (api_url, token, teamLike) => {
  const ids = getTeamIdCandidates(teamLike)
  for (const id of ids) {
    try {
      const res = await axios.get(api_url + 'coach/sessions/lasts/' + id, {
        headers: { Authorization: `Bearer ${token}` },
      })
      const d = res?.data?.data ?? {}
      return [
        d.batting,
        d.bullpen,
        d.cage,
        d.live,
        d.weight_ball,
        d.long_toss,
        d.exit_velocity,
      ].reduce((sum, arr) => sum + (Array.isArray(arr) ? arr.length : 0), 0)
    } catch (e) {
      const status = e?.response?.status
      if (status !== 404 && status !== 403) break
    }
  }
  return 0
}

const pickBestTeamForCoach = async (api_url, token, teams) => {
  const list = Array.isArray(teams) ? teams : []
  if (!list.length) return null

  for (const candidate of list) {
    const count = await getSessionCountForTeam(api_url, token, candidate)
    if (count > 0) return candidate
  }

  return list[0]
}


const submitForm = async () => {
  isLoading.status =!isLoading.status;
  const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '';
  await axios.post(api_url + 'login', {email: formData.user.toLowerCase(), password: formData.password}
  ).then(async function (response) {
    isLoading.status =!isLoading.status;
    console.log('login', response.data.data);
    if(props.title === response.data.data.type){
      console.log("Paso 2");
      isLogged.status = !isLogged.status;
       toast.fire({
        icon: 'success',
        title: 'Login User',
        text: response.data.message,
      })
      setToken(response.data.data.token);
      localStorage.setItem('auth', JSON.stringify({ token: response.data.data.token }))
      await userStore.setData(response.data.data);
      if(response.data.data.type == 'player'){
        await router.push('/player-dashboard')
      }else{
        const teams = response?.data?.data?.teams ?? [];
        const selectedTeam = await pickBestTeamForCoach(api_url, response.data.data.token, teams);
        await teamStore.setTeam(selectedTeam ?? teams[0]);
        await teamStore.setTeams(teams);
        await playerStore.setPlayers(response.data.data.players);

        await router.push('/dashboard')
      }
    }else{
      await toast.fire({
        icon: 'warning',
        title: 'Login User',
        text: 'Yo can try with a different type of user',
      })


      await router.push('/')
    }


  }).catch(async function (error) {
    isLoading.status = !isLoading.status;
    if (error.response.data.code === '001V' || error.response.status === 422 || error.response.data.code === '001-E') {

      await toast.fire({
        icon: 'warning',
        title: 'Login Warning !!!',
        text: error.response.data.message,
      })
    } else {
      await toast.fire({
        icon: 'error',
        title: 'Login Error !!!',
        text: "strike 3 is out, have a internal problem, " + error.response.data.message,
      })
    }

  })
}

</script>

<template>
  <Loader v-show="!isLoading.status"/>
  <div :style="{ backgroundImage: `url('${ props.backgroundImage }')` }"
       class="grid h-screen bg-no-repeat bg-cover place-items-center">
    <div class="grid w-full h-full grid-flow-row bg-gradient place-items-center">
      <RouterLink to="/">
      <div class="grid-rows-1"><img alt="Fungo's logo" src="../../assets/img/login/assteslogin/logo-fungo.png"></div>
      </RouterLink>
     <div class="grid-rows-1"> <h2 class="text-4xl text-white font-fungo-300 ">Login as <strong class="font-fungo-800">{{
          props.title
        }}</strong></h2></div>

      <!-- form section -->
      <FormKit type="form" :actions="false" @submit="submitForm" >
<div class="grid grid-rows-2 lg:grid-cols-2 lg:gap-x-12">

      <FormKit
        type="email"
        :label="(props.title == 'player' ? 'Player' : 'Coach') + ' email address'"
        validation="required|email"
        :placeholder="props.title+'@fungometrics.com'"
        v-model="formData.user"
        validation-visibility="blur"
        input-class="$reset h-30 rounded appearance-none bg-transparent border border-fungo-lightblue text-white"
      />
      <FormKit
        type="password"
        :label="(props.title == 'player' ? 'Player' : 'Coach') + ' password'"
        validation="required"
        v-model="formData.password"
        validation-visibility="blur"
        input-class="$reset h-30 rounded appearance-none bg-transparent border border-fungo-lightblue text-white"
      />

        <div class="grid place-content-center lg:col-span-2 md:scale-125">
          <BigButtonField color="red" label="Play Ball" type="submit"></BigButtonField>
        </div>
</div>
      </FormKit>
      <!-- end form section -->

      <div class="grid place-content-center">
        <RouterLink class="text-xl text-red-700 hover:text-red-300"
              to="/forgot-password">Password Recovery
        </RouterLink>
        <div v-if="props.title === 'player'">
          <RouterLink class="text-xl text-white hover:text-gray-300"
                      to="/register/player">Create an account
          </RouterLink>
        </div>
        <div v-if="props.title === 'coach'">
          <RouterLink class="text-xl text-white hover:text-gray-300"
                      to="/register/coach">Create an account
          </RouterLink>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.bg-gradient {
  background: linear-gradient(180deg, rgba(8, 34, 71, 0.61) 0%, #082247 100%);
}
</style>
