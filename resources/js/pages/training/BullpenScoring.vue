<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import LivePracticeStats from '@/components/statistics/LivePracticeStats.vue'
import Layout from '@/layout/Layout.vue'
import { useTrainingStore } from '@/store/training'
import { useUserStore } from '@/store/user'
import { usePlayerStore } from '@/store/players'
import { useAxiosAuth } from '@/composables/axios-auth'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { createBullpenSubmission } from '@/utils/bullpenSubmission'
import { getPitchInfoFromCoordinate } from '@/utils/bullpenCoordinates'
import { plannerSessionReturn } from '@/features/planner/lib/sessionReturn'

const training = useTrainingStore(), user = useUserStore(), roster = usePlayerStore()
const route = useRoute(), router = useRouter()
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth()
const submitPitch = createBullpenSubmission(axiosPost)
const session = ref(null), rows = ref([]), selected = ref('')
const location = ref(0), pitchType = ref(''), contact = ref('TK'), velocity = ref('')
const loading = ref(true), busy = ref(false), ready = ref(false), pending = ref(null)
const error = ref(''), notice = ref(''), showStats = ref(false), finishing = ref(false), note = ref(''), addId = ref(''), adding = ref(false)
const practiceId = route.query.session || training.trainingActive?.id
const pitchTypes = ['FB', 'CH', 'SL', 'CV', 'OTHER']
const contacts = { TK: 'Take', GB: 'Ground ball', LD: 'Line drive', FB: 'Fly ball', F: 'Foul', SM: 'Swing / miss' }
const players = computed(() => (session.value?.players || []).filter(p => user.userData.type !== 'player' || p.id === user.userData.id))
const name = p => p?.name?.full || [p?.name?.first, p?.name?.last].filter(Boolean).join(' ') || 'Player'
const current = computed(() => players.value.find(p => p.id === selected.value))
const visibleRows = computed(() => user.userData.type === 'player' ? rows.value.filter(r => r.pitcher_id === user.userData.id) : rows.value)
const teamStats = computed(() => {
  const count = visibleRows.value.length
  const strikes = visibleRows.value.filter(r => r.is_strike === true || Number(r.is_strike) === 1).length
  return { count, percent: count ? Math.round(strikes / count * 100) : null }
})
const ownRows = computed(() => visibleRows.value.filter(r => r.pitcher_id === selected.value))
const stats = computed(() => {
  const speeds = ownRows.value.map(r => Number(r.miles_per_hour)).filter(n => n > 0)
  const strikes = ownRows.value.filter(r => r.is_strike === true || Number(r.is_strike) === 1).length
  return { count: ownRows.value.length, strikes, balls: ownRows.value.length - strikes,
    percent: ownRows.value.length ? Math.round(strikes / ownRows.value.length * 100) : null,
    top: speeds.length ? Math.max(...speeds) : null,
    average: speeds.length ? Math.round(speeds.reduce((a,b) => a+b,0) / speeds.length * 10) / 10 : null }
})
const writable = computed(() => ready.value && current.value && !session.value?.is_completed && !busy.value && !pending.value)
const validPitch = computed(() => location.value > 0 && pitchTypes.includes(pitchType.value) && (velocity.value === '' || (/^\d+$/.test(String(velocity.value)) && Number(velocity.value) >= 10 && Number(velocity.value) <= 2147483647)))
const pointStyle = computed(() => ({ left: `${(((location.value - 1) % 60) + .5) / 60 * 100}%`, top: `${(Math.floor((location.value - 1) / 60) + .5) / 60 * 100}%` }))
const pitchInfo = computed(() => getPitchInfoFromCoordinate(location.value))
const available = computed(() => (roster.players || []).filter(p => !(session.value?.players || []).some(member => member.id === p.id)))
function clearPitch() { location.value = 0; pitchType.value = ''; contact.value = 'TK'; velocity.value = '' }
watch(selected, clearPitch)
onBeforeRouteLeave(() => !pending.value && !busy.value)
async function load() {
  if (pending.value) return
  loading.value = true; ready.value = false; error.value = ''
  try {
    if (!practiceId) throw new Error('Choose a bullpen session before scoring.')
    session.value = (await axiosGet(`training/${practiceId}`)).data.data
    if (session.value.type !== 'P') throw new Error('Choose a bullpen session from Practice Sessions.')
    if (session.value.is_scripted) throw new Error('Continue this scripted bullpen in the app to preserve its pitch plan.')
    let results = []
    try { results = extractSessionRows((await axiosGet(`statistics/${practiceId}/bullpen`, user.userData.type === 'player' ? { player: 1 } : undefined)).data, 'bullpen') }
    catch (err) { if (err.response?.status !== 404 || err.response?.data?.code !== '020-E') throw err }
    rows.value = results
    selected.value = players.value.some(p => p.id === selected.value) ? selected.value : players.value[0]?.id || ''
    training.setDataTraining(session.value); ready.value = true
  } catch (err) { error.value = err.response ? 'Unable to load this session. Check your access and retry.' : err.message }
  finally { loading.value = false }
}
function chooseLocation(event) {
  if (!writable.value) return
  const rect = event.currentTarget.getBoundingClientRect()
  const col = Math.max(0, Math.min(59, Math.floor((event.clientX - rect.left) / rect.width * 60)))
  const row = Math.max(0, Math.min(59, Math.floor((event.clientY - rect.top) / rect.height * 60)))
  location.value = row * 60 + col + 1
}
function moveLocation(event) {
  if (!writable.value) return
  const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -60, ArrowDown: 60 }
  if (!(event.key in steps)) return
  event.preventDefault()
  location.value = Math.max(1, Math.min(3600, (location.value || 1830) + steps[event.key]))
}
function key(value) {
  if (!writable.value) return
  if (value === 'Clear') velocity.value = ''
  else if (value === '⌫') velocity.value = String(velocity.value).slice(0, -1)
  else velocity.value = `${velocity.value}${value}`.replace(/^0+(?=\d)/, '').slice(0, 10)
}
async function save() {
  if (busy.value || (!pending.value && (!writable.value || !validPitch.value))) return
  if (!pending.value) pending.value = { practice_id: session.value.id, team_id: session.value.team_id || null,
    pitcher_id: selected.value, pitch_mark: location.value, ...getPitchInfoFromCoordinate(location.value),
    type_throw: pitchType.value, trajectory: contact.value, miles_per_hour: Number(velocity.value || 0), is_in_match: false }
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const response = await submitPitch(pending.value)
    const saved = response.data.data
    if (!rows.value.some(r => r.id === saved.id)) rows.value.push(saved)
    pending.value = null; clearPitch(); notice.value = 'Pitch saved.'
  } catch (err) {
    if (err.response?.status === 422) { pending.value = null; error.value = 'Pitch was rejected. Check the details and try again.' }
    else error.value = 'Pitch confirmation is pending. Retry this same pitch before leaving or changing details.'
  } finally { busy.value = false }
}
async function addPlayer() {
  if (!addId.value || !writable.value) return
  busy.value = true
  try {
    const sort = Math.max(-1, ...(session.value.players || []).map(p => Number(p.sort) || 0)) + 1
    await axiosPost(`coach/lineup/${session.value.id}`, { player: addId.value, pitching: true, batting: false, sort })
    adding.value = false; addId.value = ''; await load()
  } catch { error.value = 'Unable to add player. Reload to check the lineup.' }
  finally { busy.value = false }
}
const report = () => { if (ready.value && !busy.value && !pending.value) return router.push(`/session/report/${session.value.id}/bullpen${user.userData.type === 'player' ? '?scope=player' : ''}`) }
async function finish() {
  if (!writable.value || !note.value.trim()) return
  busy.value = true; error.value = ''
  try {
    await axiosPut(`training/${session.value.id}`, { end_note: note.value.trim(), is_completed: true })
    session.value.is_completed = true; training.setDataTraining(session.value)
    busy.value = false
    await router.push(plannerSessionReturn(sessionStorage, session.value.id, user.userData.id, `/session/report/${session.value.id}/bullpen`))
  } catch { error.value = 'Unable to confirm completion. Reload before trying again.' }
  finally { busy.value = false }
}
function warnBeforeUnload(event) {
  if (!pending.value && !busy.value) return
  event.preventDefault()
  event.returnValue = ''
}
onMounted(() => { load(); window.addEventListener('beforeunload', warnBeforeUnload) })
onUnmounted(() => window.removeEventListener('beforeunload', warnBeforeUnload))
</script>
<template>
  <Layout>
    <main class="bullpen-screen" :aria-busy="loading || busy">
      <h1 class="sr-only">Bullpen live scoring</h1>
      <div class="scoring-stage">
        <header class="scorebar">
          <div class="pitcher-tabs" aria-label="Session pitchers">
            <button v-if="user.userData.type === 'coach'" class="primary add-pitcher" aria-label="Add pitcher" :disabled="!writable" @click="adding = !adding">+</button>
            <button v-for="player in players" :key="player.id" :class="{ primary: selected === player.id }" :aria-pressed="selected === player.id" :disabled="busy || !!pending" @click="selected = player.id">{{ name(player) }}</button>
          </div>
          <nav aria-label="Session actions">
            <button :disabled="!ready" :aria-expanded="showStats" aria-controls="bullpen-stats" @click="showStats = !showStats">{{ showStats ? 'Hide statistics' : 'Show statistics' }} →</button>
            <button :disabled="!ready || busy || !!pending" @click="report">Session report</button>
            <button class="primary" :disabled="!writable" @click="finishing = true">End practice →</button>
          </nav>
        </header>
        <p v-if="loading" class="notice" role="status">Loading session…</p>
        <div v-if="error" role="alert" class="error">{{ error }} <button v-if="!pending" :disabled="loading || busy" @click="load">Reload session</button></div>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <p v-if="session?.is_completed" class="notice">This session is complete. Its results are read-only.</p>
        <form v-if="adding" class="panel add-form" @submit.prevent="addPlayer"><label>Player<select v-model="addId"><option value="">Select pitcher</option><option v-for="p in available" :key="p.id" :value="p.id">{{ name(p) }}</option></select></label><button :disabled="!writable || !addId">Add to session</button><button type="button" @click="adding = false">Cancel</button></form>
        <div v-if="ready" class="scoring-columns">
          <section class="panel pitch-panel" aria-label="Pitch and trajectory">
            <div class="selection-columns">
              <div><h2>Pitch</h2><div class="options"><button v-for="pitch in pitchTypes" :key="pitch" :disabled="!writable" :aria-pressed="pitchType === pitch" @click="pitchType = pitchType === pitch ? '' : pitch">{{ pitch }}</button></div></div>
              <div><h2>Trajectory</h2><div class="options"><button v-for="(label, code) in { GB: 'GB', LD: 'LD', FB: 'Fly', F: 'FOUL', SM: 'S/M' }" :key="code" :disabled="!writable" :aria-label="contacts[code]" :aria-pressed="contact === code" @click="contact = contact === code ? 'TK' : code">{{ label }}</button></div></div>
            </div>
            <p class="take-label">{{ contact === 'TK' ? 'No trajectory selected · Take' : contacts[contact] }}</p>
            <div class="summary-cards">
              <section class="summary"><h3>{{ user.userData.type === 'player' ? 'My session' : 'Team' }}</h3><dl><div><dt>Strike %</dt><dd>{{ teamStats.percent == null ? '—' : `${teamStats.percent}%` }}</dd></div><div><dt>Pitches</dt><dd>{{ teamStats.count }}</dd></div></dl></section>
              <section class="summary"><h3>Player</h3><dl><div><dt>Strike %</dt><dd>{{ stats.percent == null ? '—' : `${stats.percent}%` }}</dd></div><div><dt>Pitches</dt><dd>{{ stats.count }}</dd></div></dl></section>
            </div>
          </section>
          <section class="target-panel" aria-label="Catcher target">
            <div class="catcher" role="button" tabindex="0" :aria-disabled="!writable" aria-label="Select pitch location with tap or arrow keys" @click="chooseLocation" @keydown="moveLocation">
              <div class="strike-zone" aria-hidden="true" />
              <span v-if="location" class="pitch-dot" :style="pointStyle" aria-hidden="true" />
            </div>
            <p class="target-label" aria-live="polite">{{ location ? `${pitchInfo.zone === 'S' ? 'Strike' : 'Ball'} · ${pitchInfo.pitch_side}` : 'Tap pitch location · Arrow keys also supported' }}</p>
          </section>
          <form class="panel velocity-panel" @submit.prevent="save">
            <label class="sr-only" for="bullpen-velocity">Velocity (optional, whole mph)</label>
            <input id="bullpen-velocity" v-model="velocity" class="velocity" inputmode="numeric" placeholder="—" :disabled="!writable" />
            <div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','0','Clear','⌫']" :key="value" type="button" :disabled="!writable" :aria-label="value === '⌫' ? 'Delete last digit' : value" @click="key(value)">{{ value }}</button></div>
            <div class="save-actions"><button type="button" disabled title="Undo is not available in the app or web scorer">Undo</button><button type="submit" :disabled="busy || (!pending && (!writable || !validPitch))">{{ busy ? 'Saving…' : pending ? 'Retry same pitch' : 'Save' }}</button></div>
            <p class="velocity-help">Whole mph · Leave blank if not measured</p>
          </form>
        </div>
        <footer class="stage-footer"><button :disabled="busy || !!pending" @click="router.push('/training/bullpen')">← Leave session</button><span>Top {{ stats.top ?? '—' }} mph · Average {{ stats.average ?? '—' }} mph</span></footer>
      </div>
      <template v-if="ready">
        <section v-if="showStats && ready" id="bullpen-stats" class="panel finish"><h2>Bullpen Statistics</h2><p>Current session only. All tabs update after each confirmed save.</p><LivePracticeStats mode="P" :rows="visibleRows" :players="session.players" /></section>
        <section v-if="finishing" class="panel finish"><h2>Finish bullpen</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="!writable || !note.trim()">Complete session</button></form></section>
      </template>
    </main>
  </Layout>
