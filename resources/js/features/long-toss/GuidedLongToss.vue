<script setup>
import ChoiceButtons from './ChoiceButtons.vue'
const ratings=Array.from({length:10},(_,i)=>({label:String(i+1),value:i+1}))
const yesNo=[{label:'No',value:false},{label:'Yes',value:true}]
const phases=['offseason','preseason','inseason','returning'].map(value=>({label:value,value}))
const intensities=[{label:'Recovery',value:'recovery'},{label:'Moderate',value:'moderate'},{label:'Scheduled high intensity',value:'high'}]
const methods=[{label:'Measuring tape',value:'tape'},{label:'Marked field',value:'marked_field'},{label:'Rangefinder',value:'rangefinder'},{label:'Estimated / no throws',value:'estimate'}]
import {computed, ref, watch, onBeforeUnmount} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
const props=defineProps({practiceId:String, playerId:String, throwCount:Number})
const emit=defineEmits(['access'])
const {axiosGet,axiosPost,axiosPut}=useAxiosAuth()
const context=ref(null), guide=ref(null), preview=ref(null), busy=ref(false), error=ref(''), editing=ref(false)
const readiness=ref(7), pain=ref(null), after=ref(7), afterPain=ref(null), method=ref(''), confirmed=ref(false)
const plan=ref({})
const numeric=['min_age','max_age','measured_max_ft','start_ft','step_ft','distance_cap_ft','warmup_throws','throws_per_step','finish_throws','rest_seconds','max_throws']
const labels={min_age:'Minimum age for this plan',max_age:'Maximum age',measured_max_ft:'Coach-measured maximum (ft)',start_ft:'Starting catch distance (ft)',step_ft:'Distance increase per step (ft)',distance_cap_ft:'Coach distance ceiling (ft)',warmup_throws:'Warm-up throws',throws_per_step:'Throws at each extension step',finish_throws:'Finish throws',rest_seconds:'Rest between checkpoints (seconds)',max_throws:'Total throw ceiling'}
const endpoint=computed(()=>`long-toss-guidance/${props.practiceId}/${props.playerId}`)
let generation=0, active=true
onBeforeUnmount(()=>{active=false;generation++})
function access(){if(!active)return;emit('access',{canRecord:!busy.value && !error.value && !!guide.value?.can_record,finishReady:!!guide.value?.finished_at,guide:guide.value})}
async function load(){
  const current=++generation; emit('access',{canRecord:false,finishReady:false}); error.value=''
  try {
    const data=(await axiosGet(endpoint.value)).data.data
    if(current!==generation)return
    context.value=data; guide.value=data.guide
    plan.value=data.plan||{min_age:data.age??'',max_age:data.age??'',start_ft:45,step_ft:45,day_type:'moderate',season_phase:'inseason',throwing_history:'',scheduled_date:data.today}
    access()
  }catch{if(current===generation)error.value='Could not load guided throwing. Retry before recording throws.'}
}
watch(()=>[props.practiceId,props.playerId,props.throwCount],()=>{if(props.practiceId&&props.playerId)load()},{immediate:true})
watch([readiness,pain],()=>{preview.value=null})
async function run(action){busy.value=true;error.value='';access();try{await action()}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'Could not save. Retry to check the current state.'}finally{busy.value=false;access()}}
const checkin=()=>({readiness:Number(readiness.value),pain:pain.value,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'})
const showPreview=()=>run(async()=>{preview.value=(await axiosPost(`${endpoint.value}/preview`,checkin())).data.data})
const start=()=>run(async()=>{guide.value=(await axiosPost(`${endpoint.value}/check-in`,checkin())).data.data})
const checkpoint=(painFlag=false,fatigue=false)=>run(async()=>{
  guide.value=(await axiosPost(`${endpoint.value}/checkpoint`,{stage:guide.value.stage,pain:painFlag,fatigue})).data.data
})
const finish=()=>run(async()=>{guide.value=(await axiosPost(`${endpoint.value}/finish`,{readiness_after:Number(after.value),pain_after:afterPain.value,measurement:method.value,confirmed:confirmed.value})).data.data;await load()})
const savePlan=()=>run(async()=>{
  const body={...plan.value};for(const key of numeric)body[key]=body[key]===''||body[key]==null?null:Number(body[key])
  context.value=(await axiosPut(`${endpoint.value}/plan`,body)).data.data; editing.value=false; preview.value=null
})
</script>
<template>
<section class="guided">
  <h2>FMTRX Guided Long Toss</h2><p>{{guide?.snapshot?.version>=2?'Check-In → Going Out → Coming In → Finish':'Check-In → Warm-Up → Extension → Finish'}}</p>
  <p v-if="error" role="alert">{{error}} <button :disabled="busy" @click="load">Retry</button></p>
  <template v-if="context">
    <p><b>Distance baseline:</b> {{context.baseline.maximum_ft ?? 'Not established'}} ft · {{context.baseline.source}}</p>
    <p v-if="context.baseline.velocity_mph">Velocity reference: {{context.baseline.velocity_mph}} MPH → approximately {{context.baseline.velocity_estimate_ft}} ft. Illustrative estimate, not an effort or arm-stress measure.</p>
    <p v-if="context.baseline.individual_reference_sessions >= 3">Review reference is adapting to your confirmed measured sessions.</p>
    <p v-if="context.coach_review_needed" role="alert">Coach review: 3 unusual distances in the last 5 guided sessions. Review measurement, velocity age, and arm-readiness trends. Results remain recorded.</p>
    <button v-if="context.can_configure && !guide" @click="editing=!editing">{{editing?'Close plan settings':'Set player throwing plan'}}</button>
    <form v-if="editing && !guide" @submit.prevent="savePlan">
      <p>Set this player’s approved workload using their age, throwing history, and season phase. These are coaching settings, not universal age-based prescriptions.</p>
      <label v-for="key in numeric" :key="key">{{labels[key]}}<input v-model="plan[key]" type="number" :required="key !== 'measured_max_ft'" :disabled="busy"></label>
      <ChoiceButtons label="Season phase" v-model="plan.season_phase" :options="phases" :disabled="busy"/>
      <label>Throwing history and restrictions<textarea v-model="plan.throwing_history" required maxlength="500"/></label>
      <ChoiceButtons label="Scheduled day type" v-model="plan.day_type" :options="intensities" :disabled="busy"/>
      <label>Scheduled date<input type="date" v-model="plan.scheduled_date" required></label>
      <button :disabled="busy">Save coach plan</button>
    </form>
    <template v-if="!guide">
      <ChoiceButtons label="How does your arm feel today?" v-model="readiness" :options="ratings" :disabled="busy"/>
      <p>1 · Very tired — 10 · Feeling great. Be honest; readiness does not override a recovery day.</p>
      <ChoiceButtons label="Arm pain today?" v-model="pain" :options="yesNo" :disabled="busy"/>
      <button :disabled="busy || pain===null" @click="showPreview">Build today’s plan</button>
      <div v-if="preview">
        <h3 v-if="preview.target_ft !== null">Today’s extension target: {{preview.target_ft}} ft</h3>
        <p>{{preview.day_type}} · {{preview.message}}</p>
        <p v-if="preview.range_ft">Planning range: {{preview.range_ft[0]}}–{{preview.range_ft[1]}} ft, capped by your coach.</p>
        <button v-if="preview.status!=='needs_plan'" :disabled="busy" @click="start">{{preview.status==='rest'?'Save rest check-in':'Start guided throwing'}}</button>
      </div>
    </template>
    <template v-else>
      <p>{{guide.snapshot.message}}</p>
      <p v-if="guide.snapshot.target_ft">Today’s target: <b>{{guide.snapshot.target_ft}} ft</b></p>
      <div v-if="guide.status==='active' && guide.current_stage">
        <h3><template v-if="guide.current_stage.set_number">Set {{guide.current_stage.set_number}} · </template>{{guide.current_stage.phase}} · {{guide.current_stage.distance_ft}} ft</h3>
        <p>{{guide.current_stage.instruction}}</p><p>{{guide.stage_throws}} / {{guide.current_stage.throws}} throws recorded. Use the session controls to record each throw.</p>
        <p>Rest {{guide.current_stage.rest_seconds}} seconds before progressing. Continue only if pain-free and your arm feels good.</p>
        <button :disabled="busy || guide.stage_throws < guide.current_stage.throws" @click="checkpoint()">Rested, arm feels good — next checkpoint</button>
        <button :disabled="busy" @click="checkpoint(true,false)">Stop — arm pain</button>
        <button :disabled="busy" @click="checkpoint(false,true)">Stop — increasing fatigue</button>
      </div>
      <p v-if="guide.status==='stopped'" role="alert">Throwing stopped. Do not continue today. Tell your coach or parent if there is pain.</p>
      <form v-if="!guide.finished_at" @submit.prevent="finish">
        <h3>Finish / stop early</h3><p>You may finish early at any time. Your farthest recorded no-hop throw is saved automatically.</p>
      <ChoiceButtons label="Arm feel now" v-model="after" :options="ratings" :disabled="busy"/>
      <ChoiceButtons label="Any arm pain now?" v-model="afterPain" :options="yesNo" :disabled="busy"/>
      <ChoiceButtons label="How was distance measured?" v-model="method" :options="methods" :disabled="busy"/>
        <label><input type="checkbox" v-model="confirmed">I checked the distance and measurement method.</label>
        <button :disabled="busy || afterPain===null || !method">Save post-session check-in</button>
      </form>
      <div v-if="guide.review"><p>{{guide.review.message}}</p><p>Farthest no-hop throw: {{guide.review.distance_ft}} ft.</p><p v-if="guide.review.coach_review_needed" role="alert">Coach review requested: 3 flagged sessions within the last 5.</p></div>
    </template>
    <details v-if="context.history.length"><summary>Recent guided sessions</summary><p v-for="row in context.history" :key="row.date">{{row.date?.slice(0,10)}} · {{row.review?.distance_ft}} ft · Arm {{row.readiness}} → {{row.readiness_after}} · {{row.review?.message}}</p></details>
    <small>Targets and discrepancy thresholds are FMTRX coaching heuristics, not validated medical standards. Never throw through pain. Youth athletes should follow an individualized coach-approved plan.</small>
  </template>
</section>
</template>
<style scoped>
.guided{padding:20px;margin:16px 0;border:1px solid #3b5576;border-radius:14px;background:#11233c;color:#fff}.guided h2{font-size:22px;font-weight:800}.guided h3{font-size:19px;font-weight:700}.guided p,.guided label{display:block;margin:12px 0}.guided input:not([type=checkbox]),.guided select,.guided textarea{display:block;width:100%;padding:10px;color:#16253b;border-radius:6px;background:white}.guided button{padding:12px;margin:6px 8px 6px 0;background:#235991;border-radius:8px;min-height:44px}.guided button:disabled{opacity:.5}.guided small{display:block;color:#b8cbe1;margin-top:14px}.guided form{border-top:1px solid #3b5576;margin-top:16px;padding-top:12px}
</style>
