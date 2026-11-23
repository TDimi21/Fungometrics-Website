<script setup>
import {ref, computed, onMounted, watch} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth.js'
import {useUserStore} from '@/store/user'
import {useTeamStore} from '@/store/team'
const {axiosGet,axiosPost}=useAxiosAuth()
const user=useUserStore(), team=useTeamStore()
const coach=computed(()=>user.userData?.type==='coach')
const teamId=computed(()=>team.team?.id_team || team.team?.id || team.team?.team_id)
const rows=ref([]), candidates=ref([]), source=ref(''), email=ref(''), preview=ref(null), password=ref(''), confirmed=ref(false), busy=ref(false), error=ref(''), message=ref('')
const readable=k=>k.replaceAll('_',' ')
async function run(task) { if(busy.value)return; busy.value=true;error.value='';message.value='';try{await task()}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat()[0]||e.response?.data?.message||e.message||'Unable to connect. Try again.'}finally{busy.value=false} }
async function load(){rows.value=(await axiosGet('player-account-connections')).data.data||[]; if(coach.value&&teamId.value)candidates.value=(await axiosGet('player-account-connections/candidates',{team_id:teamId.value})).data.data||[]}
function refresh(){return run(load)}
function request(){return run(async()=>{await axiosPost('player-account-connections',{source_id:source.value,team_id:teamId.value,email:email.value});await load();message.value='Request created. Ask the player to open Settings → Player Account Connections while signed in. No email is sent.';email.value=''})}
function review(id){return run(async()=>{preview.value=(await axiosGet(`player-account-connections/${id}/preview`)).data.data;password.value='';confirmed.value=false})}
function cancel(id){return run(async()=>{await axiosPost(`player-account-connections/${id}/cancel`,{});preview.value=null;await load()})}
function accept(){return run(async()=>{try{await axiosPost(`player-account-connections/${preview.value.id}/accept`,{password:password.value,confirmed:confirmed.value,preview_hash:preview.value.preview_hash});preview.value=null;await load();message.value='Connected. Your existing login is unchanged. Refresh your roster and sessions to see your combined history.'}finally{password.value=''}})}
onMounted(refresh)
watch(teamId,()=>{source.value='';candidates.value=[];refresh()})
</script>
<template>
<section class="connections" aria-label="Player Account Connections">
 <h2>Player Account Connections</h2>
 <p>Already signed up separately from a coach-created roster profile? Connect the roster history to the existing player account.</p>
 <p role="alert" v-if="error" class="error">{{error}}</p><p role="status" v-if="message">{{message}}</p>
 <button type="button" :disabled="busy" @click="refresh">Refresh requests</button>
 <form v-if="coach" @submit.prevent="request">
  <p>Head coaches can request a connection. The player must review and approve it. Sync all devices before connecting; overlapping records require review.</p>
  <label>Unclaimed player on your selected team<select v-model="source" required><option value="" disabled>Select a player</option><option v-for="p in candidates" :key="p.id" :value="p.id">{{p.name}}</option></select></label>
  <label>Existing player account email<input type="email" v-model="email" required autocomplete="off" /></label>
  <button type="submit" :disabled="busy||!source||!teamId">Request connection</button>
 </form>
 <p v-if="!rows.length&&!busy">No connection requests.</p>
 <article v-for="row in rows" :key="row.id">
  <strong>{{row.source_name}}</strong><p>{{row.team_name}} · {{row.status}}</p>
  <template v-if="row.status==='pending'">
   <button v-if="!coach" type="button" :disabled="busy" @click="review(row.id)">Review connection</button>
   <button type="button" :disabled="busy" @click="cancel(row.id)">{{coach?'Cancel request':'Decline'}}</button>
  </template>
 </article>
 <form v-if="preview" @submit.prevent="accept" aria-label="Review profile connection">
  <h3>Connect {{preview.source_name}} to {{preview.target_name}}</h3>
  <p>Teams: {{preview.teams.join(', ')}}</p>
  <ul><li v-for="(count,kind) in preview.records" :key="kind">{{readable(kind)}}: {{count}}</li></ul>
  <p v-if="!Object.keys(preview.records).length">No recorded history yet. Team membership will still be connected.</p>
  <label class="confirm"><input type="checkbox" v-model="confirmed" required />{{preview.notice}}</label>
  <label>Confirm your current password<input type="password" v-model="password" required autocomplete="current-password" /></label>
  <button type="submit" :disabled="busy||!confirmed||!password">{{busy?'Connecting…':'Confirm connection'}}</button>
  <button type="button" :disabled="busy" @click="preview=null;password=''">Back</button>
 </form>
</section>
</template>
<style scoped>
.connections{padding:24px;margin:24px 0;background:#0c1529;color:#eef2fa;border:1px solid #45516b;border-radius:16px;max-width:960px}.connections h2{font-size:24px;font-weight:800}.connections h3{font-size:20px;font-weight:700}.connections p{margin:12px 0;line-height:1.5}.connections form,.connections article{border-top:1px solid #45516b;margin-top:20px;padding-top:16px}.connections label{display:block;margin:16px 0}.connections input:not([type=checkbox]),.connections select{display:block;width:100%;padding:12px;background:#19253b;border:1px solid #8896aa;border-radius:8px;color:white;margin-top:8px}.connections button{padding:12px 16px;border-radius:8px;background:#bf1236;color:white;margin:4px 8px 4px 0;min-height:44px}.connections button:disabled{opacity:.5}.connections :focus-visible{outline:3px solid #55cef8;outline-offset:3px}.connections .confirm{display:flex;gap:12px;align-items:flex-start}.connections .error{color:#ffb1b8}.connections li{text-transform:capitalize;line-height:1.8}
</style>
