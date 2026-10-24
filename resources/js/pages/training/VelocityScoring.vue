<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useTrainingStore } from '@/store/training'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { useAxiosAuth } from '@/composables/axios-auth'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { playerThrows, validDistance } from '@/utils/longTossScoring'
import { plannerSessionReturn } from '@/features/planner/lib/sessionReturn'

const props = defineProps({ mode: { type: String, required: true } })
const isExit = props.mode === 'EV'
const label = isExit ? 'Exit Velocity' : 'Weighted Balls'
const endpoint = isExit ? 'exitvelocity' : 'weightball'
const reportType = isExit ? 'exit_velocity' : 'weight_ball'
const emptyCode = isExit ? '028-E' : '027-E'
const action = isExit ? 'swing' : 'throw'
const trajectories = { GB: 'Ground ball', LD: 'Line drive', FB: 'Fly ball' }
const trajectory = ref(''), weight = ref(5)
const validSelection = computed(() => isExit ? Boolean(trajectories[trajectory.value]) : Number.isInteger(Number(weight.value)) && Number(weight.value) >= 1 && Number(weight.value) <= 1500)
const summarize = source => {
  const values = source.map(row => Number(row.velocity)).filter(n => Number.isFinite(n) && n > 0)
  return { count: source.length, top: values.length ? Math.max(...values) : null,
    average: values.length ? Math.round(values.reduce((a, b) => a + b, 0) / values.length * 10) / 10 : null }
}
const training = useTrainingStore(), user = useUserStore(), roster = usePlayerStore(), teamStore = useTeamStore()
const route = useRoute(), router = useRouter()
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth()
const session = ref(null), rows = ref([]), selected = ref(''), sets = ref({})
const velocity = ref(''), busy = ref(false), loading = ref(true), ready = ref(false)
const error = ref(''), notice = ref(''), finishing = ref(false), note = ref(''), adding = ref(false), addId = ref('')
const practiceId = route.query.session || training.trainingActive?.id
const players = computed(() => (session.value?.players || []).filter(p => user.userData.type !== 'player' || p.id === user.userData.id))
const current = computed(() => players.value.find(p => p.id === selected.value))
const ownRows = computed(() => playerThrows(rows.value, selected.value))
const currentSet = computed(() => sets.value[selected.value] || 1)
const inSet = computed(() => ownRows.value.filter(row => Number(row.set || 1) === currentSet.value).length)
const teamStats = computed(() => summarize(rows.value)), stats = computed(() => summarize(ownRows.value))
const available = computed(() => (roster.players || []).filter(p => !(session.value?.players || []).some(member => member.id === p.id)))
const writable = computed(() => ready.value && current.value && !session.value?.is_completed && !busy.value)
const name = p => p?.name?.full || [p?.name?.first, p?.name?.last].filter(Boolean).join(' ') || 'Player'
const mph = value => value == null ? '—' : `${value} mph`
const difference = (value, baseline) => value == null || baseline == null ? '—' : `${value - baseline >= 0 ? '+' : ''}${Math.round((value - baseline) * 10) / 10} mph`
const groups = computed(() => isExit ? Object.keys(trajectories) : [...new Set(ownRows.value.map(row => Number(row.weight)).filter(n => n > 0))].sort((a, b) => a - b))
const groupLabel = value => isExit ? trajectories[value] : `${value} oz`
const byGroup = (source, value) => summarize(source.filter(row => isExit ? row.trajectory === value : Number(row.weight) === value)).average
watch(selected, () => { velocity.value = ''; trajectory.value = '' })

