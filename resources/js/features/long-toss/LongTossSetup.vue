<script setup>
import {computed,ref,watch} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
import ChoiceButtons from './ChoiceButtons.vue'
const ratings=Array.from({length:10},(_,i)=>({label:String(i+1),value:i+1}))
const yesNo=[{label:'No',value:false},{label:'Yes',value:true}]
const intensities=[{label:'Recovery',value:'recovery'},{label:'Moderate',value:'moderate'},{label:'Scheduled high intensity',value:'high'}]
const phases=['offseason','preseason','inseason','returning'].map(value=>({label:value,value}))
import {newSetup,setupFields,setupPreview,applySharedPlan} from './sessionSetup'
const props=defineProps({players:{type:Array,default:()=>[]},mode:String,drafts:Object,disabled:Boolean,allowShared:Boolean})
const emit=defineEmits(['update:mode','update:drafts'])
const shared=ref(newSetup()),historyError=ref(''),retry=ref(0)
const {axiosGet}=useAxiosAuth()
const records=computed(()=>props.allowShared?[{id:'_shared',name:'Shared coach plan'},...props.players]:props.players)
const data=id=>id==='_shared'?shared.value:props.drafts[id]||newSetup()
const preview=id=>id==='_shared'?null:setupPreview(data(id))
watch(()=>[props.players.map(p=>p.id).sort().join(','),props.mode,retry.value],async([ids,mode],old,onCleanup)=>{
 let active=true;onCleanup(()=>{active=false});historyError.value='';if(mode!=='guided'||!ids)return
 try {
  const query=ids.split(',').map(id=>'player_ids[]='+encodeURIComponent(id)).join('&')
  const rows=(await axiosGet('long-toss-setup/players?'+query)).data.data
  if(!Array.isArray(rows))throw Error();if(!active)return
  const next={...props.drafts};for(const row of rows){const current=next[row.player_id]||newSetup();if((current.maximum_ft==null||current.maximum_ft==='')&&row.maximum_ft!=null)next[row.player_id]={...current,maximum_ft:row.maximum_ft}}
  emit('update:drafts',next)
 }catch{if(active)historyError.value='Could not load previous maximums. Retry or enter a measured maximum in feet.'}
},{immediate:true})
const update=(id,key,value)=>id==='_shared'?shared.value={...shared.value,[key]:value}:emit('update:drafts',{...props.drafts,[id]:{...data(id),[key]:value}})
</script>
<template>
<section class="lt-setup">
<h2>Long Toss session</h2>
<div class="choices"><button v-for="kind in ['regular','guided']" :key="kind" type="button" :disabled="disabled" :aria-pressed="mode===kind" :class="{chosen:mode===kind}" @click="emit('update:mode',kind)">{{kind==='regular'?'Regular':'Guided'}}</button></div>
<p>{{mode==='regular'?'Record throws at your own pace.':'Complete each player’s check-in and planned workload before starting. Use the athlete’s approved throwing program.'}}</p>
<p v-if="historyError" role="alert">{{historyError}} <button type="button" :disabled="disabled" @click="retry++">Retry previous distances</button></p>
<template v-if="mode==='guided'"><details v-for="player in records" :key="player.id"><summary>{{player.name}} <small>{{player.id==='_shared'?'Build once, apply to everyone, then adjust each player.':`${data(player.id).maximum_ft||'—'} ft maximum · ${preview(player.id)?.throws??'—'} planned throws`}}</small></summary><fieldset :disabled="disabled"><legend>{{player.name}}</legend>
<ChoiceButtons v-if="player.id!=='_shared'" label="Arm feel: 1 very tired, 10 feeling great" :model-value="data(player.id).readiness" :options="ratings" :disabled="disabled" @update:model-value="update(player.id,'readiness',$event)"/>
<ChoiceButtons v-if="player.id!=='_shared'" label="Arm pain today?" :model-value="data(player.id).pain" :options="yesNo" :disabled="disabled" @update:model-value="update(player.id,'pain',$event)"/>
<ChoiceButtons label="Today’s planned intensity" :model-value="data(player.id).day_type" :options="intensities" :disabled="disabled" @update:model-value="update(player.id,'day_type',$event)"/>
<ChoiceButtons label="Season phase" :model-value="data(player.id).season_phase" :options="phases" :disabled="disabled" @update:model-value="update(player.id,'season_phase',$event)"/>
<p>Start at 30 ft. Age comes from the player profile. Distances are spaced evenly to today’s readiness-adjusted target and back to 30 ft. Total throws are calculated from sets × throws in each direction. An optional maximum caps that total without changing your throws per set.</p>
<label v-for="[key,label,min,max] in setupFields.filter(([key])=>player.id!=='_shared'||key!=='maximum_ft')" :key="key">{{label}}<input type="number" :min="min" :max="max" :value="data(player.id)[key]" @input="update(player.id,key,$event.target.value)"></label>
<label v-if="player.id!=='_shared'">Throwing history / restrictions<textarea :value="data(player.id).throwing_history" maxlength="500" @input="update(player.id,'throwing_history',$event.target.value)"/></label>
<div v-if="preview(player.id)" class="set-preview"><strong>{{preview(player.id).message}}</strong><p v-if="preview(player.id).overLimit" role="alert">Over the throw limit. Reduce sets or throws per set before starting.</p><ol><li v-for="stage in preview(player.id).stages" :key="stage.set">Set {{stage.set}} · {{stage.direction}} · {{stage.distance}} ft · {{stage.throws}} throws</li></ol></div>
<p>Pain or increasing fatigue means stop. High readiness never overrides a recovery day. Choosing high intensity means it is scheduled in your approved program today.</p>
<button v-if="player.id==='_shared'" class="apply-workload" type="button" :disabled="disabled || !players.length" @click="emit('update:drafts',applySharedPlan(players,drafts,shared))">Apply workload to all players</button>
</fieldset></details></template>
</section>
</template>
<style scoped>
.apply-workload{grid-column:1/-1;width:100%;min-height:60px;margin:18px 0 10px;padding:18px 20px;background:#d61132;color:#fff;border:2px solid #ff8298;border-radius:12px;font-size:18px;font-weight:800;text-align:center;cursor:pointer}.apply-workload:hover:not(:disabled){background:#b90e2b}.apply-workload:focus-visible{outline:3px solid #fff;outline-offset:3px}.apply-workload:disabled{opacity:.45;cursor:not-allowed}
details{margin-top:16px;border:1px solid #65758a;border-radius:10px;padding:12px}summary{cursor:pointer;font-size:20px;font-weight:700;padding:12px}summary small{display:block;font-size:14px;font-weight:400;margin-top:6px}.lt-setup{background:#17283f;color:white;padding:20px;border-radius:16px;margin:20px 0}.choices{display:flex;gap:12px;margin:12px 0}.choices button{flex:1;background:#34465c;padding:16px;border-radius:10px;color:white}.choices button.chosen{background:#d61132}fieldset{border:1px solid #65758a;padding:18px;margin-top:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}legend,h2{font-size:22px;font-weight:700}label{display:flex;flex-direction:column;gap:6px}input,select,textarea{background:white;color:#17283f;padding:10px;border-radius:8px;min-height:44px}fieldset p,fieldset strong,.set-preview{grid-column:1/-1}p{margin-top:12px}
</style>
