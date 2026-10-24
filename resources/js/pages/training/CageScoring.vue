<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import CageGameReview from '@/components/practice/CageGameReview.vue'
import { validGameField } from '@/utils/cageGame/session'
import LiveCageStats from '@/components/statistics/LiveCageStats.vue'
import CageTarget from '@/components/practice/CageTarget.vue'
import { createCageMapper } from '@/utils/cagePerspective'
import { cageDimensions } from '@/utils/cageScoring'
import { cageStep, validScript } from '@/utils/scriptedPractice'
import Layout from '@/layout/Layout.vue'
import { useTrainingStore } from '@/store/training'
import { useUserStore } from '@/store/user'
import { useAxiosAuth } from '@/composables/axios-auth'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { plannerSessionReturn } from '@/features/planner/lib/sessionReturn'

const training = useTrainingStore(), user = useUserStore()
const route = useRoute(), router = useRouter()
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth()

const session = ref(null), rows = ref([]), selected = ref('')
const showGame = ref(false)
const tap = ref(null), velocity = ref(''), groundOverride = ref(false)
const estimate = ref(null), estimating = ref(false), estimateError = ref('')
let estimateSequence = 0, estimateTimer
const loading = ref(true), busy = ref(false), ready = ref(false), pending = ref(null)
const error = ref(''), notice = ref(''), showStats = ref(false), finishing = ref(false), note = ref(''), addId = ref(''), adding = ref(false)
const practiceId = route.query.session || training.trainingActive?.id
const players = computed(() => (session.value?.players || []).filter(p => user.userData.type !== 'player' || p.id === user.userData.id))
const name = p => p?.name?.full || [p?.name?.first, p?.name?.last].filter(Boolean).join(' ') || 'Player'
const current = computed(() => players.value.find(p => p.id === selected.value))
const visibleRows = computed(() => user.userData.type === 'player' ? rows.value.filter(r => r.user_id === user.userData.id) : rows.value)
const script = computed(() => session.value?.script_plan || [])
const drill = computed(() => cageStep(script.value, session.value?.script_completed_reps ?? rows.value.length))
const ownRows = computed(() => visibleRows.value.filter(r => r.user_id === selected.value))
const stats = computed(() => {
  const speeds = ownRows.value.map(r => Number(r.launch_angle_velocity)).filter(n => n > 0)
  return { count: ownRows.value.length,
    top: speeds.length ? Math.max(...speeds) : null,
    average: speeds.length ? Math.round(speeds.reduce((a,b) => a+b,0) / speeds.length * 10) / 10 : null }
})
const personalOnly = computed(() => user.userData.type !== 'player' || (session.value?.team_id == null && session.value?.user_id === user.userData.id))
const writable = computed(() => personalOnly.value && ready.value && current.value && !session.value?.is_completed && !busy.value && !pending.value)
const dimensions = computed(() => cageDimensions(session.value?.cage_data, current.value?.body))
const hit = computed(() => tap.value && dimensions.value ? createCageMapper(undefined, dimensions.value).mapTap(tap.value.u, tap.value.v) : null)
const ground = computed(() => groundOverride.value || hit.value?.surface === 'floor')
const estimateInput = computed(() => hit.value && Number(velocity.value) > 0 && Number(velocity.value) <= 130 && Math.abs(hit.value.sprayDeg) <= 60 ? { exit_velocity_mph: Number(velocity.value), launch_angle_deg: hit.value.launchDeg, spray_angle_deg: hit.value.sprayDeg, ground_ball: ground.value } : null)
const estimateKey = computed(() => JSON.stringify([selected.value, estimateInput.value]))
const validPitch = computed(() => (!script.value.length || !!drill.value) && !!estimateInput.value && estimate.value?.key === estimateKey.value && !estimating.value)
watch(estimateKey, () => {
  const seq = ++estimateSequence
  clearTimeout(estimateTimer); estimate.value = null; estimateError.value = ''; estimating.value = false
  if (!estimateInput.value) return
  estimating.value = true
  const key = estimateKey.value, payload = { ...estimateInput.value }
  estimateTimer = setTimeout(async () => {
    try {
      const response = await axiosPost('result/cage/estimate', payload)
      if (seq !== estimateSequence || key !== estimateKey.value) return
      const distance = response.data?.data?.distance_ft
      if (response.data?.success !== true || distance == null || !Number.isFinite(Number(distance)) || Number(distance) < 0) throw new Error('Invalid estimate')
      estimate.value = { key, distance: Number(distance) }
    } catch { if (seq === estimateSequence) estimateError.value = 'Distance estimate unavailable. Adjust the velocity or target to retry.' }
    finally { if (seq === estimateSequence) estimating.value = false }
  }, 300)
}, { flush: 'sync' })
const addRoster = ref([])
async function openAdd() {
  if (!writable.value || user.userData.type !== 'coach') return
  if (adding.value) { adding.value = false; return }
  busy.value = true; error.value = ''
  try {
    const result = await axiosGet(`coach/teams/${session.value.team_id}`)
    if (!Array.isArray(result.data.data)) throw new Error('Invalid roster')
    addRoster.value = result.data.data; adding.value = true
  } catch { error.value = 'Unable to load the session team roster. Please retry.' }
  finally { busy.value = false }
}
const available = computed(() => addRoster.value.filter(p => !(session.value?.players || []).some(member => member.id === p.id)))
function clearPitch() { tap.value = null; velocity.value = ''; groundOverride.value = false }
watch(selected, clearPitch)
onBeforeRouteLeave(() => !pending.value && !busy.value)
async function load() {
  loading.value = true; ready.value = false; error.value = ''
  try {
    if (!practiceId) throw new Error('Choose a cage session before scoring.')
    session.value = (await axiosGet(`training/${practiceId}`)).data.data
    if (session.value.type !== 'C') throw new Error('Choose a cage session from Practice Sessions.')
    if (session.value.is_scripted) throw new Error('Continue this scripted cage in the app to preserve its pitch plan.')
    if (!cageDimensions(session.value.cage_data)) throw new Error('This session has no valid cage dimensions. Open it in the app to configure the cage before scoring.')
    if (session.value.script_plan && !validScript(session.value.script_plan, 'C')) throw new Error('The cage drill plan is invalid. Please review it before scoring.')
    if (session.value.game_field && !validGameField(session.value.game_field)) throw new Error('The saved game field is invalid. Review the session before scoring.')
    let results = []
    try { results = extractSessionRows((await axiosGet(`statistics/${practiceId}/cage`, user.userData.type === 'player' ? { player: 1 } : undefined)).data, 'cage') }
    catch (err) { if (err.response?.status !== 404 || err.response?.data?.code !== '038-E') throw err }
    rows.value = results
    selected.value = players.value.some(p => p.id === selected.value) ? selected.value : players.value[0]?.id || ''
    training.setDataTraining(session.value)
    if (pending.value) { showStats.value = true; notice.value = 'Saved results reloaded. Check the last hit before recording another.' }
    pending.value = null; clearPitch(); ready.value = true
  } catch (err) { error.value = err.response ? 'Unable to load this session. Check your access and retry.' : err.message }
  finally { loading.value = false }
}
function key(value) {
  if (!writable.value) return
  if (value === 'Clear') velocity.value = ''
  else if (value === '⌫') velocity.value = String(velocity.value).slice(0, -1)
  else if (value === '.' && String(velocity.value).includes('.')) return
  else velocity.value = `${velocity.value}${value}`.replace(/^0+(?=\d)/, '').slice(0, 10)
}
async function save() {
  if (!writable.value || !validPitch.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const payload = { practice_id: session.value.id, user_id: selected.value, team_id: session.value.team_id || null,
      launch_angle: hit.value.launchDeg, launch_angle_velocity: Number(velocity.value), spray_angle: hit.value.sprayDeg,
      distance_travel: estimate.value.distance, ground_ball: ground.value, cage_mark: 0, cage_position: '0' }
    const response = await axiosPost('result/cage', payload)
    const saved = response.data?.data
    if (response.data?.status !== 'success' || !saved?.id) throw new Error('No receipt')
    rows.value.push(saved); if (session.value.script_completed_reps != null) session.value.script_completed_reps++; clearPitch(); notice.value = 'Hit saved.'; if (session.value.game_field) showGame.value = true
  } catch (err) {
    if (err.response?.status === 422) error.value = 'Hit rejected. Check the details and try again.'
    else { pending.value = true; error.value = 'Save could not be confirmed. Check saved session results before recording another hit. Do not resubmit this hit blindly.' }
  } finally { busy.value = false }
}
async function addPlayer() {
  if (!addId.value || !writable.value || !available.value.some(p => p.id === addId.value) || user.userData.type !== 'coach') return
  busy.value = true
  try {
    const sort = Math.max(-1, ...(session.value.players || []).map(p => Number(p.sort) || 0)) + 1
    await axiosPost(`coach/lineup/${session.value.id}`, { player: addId.value, pitching: false, batting: true, sort })
    adding.value = false; addId.value = ''; await load()
  } catch { error.value = 'Unable to add player. Reload to check the lineup.' }
  finally { busy.value = false }
}
const report = () => { if (ready.value && !busy.value && !pending.value) return router.push(`/session/report/${session.value.id}/cage${user.userData.type === 'player' ? '?scope=player' : ''}`) }
async function finish() {
  if (!writable.value || !note.value.trim()) return
  busy.value = true; error.value = ''
  try {
    await axiosPut(`training/${session.value.id}`, { end_note: note.value.trim(), is_completed: true })
    session.value.is_completed = true; training.setDataTraining(session.value)
    busy.value = false
    await router.push(plannerSessionReturn(sessionStorage, session.value.id, user.userData.id, `/session/report/${session.value.id}/cage${user.userData.type === 'player' ? '?scope=player' : ''}`))
  } catch { error.value = 'Unable to confirm completion. Reload before trying again.' }
  finally { busy.value = false }
}
function warnBeforeUnload(event) {
  if (!pending.value && !busy.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => { load(); window.addEventListener('beforeunload', warnBeforeUnload) })
onUnmounted(() => { ++estimateSequence; clearTimeout(estimateTimer); window.removeEventListener('beforeunload', warnBeforeUnload) })
</script>
<template>
  <Layout>
    <p v-if="session && !personalOnly" role="status" class="error">Team sessions are read-only here. Start a personal session from My Sessions to record your own results.</p><main class="cage-screen" :aria-busy="loading || busy">
    <div class="scoring-stage"><header class="scorebar"><div class="hitters"><button v-if="user.userData.type === 'coach'" class="primary" :disabled="!writable" aria-label="Add hitter" @click="openAdd">+</button><button v-for="player in players" :key="player.id" :aria-pressed="selected === player.id" :disabled="busy || !!pending" @click="selected = player.id">{{ name(player) }}</button></div><nav aria-label="Session actions"><button :disabled="!ready" :aria-expanded="showStats" @click="showStats = !showStats">{{ showStats ? 'Hide statistics' : 'Show statistics' }} →</button><button :disabled="!ready || busy || !!pending" @click="report">Session report</button><button class="primary" :disabled="!writable" @click="finishing = true">End practice →</button></nav></header>
    <h1>{{ session?.game_field ? 'Cage Game Mode' : 'Cage practice' }}</h1><button v-if="ready && session.game_field" :aria-expanded="showGame" @click="showGame = !showGame">{{ showGame ? 'Hide game field' : 'View game field' }}</button><CageGameReview v-if="ready && showGame && session.game_field" :rows="visibleRows" :field="session.game_field" :player-id="selected" :player-name="name(current)" @continue="showGame = false" /><p v-if="loading" role="status">Loading session…</p><p v-if="error" role="alert" class="error">{{ error }} <button :disabled="loading || busy" @click="load">{{ pending ? 'Check saved results' : 'Reload session' }}</button></p><p v-if="notice" role="status">{{ notice }}</p><p v-if="session?.is_completed">This session is complete. Results are read-only.</p>
    <form v-if="adding" class="panel" @submit.prevent="addPlayer"><label>Hitter<select v-model="addId"><option value="">Select hitter</option><option v-for="p in available" :key="p.id" :value="p.id">{{ name(p) }}</option></select></label><button :disabled="!writable || !addId">Add to session</button><button type="button" @click="adding = false">Cancel</button></form>
    <section v-if="ready && script.length" class="panel statistics"><h2>{{ drill ? `Round ${drill.round} of ${script.length} · ${drill.drillLabel}` : 'Cage practice rounds complete' }}</h2><template v-if="drill"><p>Swing {{ drill.index }} of {{ drill.swing_count }} · {{ drill.skill }}</p><p>{{ drill.objective }}</p><p>{{ drill.coachingCues }}</p></template><p v-else>Review your session statistics or end practice.</p><details><summary>Practice rounds</summary><ol><li v-for="(step,i) in script" :key="i">{{ i+1 }}. {{ step.drillLabel }} · {{ step.swing_count }} swings</li></ol></details></section><div v-if="ready" class="scoring-columns"><section class="panel cage-panel"><h2>Tap where the ball hits the cage</h2><CageTarget v-model="tap" :disabled="!writable" /><p class="help">Arrow keys also move the marker. Floor contact records a ground ball.</p><p class="help">{{ dimensions.widthFt.toFixed(1) }} ft wide · {{ dimensions.heightFt.toFixed(1) }} ft high · {{ dimensions.lengthFt.toFixed(1) }} ft long</p><p class="help">Contact height: {{ dimensions.contactHeightFt.toFixed(2) }} ft{{ !current?.body?.ft ? ' (default player height: 6 ft)' : '' }}</p></section>
    <section class="panel measurements"><h2>Hit measurements</h2><dl><div><dt>Estimated distance</dt><dd>{{ estimating ? '…' : estimate?.distance != null ? Math.round(estimate.distance) : '—' }} <small>ft</small></dd></div><div><dt>Launch angle</dt><dd>{{ hit?.launchDeg ?? '—' }}°</dd></div><div><dt>Spray angle</dt><dd>{{ hit?.sprayDeg ?? '—' }}°</dd></div></dl><label class="ground"><input v-model="groundOverride" type="checkbox" :disabled="!writable || hit?.surface === 'floor'" /> Ground ball: {{ ground ? 'Yes' : 'No' }}</label><p class="help">Distance is an estimate from exit velocity and cage contact location.</p><hr /><p>Session hits: <strong>{{ visibleRows.length }}</strong></p><p>Player hits: <strong>{{ stats.count }}</strong></p><p>Top EV: <strong>{{ stats.top ?? '—' }} mph</strong></p><p>Average EV: <strong>{{ stats.average ?? '—' }} mph</strong></p></section>
    <form class="panel velocity-panel" @submit.prevent="save"><label for="cage-velocity">Exit velocity · MPH</label><input id="cage-velocity" v-model="velocity" class="velocity" inputmode="decimal" placeholder="—" :disabled="!writable" /><div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','0','.','⌫']" :key="value" type="button" :disabled="!writable" :aria-label="value === '⌫' ? 'Delete last digit' : value" @click="key(value)">{{ value }}</button></div><p class="help">Enter a velocity greater than 0 and up to 130 mph, then mark contact.</p><p v-if="hit && Math.abs(hit.sprayDeg) > 60" role="alert">Select contact within a spray angle of −60° to 60°.</p><p v-if="estimateError" role="alert">{{ estimateError }}</p><div class="save-actions"><button type="button" :disabled="!writable" @click="clearPitch">Clear</button><button class="primary" :disabled="!writable || !validPitch">{{ busy ? 'Saving…' : 'Save' }}</button></div></form></div>
    <footer><button :disabled="busy || !!pending" @click="router.push(user.userData.type === 'player' ? '/training-player/cage' : '/training/cage')">← Leave session</button></footer></div>
    <section v-if="showStats && ready" class="panel statistics"><h2>Cage Statistics</h2><p>Current session only. Updates after each confirmed save.</p><LiveCageStats :rows="visibleRows" :players="session.players" /></section>
    <section v-if="finishing && ready" class="panel statistics"><h2>Finish cage practice</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="!writable || !note.trim()">Complete session</button></form></section>
  </main></Layout>
</template>
<style scoped>
.cage-screen{max-width:1800px;margin:auto;padding:16px;color:white}.scoring-stage{background:#111d30 url('../../assets/img/training/batting-stadium.png') center/cover;border:1px solid #ffffff55;border-radius:24px;padding:18px}h1{font-size:23px;font-weight:800;margin:14px 0;text-shadow:0 2px 4px black}h2{font-size:18px;font-weight:800;margin-bottom:12px}.scorebar{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#070b16dc;padding:10px;border-radius:24px}.hitters{display:flex;overflow:auto;gap:8px;flex:1}.hitters button{white-space:nowrap}nav{display:flex;gap:8px;flex-wrap:wrap}button{background:#161c2dd9;color:white;border:1px solid #ffffff50;padding:10px 16px;min-height:44px;border-radius:24px;font-weight:750}button:disabled{opacity:.45;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #80e3ff;outline-offset:3px}button.primary,button[aria-pressed=true]{background:#ff2451}.scoring-columns{display:grid;grid-template-columns:minmax(300px,1.7fr) minmax(180px,.75fr) minmax(220px,1fr);gap:16px;margin:16px 0}.panel{border:1px solid #ffffff40;background:#0a1021dd;backdrop-filter:blur(3px);padding:18px;border-radius:24px;min-width:0}.cage-panel{align-self:start}.cage-panel :deep(img){border-radius:16px}.help{font-size:12px;color:#d5d9e5;margin-top:10px}dl>div{padding:14px;background:#303346aa;margin:10px 0;border-radius:20px}dt{font-size:13px;color:#d5d9e5}dd{font-size:30px;font-weight:800}dd small{font-size:14px}.ground{display:flex;align-items:center;gap:8px;font-weight:700;min-height:44px}.ground input{width:20px;height:20px;accent-color:#ff2451}.measurements p{margin:10px 0}hr{margin:18px 0;border-color:#ffffff40}.velocity{width:100%;height:85px;background:#070b15a0;border:2px solid #ffffff60;border-radius:30px;text-align:center;font-size:42px;font-weight:800;color:white;margin-top:12px}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}.keypad button{font-size:24px;min-height:58px;background:#d91e4b99}.save-actions{display:flex;gap:12px;margin-top:16px}.save-actions button{flex:1;font-size:22px}.statistics{margin-top:18px;background:var(--app-surface,#111827);color:var(--app-text,#fff)}textarea,select{display:block;padding:12px;background:#131e30;color:white;border:1px solid #91a2bc;border-radius:10px;margin:10px 0;width:100%}.error{background:#66152d;padding:12px;border-radius:12px}footer{margin-top:14px}@media(max-width:1100px){.scorebar{flex-wrap:wrap}.scoring-columns{grid-template-columns:1fr 1fr}.cage-panel{grid-column:1/-1;max-width:650px;width:100%;justify-self:center}}@media(max-width:500px){.cage-screen,.scoring-stage{padding:8px}.panel{padding:10px}.scoring-columns{gap:8px;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr)}.keypad{gap:5px}.keypad button{padding:6px;min-height:46px}.save-actions{gap:5px}.save-actions button{font-size:16px;padding:8px}.scorebar nav button{font-size:12px;padding:8px}.measurements dd{font-size:24px}}
</style>