async function load() {
  loading.value = true; ready.value = false; error.value = ''
  try {
    if (!practiceId) throw new Error(`Choose an ${label} session before scoring.`)
    const response = await axiosGet(`training/${practiceId}`)
    session.value = response.data.data
    if (session.value.modes !== props.mode) throw new Error(`Choose a ${label} session from Practice Sessions.`)
    let results = []
    try { results = extractSessionRows((await axiosGet(`statistics/${practiceId}/${endpoint}`)).data, reportType) }
    catch (err) { if (err.response?.status !== 404 || err.response?.data?.code !== emptyCode) throw err }
    rows.value = results
    for (const player of players.value) {
      sets.value[player.id] = Math.max(1, ...playerThrows(results, player.id).map(row => Number(row.set) || 1))
    }
    selected.value = players.value.some(p => p.id === selected.value) ? selected.value : players.value[0]?.id || ''
    training.setDataTraining(session.value)
    ready.value = true
  } catch (err) { error.value = err.response ? 'Unable to load this session. Check your access and retry.' : err.message }
  finally { loading.value = false }
}
function key(value) {
  if (value === 'Clear') velocity.value = ''
  else if (value === '⌫') velocity.value = String(velocity.value).slice(0, -1)
  else velocity.value = `${velocity.value}${value}`.replace(/^0+(?=\d)/, '').slice(0, 10)
}
async function save() {
  if (!writable.value || !validDistance(velocity.value) || !validSelection.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const response = await axiosPost(`result/${endpoint}`, {
      practice_id: session.value.id, team_id: session.value.team_id || null,
      user_id: selected.value, velocity: Number(velocity.value), ...(isExit ? { trajectory: trajectory.value } : { weight: Number(weight.value) }), set: currentSet.value,
    })
    const saved = response.data?.data?.result
    if (!saved?.id) { ready.value = false; throw new Error('Check the session before recording another throw.') }
    rows.value.push(saved)
    velocity.value = ''; trajectory.value = ''; notice.value = `${isExit ? 'Swing' : 'Throw'} saved.`
  } catch (err) {
    error.value = 'Save could not be confirmed. Reload the session to check for the throw before retrying.'
    ready.value = false
  } finally { busy.value = false }
}
async function addPlayer() {
  if (!addId.value || busy.value) return
  busy.value = true; error.value = ''
  try {
    const sort = Math.max(-1, ...(session.value.players || []).map(p => Number(p.sort) || 0)) + 1
    await axiosPost(`coach/lineup/${session.value.id}`, { player: addId.value, pitching: !isExit, batting: isExit, sort })
    adding.value = false; addId.value = ''; await load()
  } catch { error.value = 'Unable to add this player. Reload to check the lineup before retrying.' }
  finally { busy.value = false }
}
const report = () => {
  if (!ready.value || busy.value) return
  return router.push(`/session/report/${session.value.id}/${reportType}${user.userData.type === 'player' ? '?scope=player' : ''}`)
}
async function finish() {
  if (!note.value.trim() || busy.value) return
  busy.value = true; error.value = ''
  try {
    await axiosPut(`training/${session.value.id}`, { end_note: note.value.trim(), is_completed: true })
    session.value.is_completed = true; training.setDataTraining(session.value)
    await router.push(plannerSessionReturn(sessionStorage, session.value.id, user.userData.id, `/session/report/${session.value.id}/${reportType}`))
  } catch { error.value = 'Unable to confirm completion. Reload the session before trying again.' }
  finally { busy.value = false }
}
onMounted(load)
</script>

