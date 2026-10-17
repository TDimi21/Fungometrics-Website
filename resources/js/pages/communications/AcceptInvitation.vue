<script setup>
import { ref, computed, onMounted } from 'vue'
import { retainInvitationToken, retainInvitationDraft, clearInvitationToken, rememberCommunicationDestination, consumeCommunicationDestination } from '@/utils/communicationDestination.js'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/store/auth'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { prepareSessionForUser } from '@/utils/sessionCache'
import { endWebSession } from '@/utils/webSession.js'
import { useAccessStore } from '@/store/access'
const props = defineProps({ playerClaim: { type: Boolean, default: false } })
const context = ref(null), setupStep = ref(false)
const otherAccount = computed(() => props.playerClaim && context.value?.signed_in && !context.value?.matching_account)
const access = useAccessStore()
const router = useRouter()
const auth = useAuthStore()
const user = useUserStore()
const teams = useTeamStore()
const players = usePlayerStore()
const api = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || '/api/'
const token = retainInvitationToken(new URLSearchParams(window.location.hash.slice(1)).get('token'))
rememberCommunicationDestination('/invitations/accept')
// Keep the secret out of history, referrers, storage and server access logs.
window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search)
const valid = ref(false), busy = ref(false), error = ref(''), accepted = ref(false)
const draft = retainInvitationDraft()
const email = ref(draft.email), password = ref(''), firstName = ref(''), lastName = ref('')
const claimCode = ref(draft.claimCode)
function useTeamCode() {
  clearInvitationToken()
  consumeCommunicationDestination('/')
}
function continueToLogin() {
  retainInvitationDraft({ email: email.value, claimCode: claimCode.value })
  rememberCommunicationDestination('/invitations/accept')
}
const expiry = ref(''), loginPath = ref('/login/player')
const errorText = e => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || 'Unable to complete this request. Please try again.'
onMounted(async () => {
  if (!token) {
    if (props.playerClaim) error.value = 'This invitation link is invalid. Reopen the link from your coach or request a new invitation.'
    else valid.value = true
    return
  }
  try {
    const { data } = await axios.post(api + (props.playerClaim ? 'invitations/player-context' : 'invitations/inspect'), { token }, { withCredentials: true })
    if (props.playerClaim) { context.value = data; email.value = data.email }
    valid.value = props.playerClaim ? true : data.valid
    expiry.value = new Date(data.expires_at).toLocaleDateString()
  } catch (e) { if ([403,404,410,422].includes(e?.response?.status)) { clearInvitationToken(); consumeCommunicationDestination('/'); } error.value = errorText(e) }
})
async function switchAccount() {
  busy.value = true; error.value = ''
  try {
    await endWebSession(api)
    auth.isLogged.status = false; auth.setToken('')
    user.setData({}); teams.setTeams([]); teams.setTeam({}); players.setPlayers([]); access.clear()
    context.value.signed_in = false; context.value.matching_account = false
    password.value = ''
  } catch { error.value = 'Unable to sign out. Please try again or open this invitation in a private browser window.' }
  finally { busy.value = false }
}
async function accept() {
  if (otherAccount.value) return
  if (props.playerClaim && !setupStep.value) { setupStep.value = true; return }
  busy.value = true; error.value = ''
  try {
    const { data } = await axios.post(api + 'invitations/accept', { ...(token ? { token } : { claim_code: claimCode.value }), email: email.value, password: password.value || null, first_name: firstName.value, last_name: lastName.value }, { withCredentials: true })
    accepted.value = true; clearInvitationToken(); consumeCommunicationDestination(data.dashboard_path); rememberCommunicationDestination(data.dashboard_path); loginPath.value = data.login_path
    if ((props.playerClaim ? context.value?.matching_account : auth.isLogged.status) && !password.value) { await router.replace(consumeCommunicationDestination(data.dashboard_path)); return }
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
    <h1>{{ playerClaim ? 'Claim your player profile' : 'Welcome to FMTRX' }}</h1>
    <p v-if="playerClaim">Your coach invited you to {{ context?.team_name || 'FMTRX' }}. Confirm your email and team below to claim your existing profile.</p>
    <p v-else>Claim the profile your coach created, including your existing training history. No team code is needed.</p>
    <p role="alert" v-if="error">{{ error }}</p>
    <RouterLink v-if="accepted" :to="loginPath">Sign in to your dashboard</RouterLink>
    <form v-else-if="valid" @submit.prevent="accept" autocomplete="off">
      <div v-if="otherAccount" role="alert">
        <p>A different account is signed in. Sign out before claiming this player profile. This will not change the signed-in account.</p>
        <button type="button" :disabled="busy" @click="switchAccount">Sign out and continue as player</button>
      </div>
      <p v-if="expiry">Expires {{ expiry }}.</p>
      <p v-if="!playerClaim">Enter the email that received this invitation.</p>
      <label v-if="!token">Invitation claim code<input v-model="claimCode" required maxlength="24" autocomplete="off" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" spellcheck="false"></label>
      <label v-if="playerClaim">Invited player email<input :value="context?.email" type="text" name="invited-player-email" autocomplete="off" readonly></label>
      <label v-else>Email<input v-model="email" type="email" autocomplete="email" required maxlength="191"></label>
      <label v-if="playerClaim">Team code<input :value="context?.team_code || 'Connected by invitation'" name="invited-player-team" autocomplete="off" readonly></label>
      <p v-if="playerClaim && !setupStep">Your invitation has filled in these details. No backup code is needed.</p>
      <label v-if="!playerClaim || setupStep">{{ playerClaim && !context?.has_password ? 'Create your player password' : 'Player password' }}<input v-model="password" type="password" :autocomplete="playerClaim && !context?.has_password ? 'new-password' : 'current-password'" minlength="8" maxlength="20" :required="playerClaim ? !context?.matching_account : !auth.isLogged.status"></label>
      <p v-if="playerClaim && setupStep">{{ context?.has_password ? 'Use your existing player password. If already signed in to this player account, you can leave it blank.' : 'Choose a password for this player profile. Your coach account and its password are separate.' }}</p>
      <RouterLink v-if="!playerClaim && !auth.isLogged.status" @click="continueToLogin" to="/login/player">Sign in to an existing player account, then return here</RouterLink>
      <RouterLink v-if="!playerClaim && !auth.isLogged.status" @click="continueToLogin" to="/login/coach">Sign in to an existing coach account, then return here</RouterLink>
      <p v-if="!playerClaim">Already have an account? Use its password. New to FMTRX? Choose an 8–20 character password here to finish setting up this existing profile; do not register a second profile. If you are signed in to the invited account, leave this blank.</p>
      <details v-if="!playerClaim"><summary>Creating a new coach account?</summary>
        <label>First name<input v-model="firstName" autocomplete="given-name" maxlength="100"></label>
        <label>Last name<input v-model="lastName" autocomplete="family-name" maxlength="100"></label>
      </details>
      <button :disabled="busy || otherAccount">{{ busy ? 'Connecting…' : playerClaim ? (setupStep ? 'Finish and open my profile' : 'Claim Profile') : 'Claim profile / accept invitation' }}</button>
    </form>
    <p v-else-if="!error">Checking invitation…</p>
    <p v-if="error && !accepted">If this invitation expired, was revoked, or was already used, ask your coach for a new invitation or sign in if you already claimed your profile.</p>
    <RouterLink @click="useTeamCode" to="/login/player?mode=team">No email invitation? Use a team code instead</RouterLink>
  </main>
</template>
<style scoped>
.invite-page{max-width:560px;margin:5vh auto;padding:28px;background:#fff;color:#182638;border-radius:16px;line-height:1.6}.invite-page h1{font-size:28px;font-weight:800}.invite-page p{margin:14px 0}.invite-page label{display:block;margin:14px 0;font-weight:600}.invite-page input{display:block;width:100%;border:1px solid #65738a;border-radius:8px;padding:12px;color:#182638;background:#fff}.invite-page button{padding:12px 20px;background:#c8102e;color:#fff;border-radius:8px;margin-top:18px;min-height:44px}.invite-page button:disabled{opacity:.6}.invite-page a{color:#1355ab;text-decoration:underline}
</style>
