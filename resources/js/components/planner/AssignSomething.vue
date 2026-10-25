<script setup>
import {ref, computed, onMounted} from 'vue'
import {useAxiosAuth} from '@/composables/axios-auth'
import {SCORED_SESSIONS, simpleAssignment} from '@/features/planner/lib/assignmentFlow'
const props = defineProps({teamId: [String, Number], teamName: String, date: String})
const emit = defineEmits(['close', 'assigned', 'advanced'])
const {axiosGet, axiosPost} = useAxiosAuth()
const step = ref(0), kind = ref(''), session = ref(''), title = ref(''), notes = ref(''), date = ref(props.date), due = ref(''), players = ref([]), roster = ref([]), groups = ref([]), templates = ref([]), template = ref(null), error = ref(''), loading = ref(true), busy = ref(false), audience = ref('team')
const id = crypto.randomUUID(), itemId = crypto.randomUUID()
const name = p => p.name?.full || p.name || [p.first_name, p.last_name].filter(Boolean).join(' ') || 'Player'
const ready = computed(() => kind.value === 'guided' ? !!template.value : !!title.value.trim() && (kind.value === 'quick' || !!session.value))
onMounted(async () => {
  try {
    const [people, teams, library] = await Promise.all([axiosGet(`coach/teams/${props.teamId}`), axiosGet(`coach/player-groups?team_id=${props.teamId}`).catch(()=>({data:{data:[]}})), axiosGet('coach/workout-templates').catch(()=>({data:{data:[]}}))])
    roster.value = people.data.data || []; groups.value = teams.data.data || []; templates.value = library.data.data || []
    players.value = roster.value.map(p => String(p.id ?? p.user_id))
  } catch {error.value = 'Could not load assignment options. Close and retry.'}
  finally {loading.value = false}
})
function choose(value) {kind.value = value; step.value = 1; title.value = ''; session.value = ''; template.value = null}
function chooseSession(s) {session.value = s.id; title.value = s.name + ' Session'}
function selectAudience(value) {audience.value = value; players.value = value === 'team' ? roster.value.map(p=>String(p.id ?? p.user_id)) : []}
function selectGroup(e) {const g = groups.value.find(g=>String(g.id)===e.target.value); players.value = (g?.member_ids || []).map(String)}
async function publish() {
  if (busy.value || !players.value.length) return
  busy.value = true; error.value = ''
  try {
    let body
    if (kind.value === 'guided') {
      const {data} = await axiosPost(`coach/workout-templates/${template.value.id}/use`, {id, team_id: String(props.teamId), date: date.value, player_ids: players.value})
      body = {...data.data, assigned_player_ids: players.value, status: 'published', settings: {...data.data.settings, assignment_kind: 'guided', due_time: due.value || null}}
      if (notes.value) body.buckets = [...body.buckets, {type: 'coach_notes', note: notes.value, items: []}]
    } else body = simpleAssignment({id, itemId, teamId: props.teamId, kind: kind.value, sessionType: session.value, name: title.value, instructions: notes.value, date: date.value, dueTime: due.value, players: players.value})
    const {data} = await axiosPost('coach/daily-plans', body)
    if (!data?.data?.id) throw new Error('Save was not confirmed. Retry with this form open.')
    emit('assigned')
  } catch(e) {error.value = e.response?.data?.message || e.message || 'Assignment could not be saved.'}
  finally {busy.value = false}
}
</script>
<template>
  <section class="assign-flow" aria-label="Assign something">
    <header><button :disabled="busy" @click="step ? step-- : emit('close')">← {{step ? 'Back' : 'Cancel'}}</button><span>{{ step ? `Step ${step} of 3` : teamName }}</span></header>
    <p v-if="error" role="alert">{{error}}</p><p v-if="loading" role="status">Loading your team and library…</p>
    <template v-if="step===0">
      <h2>What do you want your players to do?</h2>
      <button v-for="option in [{id:'quick',icon:'✓',name:'Quick Task',hint:'Simple instructions with a completion check-off.'},{id:'guided',icon:'▤',name:'Guided Workout',hint:'Exercises, sets and reps from your workout library.'},{id:'scored',icon:'▥',name:'Scored Session',hint:'Record actual performance using FMTRX scoring tools.'}]" :key="option.id" class="choice" :disabled="loading || !!error" @click="choose(option.id)"><span class="choice-icon">{{option.icon}}</span><span><strong>{{option.name}}</strong><small>{{option.hint}}</small></span><span>›</span></button>
      <p class="info">Live AB / Game scoring is coach-controlled. Start games from Sessions.</p>
    </template>
    <template v-if="step===1">
      <h2>{{kind==='quick'?'Assign a Quick Task':kind==='guided'?'Choose a Guided Workout':'Assign a Scored Session'}}</h2>
      <template v-if="kind==='scored'"><button v-for="s in SCORED_SESSIONS" :key="s.id" class="choice" :class="{selected:session===s.id}" :aria-pressed="session===s.id" @click="chooseSession(s)"><span><strong>{{s.name}}</strong><small>{{s.description}}</small></span><span>{{session===s.id?'●':'○'}}</span></button></template>
      <template v-if="kind==='guided'"><button v-for="t in templates" :key="t.id" class="choice" :class="{selected:template?.id===t.id}" @click="template=t;title=t.name"><span><strong>{{t.name}}</strong><small>{{t.sections?.length || 0}} blocks · {{t.estimated_duration_minutes || '—'}} minutes</small></span></button><p v-if="!templates.length">Your library has no workouts yet.</p><button @click="emit('advanced')">Build a custom workout →</button></template>
      <label v-else>Assignment name<input v-model="title" maxlength="200" placeholder="What should the player do?"></label>
      <button class="primary" :disabled="!ready" @click="step=2">Next: Choose Players →</button>
    </template>
    <template v-if="step===2">
      <h2>Who and when?</h2><p>{{teamName}} · only selected players receive this assignment.</p>
      <div class="tabs"><button v-for="a in ['team','group','individuals']" :key="a" :class="{selected:audience===a}" @click="selectAudience(a)">{{a==='team'?'Entire Team':a==='group'?'Saved Group':'Individual Players'}}</button></div>
      <label v-if="audience==='group'">Group<select aria-label="Assignment group" @change="selectGroup"><option value="">Choose a group</option><option v-for="g in groups" :key="g.id" :value="g.id">{{g.name}}</option></select><small v-if="!groups.length">No saved groups. Choose individual players instead.</small></label>
      <div v-if="audience==='individuals'" class="player-list"><label v-for="p in roster" :key="p.id ?? p.user_id"><input type="checkbox" v-model="players" :value="String(p.id ?? p.user_id)">{{name(p)}}</label></div>
      <p>{{players.length}} players selected</p><div class="fields"><label>Date<input type="date" v-model="date" required></label><label>Due time (optional)<input type="time" v-model="due"></label></div>
      <label>Coach instructions (optional)<textarea v-model="notes" maxlength="500" rows="4" placeholder="Explain what you want players to work on."></textarea><small>{{notes.length}}/500</small></label>
      <button class="primary" :disabled="!players.length || !date" @click="step=3">Next: Review & Assign →</button>
    </template>
    <template v-if="step===3">
      <h2>Review Assignment</h2><h3>{{title}}</h3><p>{{teamName}} · {{players.length}} players · {{date}} · {{due || 'End of day'}}</p>
      <p class="recipients">{{roster.filter(p=>players.includes(String(p.id ?? p.user_id))).map(name).join(', ')}}</p>
      <article class="preview"><small>PLAYER PREVIEW</small><h3>{{title}}</h3><p>{{notes || 'Follow the assigned training instructions.'}}</p><p v-for="s in template?.sections || []" :key="s.id">{{s.name}} · {{s.exercises?.length || 0}} exercises</p><strong class="preview-action">{{kind==='quick'?'Complete Task':kind==='guided'?'Start Workout':'Record Session'}}</strong></article>
      <p>Players record results in their session. You can review their progress and reports here.</p>
      <button class="primary" :disabled="busy" @click="publish">{{busy?'Assigning…':`Assign to ${players.length} Players`}}</button>
    </template>
  </section>
