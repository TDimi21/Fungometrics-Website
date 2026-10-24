<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TrainingSessionStatsTabs from '@/components/statistics/training/TrainingSessionStatsTabs.vue'
import Layout from '@/layout/Layout.vue'
import { useTrainingStore } from '@/store/training'
import { useUserStore } from '@/store/user'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { useAxiosAuth } from '@/composables/axios-auth'
import { extractSessionRows } from '@/utils/sessionReportRows'
import { playerThrows, summarizeThrows, validDistance } from '@/utils/longTossScoring'
import { plannerSessionReturn } from '@/features/planner/lib/sessionReturn'

const training = useTrainingStore(), user = useUserStore(), roster = usePlayerStore(), teamStore = useTeamStore()
const route = useRoute(), router = useRouter()
const { axiosGet, axiosPost, axiosPut } = useAxiosAuth()
const session = ref(null), rows = ref([]), selected = ref(''), sets = ref({})
const distance = ref(''), hop = ref(0), busy = ref(false), loading = ref(true), ready = ref(false)
const error = ref(''), notice = ref(''), finishing = ref(false), note = ref(''), adding = ref(false), addId = ref('')
const practiceId = route.query.session || training.trainingActive?.id
const players = computed(() => (session.value?.players || []).filter(p => user.userData.type !== 'player' || p.id === user.userData.id))
const current = computed(() => players.value.find(p => p.id === selected.value))
const ownRows = computed(() => playerThrows(rows.value, selected.value))
const currentSet = computed(() => sets.value[selected.value] || 1)
const inSet = computed(() => ownRows.value.filter(row => Number(row.set || 1) === currentSet.value).length)
const teamStats = computed(() => summarizeThrows(rows.value)), stats = computed(() => summarizeThrows(ownRows.value))
const available = computed(() => (roster.players || []).filter(p => !(session.value?.players || []).some(member => member.id === p.id)))
const personalOnly = computed(() => user.userData.type !== 'player' || (session.value?.team_id == null && session.value?.user_id === user.userData.id))
const writable = computed(() => personalOnly.value && ready.value && current.value && !session.value?.is_completed && !busy.value)
const name = p => p?.name?.full || [p?.name?.first, p?.name?.last].filter(Boolean).join(' ') || 'Player'
const feet = value => value == null ? '—' : `${value} ft`
const difference = (value, baseline) => value == null || baseline == null ? '—' : `${value - baseline >= 0 ? '+' : ''}${Math.round((value - baseline) * 10) / 10} ft`
const byHop = (source, value) => summarizeThrows(source.filter(row => Number(row.hop || 0) === value)).average
watch(selected, () => { distance.value = ''; hop.value = 0 })

async function load() {
  loading.value = true; ready.value = false; error.value = ''
  try {
    if (!practiceId) throw new Error('Choose a Long Toss session before scoring.')
    const response = await axiosGet(`training/${practiceId}`)
    session.value = response.data.data
    if (session.value.modes !== 'LT') throw new Error('This is not a Long Toss session. Choose Long Toss from Practice Sessions.')
    let results = []
    try { results = extractSessionRows((await axiosGet(`statistics/${practiceId}/longtoss`)).data, 'long_toss') }
    catch (err) { if (err.response?.status !== 404 || err.response?.data?.code !== '026-E') throw err }
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
  if (value === 'Clear') distance.value = ''
  else if (value === '⌫') distance.value = String(distance.value).slice(0, -1)
  else distance.value = `${distance.value}${value}`.replace(/^0+(?=\d)/, '').slice(0, 10)
}
async function save() {
  if (!writable.value || !validDistance(distance.value)) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const response = await axiosPost('result/longtoss', {
      practice_id: session.value.id, team_id: session.value.team_id || null,
      user_id: selected.value, distance: Number(distance.value), hop: hop.value, set: currentSet.value,
    })
    const saved = response.data?.data?.result
    if (!saved?.id) { ready.value = false; throw new Error('Check the session before recording another throw.') }
    rows.value.push(saved)
    distance.value = ''; hop.value = 0; notice.value = 'Throw saved.'
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
    await axiosPost(`coach/lineup/${session.value.id}`, { player: addId.value, pitching: true, batting: false, sort })
    adding.value = false; addId.value = ''; await load()
  } catch { error.value = 'Unable to add this player. Reload to check the lineup before retrying.' }
  finally { busy.value = false }
}
const showStats = ref(false)
const statsRows = computed(() => (user.userData.type === 'player' ? playerThrows(rows.value, user.userData.id) : rows.value).map(row => ({
  ...row,
  player_name: row.player_name || name((session.value?.players || []).find(player => player.id === row.user_id)),
})))
const report = () => {
  if (!ready.value || busy.value) return
  return router.push(`/session/report/${session.value.id}/long_toss${user.userData.type === 'player' ? '?scope=player' : ''}`)
}
async function finish() {
  if (!writable.value || !note.value.trim()) return
  busy.value = true; error.value = ''
  try {
    await axiosPut(`training/${session.value.id}`, { end_note: note.value.trim(), is_completed: true })
    session.value.is_completed = true; training.setDataTraining(session.value)
    await router.push(plannerSessionReturn(sessionStorage, session.value.id, user.userData.id, `/session/report/${session.value.id}/long_toss${user.userData.type === 'player' ? '?scope=player' : ''}`))
  } catch { error.value = 'Unable to confirm completion. Reload the session before trying again.' }
  finally { busy.value = false }
}
onMounted(load)
</script>

