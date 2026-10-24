<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
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
    <main class="lt-scoring" :aria-busy="loading || busy">
      <header class="lt-header">
        <div><p class="eyebrow">Bullpen · Live scoring</p><h1>Bullpen</h1><p>Select pitch location, pitch type, and contact. Add velocity when measured.</p></div>
        <nav aria-label="Session actions">
          <button :disabled="busy || !!pending" @click="router.push('/training/bullpen')">Leave session</button>
          <button :disabled="!ready" :aria-expanded="showStats" @click="showStats = !showStats">{{ showStats ? 'Hide stats' : 'Stats' }}</button>
          <button :disabled="!ready || busy || !!pending" @click="report">Session report</button>
          <button class="primary" :disabled="!writable" @click="finishing = true">End session</button>
        </nav>
      </header>
      <p v-if="loading" role="status">Loading session…</p>
      <div v-if="error" role="alert" class="lt-error">{{ error }} <button v-if="!pending" :disabled="loading || busy" @click="load">Reload session</button></div>
      <p v-if="notice" role="status">{{ notice }}</p>
      <p v-if="session?.is_completed">This session is complete. Its results are read-only.</p>
      <template v-if="ready">
        <section class="lt-player panel">
          <label>Pitcher<select v-model="selected" :disabled="busy || !!pending"><option v-for="player in players" :key="player.id" :value="player.id">{{ name(player) }}</option></select></label>
          <button v-if="user.userData.type === 'coach'" :disabled="!writable" @click="adding = !adding">Add pitcher</button>
        </section>
        <form v-if="adding" class="panel" @submit.prevent="addPlayer"><label>Player<select v-model="addId"><option value="">Select pitcher</option><option v-for="p in available" :key="p.id" :value="p.id">{{ name(p) }}</option></select></label><button :disabled="!writable || !addId">Add to session</button></form>
        <div class="lt-grid">
          <section class="panel pitch-controls">
            <h2>Pitch type</h2><div class="pitch-options"><button v-for="pitch in pitchTypes" :key="pitch" :disabled="!writable" :aria-pressed="pitchType === pitch" @click="pitchType = pitchType === pitch ? '' : pitch">{{ pitch }}</button></div>
            <h3>Contact</h3><div class="pitch-options"><button v-for="(label, code) in contacts" :key="code" :disabled="!writable" :aria-pressed="contact === code" @click="contact = contact === code ? 'TK' : code">{{ label }}</button></div>
          </section>
          <section class="panel">
            <h2>Pitch location</h2><p>Tap the catcher target. Keyboard: use arrow keys to place the marker.</p>
            <div class="catcher" role="button" tabindex="0" :aria-disabled="!writable" aria-label="Select pitch location with tap or arrow keys" @click="chooseLocation" @keydown="moveLocation">
              <div class="strike-zone" aria-hidden="true"><span v-for="n in 9" :key="n" /></div>
              <span v-if="location" class="pitch-dot" :style="pointStyle" aria-hidden="true" />
            </div>
            <p aria-live="polite">{{ location ? `${pitchInfo.zone === 'S' ? 'Strike' : 'Ball'} · ${pitchInfo.pitch_side}` : 'Choose a location' }}</p>
            <dl class="metrics counters"><div><dt>Pitches</dt><dd>{{ stats.count }}</dd></div><div><dt>Strikes</dt><dd>{{ stats.strikes }}</dd></div><div><dt>Balls</dt><dd>{{ stats.balls }}</dd></div></dl>
            <dl class="metrics"><div><dt>Strike %</dt><dd>{{ stats.percent == null ? '—' : `${stats.percent}%` }}</dd></div><div><dt>Top / avg mph</dt><dd>{{ stats.top ?? '—' }} / {{ stats.average ?? '—' }}</dd></div></dl>
          </section>
          <form class="panel" @submit.prevent="save">
            <label>Velocity (optional, whole mph)<input v-model="velocity" class="distance" inputmode="numeric" placeholder="—" :disabled="!writable" /></label>
            <p>Leave blank when velocity was not measured.</p>
            <div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','Clear','0','⌫']" :key="value" type="button" :disabled="!writable" @click="key(value)">{{ value }}</button></div>
            <button class="primary save" :disabled="busy || (!pending && (!writable || !validPitch))">{{ busy ? 'Saving…' : pending ? 'Retry same pitch' : 'Save pitch' }}</button>
          </form>
        </div>
        <section v-if="showStats" class="panel finish"><h2>Live bullpen statistics</h2><p>Saved pitches for this session. Updates after every confirmed save.</p><div class="table-scroll"><table><thead><tr><th>Pitcher</th><th>Pitch</th><th>Contact</th><th>Location</th><th>mph</th></tr></thead><tbody><tr v-for="row in visibleRows" :key="row.id"><td>{{ name(session.players.find(p => p.id === row.pitcher_id)) }}</td><td>{{ row.type_throw }}</td><td>{{ contacts[row.trajectory] || row.trajectory }}</td><td>{{ row.zone }} · {{ row.pitch_side }}</td><td>{{ Number(row.miles_per_hour) > 0 ? row.miles_per_hour : '—' }}</td></tr></tbody></table><p v-if="!visibleRows.length">No pitches recorded yet.</p></div></section>
        <section v-if="finishing" class="panel finish"><h2>Finish bullpen</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="!writable || !note.trim()">Complete session</button></form></section>
      </template>
    </main>
  </Layout>
