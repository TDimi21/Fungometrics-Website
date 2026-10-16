<script setup>
import { ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const props=defineProps({teamId:String})
const {axiosGet,axiosPost}=useAxiosAuth()
const state=ref(null),message=ref(''),text=ref(''),plan=ref(''),busy=ref(false),confirmed=ref(false)
let requestId=null
const errorText=e=>Object.values(e?.response?.data?.errors||{}).flat().join(' ')||e?.response?.data?.message||'Could not save this communication.'
async function load(){if(!props.teamId)return;try{state.value=(await axiosGet(`coach/teams/${props.teamId}/communications`)).data}catch(e){message.value=errorText(e)}}
watch(()=>props.teamId,()=>{state.value=null;plan.value='';text.value='';requestId=null;load()},{immediate:true})
async function announce(){busy.value=true;requestId ||= crypto.randomUUID();try{await axiosPost(`coach/teams/${props.teamId}/communications/announcements`,{message:text.value,request_id:requestId});requestId=null;text.value='';message.value='Announcement recorded for eligible recipients.';await load()}catch(e){message.value=errorText(e)}finally{busy.value=false}}
async function notify(){const selected=state.value?.plans.find(p=>p.id===plan.value);if(!selected)return;busy.value=true;try{await axiosPost(`coach/workouts/${selected.id}/notify-update`,{version:selected.version,confirmed:confirmed.value});message.value='Update notification recorded once for this workout version.';confirmed.value=false;await load()}catch(e){message.value=errorText(e)}finally{busy.value=false}}
</script>
<template><section v-if="state" class="coach-communications"><h2>Training updates</h2><p>Published workouts, finalized assessments and published development plans create notifications automatically. Editing a workout does not send another email.</p><p v-if="!state.send_enabled">Live automated email is disabled. Recorded updates can be reviewed before activation.</p><p role="status">{{ message }}</p>
<form @submit.prevent="notify"><label>Notify players about a saved workout update<select v-model="plan" required><option value="">Choose a published workout</option><option v-for="p in state.plans" :key="p.id" :value="p.id">{{ p.name }} · {{ String(p.date).slice(0,10) }}</option></select></label><label><input v-model="confirmed" type="checkbox" required> Notify assigned players about this version</label><button :disabled="busy||!state.enabled">Notify players</button></form>
<form @submit.prevent="announce"><label>Team announcement<textarea v-model="text" required maxlength="2000" rows="3"></textarea></label><p>Use for team training information. Marketing messages are not enabled.</p><button :disabled="busy||!state.enabled">Send team announcement</button></form>
<h3>Recent delivery activity</h3><button @click="load" :disabled="busy">Refresh</button><ul><li v-for="d in state.deliveries.data" :key="d.id">{{ d.event_type }} · {{ d.status }} <span v-if="d.failure_code">({{ d.failure_code }})</span></li></ul><p>“Sent” means accepted by the email provider, not confirmed inbox delivery.</p></section></template>
<style scoped>
.coach-communications{margin-top:32px;line-height:1.6}.coach-communications h2{font-size:24px;font-weight:700}.coach-communications h3{font-size:18px;font-weight:700}.coach-communications form{border-top:1px solid #ccd5e0;margin-top:16px;padding-top:16px}.coach-communications label{display:block;margin:12px 0}.coach-communications select,.coach-communications textarea{display:block;width:100%;padding:12px;border:1px solid #65738a;border-radius:8px;background:#fff;color:#182638}.coach-communications button{min-height:44px;padding:10px 16px;border:1px solid #65738a;border-radius:8px;background:#ecf1f8;color:#182638;margin:8px 0}.coach-communications button:disabled{opacity:.5}
</style>
