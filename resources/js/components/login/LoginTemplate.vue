<script setup>
import { reactive, ref, computed } from 'vue'
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
const loginMode = ref(props.title === 'player' ? 'claim' : 'email')
const claimForm = reactive({ phone: '', teamCode: '' })
const claimStep = ref('find')
const claimedSession = ref(null)
const claimCredentials = reactive({ email: '', password: '', confirmPassword: '' })
const isPlayerLogin = computed(() => props.title === 'player')
const isClaimMode = computed(() => isPlayerLogin.value && loginMode.value === 'claim')
const isClaimCredentialsStep = computed(() => isClaimMode.value && claimStep.value === 'credentials')

const normalizeDigits = (value) => String(value || '').replace(/\D+/g, '')

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

const applyAuthSession = async ({ payload, apiUrl }) => {
  const token = payload?.token
  const user = payload?.user || null
  const team = payload?.team || null
  const teams = user?.teams || []

  if (!token || !user) {
    throw new Error('missing auth payload')
  }

  setToken(token)
  isLogged.status = true
  localStorage.setItem('auth', JSON.stringify({ token }))
  await userStore.setData(user)

  if ((user?.type || '').toLowerCase() === 'player') {
    await teamStore.setTeam(team || {})
    await teamStore.setTeams(team ? [team] : [])
    await router.push('/player-dashboard')
    return
  }

  const rosterTeams = Array.isArray(teams) ? teams : []
  const selectedTeam = await pickBestTeamForCoach(apiUrl, token, rosterTeams)
  await teamStore.setTeam(selectedTeam ?? rosterTeams[0] ?? team ?? {})
  await teamStore.setTeams(rosterTeams)
  await playerStore.setPlayers(user?.players || [])
  await router.push('/dashboard')
}