</template>
<style scoped>
.assign-flow{max-width:680px;margin:auto;background:#071724;border:1px solid #2b495d;border-radius:14px;padding:24px;color:#f0f6ff}.assign-flow header,.fields,.tabs{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.assign-flow h2{font-size:25px;font-weight:800;margin:26px 0 20px}.assign-flow h3{font-size:19px;font-weight:700}.assign-flow p,.assign-flow small,.assign-flow header span{color:#a9bfd2;font-size:13px;line-height:1.6;margin:12px 0}.assign-flow button{min-height:44px;border:1px solid #2b495d;border-radius:8px;padding:12px;background:#0b2030;color:white}.assign-flow button:disabled{opacity:.5;cursor:not-allowed}.assign-flow .choice{display:flex;align-items:center;gap:16px;width:100%;text-align:left;padding:20px;margin:12px 0}.choice>span:nth-last-child(2){flex:1}.choice small{display:block;margin:6px 0 0}.choice-icon{font-size:30px;color:#46d99b}.assign-flow .selected{border-color:#ff2948;background:#241b2b}.assign-flow .primary,.preview-action{display:block;width:100%;margin-top:24px;background:#ec1638;border:1px solid #ff4760;color:white;padding:14px;border-radius:8px;font-weight:700;text-align:center}.assign-flow label{display:block;margin:18px 0;color:#dce9f5;font-size:14px}.assign-flow input:not([type=checkbox]),.assign-flow select,.assign-flow textarea{display:block;width:100%;margin-top:8px;padding:12px;border:1px solid #335268;border-radius:8px;background:#0b2030;color:white;color-scheme:dark}.fields>label{flex:1}.assign-flow .player-list{max-height:280px;overflow:auto}.player-list label{display:flex;gap:12px;align-items:center}.preview,.info{background:#0d2233;border:1px solid #2b495d;border-radius:12px;padding:18px}.assign-flow :is(button,input,select,textarea):focus-visible{outline:2px solid #75bbff;outline-offset:3px}.recipients{max-height:100px;overflow:auto}
</style>
