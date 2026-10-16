<script setup>
import { ref, onMounted } from 'vue'
import { retainInvitationToken, clearInvitationToken, rememberCommunicationDestination, consumeCommunicationDestination } from '@/utils/communicationDestination.js'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { prepareSessionForUser } from '@/utils/sessionCache'
const router = useRouter()
const auth = useAuthStore()
const user = useUserStore()
const teams = useTeamStore()
const players = usePlayerStore()
const api = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '/api/'
const token = retainInvitationToken(new URLSearchParams(window.location.hash.slice(1)).get('token'))
if (token) rememberCommunicationDestination('/invitations/accept')
// Keep the secret out of history, referrers, storage and server access logs.
window.history.replaceState(null, '', window.location.pathname)
const valid = ref(false), busy = ref(false), error = ref(''), accepted = ref(false)
const email = ref(''), password = ref(''), firstName = ref(''), lastName = ref('')
const expiry = ref(''), loginPath = ref('/login/player')
const errorText = e => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || 'Unable to complete this request. Please try again.'
onMounted(async () => {
  try {
    const { data } = await axios.post(api + 'invitations/inspect', { token })
    valid.value = data.valid
    expiry.value = new Date(data.expires_at).toLocaleDateString()
  } catch (e) { if ([403,404,410,422].includes(e?.response?.status)) { clearInvitationToken(); consumeCommunicationDestination('/'); } error.value = errorText(e) }
})
async function accept() {
  busy.value = true; error.value = ''
  try {
    const { data } = await axios.post(api + 'invitations/accept', { token, email: email.value, password: password.value || null, first_name: firstName.value, last_name: lastName.value }, { withCredentials: true })
    accepted.value = true; clearInvitationToken(); consumeCommunicationDestination(data.dashboard_path); rememberCommunicationDestination(data.dashboard_path); loginPath.value = data.login_path
    if (auth.isLogged.status && !password.value) { await router.replace(consumeCommunicationDestination(data.dashboard_path)); return }
    // Reuse the standard login endpoint and its cookie/session policy.
    const login = await axios.post(api + 'login', { email: email.value, password: password.value }, { withCredentials: true, headers: { 'X-FMTRX-Client': 'web' } })
    const account = login.data.data
    prepareSessionForUser(account.id)
    auth.setToken(''); auth.isLogged.status = true
    user.setData(account)
    teams.setTeams(account.teams || []); teams.setTeam(account.teams?.[0] || {})
    players.setPlayers(account.players || [])
    password.value = ''
    await router.replace(consumeCommunicationDestination(data.dashboard_path))
  } catch (e) { error.value = accepted.value ? 'Invitation accepted. Sign in to open your dashboard.' : errorText(e) }
  finally { busy.value = false }
}
</script>
<template>
  <main class="invite-page">
    <h1>Welcome to FMTRX</h1>
    <p>Accept your team invitation to open your existing profile and training history.</p>
    <p role="alert" v-if="error">{{ error }}</p>
    <RouterLink v-if="accepted" :to="loginPath">Sign in to your dashboard</RouterLink>
    <form v-else-if="valid" @submit.prevent="accept">
      <p>Expires {{ expiry }}. Enter the email that received this invitation.</p>
      <label>Email<input v-model="email" type="email" autocomplete="email" required maxlength="191"></label>
      <label>Password<input v-model="password" type="password" autocomplete="current-password" minlength="8" maxlength="20" :required="!auth.isLogged.status"></label>
      <RouterLink v-if="!auth.isLogged.status" to="/login/player">Sign in to an existing player account, then return here</RouterLink>
      <RouterLink v-if="!auth.isLogged.status" to="/login/coach">Sign in to an existing coach account, then return here</RouterLink>
      <p>Already have an account? Use its password. Otherwise choose an 8–20 character password to complete your account. If you are signed in to the invited account, leave this blank.</p>
      <details><summary>Creating a new coach account?</summary>
        <label>First name<input v-model="firstName" autocomplete="given-name" maxlength="100"></label>
        <label>Last name<input v-model="lastName" autocomplete="family-name" maxlength="100"></label>
      </details>
      <button :disabled="busy">{{ busy ? 'Connecting…' : 'Accept invitation' }}</button>
    </form>
    <p v-else-if="!error">Checking invitation…</p>
  </main>
</template>
<style scoped>
.invite-page{max-width:560px;margin:5vh auto;padding:28px;background:#fff;color:#182638;border-radius:16px;line-height:1.6}.invite-page h1{font-size:28px;font-weight:800}.invite-page p{margin:14px 0}.invite-page label{display:block;margin:14px 0;font-weight:600}.invite-page input{display:block;width:100%;border:1px solid #65738a;border-radius:8px;padding:12px;color:#182638;background:#fff}.invite-page button{padding:12px 20px;background:#c8102e;color:#fff;border-radius:8px;margin-top:18px;min-height:44px}.invite-page button:disabled{opacity:.6}.invite-page a{color:#1355ab;text-decoration:underline}
</style>