<template>
  <Layout>
    <main class="lt-scoring" :aria-busy="loading || busy">
      <header class="lt-header">
        <div><p class="eyebrow">Training mode · Live scoring</p><h1>{{ label }}</h1><p>Record each {{ action }} in mph, just like the app.</p></div>
        <nav aria-label="Session actions">
          <button :disabled="busy" @click="router.push(`/training/training-mode?mode=${props.mode}`)">Leave session</button>
          <button :disabled="!ready || busy" @click="report" title="View statistics for this session">Stats</button>
          <button class="primary" :disabled="!writable" @click="finishing = true">End session</button>
        </nav>
      </header>
      <p v-if="loading" role="status">Loading session…</p>
      <div v-if="error" role="alert" class="lt-error">{{ error }} <button :disabled="busy || loading" @click="load">Reload session</button></div>
      <p v-if="notice" role="status">{{ notice }}</p>
      <p v-if="session?.is_completed">This session is complete. View the session report to review results.</p>
      <template v-if="ready">
        <section class="lt-player panel">
          <label>Player<select v-model="selected" :disabled="busy"><option v-for="player in players" :key="player.id" :value="player.id">{{ name(player) }}</option></select></label>
          <p v-if="current">Jersey {{ current.shirt_number || '—' }}</p>
          <button v-if="user.userData.type === 'coach' && teamStore.team?.id === session.team_id && !session.is_completed" :disabled="busy || !available.length" @click="adding = !adding">Add player</button>
          <p v-if="!players.length">No eligible players in this session.</p>
          <form v-if="adding" @submit.prevent="addPlayer"><label>Team player<select v-model="addId" required><option value="">Select a player</option><option v-for="player in available" :key="player.id" :value="player.id">{{ name(player) }}</option></select></label><button :disabled="busy || !addId">Add to session</button></form>
        </section>
        <div class="lt-grid">
          <section class="panel">
            <template v-if="isExit"><h2>Ball flight</h2><p>Select the batted-ball trajectory. Click again to deselect.</p>
              <div class="hop-options"><button v-for="(title, value) in trajectories" :key="value" :aria-pressed="trajectory === value" :disabled="!writable" @click="trajectory = trajectory === value ? '' : value">{{ title }}</button></div>
            </template>
            <template v-else><h2>Ball weight</h2><p>Weight in ounces, matching the app.</p><label for="ball-weight">Ounces (oz)</label>
              <div class="weight-controls"><button :disabled="!writable || Number(weight) <= 1" aria-label="Decrease ball weight" @click="weight = Math.max(1, Number(weight) - 1)">−</button><input id="ball-weight" v-model="weight" type="number" min="1" max="1500" step="1" :disabled="!writable" /><button :disabled="!writable || Number(weight) >= 1500" aria-label="Increase ball weight" @click="weight = Math.min(1500, Number(weight) + 1)">+</button></div>
            </template>
            <button class="primary new-set" :disabled="!writable || !inSet" @click="sets[selected] = currentSet + 1">New set +</button>
            <p>New sets apply to the selected player. The next saved {{ action }} records the set.</p>
          </section>
          <section class="panel"><h2>Session statistics</h2><p>Velocities from this session only.</p>
            <dl class="metrics"><div><dt>Top team velocity</dt><dd>{{ mph(teamStats.top) }}</dd></div><div><dt>Average team velocity</dt><dd>{{ mph(teamStats.average) }}</dd></div></dl>
            <dl class="metrics counters"><div><dt>Set</dt><dd>{{ currentSet }}</dd></div><div><dt>In set</dt><dd>{{ inSet }}</dd></div><div><dt>Total {{ isExit ? 'swings' : 'throws' }}</dt><dd>{{ stats.count }}</dd></div></dl>
            <dl class="metrics"><div><dt>Player top velocity</dt><dd>{{ mph(stats.top) }}</dd><small>{{ difference(stats.top, teamStats.top) }} vs team top</small></div><div><dt>Player average</dt><dd>{{ mph(stats.average) }}</dd><small>{{ difference(stats.average, teamStats.average) }} vs team average</small></div></dl>
            <h3>Average velocity by {{ isExit ? 'ball flight' : 'ball weight' }}</h3>
            <p v-if="!groups.length">Save a throw to see averages by weight.</p>
            <div class="hop-stats"><div v-for="value in groups" :key="value"><strong>{{ groupLabel(value) }}</strong><p>{{ mph(byGroup(ownRows, value)) }}</p><small>{{ difference(byGroup(ownRows, value), byGroup(rows, value)) }} vs team</small></div></div>
          </section>
          <form class="panel" @submit.prevent="save"><label for="lt-distance">Velocity (whole mph)</label><input id="lt-distance" v-model="velocity" class="distance" inputmode="numeric" autocomplete="off" :disabled="!writable" placeholder="0" />
            <div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','Clear','0','⌫']" :key="value" type="button" :disabled="!writable" :aria-label="value === '⌫' ? 'Delete last digit' : value" @click="key(value)">{{ value }}</button></div>
            <button class="primary save" :disabled="!writable || !validDistance(velocity) || !validSelection">{{ busy ? 'Saving…' : `Save ${action}` }}</button>
          </form>
        </div>
        <section v-if="finishing" class="panel finish"><h2>Finish {{ label }}</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="busy || !note.trim()">Complete session</button></form></section>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.lt-scoring{--lt-bg:var(--app-surface,#fff);--lt-ink:var(--app-text,#17243b);max-width:1440px;margin:auto;padding:28px;color:var(--lt-ink)}
.lt-header,.lt-player{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.lt-header{padding:24px;border-radius:18px;background:#101e35;color:#fff;margin-bottom:20px}.lt-header p{color:#d4dfef}.lt-header h1{font-size:30px;font-weight:800}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:1.5px}nav{display:flex;gap:10px;flex-wrap:wrap}.panel{background:var(--lt-bg);border:1px solid #9eafc5;border-radius:16px;padding:22px;color:var(--lt-ink)}.lt-player{margin:18px 0}.lt-grid{display:grid;grid-template-columns:minmax(180px,1fr) minmax(300px,2fr) minmax(220px,1fr);gap:18px}h2{font-size:19px;font-weight:750;margin-bottom:8px}h3{font-weight:700;margin-top:20px}p{font-size:14px;line-height:1.6}button{border:1px solid #879bb6;border-radius:9px;padding:12px 16px;font-weight:650;background:#f1f5fa;color:#17243b;min-height:44px}button:disabled{opacity:.5;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #1684d4;outline-offset:3px}.primary,button[aria-pressed=true]{background:#cf1737;border-color:#cf1737;color:white}.hop-options{display:grid;gap:12px;margin-top:22px}.new-set,.save{width:100%;margin-top:18px}.metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.metrics>div,.hop-stats>div{background:#edf2f8;color:#17243b;border-radius:10px;padding:12px}dt{font-size:12px}dd{font-size:24px;font-weight:800}.counters{grid-template-columns:repeat(3,1fr)}small{font-size:11px}.hop-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:12px}.hop-stats>div{padding:8px;text-align:center}label{display:block;font-weight:650}select,input,textarea{display:block;border:1px solid #879bb6;border-radius:8px;background:#fff;color:#17243b;padding:12px;max-width:100%;margin-top:6px}select{min-width:220px}.distance{font-size:40px;text-align:center;width:100%;font-weight:800}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:18px}.keypad button{font-size:20px;padding:16px 5px}.lt-error{padding:15px;background:#fff1f2;color:#911a2d;border:1px solid #e797a6;border-radius:10px}.finish{margin-top:20px}.finish textarea{width:100%;min-height:100px;margin-bottom:15px}.finish button{margin-right:10px}@media(max-width:1100px){.lt-grid{grid-template-columns:1fr 1fr}.lt-grid>section:nth-child(2){grid-column:1/-1;grid-row:2}}@media(max-width:600px){.lt-scoring{padding:12px}.lt-grid{display:flex;flex-direction:column}.lt-grid>form{order:-1}.hop-options{grid-template-columns:repeat(2,1fr)}.panel{padding:16px}.hop-stats{grid-template-columns:repeat(2,1fr)}}
.weight-controls{display:flex;align-items:center;gap:8px;margin-top:12px}.weight-controls input{width:90px;min-width:0;text-align:center}.hop-stats{grid-template-columns:repeat(auto-fit,minmax(90px,1fr))}
</style>
