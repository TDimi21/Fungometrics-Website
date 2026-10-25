<script setup>
import {ref, computed, watch, onMounted, onBeforeUnmount} from 'vue'
import {storeToRefs} from 'pinia'
import {useTeamStore} from '@/store/team'
import {useAxiosAuth} from '@/composables/axios-auth'
import {assignmentStatus, assignmentSummary, groupAssignments} from '@/features/planner/lib/assignmentFlow'
import {localDateKey} from '@/features/planner/lib/calendar'
import {planFromApi} from '@/features/planner/dailyPlanner'
import AssignSomething from './AssignSomething.vue'
const props = defineProps({date: String, openAssignment: Boolean})
const emit = defineEmits(['date','calendar','library','advanced','review','edit','assigned'])
const store = useTeamStore(), {team, teams, active_team_id, default_team_id} = storeToRefs(store), {axiosGet} = useAxiosAuth()
const teamId = computed(()=>team.value?.id_team ?? team.value?.id)
const rows = ref([]), roster = ref([]), plans = ref([]), loading = ref(false), error = ref(''), assigning = ref(false), selected = ref(null), results = ref(false)
const counts = computed(()=>assignmentSummary(rows.value)), assignments = computed(()=>groupAssignments(rows.value))
const shown = computed(()=>selected.value ? rows.value.filter(r=>r.id===selected.value) : rows.value)
const labels = {completed:'Completed',in_progress:'In Progress',not_started:'Not Started'}
const name = id => {const p=roster.value.find(p=>String(p.id ?? p.user_id)===String(id));return p?.name?.full || [p?.first_name,p?.last_name].filter(Boolean).join(' ') || 'Player'}
let generation=0
async function load() {
 const g=++generation; rows.value=[];roster.value=[];plans.value=[];error.value='';selected.value=null
 if(!teamId.value){loading.value=false;return}
 loading.value=true
 try {
  const [day,people,saved]=await Promise.all([axiosGet(`coach/planner/day?team_id=${teamId.value}&date=${props.date}`),axiosGet(`coach/teams/${teamId.value}`),axiosGet(`coach/daily-plans?team_id=${teamId.value}`)])
  if(g!==generation)return
  rows.value=day.data.data || [];roster.value=people.data.data || [];plans.value=(saved.data.data || []).map(planFromApi)
 }catch {if(g===generation)error.value='Could not load today’s assignments. Please retry.'}
 finally{if(g===generation)loading.value=false}
}
function review(row) {const plan=plans.value.find(p=>p.id===row.id);if(plan)emit('review',plan,row.assignment.user_id);else error.value='This assignment is unavailable. Refresh to retry.'}
function selectTeam(e) {const t=teams.value.find(t=>String(t.id_team ?? t.id)===e.target.value);if(t)store.setTeam(t)}
function assigned(){assigning.value=false;load();emit('assigned')}
watch([teamId,()=>props.date],()=>{assigning.value=false;load()},{immediate:true})
watch(()=>props.openAssignment,value=>{if(value)assigning.value=true},{immediate:true})
onMounted(()=>store.initializePreferences())
onBeforeUnmount(()=>generation++)
</script>
<template>
 <AssignSomething v-if="assigning && teamId" :key="teamId" :team-id="teamId" :team-name="team?.name" :date="date" @close="assigning=false" @assigned="assigned" @advanced="assigning=false;emit('advanced')" />
 <section v-else class="today-training">
  <header><div><small>FMTRX / DAILY PLANNER</small><h1>Today’s Training</h1><label class="date-label"><input type="date" :value="date" aria-label="Training date" @change="$event.target.value && emit('date',$event.target.value)"><button v-if="date!==localDateKey()" @click="emit('date',localDateKey())">Today</button></label></div><label class="team-picker">Viewing team<select :value="teamId || ''" @change="selectTeam"><option v-if="!teams.length" value="">Select a team</option><option v-for="t in teams" :key="t.id_team ?? t.id" :value="t.id_team ?? t.id">{{t.name}}{{String(t.id_team ?? t.id)===String(default_team_id)?' ★':''}}</option></select><button v-if="active_team_id && String(active_team_id)!==String(default_team_id)" @click="store.setDefaultTeam()">Set as Default</button></label></header>
  <button class="assign-button" :disabled="!teamId" @click="assigning=true">＋ Assign Something</button>
  <p v-if="store.preferenceError" role="alert">{{store.preferenceError}}</p><p v-if="error" role="alert">{{error}} <button @click="load">Retry</button></p><p v-if="loading" role="status">Loading assignments and results…</p>
  <div class="summary" aria-label="Player assignment progress"><article v-for="key in ['assigned','completed','in_progress','not_started']" :key="key" :class="key"><strong>{{loading || error?'—':counts[key]}}</strong><span>{{key==='assigned'?'Assigned':labels[key]}}</span></article></div>
  <small>Player assignments for {{date}} · each player’s assignment is counted once.</small>
  <div class="section-heading"><h2>Today’s Assignments</h2><button :disabled="loading" @click="load">Refresh</button><button @click="emit('calendar')">View Calendar →</button></div>
  <div class="assignments"><button v-for="a in assignments" :key="a.id" class="assignment-row" @click="selected=a.id;results=true"><span class="assignment-icon" :class="{done:a.completed===a.assigned}">{{a.completed===a.assigned?'✓':'▥'}}</span><span class="assignment-name"><strong>{{a.name || 'Training assignment'}}</strong><small>{{a.assigned}} players · {{a.rows[0]?.settings?.due_time || 'Due today'}}</small></span><progress :value="a.completed" :max="a.assigned" :aria-label="`${a.completed} of ${a.assigned} complete`"></progress><span>{{a.completed}} / {{a.assigned}}</span><span>›</span></button><p v-if="!loading && !error && !assignments.length" class="empty">{{teamId?'No assignments for this date. Assign a task, workout or scored session to get started.':'Select a team to see its training.'}}</p></div>
  <details v-if="plans.some(p=>p.status==='draft' && p.date===date)" class="drafts"><summary>Saved drafts</summary><button v-for="p in plans.filter(p=>p.status==='draft' && p.date===date)" :key="p.id" @click="emit('edit',p)">{{p.name || 'Untitled draft'}} →</button></details>
  <div class="section-heading"><h2>Player Results</h2><button @click="results=!results;selected=null">{{results?'Hide results':'View player progress'}} →</button></div><p class="hint">See what players have recorded, open their reports, and review their feedback.</p>
  <section v-if="results" class="results"><div class="section-heading"><h3>{{selected?assignments.find(a=>a.id===selected)?.name:'All assignments'}}</h3><button v-if="selected" @click="selected=null">Show all</button><button :disabled="loading" @click="load">Refresh</button></div><div class="table-scroll"><table><thead><tr><th>Player</th><th>Assignment</th><th>Status</th><th>Recorded</th><th>Review</th></tr></thead><tbody><tr v-for="row in shown" :key="row.id+row.assignment.user_id"><td>{{name(row.assignment.user_id)}}</td><td>{{row.name}}</td><td><span :class="['badge',assignmentStatus(row)]">{{labels[assignmentStatus(row)]}}</span></td><td>{{row.session_links?.length || 0}} sessions · {{Object.values(row.actual_results || {}).filter(a=>a.done).length}} tasks</td><td><button @click="review(row)">View results →</button></td></tr></tbody></table></div><p v-if="!shown.length">No player results for this date yet.</p></section>
  <h2 class="quick-title">Quick Actions</h2><nav class="quick-actions" aria-label="Planner quick actions"><button @click="emit('calendar')">▣ View Calendar</button><button @click="emit('library')">▤ Workout Library</button><button @click="results=true;selected=null">▥ Player Progress</button></nav>
 </section>
