<script setup>
import {ref,watch,onMounted,onBeforeUnmount,nextTick} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
import {readinessFields,readinessContext,readinessQuery,emptyReadiness,readinessValid,readinessPayload} from './contract'
import ReadinessEntry from './ReadinessEntry.vue'
const props=defineProps({playerId:String,teamId:String,readOnly:Boolean,entryDate:String})
const {axiosGet,axiosPut}=useAxiosAuth()
const data=ref(null),error=ref(''),busy=ref(false),open=ref(false),form=ref(false),answers=ref(emptyReadiness()),history=ref(false)
let generation=0
const dialog=ref(null)
let previousFocus=null
watch(open,async visible=>{
 if(visible){previousFocus=document.activeElement;await nextTick();dialog.value?.querySelector('button,input')?.focus()}
 else previousFocus?.focus?.()
})
function trapFocus(event){
 if(event.key!=='Tab')return
 const nodes=[...(dialog.value?.querySelectorAll('button:not(:disabled),input:not(:disabled),textarea:not(:disabled)')||[])]
 if(!nodes.length)return
 if(event.shiftKey&&document.activeElement===nodes[0]){event.preventDefault();nodes[nodes.length-1].focus()}
 else if(!event.shiftKey&&document.activeElement===nodes[nodes.length-1]){event.preventDefault();nodes[0].focus()}
}
const prompted=new Set()
const expanded=ref({})
async function load(){
 const run=++generation,ctx={...readinessContext(),...(props.entryDate?{date:props.entryDate}:{})}
 if(!props.playerId&&!props.teamId)return
 error.value=''
 try{
  const url=props.teamId?`coach/teams/${props.teamId}/readiness`:props.readOnly?`players/${props.playerId}/readiness`:'player/readiness'
  const response=await axiosGet(`${url}?${readinessQuery(ctx)}`)
  if(run!==generation)return
  data.value=response.data.data
  if(!props.teamId&&!props.readOnly){
   const key=`${props.playerId}:${ctx.date}`
   if(data.value.today){answers.value={...data.value.today.answers};open.value=false}
   else if(!prompted.has(key)){
    prompted.add(key)
    let dismissed=false;try{dismissed=sessionStorage.getItem(`readiness:${key}`)==='shown';sessionStorage.setItem(`readiness:${key}`,'shown')}catch{}
    if(!dismissed){form.value=false;open.value=true;answers.value=emptyReadiness()}
   }
  }
 }catch{if(run===generation)error.value='Readiness could not load. Please retry.'}
}
async function save(){
 if(!readinessValid(answers.value))return
 busy.value=true;error.value=''
 try{await axiosPut('player/readiness',{...readinessContext(),answers:readinessPayload(answers.value)});open.value=false;await load()}
 catch(e){error.value=e.response?.data?.message||'Not saved. Please retry.'}
 finally{busy.value=false}
}
function start(){answers.value={...(data.value?.today?.answers||emptyReadiness())};form.value=true;open.value=true}
watch(()=>[props.playerId,props.teamId,props.readOnly,props.entryDate],()=>{data.value=null;open.value=false;load()},{immediate:true})
const foreground=()=>{if(document.visibilityState==='visible')load()}
onMounted(()=>document.addEventListener('visibilitychange',foreground))
onBeforeUnmount(()=>{generation++;document.removeEventListener('visibilitychange',foreground)})
</script>
<template>
 <section class="readiness">
  <h2>{{teamId?'Player Readiness':'Daily Readiness'}}</h2>
  <p>1 = in pain / feeling very poor · 10 = feeling great. Higher always means better.</p>
  <p v-if="error" role="alert" class="alert">{{error}}</p>
  <template v-if="teamId">
   <p>{{data?.players?.filter(p=>p.readiness).length||0}} / {{data?.players?.length||0}} submitted today</p>
   <div class="history"><details v-for="p in data?.players||[]" :key="p.user_id" @toggle="expanded[p.user_id]=$event.target.open"><summary>{{p.name}} · {{p.readiness?`${p.readiness.average}/10${p.readiness.needs_attention?' · Review':''}`:'Not submitted'}}</summary><DailyReadiness v-if="expanded[p.user_id]" :player-id="p.user_id" read-only/></details></div>
  </template>
  <template v-else>
   <p>{{data?.today?`Submitted ${entryDate||'today'} · Average ${data.today.average}/10`:readOnly?'No readiness entry for this date.':'Ready to submit today’s readiness check?'}}</p>
   <button v-if="!readOnly" :disabled="!data" @click="start">{{data?.today?'View / Update Today':'Start Readiness Check'}}</button>
   <ReadinessEntry v-if="data?.today" :entry="data.today"/>
   <button @click="history=!history">{{history?'Hide History':'Recent History'}}</button>
   <div v-if="history" class="history"><ReadinessEntry v-for="entry in data?.history||[]" :key="entry.id" :entry="entry"/><p v-if="!data?.history?.length">No readiness entries yet.</p></div>
  </template>
  <button @click="load">Refresh</button>
  <Teleport to="body"><div v-if="open&&!teamId&&!readOnly" class="readiness-backdrop"><section ref="dialog" @keydown="trapFocus" class="readiness-dialog readiness" role="dialog" aria-modal="true" aria-labelledby="readiness-title" @keydown.esc="!busy&&(open=false)">
   <h2 id="readiness-title">Daily Readiness Check</h2>
   <p>Are you ready to tell us how you feel today? Your answers are saved to your player history and visible to coaches on your connected teams.</p>
   <button v-if="!form" autofocus @click="form=true">Yes, Start Check</button>
   <form v-else @submit.prevent="save">
    <p><strong>Rate each area 1–10: 1 = in pain / feeling very poor, 10 = feeling great.</strong> For calmness, 10 means relaxed and 1 means very stressed.</p>
    <label>Hours slept (0–24 hours, not a rating)<input v-model="answers.sleep_hours" type="number" min="0" max="24" step="0.25" required/></label>
    <fieldset v-for="[key,label] in readinessFields" :key="key"><legend>{{label}}</legend><div class="rating-options"><label v-for="n in 10" :key="n" :class="{selected:Number(answers[key])===n}"><input v-model="answers[key]" type="radio" :name="key" :value="n" required/>{{n}}</label></div></fieldset>
    <label><input v-model="answers.pain_flag" type="checkbox"/>Any pain today? (Separate from your ratings)</label>
    <label>Optional notes for you and your coach<textarea v-model="answers.notes" maxlength="2000"/></label>
    <p>This is a self-report, not medical clearance. Report pain to your coach; a high average does not cancel a pain flag.</p>
    <p v-if="error" role="alert" class="alert">{{error}}</p><button :disabled="busy||!readinessValid(answers)">{{busy?'Saving…':'Submit Readiness'}}</button>
   </form>
   <button :disabled="busy" @click="open=false">Later / Close</button>
  </section></div></Teleport>
 </section>