</template>
<style scoped>
.lt-scoring{--lt-bg:var(--app-surface,#fff);--lt-ink:var(--app-text,#17243b);max-width:1440px;margin:auto;padding:28px;color:var(--lt-ink)}
.lt-header,.lt-player{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.lt-header{padding:24px;border-radius:18px;background:#101e35;color:#fff;margin-bottom:20px}.lt-header p{color:#d4dfef}.lt-header h1{font-size:30px;font-weight:800}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:1.5px}nav{display:flex;gap:10px;flex-wrap:wrap}.panel{background:var(--lt-bg);border:1px solid #9eafc5;border-radius:16px;padding:22px;color:var(--lt-ink)}.lt-player{margin:18px 0}.lt-grid{display:grid;grid-template-columns:minmax(180px,1fr) minmax(300px,2fr) minmax(220px,1fr);gap:18px}h2{font-size:19px;font-weight:750;margin-bottom:8px}h3{font-weight:700;margin-top:20px}p{font-size:14px;line-height:1.6}button{border:1px solid #879bb6;border-radius:9px;padding:12px 16px;font-weight:650;background:#f1f5fa;color:#17243b;min-height:44px}button:disabled{opacity:.5;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #1684d4;outline-offset:3px}.primary,button[aria-pressed=true]{background:#cf1737;border-color:#cf1737;color:white}.hop-options{display:grid;gap:12px;margin-top:22px}.new-set,.save{width:100%;margin-top:18px}.metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.metrics>div,.hop-stats>div{background:#edf2f8;color:#17243b;border-radius:10px;padding:12px}dt{font-size:12px}dd{font-size:24px;font-weight:800}.counters{grid-template-columns:repeat(3,1fr)}small{font-size:11px}.hop-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:12px}.hop-stats>div{padding:8px;text-align:center}label{display:block;font-weight:650}select,input,textarea{display:block;border:1px solid #879bb6;border-radius:8px;background:#fff;color:#17243b;padding:12px;max-width:100%;margin-top:6px}select{min-width:220px}.distance{font-size:40px;text-align:center;width:100%;font-weight:800}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:18px}.keypad button{font-size:20px;padding:16px 5px}.lt-error{padding:15px;background:#fff1f2;color:#911a2d;border:1px solid #e797a6;border-radius:10px}.finish{margin-top:20px}.finish textarea{width:100%;min-height:100px;margin-bottom:15px}.finish button{margin-right:10px}@media(max-width:1100px){.lt-grid{grid-template-columns:1fr 1fr}.lt-grid>section:nth-child(2){grid-column:1/-1;grid-row:2}}@media(max-width:600px){.lt-scoring{padding:12px}.lt-grid{display:flex;flex-direction:column}.lt-grid>form{order:-1}.hop-options{grid-template-columns:repeat(2,1fr)}.panel{padding:16px}.hop-stats{grid-template-columns:repeat(2,1fr)}}
.weight-controls{display:flex;align-items:center;gap:8px;margin-top:12px}.weight-controls input{width:90px;min-width:0;text-align:center}.hop-stats{grid-template-columns:repeat(auto-fit,minmax(90px,1fr))}
.pitch-options{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}.catcher{position:relative;width:100%;aspect-ratio:1;background:#15243b url('../../assets/img/training/catcher.png') center/100% 100%;cursor:crosshair;border-radius:12px;touch-action:manipulation}.catcher:focus-visible{outline:3px solid #1684d4}.strike-zone{position:absolute;left:25%;top:23.333%;width:50%;height:51.667%;display:grid;grid-template-columns:repeat(3,1fr);border:2px solid #fff;background:#16937544;pointer-events:none}.strike-zone span{border:1px solid #ffffff88}.pitch-dot{position:absolute;transform:translate(-50%,-50%);width:16px;height:16px;border-radius:50%;border:3px solid white;background:#e51e46;pointer-events:none}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #9eafc5}
</style>