<template>
  <Layout>
    <p v-if="session && !personalOnly" role="status" class="error">Team sessions are read-only here. Start a personal session from My Sessions to record your own results.</p>
    <main class="lt-scoring" :aria-busy="loading || busy">
      <header class="lt-header">
        <div><p class="eyebrow">Training mode · Live scoring</p><h1>Long Toss</h1><p>Record each throw in feet, just like the app.</p></div>
        <nav aria-label="Session actions">
          <button :disabled="busy" @click="router.push(user.userData.type === 'player' ? '/training-player/training-mode?mode=LT' : '/training/training-mode?mode=LT')">Leave session</button>
          <button :disabled="!ready || busy" :aria-expanded="showStats" aria-controls="live-session-stats" @click="showStats = !showStats">{{ showStats ? 'Hide stats' : 'Stats' }}</button>
          <button :disabled="!ready || busy" @click="report">Session report</button>
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
          <section class="panel"><h2>Throw type</h2><p>Select hops. Click again for no hops.</p>
            <div class="hop-options"><button v-for="value in [0,1,2,3]" :key="value" :aria-pressed="hop === value" :disabled="!writable" @click="hop = hop === value ? 0 : value">{{ value === 0 ? 'No hops' : `${value} hop${value > 1 ? 's' : ''}` }}</button></div>
            <button class="primary new-set" :disabled="!writable || !inSet" @click="sets[selected] = currentSet + 1">New set +</button>
            <p>New sets apply to the selected player. The next saved throw records the set.</p>
          </section>
          <section class="panel"><h2>Session statistics</h2><p>Distances from this session only.</p>
            <dl class="metrics"><div><dt>Top team distance</dt><dd>{{ feet(teamStats.top) }}</dd></div><div><dt>Average team distance</dt><dd>{{ feet(teamStats.average) }}</dd></div></dl>
            <dl class="metrics counters"><div><dt>Set</dt><dd>{{ currentSet }}</dd></div><div><dt>In set</dt><dd>{{ inSet }}</dd></div><div><dt>Total throws</dt><dd>{{ stats.count }}</dd></div></dl>
            <dl class="metrics"><div><dt>Player top distance</dt><dd>{{ feet(stats.top) }}</dd><small>{{ difference(stats.top, teamStats.top) }} vs team top</small></div><div><dt>Player average</dt><dd>{{ feet(stats.average) }}</dd><small>{{ difference(stats.average, teamStats.average) }} vs team average</small></div></dl>
            <h3>Average distance by hops</h3><div class="hop-stats"><div v-for="value in [0,1,2,3]" :key="value"><strong>{{ value }} hops</strong><p>{{ feet(byHop(ownRows, value)) }}</p><small>{{ difference(byHop(ownRows, value), byHop(rows, value)) }} vs team</small></div></div>
          </section>
          <form class="panel" @submit.prevent="save"><label for="lt-distance">Distance (whole feet)</label><input id="lt-distance" v-model="distance" class="distance" inputmode="numeric" autocomplete="off" :disabled="!writable" placeholder="0" />
            <div class="keypad"><button v-for="value in ['1','2','3','4','5','6','7','8','9','Clear','0','⌫']" :key="value" type="button" :disabled="!writable" :aria-label="value === '⌫' ? 'Delete last digit' : value" @click="key(value)">{{ value }}</button></div>
            <button class="primary save" :disabled="!writable || !validDistance(distance)">{{ busy ? 'Saving…' : 'Save throw' }}</button>
          </form>
        </div>
        <section v-if="showStats" id="live-session-stats" class="panel finish" aria-label="Live session statistics">
          <h2>Session statistics</h2>
          <p>Updates after each saved throw or swing. Keep scoring above without leaving this session.</p>
          <TrainingSessionStatsTabs :mode="'LT'" :rows="statsRows" :team-name="teamStore.team?.name || 'Session'" :editable="false" />
        </section>
        <section v-if="finishing" class="panel finish"><h2>Finish Long Toss</h2><form @submit.prevent="finish"><label>Session notes<textarea v-model="note" required :disabled="busy" /></label><button type="button" :disabled="busy" @click="finishing = false">Keep scoring</button><button class="primary" :disabled="busy || !note.trim()">Complete session</button></form></section>
      </template>
    </main>
  </Layout>
