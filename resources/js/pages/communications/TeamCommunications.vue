<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from '@/store/team'
import { useAxiosAuth } from '@/composables/axios-auth'
const teamStore = useTeamStore()
const teamId = computed(() => teamStore.team?.id_team || teamStore.team?.id)
const { axiosGet, axiosPost } = useAxiosAuth()
const state = ref(null), error = ref(''), message = ref(''), busy = ref(false)
const selected = ref(''), email = ref(''), role = ref('player')
const playerPage = ref(1), invitationPage = ref(1)
const errorText = e => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || 'Unable to load invitations.'
async function load() {
  if (!teamId.value) { state.value = null; return }
  try { state.value = (await axiosGet(`coach/teams/${teamId.value}/invitations`, { players_page: playerPage.value, page: invitationPage.value })).data }
  catch (e) { error.value = errorText(e) }
}
watch(teamId, () => { playerPage.value = 1; invitationPage.value = 1; selected.value = ''; email.value = ''; state.value = null; load() })
watch(selected, id => { email.value = state.value?.players?.data?.find(p => p.id === id)?.email || '' })
watch(role, () => { selected.value = ''; email.value = '' })
onMounted(load)
async function send() { await perform(() => axiosPost(`coach/teams/${teamId.value}/invitations`, { role: role.value, user_id: selected.value || null, email: email.value }), 'Invitation queued. Delivery status will update when the worker sends it.') }
async function perform(action, success) {
  busy.value = true; error.value = ''; message.value = ''
  try { await action(); message.value = success; await load() } catch (e) { error.value = errorText(e) } finally { busy.value = false }
}
async function manage(id, action) { await perform(() => axiosPost(`coach/invitations/${id}/${action}`, {}), action === 'resend' ? 'A new invitation is queued; the previous link is invalid.' : 'Invitation revoked.') }
async function page(kind, delta) { if (kind === 'players') playerPage.value += delta; else invitationPage.value += delta; await load() }
</script>
<template>
  <Layout><section class="communications">
    <h1>Team Communications</h1>
    <p>Invite players to their existing profiles, preserving workouts, assessments and training history.</p>
    <p v-if="!teamId">Select a team first.</p>
    <p v-if="error" role="alert">{{ error }}</p><p v-if="message" role="status">{{ message }}</p>
    <template v-if="state">
      <p v-if="!state.enabled">Invitations are awaiting activation. Email delivery is disabled.</p>
      <form @submit.prevent="send">
        <label>Invite<select v-model="role"><option value="player">Player</option><option v-if="state.can_invite_coaches" value="coach">Assistant coach</option></select></label>
        <label v-if="role === 'player'">Existing player<select v-model="selected" required><option value="">Choose a player</option><option v-for="p in state.players.data" :key="p.id" :value="p.id">{{ p.name || 'Unnamed player' }} · {{ p.connected ? 'Connected' : 'Not connected' }}</option></select></label>
        <div v-if="role === 'player' && state.players.last_page > 1"><button type="button" :disabled="playerPage === 1" @click="page('players',-1)">Previous players</button><span> {{ playerPage }} / {{ state.players.last_page }} </span><button type="button" :disabled="playerPage === state.players.last_page" @click="page('players',1)">More players</button></div>
        <label>Email<input v-model="email" type="email" required maxlength="254"></label>
        <p v-if="role === 'coach'">Assistant coaches receive the existing team coach permissions. This invitation does not grant head-coach or administrator access.</p>
        <button :disabled="busy || !state.enabled">Send invitation</button>
      </form>
      <h2>Invitations</h2><button :disabled="busy" @click="load">Refresh status</button>
      <p v-if="!state.invitations.data.length">No invitations yet.</p>
      <ul><li v-for="i in state.invitations.data" :key="i.id"><strong>{{ i.email }}</strong><span>{{ i.role }} · {{ i.status }} · Delivery: {{ i.delivery_status }}</span><p v-if="i.failure_code">Delivery could not be confirmed. Resend creates a new link.</p><div v-if="i.status !== 'accepted' && i.status !== 'revoked'"><button :disabled="busy || !state.enabled" @click="manage(i.id,'resend')">Resend</button><button :disabled="busy || !state.enabled" @click="manage(i.id,'revoke')">Revoke</button></div></li></ul>
      <div v-if="state.invitations.last_page > 1"><button :disabled="invitationPage === 1" @click="page('invitations',-1)">Previous</button><span> {{ invitationPage }} / {{ state.invitations.last_page }} </span><button :disabled="invitationPage === state.invitations.last_page" @click="page('invitations',1)">Next</button></div>
    </template>
  </section></Layout>
</template>
<style scoped>
.communications{padding:28px;max-width:1050px;margin:24px auto;background:#fff;color:#182638;border-radius:16px;line-height:1.5}.communications h1{font-size:28px;font-weight:800}.communications h2{font-size:22px;font-weight:700;margin-top:28px}.communications p{margin:12px 0}.communications label{display:block;margin:16px 0;font-weight:600}.communications input,.communications select{display:block;width:100%;max-width:540px;padding:12px;border:1px solid #65738a;border-radius:8px;color:#182638;background:#fff}.communications button{min-height:44px;border:1px solid #65738a;padding:10px 16px;border-radius:8px;margin:4px;background:#ecf1f8;color:#182638}.communications button:disabled{opacity:.5}.communications li{padding:16px 0;border-bottom:1px solid #d1d9e5;overflow-wrap:anywhere}.communications li span{display:block}
</style>
