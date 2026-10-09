<script setup>
import {computed, ref, watch, onBeforeUnmount} from 'vue'
import {storeToRefs} from 'pinia'
import {useTeamStore} from '@/store/team'
import {useAxiosAuth} from '@/composables/axios-auth'
import {localDateKey, shiftCalendarDate, plansOnDate} from '@/features/planner/lib/calendar'
import {planFromApi, bucketTitle, estimateMinutes} from '@/features/planner/dailyPlanner'
import {plannerLink, teamPlannerRows} from '@/features/planner/lib/plannerLinks'
const {team} = storeToRefs(useTeamStore())
const teamId = computed(() => team.value?.id_team ?? team.value?.id ?? null)
const {axiosGet} = useAxiosAuth()
const date = ref(localDateKey()), plans = ref([]), loading = ref(false), error = ref('')
const day = computed(() => plansOnDate(plans.value,date.value))
const assigned = computed(() => new Set(day.value.flatMap(p=>p.assignedPlayerIds)).size)
let generation = 0
async function load() {
  const request = ++generation, id = teamId.value
  plans.value=[]; error.value=''; loading.value=false
  if(!id)return
  loading.value=true
  try {
    const {data} = await axiosGet('coach/daily-plans')
    if(!Array.isArray(data?.data)) throw new Error('Invalid planner response')
    if(request===generation)plans.value=teamPlannerRows(data.data,id).map(planFromApi)
  } catch {if(request===generation)error.value='Could not load workouts. Please retry.'}
  finally {if(request===generation)loading.value=false}
}
watch(teamId,load,{immediate:true})
onBeforeUnmount(()=>{generation++})
</script>
<template>
  <section class="dashboard-planner" aria-label="Daily Planner">
    <header><div><span>YOUR TEAM’S DAY</span><h2>Daily Planner</h2></div><RouterLink :to="plannerLink(date)">Open →</RouterLink></header>
    <div class="planner-date"><button aria-label="Previous planner day" @click="date=shiftCalendarDate(date,-1)">‹</button><input type="date" :value="date" aria-label="Dashboard planner date" @change="$event.target.value && (date=$event.target.value)"><button aria-label="Next planner day" @click="date=shiftCalendarDate(date,1)">›</button><button @click="date=localDateKey()">Today</button></div>
    <div class="planner-summary"><span>{{ day.length }} workouts · {{ assigned }} players</span><button :disabled="loading" @click="load" aria-label="Refresh daily planner">↻</button></div>
    <p v-if="!teamId">Select a team to see its daily plans.</p><p v-else-if="loading" role="status">Loading workouts…</p><div v-else-if="error" role="alert"><p>{{ error }}</p><button @click="load">Retry</button></div><p v-else-if="!day.length">No workouts scheduled for this day.</p>
    <div v-else class="planner-workouts"><article v-for="plan in day" :key="plan.id"><div class="plan-title"><h3>{{ plan.name || 'Untitled workout' }}</h3><span :class="plan.status">{{ plan.status==='published'?'Published':'Draft' }}</span></div><p>{{ estimateMinutes(plan) }} min · {{ plan.assignedPlayerIds.length }} players</p><p v-if="plan.primaryGoal">{{ plan.primaryGoal }}</p><ul><li v-for="bucket in plan.buckets.filter(b=>b.type!=='coach_notes')" :key="bucket.type"><span class="block-dot"></span><div><strong>{{ bucketTitle(bucket.type) }}</strong><small>{{ bucket.startTime || 'Time not set' }}{{ bucket.endTime ? ' – '+bucket.endTime : '' }}{{ bucket.location ? ' · '+bucket.location : '' }}</small></div></li></ul><div class="planner-actions"><RouterLink :to="plannerLink(date,'edit',plan.id)">{{ plan.status==='published'?'Edit workout':'Review & publish' }}</RouterLink><RouterLink v-if="plan.status==='published'" :to="plannerLink(date,'players',plan.id)">Player progress</RouterLink></div></article></div>
    <RouterLink v-if="teamId" class="create-plan" :to="plannerLink(date,'create')">＋ Create plan for this day</RouterLink>
  </section>
</template>
<style scoped>
.dashboard-planner{background:linear-gradient(120deg,#142239,#0a1422);border:1px solid #3a506b;border-top:3px solid #f03940;border-radius:9px;padding:15px;color:#ecf3fd;min-width:0}.dashboard-planner header{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}.dashboard-planner header span{font-size:8px;letter-spacing:1.7px;color:#9eafc7}.dashboard-planner h2{font-size:17px;font-weight:800;margin-top:4px}.dashboard-planner a{color:#a7c9ff;font-size:11px}.planner-date{display:flex;gap:5px}.dashboard-planner button,.dashboard-planner input{padding:7px;border:1px solid #354b66;border-radius:5px;background:#16263b;color:#c5d6ee;font-size:11px;min-height:34px}.dashboard-planner input{flex:1;min-width:0;width:0;color-scheme:dark}.planner-summary{display:flex;align-items:center;justify-content:space-between;color:#9fb3d0;font-size:11px;margin:10px 0}.planner-summary button{border:0;background:none;font-size:19px}.dashboard-planner p{font-size:11px;color:#a5b8d2;line-height:1.6;margin:7px 0}.planner-workouts{max-height:560px;overflow:auto}.planner-workouts article{padding:12px;background:#091524;border:1px solid #2b3e55;border-radius:7px;margin-bottom:10px}.plan-title{display:flex;gap:8px;align-items:flex-start;justify-content:space-between}.plan-title h3{font-size:13px;font-weight:700}.plan-title>span{font-size:8px;text-transform:uppercase;color:#f4bd6c;white-space:nowrap}.plan-title>.published{color:#62d3a6}.dashboard-planner ul{list-style:none;padding:0;margin:12px 0}.dashboard-planner li{display:flex;gap:8px;margin:10px 0}.block-dot{width:6px;height:6px;border-radius:50%;background:#fa424b;margin-top:5px;flex-shrink:0}.dashboard-planner li strong{display:block;font-size:11px;font-weight:500}.dashboard-planner small{display:block;font-size:9px;color:#819abb;margin-top:3px}.planner-actions{display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #293d54;padding-top:10px}.dashboard-planner .create-plan{display:block;text-align:center;background:linear-gradient(110deg,#ed2934,#9e141f);border:1px solid #eb3942;color:white;border-radius:6px;padding:12px;margin-top:12px;font-weight:700}.dashboard-planner :is(a,button,input):focus-visible{outline:2px solid #7ebbff;outline-offset:3px}
</style>