</template>
<style scoped>
.readiness{background:#101d30;color:#fff;padding:18px;border-radius:14px;margin:12px 0}.readiness h2{font-size:22px;font-weight:800}.readiness p{margin:10px 0;line-height:1.5}.readiness button{background:#bd143b;color:white;border-radius:8px;padding:12px;margin:5px;min-height:44px}.readiness button:disabled{opacity:.5}.readiness input[type=number],.readiness textarea{display:block;background:#24334b;color:white;border:1px solid #526783;border-radius:8px;padding:10px;width:100%}.readiness label{display:block;margin:8px 0}.readiness fieldset{margin:16px 0}.rating-options{display:flex;flex-wrap:wrap;gap:6px}.rating-options label{display:flex;gap:4px;align-items:center;justify-content:center;background:#24334b;min-width:46px;min-height:44px;border-radius:8px;padding:5px}.rating-options .selected{background:#bd143b}.history{max-height:360px;overflow:auto}.history summary{padding:12px;cursor:pointer}.alert{color:#ffb4a8}.readiness-backdrop{position:fixed;inset:0;z-index:9999;background:#000a;display:flex;align-items:center;justify-content:center;padding:12px}.readiness-dialog{width:100%;max-width:650px;max-height:90dvh;overflow:auto;overscroll-behavior:contain}
</style>
