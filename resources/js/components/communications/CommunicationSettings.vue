<script setup>
import { ref, onMounted } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const { axiosGet, axiosPut, axiosPost } = useAxiosAuth()
const labels={workouts:'Workout assignments',assessments:'Assessment results',development_plans:'Development plans',weekly_reports:'Weekly progress summaries (opt-in)',team_announcements:'Team announcements'}
const preferences=ref(null), inbox=ref(null), message=ref(''),busy=ref(false)
const errorText=e=>Object.values(e?.response?.data?.errors||{}).flat().join(' ')||e?.response?.data?.message||'Unable to update notifications.'
async function load(page=1){try{inbox.value=(await axiosGet('me/communications',{page})).data}catch(e){message.value=errorText(e)}}
onMounted(async()=>{try{preferences.value=(await axiosGet('me/communication-preferences')).data.preferences;await load()}catch(e){message.value=errorText(e)}})
async function save(){busy.value=true;try{preferences.value=(await axiosPut('me/communication-preferences',preferences.value)).data.preferences;message.value='Email preferences saved.'}catch(e){message.value=errorText(e)}finally{busy.value=false}}
async function read(row){try{await axiosPost(`me/communications/${row.id}/read`,{});row.read_at=new Date().toISOString()}catch(e){message.value=errorText(e)}}
</script>
<template><section class="communication-settings">
<h2>Email notifications</h2><p>Choose which training emails you receive. Account security, invitations and subscription status messages are separate.</p>
<p role="status" v-if="message">{{ message }}</p>
<form v-if="preferences" @submit.prevent="save"><label v-for="(label,key) in labels" :key="key"><input type="checkbox" v-model="preferences[key]"> {{ label }}</label><button :disabled="busy">Save email preferences</button></form>
<h2>Recent updates</h2><p v-if="inbox && !inbox.data.length">No updates on this page.</p>
<article v-for="row in inbox?.data || []" :key="row.id"><h3>{{ row.content.heading }}</h3><p v-for="(line,i) in row.content.lines" :key="i">{{ line }}</p><a :href="row.content.action_url" @click="read(row)">Open in FMTRX</a><button v-if="!row.read_at" @click="read(row)">Mark read</button></article>
<div v-if="inbox?.last_page>1"><button :disabled="inbox.current_page<=1" @click="load(inbox.current_page-1)">Previous</button><button :disabled="inbox.current_page>=inbox.last_page" @click="load(inbox.current_page+1)">Next</button></div>
</section></template>
<style scoped>
.communication-settings{margin-top:24px;padding:20px;border-radius:12px;background:#fff;color:#15243a;line-height:1.6}.communication-settings h2{font-size:22px;font-weight:700;margin:16px 0 8px}.communication-settings label{display:block;padding:10px 0}.communication-settings button{min-height:44px;padding:10px 16px;border-radius:8px;border:1px solid #72829a;margin:8px 8px 8px 0;background:#ecf1f8;color:#15243a}.communication-settings article{border-top:1px solid #ccd5e0;padding:16px 0}.communication-settings h3{font-weight:700}.communication-settings a{color:#1655ab;text-decoration:underline}.communication-settings input{margin-right:8px}.communication-settings button:disabled{opacity:.5}
</style>