</template>
<style scoped>
.bullpen-screen{max-width:1800px;margin:auto;padding:16px;color:#fff}.scoring-stage{position:relative;isolation:isolate;min-height:630px;overflow:hidden;border-radius:22px;border:1px solid #ffffff40;background:#172335 url('../../assets/img/training/bullpen-stadium.png') center center/100% 100% no-repeat;padding:16px}.scoring-stage::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,#0003,transparent 40%,transparent 60%,#0003);z-index:-1}.scorebar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:8px;border-radius:24px;background:#05070bd9;border:1px solid #ffffff30}.pitcher-tabs{display:flex;gap:8px;overflow-x:auto;min-width:0;flex:1}.pitcher-tabs button{white-space:nowrap}.scorebar nav{display:flex;gap:8px;flex-wrap:wrap}button{min-height:44px;padding:10px 18px;border:1px solid #ffffff40;border-radius:20px;color:white;background:#14172099;font-weight:800;font-size:16px;cursor:pointer}button:disabled{opacity:.45;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,.catcher:focus-visible{outline:3px solid #8de6ff;outline-offset:3px}.primary,button[aria-pressed=true]{background:#ff2451;border-color:#ff5275}.add-pitcher{font-size:30px;padding:0 18px;line-height:1}.scoring-columns{display:grid;grid-template-columns:minmax(260px,30%) minmax(220px,1fr) minmax(240px,28%);gap:14px;margin-top:18px;align-items:stretch;min-height:490px}.panel{padding:16px;background:#080b13a8;border:1px solid #ffffff35;border-radius:25px;backdrop-filter:blur(3px)}.selection-columns{display:grid;grid-template-columns:1fr 1fr;gap:10px}h2{font-size:18px;font-weight:800;margin-bottom:10px}.options{display:grid;gap:9px}.options button{height:52px;text-transform:uppercase;font-size:20px;background:#ffffff10}.options button[aria-pressed=true]{background:#ff2451b8}.take-label{font-size:11px;min-height:18px;margin:8px 0;color:#e4e6ee}.summary-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px}.summary{background:#25234bb8;border:1px solid #ffffff25;border-radius:20px;padding:10px}.summary h3{text-align:center;font-size:18px;font-weight:800;margin-bottom:8px}.summary dl{display:grid;gap:6px}.summary dl>div{display:flex;align-items:center;justify-content:space-between;gap:6px;border-radius:20px;border:1px solid #ffffff22;padding:8px;background:#0002}.summary dt{text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:1px;color:#d0cfe0}.summary dd{font-size:22px;font-weight:800}.target-panel{display:flex;flex-direction:column;justify-content:center;min-width:0}.catcher{position:relative;width:100%;aspect-ratio:1;cursor:crosshair;touch-action:manipulation;border-radius:12px}.strike-zone{position:absolute;left:25%;top:23.333%;width:50%;height:51.667%;border:3px solid white;border-radius:8px;background:#ffffff50;pointer-events:none;box-shadow:0 0 0 2px #ffffff50}.pitch-dot{position:absolute;transform:translate(-50%,-50%);width:17px;height:17px;border-radius:50%;border:3px solid white;background:#ff2451;box-shadow:0 2px 8px #000;pointer-events:none}.target-label{align-self:center;padding:8px 12px;border-radius:15px;background:#060a12b8;font-size:12px;text-align:center}.velocity-panel{display:flex;flex-direction:column;justify-content:center}.velocity{width:100%;min-width:0;height:85px;border:2px solid #ffffff55;border-radius:35px;background:#050710bb;text-align:center;color:white;font-size:44px;font-weight:800}.velocity::placeholder{color:#ddd}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:15px}.keypad button{background:#ea174a80;border-color:#ff7e9950;border-radius:30px;font-size:24px;min-height:60px;padding:10px 4px}.keypad button:nth-last-child(2){font-size:15px}.save-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:15px}.save-actions button{background:#ec204798;font-size:24px;min-height:58px;border-radius:30px}.velocity-help{text-align:center;font-size:11px;margin-top:12px;color:#ece5e9}.stage-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:12px;font-size:13px}.stage-footer span{padding:6px 12px;background:#050710b8;border-radius:20px}.notice,.error{padding:10px 16px;border-radius:12px;margin-top:10px;background:#070d18dc}.error{background:#5f1029ef}.finish{margin-top:18px;background:var(--app-surface,#10192b);color:var(--app-text,#fff)}.finish textarea{display:block;width:100%;min-height:90px;padding:12px;margin:12px 0;border:1px solid #9ca6bd;border-radius:10px;color:#152035;background:#fff}.finish button{margin-right:8px;background:#283a59}.finish .primary{background:#db1742}.add-form{display:flex;gap:12px;align-items:end;margin-top:10px}.add-form select{display:block;background:#152035;color:#fff;padding:12px;border-radius:10px}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #9eafc5}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(min-width:1400px){.scoring-columns{min-height:580px}.options button{height:65px}.keypad button{min-height:75px}.summary dd{font-size:27px}}
@media(max-width:1000px){.bullpen-screen{padding:8px}.scoring-stage{padding:10px}.scorebar{flex-wrap:wrap}.scorebar nav{margin-left:auto}.scoring-columns{grid-template-columns:minmax(220px,31%) minmax(170px,1fr) minmax(200px,28%);gap:8px}.panel{padding:10px}.options button{font-size:17px;padding:8px}.summary{padding:6px}.summary dt{font-size:9px;letter-spacing:0}.summary dd{font-size:18px}.keypad{gap:8px}}
@media(max-width:700px) and (orientation:portrait){.scoring-columns{grid-template-columns:1fr 1fr}.target-panel{grid-column:1/-1;grid-row:1;max-width:350px;width:100%;justify-self:center}.catcher{background:url('../../assets/img/training/bullpen-stadium.png') center/cover}.scoring-stage{background-size:auto 100%;background-position:center}.pitch-panel,.velocity-panel{min-width:0}.summary-cards{grid-template-columns:1fr}.options button{font-size:14px}.keypad button{min-height:48px;font-size:20px}.save-actions{gap:5px}.save-actions button{font-size:16px;padding:8px}.scorebar nav button{font-size:12px;padding:8px 10px}.stage-footer{flex-wrap:wrap}}
</style>
