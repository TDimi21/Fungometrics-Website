<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import LivePracticeStats from '@/components/statistics/LivePracticeStats.vue'
import { nextBattingStep, roundLabel, roundGoal, validScript } from '@/utils/scriptedPractice'
import Layout from '@/layout/Layout.vue'
import { useTrainingStore } from '@/store/training'
import { useUserStore } from '@/store/user'
import { usePlayerStore } from '@/store/players'
import { useAxiosAuth } from '@/composables/axios-auth'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { battingPayload } from '@/utils/battingScoring'
import BattingScoringField from '@/components/practice/BattingScoringField.vue'
import { getPitchInfoFromCoordinate } from '@/utils/bullpenCoordinates'
import { plannerSessionReturn } from '@/features/planner/lib/sessionReturn'

const training = useTrainingStore(), user = useUserStore(), roster = usePlayerStore()
const route = useRoute(), router = useRouter()
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth()

const session = ref(null), rows = ref([]), selected = ref('')
const location = ref(0), field = ref(0), quality = ref(''), trajectory = ref(''), velocity = ref('')
const loading = ref(true), busy = ref(false), ready = ref(false), pending = ref(null)
const error = ref(''), notice = ref(''), showStats = ref(false), finishing = ref(false), note = ref(''), addId = ref(''), adding = ref(false)
const practiceId = route.query.session || training.trainingActive?.id
const scriptRounds = ref([]), scriptSwings = ref([])
const qualities = { W: 'WEAK', A: 'AVG', H: 'HARD', M: 'MISS/F' }
const qualityOptions = computed(() => session.value?.is_scripted ? { R:'BARREL', ...qualities } : qualities)
const trajectories = ['GB', 'PF', 'FB', 'LD']
const players = computed(() => [...(session.value?.players || [])].sort((a,b) => Number(a.sort || 0)-Number(b.sort || 0)).filter(p => user.userData.type !== 'player' || p.id === user.userData.id))
const name = p => p?.name?.full || [p?.name?.first, p?.name?.last].filter(Boolean).join(' ') || 'Player'
const current = computed(() => players.value.find(p => p.id === selected.value))
const visibleRows = computed(() => user.userData.type === 'player' ? rows.value.filter(r => r.batter_id === user.userData.id) : rows.value)
const teamStats = computed(() => {
  const count = visibleRows.value.length
  const strikes = visibleRows.value.filter(r => r.is_contact === true || Number(r.is_contact) === 1).length
  return { count, percent: count ? Math.round(strikes / count * 100) : null }
})
const step = computed(() => nextBattingStep(scriptRounds.value, players.value, scriptSwings.value))
watch(step, value => { if (session.value?.is_scripted && value && !pending.value) selected.value = value.playerId })
const ownRows = computed(() => visibleRows.value.filter(r => r.batter_id === selected.value))
const stats = computed(() => {
  const speeds = ownRows.value.map(r => Number(r.velocity)).filter(n => n > 0)
  const strikes = ownRows.value.filter(r => r.is_contact === true || Number(r.is_contact) === 1).length
  return { count: ownRows.value.length, strikes, balls: ownRows.value.length - strikes,
    percent: ownRows.value.length ? Math.round(strikes / ownRows.value.length * 100) : null,
    top: speeds.length ? Math.max(...speeds) : null,
    average: speeds.length ? Math.round(speeds.reduce((a,b) => a+b,0) / speeds.length * 10) / 10 : null }
})
const personalOnly = computed(() => user.userData.type !== 'player' || (session.value?.team_id == null && session.value?.user_id === user.userData.id))
const writable = computed(() => personalOnly.value && ready.value && current.value && !session.value?.is_completed && !busy.value && !pending.value)
const validPitch = computed(() => (!session.value?.is_scripted || (!!step.value && step.value.playerId === selected.value && ['R','H','A','W','M'].includes(quality.value) && Number(velocity.value || 0) <= 130)) && (!['W', 'A', 'H', 'R'].includes(quality.value) || trajectories.includes(trajectory.value)) && (velocity.value === '' || (/^\d+$/.test(String(velocity.value)) && Number(velocity.value) <= 2147483647)))
const pointStyle = computed(() => ({ left: `${(((location.value - 1) % 60) + .5) / 60 * 100}%`, top: `${(Math.floor((location.value - 1) / 60) + .5) / 60 * 100}%` }))
const pitchInfo = computed(() => getPitchInfoFromCoordinate(location.value))
const available = computed(() => (roster.players || []).filter(p => !(session.value?.players || []).some(member => member.id === p.id)))
function clearPitch() { location.value = 0; field.value = 0; quality.value = ''; trajectory.value = ''; velocity.value = '' }
function chooseQuality(code) {
  if (!writable.value) return
  quality.value = quality.value === code ? '' : code
  if (quality.value === 'M') { field.value = 0; trajectory.value = ''; velocity.value = '' }
}
watch(selected, clearPitch)
onBeforeRouteLeave(() => !pending.value && !busy.value)
async function load() {
  loading.value = true; ready.value = false; error.value = ''
  try {
    if (!practiceId) throw new Error('Choose a batting session before scoring.')
    session.value = (await axiosGet(`training/${practiceId}`)).data.data
    if (session.value.type !== 'B') throw new Error('Choose a batting session from Practice Sessions.')
    if (session.value.is_scripted) {
      const result = (await axiosGet(`result/scripted-bp/${practiceId}`)).data.data
      scriptRounds.value = result.plan?.rounds || []
      if (!validScript(scriptRounds.value, 'B')) throw new Error('This scripted batting session has no valid saved plan. Continue in the app that created it.')
      scriptSwings.value = (result.batters || []).flatMap(b => b.rounds.flatMap(r => r.swings))
    }
    let results = []
    try { results = extractSessionRows((await axiosGet(`statistics/${practiceId}/batting`, user.userData.type === 'player' ? { player: 1 } : undefined)).data, 'batting') }
    catch (err) { if (err.response?.status !== 404 || err.response?.data?.code !== '019-E') throw err }
    rows.value = results
    selected.value = session.value.is_scripted && step.value ? step.value.playerId : players.value.some(p => p.id === selected.value) ? selected.value : players.value[0]?.id || ''
    training.setDataTraining(session.value)
    if (pending.value) { showStats.value = true; notice.value = 'Saved results reloaded. Check the last swing before recording another.' }
    pending.value = null; clearPitch(); ready.value = true
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
  if (!writable.value || !validPitch.value) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const payload = battingPayload({ session: session.value, batter: selected.value, pitch: location.value, field: field.value, quality: quality.value, trajectory: trajectory.value, velocity: velocity.value })
    let saved
    if (session.value.is_scripted) {
      const direction = String(payload.field_direction || '').toUpperCase()
      const response = await axiosPost('result/scripted-bp/swing', {
        practice_id: session.value.id, batter_id: step.value.playerId, round_type: step.value.round_type, round_swing_index: step.value.index,
        contact_type: {R:'Barrel',H:'Hard',A:'Average',W:'Weak',M:'Miss'}[quality.value],
        trajectory: {GB:'GroundBall',LD:'LineDrive',FB:'FlyBall',PF:'PopUp'}[trajectory.value] || null,
        direction: direction === 'LF' ? 'Pull' : direction === 'RF' ? 'Oppo' : direction === 'CF' ? 'Middle' : null,
        exit_velocity: quality.value === 'M' ? null : velocity.value === '' ? null : Number(velocity.value),
        web_result: { pitch_mark: payload.pitch_mark, field_mark: payload.field_mark, pitch_location:payload.pitch_location, zone:payload.zone, field_direction:payload.field_direction },
      })
      saved = response.data?.data?.batting_result
      const swing = response.data?.data?.swing
      if (response.data?.status !== 'success' || !saved?.id || !swing?.id) throw new Error('No receipt')
      scriptSwings.value.push(swing)
      notice.value = `Swing saved · ${response.data.data.score} points`
      if (step.value) selected.value = step.value.playerId
    } else {
      const response = await axiosPost('result/batting', payload)
      saved = response.data?.data
      if (response.data?.status !== 'success' || !saved?.id) throw new Error('No receipt')
    }
    rows.value.push(saved); clearPitch(); if (!session.value.is_scripted) notice.value = 'Swing saved.'
  } catch (err) {
    if (err.response?.status === 422) error.value = 'Swing rejected. Check the details and try again.'
    else { pending.value = true; error.value = 'Save could not be confirmed. Check saved session results before recording another swing. Do not resubmit this swing blindly.' }
  } finally { busy.value = false }
}
async function addPlayer() {
  if (!addId.value || !writable.value) return
  busy.value = true
  try {
    const sort = Math.max(-1, ...(session.value.players || []).map(p => Number(p.sort) || 0)) + 1
    await axiosPost(`coach/lineup/${session.value.id}`, { player: addId.value, pitching: false, batting: true, sort })
    adding.value = false; addId.value = ''; await load()
  } catch { error.value = 'Unable to add player. Reload to check the lineup.' }
  finally { busy.value = false }
}
const report = () => { if (ready.value && !busy.value && !pending.value) return router.push(`/session/report/${session.value.id}/batting${user.userData.type === 'player' ? '?scope=player' : ''}`) }
async function finish() {
  if (!writable.value || !note.value.trim()) return
  busy.value = true; error.value = ''
  try {
    await axiosPut(`training/${session.value.id}`, { end_note: note.value.trim(), is_completed: true })
    session.value.is_completed = true; training.setDataTraining(session.value)
    busy.value = false
    await router.push(plannerSessionReturn(sessionStorage, session.value.id, user.userData.id, `/session/report/${session.value.id}/batting${user.userData.type === 'player' ? '?scope=player' : ''}`))
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
    <p v-if="session && !personalOnly" role="status" class="error">Team sessions are read-only here. Start a personal session from My Sessions to record your own results.</p>
    <main class="bullpen-screen" :aria-busy="loading || busy">
      <h1 class="sr-only">Batting practice live scoring</h1>
      <div class="scoring-stage batting-stage">
        <header class="scorebar">
          <div class="pitcher-tabs" aria-label="Session hitters">
            <button v-if="user.userData.type === 'coach'" class="primary add-pitcher" aria-label="Add hitter" :disabled="!writable" @click="adding = !adding">+</button>
            <button v-for="player in players" :key="player.id" :class="{ primary: selected === player.id }" :aria-pressed="selected === player.id" :disabled="busy || !!pending || session?.is_scripted" @click="selected = player.id">{{ name(player) }}</button>
          </div>
          <nav aria-label="Session actions"><button :disabled="!ready" :aria-expanded="showStats" aria-controls="batting-stats" @click="showStats = !showStats">{{ showStats ? 'Hide statistics' : 'Show statistics' }} →</button><button :disabled="!ready || busy || !!pending" @click="report">Session report</button><button class="primary" :disabled="!writable" @click="finishing = true">End practice →</button></nav>
        </header>
        <section v-if="ready && session.is_scripted" class="panel finish"><h2>{{ step ? roundLabel(step.round_type) : 'Scripted BP complete' }}</h2><p v-if="step">Round {{ step.round+1 }} of {{ scriptRounds.length }} · {{ name(current) }} · Swing {{ step.index }} of {{ step.swing_count }}</p><p v-if="step">{{ roundGoal(step.round_type) }}</p><p v-else>Review the scorecard and session report, then end practice.</p><details><summary>Scripted scorecard · {{ scriptSwings.length }} swings</summary><table><thead><tr><th>Hitter</th><th>Round</th><th>Swings</th><th>Points</th></tr></thead><tbody><template v-for="player in players" :key="player.id"><tr v-for="round in scriptRounds" :key="round.round_type"><td>{{ name(player) }}</td><td>{{ roundLabel(round.round_type) }}</td><td>{{ scriptSwings.filter(s=>s.batter_id===player.id && s.round_type===round.round_type).length }} / {{ round.swing_count }}</td><td>{{ scriptSwings.filter(s=>s.batter_id===player.id && s.round_type===round.round_type).reduce((total,s)=>total+Number(s.raw_score),0) }}</td></tr></template></tbody></table></details></section>
        <p v-if="loading" class="notice" role="status">Loading session…</p>
        <div v-if="error" role="alert" class="error">{{ error }} <button :disabled="loading || busy" @click="load">{{ pending ? 'Check saved results' : 'Reload session' }}</button></div>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <p v-if="session?.is_completed" class="notice">This session is complete. Results are read-only.</p>
        <form v-if="adding" class="panel add-form" @submit.prevent="addPlayer"><label>Hitter<select v-model="addId"><option value="">Select hitter</option><option v-for="p in available" :key="p.id" :value="p.id">{{ name(p) }}</option></select></label><button :disabled="!writable || !addId">Add to session</button><button type="button" @click="adding = false">Cancel</button></form>
        <template v-if="ready">
          <div class="batting-selectors">
            <section class="panel pill-row" aria-label="Contact quality"><button v-for="(label,code) in qualityOptions" :key="code" :disabled="!writable || (quality === 'M' && code !== 'M')" :aria-pressed="quality === code" @click="chooseQuality(code)">{{ label }}</button></section>
            <section class="panel pill-row" aria-label="Trajectory"><button v-for="code in trajectories" :key="code" :disabled="!writable || quality === 'M'" :aria-pressed="trajectory === code" @click="trajectory = trajectory === code ? '' : code">{{ code }}</button></section>
          </div>
          <div class="scoring-columns batting-columns">
            <section class="panel catcher-panel"><h2>Pitch location</h2><div class="catcher batting-catcher" role="button" tabindex="0" :aria-disabled="!writable" aria-label="Pitch location: tap catcher or use arrow keys" @click="chooseLocation" @keydown="moveLocation"><div class="strike-zone batting-zone" aria-hidden="true"><span v-for="n in 9" :key="n" /></div><span v-if="location" class="pitch-dot" :style="pointStyle" /></div><p class="target-label">{{ location ? `${pitchInfo.zone === 'S' ? 'Strike' : 'Ball'} · ${pitchInfo.pitch_side}` : 'Tap pitch location (optional)' }}</p></section>
            <section class="panel field-panel"><h2>Hit location</h2><BattingScoringField v-model="field" :disabled="!writable || quality === 'M'" /><p class="target-label">{{ quality === 'M' ? 'MISS/F · Field and exit velocity excluded' : 'Tap the field · Standard high school dimensions' }}</p></section>
            <form class="panel velocity-panel" @submit.prevent="save"><label for="batting-velocity">Exit velocity · MPH</label><input id="batting-velocity" v-model="velocity" class="velocity" inputmode="numeric" placeholder="—" :disabled="!writable || quality === 'M'" /><div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','0','Clear','⌫']" :key="value" type="button" :disabled="!writable || quality === 'M'" :aria-label="value === '⌫' ? 'Delete last digit' : value" @click="key(value)">{{ value }}</button></div><button class="primary batting-save" :disabled="!writable || !validPitch">{{ busy ? 'Saving…' : 'Save' }}</button><p class="velocity-help">Whole mph · Leave blank if not measured</p><p class="velocity-help">No contact, trajectory, field or velocity selected records a take.</p></form>
          </div>
          <div class="batting-totals"><span>{{ user.userData.type === 'player' ? 'My session' : 'Team' }}: {{ teamStats.count }} pitches · {{ teamStats.percent ?? '—' }}% contact</span><span>Player: {{ stats.count }} pitches · {{ stats.percent ?? '—' }}% contact</span><span>Top EV {{ stats.top ?? '—' }} · Avg EV {{ stats.average ?? '—' }} mph</span></div>
        </template>
        <footer class="stage-footer"><button :disabled="busy || !!pending" @click="router.push(user.userData.type === 'player' ? '/training-player/batting' : '/training/batting')">← Leave session</button></footer>
      </div>
      <section v-if="showStats && ready" id="batting-stats" class="panel finish"><h2>Batting Statistics</h2><p>Current session only. All tabs update after each confirmed save.</p><LivePracticeStats mode="B" :rows="visibleRows" :players="session.players" /></section>
      <section v-if="finishing && ready" class="panel finish"><h2>Finish batting practice</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="!writable || !note.trim()">Complete session</button></form></section>
    </main>
  </Layout>
</template>
<style scoped>
.bullpen-screen{max-width:1800px;margin:auto;padding:16px;color:#fff}.scoring-stage{position:relative;isolation:isolate;min-height:630px;overflow:hidden;border-radius:22px;border:1px solid #ffffff40;background:#172335 url('../../assets/img/training/bullpen-stadium.png') center center/100% 100% no-repeat;padding:16px}.scoring-stage::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,#0003,transparent 40%,transparent 60%,#0003);z-index:-1}.scorebar{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:8px;border-radius:24px;background:#05070bd9;border:1px solid #ffffff30}.pitcher-tabs{display:flex;gap:8px;overflow-x:auto;min-width:0;flex:1}.pitcher-tabs button{white-space:nowrap}.scorebar nav{display:flex;gap:8px;flex-wrap:wrap}button{min-height:44px;padding:10px 18px;border:1px solid #ffffff40;border-radius:20px;color:white;background:#14172099;font-weight:800;font-size:16px;cursor:pointer}button:disabled{opacity:.45;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,.catcher:focus-visible{outline:3px solid #8de6ff;outline-offset:3px}.primary,button[aria-pressed=true]{background:#ff2451;border-color:#ff5275}.add-pitcher{font-size:30px;padding:0 18px;line-height:1}.scoring-columns{display:grid;grid-template-columns:minmax(260px,30%) minmax(220px,1fr) minmax(240px,28%);gap:14px;margin-top:18px;align-items:stretch;min-height:490px}.panel{padding:16px;background:#080b13a8;border:1px solid #ffffff35;border-radius:25px;backdrop-filter:blur(3px)}.selection-columns{display:grid;grid-template-columns:1fr 1fr;gap:10px}h2{font-size:18px;font-weight:800;margin-bottom:10px}.options{display:grid;gap:9px}.options button{height:52px;text-transform:uppercase;font-size:20px;background:#ffffff10}.options button[aria-pressed=true]{background:#ff2451b8}.take-label{font-size:11px;min-height:18px;margin:8px 0;color:#e4e6ee}.summary-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px}.summary{background:#25234bb8;border:1px solid #ffffff25;border-radius:20px;padding:10px}.summary h3{text-align:center;font-size:18px;font-weight:800;margin-bottom:8px}.summary dl{display:grid;gap:6px}.summary dl>div{display:flex;align-items:center;justify-content:space-between;gap:6px;border-radius:20px;border:1px solid #ffffff22;padding:8px;background:#0002}.summary dt{text-transform:uppercase;font-size:11px;font-weight:800;letter-spacing:1px;color:#d0cfe0}.summary dd{font-size:22px;font-weight:800}.target-panel{display:flex;flex-direction:column;justify-content:center;min-width:0}.catcher{position:relative;width:100%;aspect-ratio:1;cursor:crosshair;touch-action:manipulation;border-radius:12px}.strike-zone{position:absolute;left:25%;top:23.333%;width:50%;height:51.667%;border:3px solid white;border-radius:8px;background:#ffffff50;pointer-events:none;box-shadow:0 0 0 2px #ffffff50}.pitch-dot{position:absolute;transform:translate(-50%,-50%);width:17px;height:17px;border-radius:50%;border:3px solid white;background:#ff2451;box-shadow:0 2px 8px #000;pointer-events:none}.target-label{align-self:center;padding:8px 12px;border-radius:15px;background:#060a12b8;font-size:12px;text-align:center}.velocity-panel{display:flex;flex-direction:column;justify-content:center}.velocity{width:100%;min-width:0;height:85px;border:2px solid #ffffff55;border-radius:35px;background:#050710bb;text-align:center;color:white;font-size:44px;font-weight:800}.velocity::placeholder{color:#ddd}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:15px}.keypad button{background:#ea174a80;border-color:#ff7e9950;border-radius:30px;font-size:24px;min-height:60px;padding:10px 4px}.keypad button:nth-last-child(2){font-size:15px}.save-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:15px}.save-actions button{background:#ec204798;font-size:24px;min-height:58px;border-radius:30px}.velocity-help{text-align:center;font-size:11px;margin-top:12px;color:#ece5e9}.stage-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:12px;font-size:13px}.stage-footer span{padding:6px 12px;background:#050710b8;border-radius:20px}.notice,.error{padding:10px 16px;border-radius:12px;margin-top:10px;background:#070d18dc}.error{background:#5f1029ef}.finish{margin-top:18px;background:var(--app-surface,#10192b);color:var(--app-text,#fff)}.finish textarea{display:block;width:100%;min-height:90px;padding:12px;margin:12px 0;border:1px solid #9ca6bd;border-radius:10px;color:#152035;background:#fff}.finish button{margin-right:8px;background:#283a59}.finish .primary{background:#db1742}.add-form{display:flex;gap:12px;align-items:end;margin-top:10px}.add-form select{display:block;background:#152035;color:#fff;padding:12px;border-radius:10px}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #9eafc5}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
@media(min-width:1400px){.scoring-columns{min-height:580px}.options button{height:65px}.keypad button{min-height:75px}.summary dd{font-size:27px}}
@media(max-width:1000px){.bullpen-screen{padding:8px}.scoring-stage{padding:10px}.scorebar{flex-wrap:wrap}.scorebar nav{margin-left:auto}.scoring-columns{grid-template-columns:minmax(220px,31%) minmax(170px,1fr) minmax(200px,28%);gap:8px}.panel{padding:10px}.options button{font-size:17px;padding:8px}.summary{padding:6px}.summary dt{font-size:9px;letter-spacing:0}.summary dd{font-size:18px}.keypad{gap:8px}}
@media(max-width:700px) and (orientation:portrait){.scoring-columns{grid-template-columns:1fr 1fr}.target-panel{grid-column:1/-1;grid-row:1;max-width:350px;width:100%;justify-self:center}.catcher{background:url('../../assets/img/training/bullpen-stadium.png') center/cover}.scoring-stage{background-size:auto 100%;background-position:center}.pitch-panel,.velocity-panel{min-width:0}.summary-cards{grid-template-columns:1fr}.options button{font-size:14px}.keypad button{min-height:48px;font-size:20px}.save-actions{gap:5px}.save-actions button{font-size:16px;padding:8px}.scorebar nav button{font-size:12px;padding:8px 10px}.stage-footer{flex-wrap:wrap}}
.batting-stage{background-image:url('../../assets/img/training/batting-stadium.png')}.batting-selectors{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:15px}.pill-row{display:flex;gap:8px;padding:8px;justify-content:center}.pill-row button{flex:1;padding:10px 6px;min-width:0}.batting-columns{grid-template-columns:minmax(200px,28%) minmax(240px,1fr) minmax(210px,25%);min-height:440px}.batting-catcher{background:url('../../assets/img/training/batting-catcher.png') center/100% 100%;aspect-ratio:3/4;flex:1}.catcher-panel,.field-panel{display:flex;flex-direction:column;min-width:0}.field-panel{background:#081a16a8}.field-panel :deep(svg){flex:1}.batting-zone{display:grid;grid-template-columns:repeat(3,1fr)}.batting-zone span{border:1px solid #ffffff99}.batting-save{width:100%;margin-top:14px}.batting-totals{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.batting-totals span{padding:8px 14px;border-radius:20px;background:#080b18d9;border:1px solid #ffffff30}.batting-columns .keypad button{min-height:50px}.velocity-panel label{font-weight:800;margin-bottom:10px}@media(max-width:800px){.batting-selectors{grid-template-columns:1fr}.batting-columns{grid-template-columns:1fr 1fr}.field-panel{grid-column:1/-1;grid-row:1}.field-panel :deep(svg){min-height:300px}.batting-columns .velocity-panel{grid-column:2;grid-row:2}.batting-columns .catcher-panel{grid-column:1;grid-row:2}.pill-row button{font-size:14px}.batting-columns .keypad button{font-size:18px}}
</style>
