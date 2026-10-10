<script setup>
import {ref, computed, watch, onBeforeUnmount} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
import {planFromApi} from '@/features/planner/dailyPlanner'
const props = defineProps({teamId:[String,Number], date:String})
const emit = defineEmits(['review'])
const {axiosGet} = useAxiosAuth()
const rows=ref([]), loading=ref(false), error=ref(''), filter=ref('all')
const filters=[['all','All'],['reported','Reported concerns'],['unfinished','Unfinished'],['check_in','Missing check-ins'],['not_started','Not started'],['review','Awaiting review']]
const shown=computed(()=>rows.value.filter(row=>filter.value==='all'||row.categories.includes(filter.value)))
let generation=0
async function load() {
  const id=++generation; rows.value=[]; error.value=''; loading.value=false
  if(!props.teamId||!props.date)return
  loading.value=true
  try {
    const res=await axiosGet(`coach/workout-attention?team_id=${encodeURIComponent(props.teamId)}&date=${encodeURIComponent(props.date)}`)
    if(!Array.isArray(res.data?.data))throw new Error('Unavailable')
    if(id===generation)rows.value=res.data.data
  } catch {if(id===generation)error.value='Could not load follow-ups. Please retry.'}
  finally {if(id===generation)loading.value=false}
}
watch(()=>[props.teamId,props.date],load,{immediate:true})
onBeforeUnmount(()=>generation++)
</script>
<template>
  <section class="attention-panel">
    <header><h2>Needs Attention <span v-if="!loading && !error">({{ rows.length }})</span></h2><button @click="load" :disabled="loading">Refresh</button></header>
    <p>Follow-ups for {{ date }}. Select a player to review results and send feedback.</p>
    <div class="attention-filters"><button v-for="[key,label] in filters" :key="key" :aria-pressed="filter===key" @click="filter=key">{{ label }}</button></div>
    <p v-if="loading" role="status">Loading follow-ups…</p>
    <p v-else-if="error" role="alert">{{ error }}</p>
    <p v-else-if="!shown.length">{{ rows.length ? 'No follow-ups in this category.' : 'No follow-ups for this date.' }}</p>
    <button v-for="row in shown" :key="row.plan.id+':'+row.player.id" class="attention-row" @click="emit('review',planFromApi(row.plan),row.player.id)">
      <strong>{{ [row.player.first_name,row.player.last_name].filter(Boolean).join(' ') || 'Player' }} · {{ row.plan.name }}</strong>
      <span>{{ row.summary.completed_drills }}/{{ row.summary.counted_drills }} required drills · {{ row.summary.completion_pct == null ? 'No drill percentage' : row.summary.completion_pct+'%' }} · {{ row.summary.review_status==='reviewed'?'Reviewed':'Not reviewed' }}</span>
      <span>{{ row.reasons.join(' · ') }}</span><span class="review-link">Review workout →</span>
    </button>
  </section>
</template>
<style scoped>
.attention-panel{background:#101d30;border:1px solid #49617e;border-radius:12px;padding:16px;margin-bottom:16px;color:#edf3ff}.attention-panel header{display:flex;align-items:center;justify-content:space-between;gap:8px}.attention-panel h2{font-size:15px;font-weight:800}.attention-panel p{color:#b9c9df;font-size:13px;margin:12px 0}.attention-panel button{min-height:44px;border:1px solid #49617e;border-radius:8px;padding:8px;background:#182940;color:#edf3ff;text-align:left}.attention-filters{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0}.attention-filters button{font-size:12px}.attention-filters button[aria-pressed=true]{background:#a81428;border-color:#ed3448}.attention-row{display:block;width:100%;margin-top:10px}.attention-row span{display:block;font-size:12px;color:#c7d5e8;margin-top:7px}.attention-row .review-link{color:#80caff}
</style>