</template>

<style scoped>
.lt-scoring{--lt-bg:var(--app-surface,#fff);--lt-ink:var(--app-text,#17243b);max-width:1440px;margin:auto;padding:28px;color:var(--lt-ink)}
.lt-header,.lt-player{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.lt-header{padding:24px;border-radius:18px;background:#101e35;color:#fff;margin-bottom:20px}.lt-header p{color:#d4dfef}.lt-header h1{font-size:30px;font-weight:800}.eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:1.5px}nav{display:flex;gap:10px;flex-wrap:wrap}.panel{background:var(--lt-bg);border:1px solid #9eafc5;border-radius:16px;padding:22px;color:var(--lt-ink)}.lt-player{margin:18px 0}.lt-grid{display:grid;grid-template-columns:minmax(180px,1fr) minmax(300px,2fr) minmax(220px,1fr);gap:18px}h2{font-size:19px;font-weight:750;margin-bottom:8px}h3{font-weight:700;margin-top:20px}p{font-size:14px;line-height:1.6}button{border:1px solid #879bb6;border-radius:9px;padding:12px 16px;font-weight:650;background:#f1f5fa;color:#17243b;min-height:44px}button:disabled{opacity:.5;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #1684d4;outline-offset:3px}.primary,button[aria-pressed=true]{background:#cf1737;border-color:#cf1737;color:white}.hop-options{display:grid;gap:12px;margin-top:22px}.new-set,.save{width:100%;margin-top:18px}.metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.metrics>div,.hop-stats>div{background:#edf2f8;color:#17243b;border-radius:10px;padding:12px}dt{font-size:12px}dd{font-size:24px;font-weight:800}.counters{grid-template-columns:repeat(3,1fr)}small{font-size:11px}.hop-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:12px}.hop-stats>div{padding:8px;text-align:center}label{display:block;font-weight:650}select,input,textarea{display:block;border:1px solid #879bb6;border-radius:8px;background:#fff;color:#17243b;padding:12px;max-width:100%;margin-top:6px}select{min-width:220px}.distance{font-size:40px;text-align:center;width:100%;font-weight:800}.keypad{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:18px}.keypad button{font-size:20px;padding:16px 5px}.lt-error{padding:15px;background:#fff1f2;color:#911a2d;border:1px solid #e797a6;border-radius:10px}.finish{margin-top:20px}.finish textarea{width:100%;min-height:100px;margin-bottom:15px}.finish button{margin-right:10px}@media(max-width:1100px){.lt-grid{grid-template-columns:1fr 1fr}.lt-grid>section:nth-child(2){grid-column:1/-1;grid-row:2}}@media(max-width:600px){.lt-scoring{padding:12px}.lt-grid{display:flex;flex-direction:column}.lt-grid>form{order:-1}.hop-options{grid-template-columns:repeat(2,1fr)}.panel{padding:16px}.hop-stats{grid-template-columns:repeat(2,1fr)}}
</style>