const userNeedsCredentials = (user) => {
  const email = String(user?.email || '').trim()
  return !email || !email.includes('@')
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
      isLogged.status = true;
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

const submitClaimForm = async () => {
  const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
  const phone = normalizeDigits(claimForm.phone)
  const teamCode = String(claimForm.teamCode || '').trim().toUpperCase()

  if (phone.length < 10) {
    await toast.fire({ icon: 'warning', title: 'Claim Warning', text: 'Enter your full mobile number.' })
    return
  }

  if (teamCode.length !== 6) {
    await toast.fire({ icon: 'warning', title: 'Claim Warning', text: 'Team code must be 6 characters.' })
    return
  }

  isLoading.status = false
  try {
    const dataForm = new FormData()
    dataForm.append('phone', phone)
    dataForm.append('team_code', teamCode)

    const response = await axios.post(api_url + 'player/join', dataForm)
    const body = response?.data || {}

    if (body?.status === 'not_registered') {
      await toast.fire({
        icon: 'warning',
        title: 'Not Registered',
        text: 'No account found with that phone number. Please create your player account first.',
      })
      await router.push('/register/player')
      return
    }

    if (body?.status !== 'success' || !body?.data?.token) {
      throw new Error(body?.message || 'Invalid phone number or team code')
    }

    const token = body?.data?.token
    const user = body?.data?.user
    if (userNeedsCredentials(user)) {
      claimedSession.value = body.data
      claimCredentials.email = String(user?.email || '').trim().toLowerCase()
      claimCredentials.password = ''
      claimCredentials.confirmPassword = ''
      claimStep.value = 'credentials'
      await toast.fire({
        icon: 'info',
        title: 'Set Login Credentials',
        text: 'Set your email and password to finish claiming your profile.',
      })
      return
    }

    await applyAuthSession({ payload: body.data, apiUrl: api_url })
    await toast.fire({ icon: 'success', title: 'Claim Complete', text: body?.message || 'Profile claimed successfully.' })
  } catch (error) {
    const message = error?.response?.data?.message || error?.message || 'Could not claim profile. Please try again.'
    await toast.fire({ icon: 'error', title: 'Claim Error', text: message })
  } finally {
    isLoading.status = true
  }
}

const submitClaimCredentials = async () => {
  const api_url = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
  const email = String(claimCredentials.email || '').trim().toLowerCase()
  const password = String(claimCredentials.password || '')
  const confirmation = String(claimCredentials.confirmPassword || '')
  const token = claimedSession.value?.token

  if (!token) {
    await toast.fire({ icon: 'warning', title: 'Claim Warning', text: 'Claim session expired. Please claim again.' })
    claimStep.value = 'find'
    return
  }

  if (!email || !email.includes('@')) {
    await toast.fire({ icon: 'warning', title: 'Email Required', text: 'Enter a valid email address.' })
    return
  }

  if (password.length < 8) {
    await toast.fire({ icon: 'warning', title: 'Password Too Short', text: 'Password must be at least 8 characters.' })
    return
  }

  if (password !== confirmation) {
    await toast.fire({ icon: 'warning', title: 'Validation', text: 'Passwords do not match.' })
    return
  }

  isLoading.status = false
  try {
    const dataForm = new FormData()
    dataForm.append('email', email)
    dataForm.append('password', password)
    dataForm.append('password_confirmation', confirmation)

    const response = await axios.post(api_url + 'player/set-credentials', dataForm, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })

    const body = response?.data || {}
    if (body?.status !== 'success') {
      throw new Error(body?.message || 'Could not save credentials')
    }

    const payload = {
      ...(claimedSession.value || {}),
      user: {
        ...(claimedSession.value?.user || {}),
        email,
      },
    }

    await applyAuthSession({ payload, apiUrl: api_url })
    claimStep.value = 'find'
    claimedSession.value = null
    await toast.fire({ icon: 'success', title: 'Claim Complete', text: 'Your profile is now ready to use.' })
  } catch (error) {
    const message = error?.response?.data?.message || error?.message || 'Failed to save credentials. Please try again.'
    await toast.fire({ icon: 'error', title: 'Claim Error', text: message })
  } finally {
    isLoading.status = true
  }
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

      <div v-if="isPlayerLogin" class="w-full max-w-xl mb-4">
        <div class="inline-flex p-1 rounded-xl bg-white/10 border border-white/20">
          <button
            type="button"
            class="px-4 py-2 text-xs font-black tracking-wider rounded-lg transition"
            :class="loginMode === 'email' ? 'bg-[#C00000] text-white' : 'text-white/75 hover:text-white'"
            @click="loginMode = 'email'"
          >
            Email Login
          </button>
          <button
            type="button"
            class="px-4 py-2 text-xs font-black tracking-wider rounded-lg transition"
            :class="loginMode === 'claim' ? 'bg-[#C00000] text-white' : 'text-white/75 hover:text-white'"
            @click="loginMode = 'claim'"
          >
            Claim Code
          </button>
        </div>
      </div>

      <!-- form section -->
      <FormKit v-if="!isClaimMode" type="form" :actions="false" @submit="submitForm" >
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

      <form v-else-if="!isClaimCredentialsStep" class="w-full max-w-xl" @submit.prevent="submitClaimForm">
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-bold text-white mb-2">Mobile Number</label>
            <input
              v-model="claimForm.phone"
              type="tel"
              autocomplete="tel"
              placeholder="(555) 867-5309"
              class="w-full rounded border border-fungo-lightblue bg-transparent text-white px-3 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-bold text-white mb-2">Team Code</label>
            <input
              v-model="claimForm.teamCode"
              type="text"
              maxlength="6"
              autocomplete="off"
              placeholder="HAWK7X"
              @input="claimForm.teamCode = String(claimForm.teamCode || '').toUpperCase()"
              class="w-full rounded border border-fungo-lightblue bg-transparent text-white px-3 py-2 uppercase tracking-[0.18em]"
            />
          </div>
          <p class="text-xs text-white/75">
            Use the same mobile number your coach used when adding your profile.
          </p>
          <div class="grid place-content-center">
            <BigButtonField color="red" label="Claim Profile" type="submit"></BigButtonField>
          </div>
        </div>
      </form>

      <form v-else class="w-full max-w-xl" @submit.prevent="submitClaimCredentials">
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-bold text-white mb-2">Email Address</label>
            <input
              v-model="claimCredentials.email"
              type="email"
              autocomplete="email"
              placeholder="you@example.com"
              class="w-full rounded border border-fungo-lightblue bg-transparent text-white px-3 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-bold text-white mb-2">New Password</label>
            <input
              v-model="claimCredentials.password"
              type="password"
              autocomplete="new-password"
              placeholder="At least 8 characters"
              class="w-full rounded border border-fungo-lightblue bg-transparent text-white px-3 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-bold text-white mb-2">Confirm Password</label>
            <input
              v-model="claimCredentials.confirmPassword"
              type="password"
              autocomplete="new-password"
              placeholder="Re-enter password"
              class="w-full rounded border border-fungo-lightblue bg-transparent text-white px-3 py-2"
            />
          </div>
          <p class="text-xs text-white/75">
            This is required one time to finish claiming your profile.
          </p>
          <div class="grid place-content-center">
            <BigButtonField color="red" label="Set Credentials" type="submit"></BigButtonField>
          </div>
          <div class="grid place-content-center">
            <button
              type="button"
              class="text-xs font-bold tracking-wider text-white/80 hover:text-white"
              @click="claimStep = 'find'"
            >
              Back to claim form
            </button>
          </div>
        </div>
      </form>
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
