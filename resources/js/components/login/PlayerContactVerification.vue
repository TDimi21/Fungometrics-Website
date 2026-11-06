<script setup>
import {ref} from 'vue'
import axios from 'axios'
const props=defineProps({challenge:Object})
const emit=defineEmits(['ready','restart','signin'])
const code=ref(''), verified=ref(null), selected=ref(''), error=ref(''), busy=ref(false)
const post=async(path,payload)=>{const {data}=await axios.post((import.meta.env.VITE_API_ENDPOINT || '/api/')+path,payload);return data.data}
async function submit(){
 if(busy.value)return
 busy.value=true;error.value=''
 try{
  if(!verified.value) verified.value=await post('player/claim/verify-contact',{challenge_id:props.challenge.challenge_id,code:code.value})
  else {
   const result=await post('player/claim/confirm-profile',{challenge_id:props.challenge.challenge_id,selection_token:verified.value.selection_token,player_id:selected.value})
   if(result.next_action==='sign_in')emit('signin');else emit('ready',result.token)
  }
 }catch(e){error.value=e.response?.data?.message || 'Could not verify this profile. Try again.'}finally{busy.value=false}
}
</script>
<template><form class="w-full max-w-xl text-white space-y-4" @submit.prevent="submit">
 <h2>{{verified ? 'Confirm your player profile' : 'Verify your contact'}}</h2>
 <p>Your coach already created your profile. Claim it to access your training and results.</p>
 <template v-if="!verified"><label>Verification code<input v-model="code" class="block p-3 text-black" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required /></label><p>Check your {{challenge.delivery==='sms'?'text messages':'email'}}. The code expires in 10 minutes.</p></template>
 <template v-else><p>Select the correct player on {{verified.team_name}}. Shared family contacts do not merge profiles.</p><label v-for="player in verified.profiles" :key="player.id" class="block"><input v-model="selected" type="radio" :value="player.id" required /> {{player.name}} {{player.connected?'· Already connected':''}}</label></template>
 <p v-if="error" role="alert">{{error}}</p><button class="bg-red-600 rounded p-3" :disabled="busy">{{verified?'Confirm This Profile':'Verify Contact'}}</button>
 <button type="button" class="p-3 underline" :disabled="busy" @click="emit('restart')">Change contact or request a new code</button>
</form></template>