</template>
<style scoped>
.today-training{container-type:inline-size;min-width:0;max-width:1100px;margin:auto;padding:28px;background:linear-gradient(130deg,#071a28,#04111d);border:1px solid #2d4c61;border-radius:14px;color:#f4f8ff}.today-training header{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap}.today-training h1{font-size:29px;font-weight:800;margin:6px 0}.today-training h2{font-size:17px;font-weight:750}.today-training small,.hint{font-size:12px;color:#a9bfd1}.today-training button{min-height:44px;background:#0a2030;border:1px solid #29495f;border-radius:7px;padding:10px 14px;color:#e6f0fb}.today-training button:disabled{opacity:.5}.today-training :is(button,select,input):focus-visible{outline:2px solid #81c9ff;outline-offset:3px}.today-training select,.today-training input{background:#0a2030;border:1px solid #29495f;color:#e6f0fb;border-radius:7px;padding:10px;color-scheme:dark;min-height:44px}.team-picker{font-size:11px;color:#a9bfd1;min-width:220px}.team-picker select{display:block;width:100%;font-size:14px;margin-top:5px}.team-picker button{font-size:11px;padding:4px;background:none;border:0}.date-label{display:flex;gap:8px}.today-training .assign-button{width:100%;background:#ee1735;border-color:#ff3552;font-size:17px;font-weight:750;margin:22px 0;padding:16px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:8px}.summary article{background:#0c2434;border-radius:9px;text-align:center;padding:18px 8px}.summary strong{display:block;font-size:28px;font-weight:800}.summary span{font-size:12px;display:block;margin-top:5px}.completed strong,.done{color:#20db83}.in_progress strong{color:#f5cd46}.not_started strong{color:#ff4252}.section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:26px 0 12px;flex-wrap:wrap}.section-heading button{border:0;background:none;font-size:12px}.assignments{border:1px solid #29495f;border-radius:10px;overflow:hidden}.today-training .assignment-row{display:flex;align-items:center;gap:16px;text-align:left;border:0;border-bottom:1px solid #29495f;border-radius:0;background:#0a1d2c;width:100%;padding:20px}.assignment-icon{font-size:25px;color:#a1b2ff}.assignment-name{flex:1;min-width:0;overflow-wrap:anywhere}.assignment-name strong{font-size:15px;display:block}.assignment-name small{display:block;margin-top:5px}.assignment-row progress{width:100px;height:7px;accent-color:#20db83}.assignment-row>span:not(.assignment-name){font-size:13px;white-space:nowrap}.assignment-row .assignment-icon{font-size:25px}.empty{padding:25px;font-size:14px;color:#a9bfd1}.quick-title{margin:24px 0 12px}.quick-actions{display:flex;gap:12px;flex-wrap:wrap}.quick-actions button{flex:1;white-space:nowrap}.results{border:1px solid #29495f;border-radius:12px;padding:16px}.table-scroll{overflow:auto}table{width:100%;font-size:13px;text-align:left}th,td{padding:12px 8px;border-bottom:1px solid #29495f}th{color:#a9bfd1;font-size:11px}.badge{white-space:nowrap;font-size:11px;border-radius:20px;padding:5px 8px;background:#1c2b3c}.badge.completed{color:#20db83}.badge.in_progress{color:#f5cd46}.badge.not_started{color:#ff6574}.drafts{margin:16px 0;color:#a9bfd1;font-size:13px}.drafts button{display:block;margin-top:10px}@media(max-width:600px){.today-training{padding:16px}.team-picker{width:100%}.summary{gap:6px}.summary strong{font-size:23px}.summary span{font-size:10px}.today-training .assignment-row{gap:10px;padding:14px}.assignment-row progress{width:45px}.assignment-name strong{font-size:13px}.quick-actions button{font-size:12px}}
@container(max-width:600px){.assignment-row progress{width:40px}.today-training .assignment-row{gap:8px;padding:12px}.assignment-name strong{font-size:13px}.summary{gap:6px}.summary strong{font-size:23px}.summary span{font-size:10px}.quick-actions button{font-size:12px}}
</style>
