<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useUserStore } from '@/store/user'
import { draftKey, nextIncomplete, resultSummary, percentComplete } from './workflow'

const { axiosGet, axiosPost, axiosPut, axiosPatch } = useAxiosAuth()
const events = ref([]), teams = ref([]), eventId = ref(''), snapshot = ref(null)
const station = ref('pushups'), playerId = ref(''), tab = ref('Assessment')
const error = ref(''), notice = ref(''), saving = ref(false), loading = ref(false), polling = ref(false)
const search = ref(''), completionFilter = ref('all'), showRoster = ref(false), showProgress = ref(false)
const rankings = ref([]), rankStation = ref(''), ageFilter = ref(''), gradFilter = ref(''), resultSearch = ref('')
const showCreate = ref(false), showAdd = ref(false), candidateSearch = ref(''), candidates = ref([]), adding = ref(false)
const newEvent = reactive({ name: '', location: 'The Yard', assessment_date: new Date().toLocaleDateString('en-CA'), team_id: '' })
const newPlayer = reactive({ first_name: '', last_name: '', phone: '', born_date: '', grad_year: '', height_in_ft: '', height_in_inch: '', weight: '', position: '', hit_side: '', throw_side: '' })
const includePhone = ref(false), claimCode = ref(''), claimName = ref('')
const currentUserId = useUserStore().userData?.id
const storageKey = currentUserId ? `fmtrx-assessment-drafts:${currentUserId}` : null
let restored = {}
try { restored = JSON.parse((storageKey && sessionStorage.getItem(storageKey)) || '{}') } catch (_) { /* browser storage may be disabled */ }
const drafts = reactive(restored)
const players = computed(() => snapshot.value?.players || [])
const results = computed(() => snapshot.value?.results || [])
const stations = computed(() => snapshot.value?.stations || {})
const definition = computed(() => stations.value[station.value])
const event = computed(() => snapshot.value?.assessment)
const selectedPlayer = computed(() => players.value.find(p => p.id === playerId.value))
const currentKey = computed(() => draftKey(eventId.value, playerId.value, station.value))
const draft = computed(() => drafts[currentKey.value])
const completed = (id, key = station.value) => results.value.some(r => r.player_id === id && r.station === key)
const completionCount = id => results.value.filter(r => r.player_id === id).length
const progress = key => results.value.filter(r => r.station === key).length
const completePlayers = computed(() => players.value.filter(p => completionCount(p.id) === Object.keys(stations.value).length).length)
const filteredPlayers = computed(() => players.value.filter(p => p.name.toLowerCase().includes(search.value.toLowerCase()) && (completionFilter.value === 'all' || completed(p.id) === (completionFilter.value === 'complete'))))
const dirtyCount = computed(() => Object.values(drafts).filter(d => d.dirty).length)
const summary = r => resultSummary(r, stations.value)
const initials = p => `${p?.first_name?.[0] || ''}${p?.last_name?.[0] || ''}`
const playerName = id => players.value.find(p => p.id === id)?.name || 'Player'
const errorMessage = e => Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || 'Unable to complete this action. Please try again.'
const filteredRankings = computed(() => rankings.value.filter(r => (!rankStation.value || r.station === rankStation.value) && (!ageFilter.value || r.player.age === Number(ageFilter.value)) && (!gradFilter.value || Number(r.player.grad_year) === Number(gradFilter.value)) && r.player.name.toLowerCase().includes(resultSearch.value.toLowerCase())))
const activeResult = computed(() => results.value.find(r => r.player_id === playerId.value && r.station === station.value))
const conflict = computed(() => draft.value?.dirty && (activeResult.value?.revision || 0) !== draft.value.revision)

function loadDraft(force = false) {
  if (!selectedPlayer.value || !definition.value) return
  const existing = drafts[currentKey.value]
  if (!force && existing?.dirty) return
  const r = activeResult.value
  drafts[currentKey.value] = { values: r ? [...r.values] : Array(definition.value.count).fill(''), notes: r?.notes || '', protocol: r?.protocol || '', revision: r?.revision || 0, dirty: false }
}
function changed() { draft.value.dirty = true; notice.value = '' }
function selectPlayer(id) { if (saving.value) return; playerId.value = id; showRoster.value = false; loadDraft() }
function selectStation(key) { if (saving.value) return; station.value = key; showProgress.value = false; loadDraft() }
function navigate(offset) {
  const i = players.value.findIndex(p => p.id === playerId.value)
  selectPlayer(players.value[(i + offset + players.value.length) % players.value.length]?.id)
}
function reloadSaved() {
  if (!draft.value?.dirty || window.confirm('Discard this local draft and load the latest saved result?')) loadDraft(true)
}
async function refresh(silent = false) {
  if (!eventId.value || polling.value) return
  const requested = eventId.value
  polling.value = true
  if (!silent) loading.value = true
  try {
    const { data } = await axiosGet(`free-assessments/${requested}`)
    if (requested !== eventId.value) return
    snapshot.value = data
    if (!players.value.some(p => p.id === playerId.value)) playerId.value = players.value[0]?.id || ''
    loadDraft()
  } catch (e) { error.value = errorMessage(e) }
  finally { polling.value = false; loading.value = false }
}
async function loadEvents() {
  const { data } = await axiosGet('free-assessments')
  events.value = data.events; teams.value = data.teams
  newEvent.team_id ||= teams.value[0]?.id || ''
  eventId.value ||= events.value.find(e => e.status === 'active')?.id || events.value[0]?.id || ''
}
async function saveNext() {
  if (!draft.value || saving.value) return
  error.value = ''; notice.value = ''
  if (draft.value.values.some(v => v === '' || v === null || !Number.isFinite(Number(v)))) { error.value = 'Enter every attempt before saving this station.'; return }
  const key = currentKey.value, id = playerId.value, selectedStation = station.value, selectedEvent = eventId.value
  const payload = { revision: draft.value.revision, values: draft.value.values.map(Number), notes: draft.value.notes, protocol: draft.value.protocol || null }
  saving.value = true
  try {
    await axiosPut(`free-assessments/${selectedEvent}/players/${id}/stations/${selectedStation}`, payload)
    delete drafts[key]
    // Save acknowledgment is followed by authoritative progress; polling cannot replace the draft during editing.
    while (polling.value) await new Promise(resolve => setTimeout(resolve, 50))
    await refresh()
    playerId.value = nextIncomplete(players.value, results.value, selectedStation, id)
    loadDraft()
    notice.value = `Saved ${stations.value[selectedStation].name} for ${playerName(id)}.`
  } catch (e) { error.value = errorMessage(e) }
  finally { saving.value = false }
}
async function createEvent() {
  if (adding.value) return
  adding.value = true; error.value = ''
  try {
    const { data } = await axiosPost('free-assessments', newEvent)
    await loadEvents(); eventId.value = data.id; showCreate.value = false
  } catch (e) { error.value = errorMessage(e) }
  finally { adding.value = false }
}
async function searchCandidates() {
  const requested = eventId.value, query = candidateSearch.value
  try { const { data } = await axiosGet(`free-assessments/${requested}/candidates`, { q: query }); if (requested === eventId.value && query === candidateSearch.value) candidates.value = data }
  catch (e) { error.value = errorMessage(e) }
}
async function addPlayer(existingId = null) {
  adding.value = true; error.value = ''
  try {
    const { data } = await axiosPost(`free-assessments/${eventId.value}/${existingId ? 'players' : 'quick-add'}`, existingId ? { player_id: existingId } : newPlayer)
    while (polling.value) await new Promise(resolve => setTimeout(resolve, 50))
    await refresh(); selectPlayer(data.player_id); showAdd.value = false
    Object.keys(newPlayer).forEach(k => { newPlayer[k] = '' })
  } catch (e) { error.value = errorMessage(e) }
  finally { adding.value = false }
}
async function loadRankings() {
  const requested = eventId.value
  if (!requested) return
  try { const { data } = await axiosGet(`free-assessments/${requested}/rankings`); if (requested === eventId.value) rankings.value = data }
  catch (e) { error.value = errorMessage(e) }
}
async function exportCsv() {
  error.value = ''
  try {
    const { data } = await axiosGet(`free-assessments/${eventId.value}/export`, { include_phone: includePhone.value ? 1 : 0 })
    const url = URL.createObjectURL(new Blob(['\ufeff', data], { type: 'text/csv;charset=utf-8' }))
    const link = document.createElement('a'); link.href = url; link.download = `assessment-${event.value.assessment_date.slice(0, 10)}.csv`; link.click(); URL.revokeObjectURL(url)
  } catch (e) { error.value = errorMessage(e) }
}
async function printReport() { await loadRankings(); window.print() }
async function changeStatus() {
  try {
    await axiosPatch(`free-assessments/${eventId.value}`, { status: event.value.status === 'completed' ? 'active' : 'completed' })
    await refresh(); await loadEvents()
  } catch (e) { error.value = errorMessage(e) }
}
async function issueClaim(p) {
  try { const { data } = await axiosPost(`free-assessments/${eventId.value}/players/${p.id}/claim`, {}); claimCode.value = data.code; claimName.value = p.name }
  catch (e) { error.value = errorMessage(e) }
}
function openEntry(r) { if (saving.value) return; tab.value = 'Assessment'; station.value = r.station; playerId.value = r.player_id; loadDraft() }
function beforeUnload(e) { if (dirtyCount.value) { e.preventDefault(); e.returnValue = '' } }
watch([playerId, station], () => loadDraft())
watch(eventId, async () => { snapshot.value = null; playerId.value = ''; rankings.value = []; error.value = ''; while (polling.value) await new Promise(resolve => setTimeout(resolve, 50)); await refresh(); if (tab.value !== 'Assessment') await loadRankings() })
watch(tab, value => { if (['Results', 'Rankings', 'Export'].includes(value)) loadRankings() })
watch(showAdd, value => { if (value) searchCandidates() })
watch(drafts, () => { try { if (storageKey) sessionStorage.setItem(storageKey, JSON.stringify(Object.fromEntries(Object.entries(drafts).filter(([, d]) => d.dirty)))) } catch (_) { /* in-memory drafts remain available */ } }, { deep: true })
let timer
onMounted(async () => {
  window.addEventListener('beforeunload', beforeUnload)
  try { await loadEvents() } catch (e) { error.value = errorMessage(e) }
  timer = setInterval(() => { if (!document.hidden && !saving.value) { refresh(true); if (['Results', 'Rankings'].includes(tab.value)) loadRankings() } }, 10000)
})
onUnmounted(() => { clearInterval(timer); window.removeEventListener('beforeunload', beforeUnload) })
</script>

<template>
  <Layout>
    <main class="fa-shell">
      <header class="fa-header no-print">
        <div><h1>The Yard Free Assessment</h1><p>Test Today. Build Tomorrow.</p></div>
        <div class="header-actions">
          <label class="sr-only" for="assessment-select">Assessment</label>
          <select id="assessment-select" v-model="eventId" :disabled="saving"><option value="" disabled>Select assessment</option><option v-for="e in events" :key="e.id" :value="e.id">{{ e.name }} · {{ e.assessment_date.slice(0,10) }} · {{ e.status }}</option></select>
          <button :disabled="saving" @click="showCreate = true">New Assessment</button>
          <button class="primary" :disabled="saving || !event || event.status === 'completed'" @click="showAdd = true">＋ Add Player</button>
        </div>
      </header>
      <p v-if="error" role="alert" class="alert error no-print">{{ error }}</p>
      <p v-if="notice" role="status" class="alert success no-print">{{ notice }}</p>
      <p v-if="loading" class="no-print" role="status">Loading assessment…</p>
      <section v-if="!event && !loading" class="panel empty no-print"><h2>Start your assessment</h2><p>Create an event for your team, then add players and record their station results.</p><button class="primary" @click="showCreate = true">Create Assessment</button></section>
      <template v-if="event">
        <div class="event-meta no-print"><span>{{ players.length }} players · {{ completePlayers }} complete · {{ players.length - completePlayers }} in progress</span><span v-if="dirtyCount">{{ dirtyCount }} unsaved draft(s)</span><button :disabled="saving || !!dirtyCount" @click="changeStatus">{{ event.status === 'completed' ? 'Reopen assessment' : 'Complete assessment' }}</button></div>
        <nav class="top-tabs no-print" aria-label="Assessment sections"><button v-for="name in ['Assessment','Players','Results','Rankings','Export']" :key="name" :class="{ active: tab === name }" @click="tab = name">{{ name }}</button></nav>
        <template v-if="tab === 'Assessment'">
          <nav class="station-tabs no-print" aria-label="Testing stations"><button v-for="(s, key) in stations" :key="key" :class="{ active: station === key }" :aria-pressed="station === key" :disabled="saving" @click="selectStation(key)"><span class="station-symbol" aria-hidden="true">{{ s.unit === 'reps' ? '↟' : s.unit === 'sec' ? '◷' : s.unit === 'mph' ? '↗' : '↔' }}</span>{{ s.name }}</button></nav>
          <div class="mobile-tools no-print"><button @click="showRoster = !showRoster">Players ({{ players.length }})</button><button @click="showProgress = !showProgress">Station progress</button></div>
          <div class="assessment-grid no-print">
            <aside class="panel roster" :class="{ expanded: showRoster }">
              <div class="panel-pad"><h2>Players ({{ players.length }})</h2><input v-model="search" aria-label="Search players" placeholder="Search players…"><select v-model="completionFilter" aria-label="Station completion filter"><option value="all">All players</option><option value="incomplete">Incomplete</option><option value="complete">Completed</option></select></div>
              <div class="roster-list"><button v-for="p in filteredPlayers" :key="p.id" :class="['player-row', { selected: playerId === p.id }]" :disabled="saving" @click="selectPlayer(p.id)"><span class="avatar">{{ initials(p) }}</span><span><strong>{{ p.name }}</strong><small>Age {{ p.age ?? '—' }} · {{ p.grad_year || '—' }}<br>{{ p.height || '—' }} · {{ p.weight ?? '—' }} lbs</small></span><span class="completion" :class="{ done: completed(p.id) }" :aria-label="completed(p.id) ? 'Completed' : 'Incomplete'">{{ completed(p.id) ? '✓' : '○' }}</span></button><p v-if="!filteredPlayers.length" class="empty">No players found.</p></div>
            </aside>
            <div class="center-column">
              <section class="panel scoring">
                <header class="scoring-heading"><div><h2>{{ definition?.name }}</h2><p>{{ definition?.count }} {{ definition?.count === 1 ? 'result' : 'attempts' }} · {{ definition?.unit }}</p></div><div><button :disabled="!selectedPlayer || saving" @click="navigate(-1)">‹ Previous</button><button :disabled="!selectedPlayer || saving" @click="navigate(1)">Next ›</button></div></header>
                <template v-if="selectedPlayer && draft">
                  <div class="player-header"><span class="avatar large">{{ initials(selectedPlayer) }}</span><div><h2>{{ selectedPlayer.name }} <small class="badge">{{ completed(playerId) ? 'Recorded' : 'In progress' }}</small></h2><p>Age {{ selectedPlayer.age ?? '—' }} · Grad {{ selectedPlayer.grad_year || '—' }} · {{ selectedPlayer.height || 'Height —' }} · {{ selectedPlayer.weight ?? '—' }} lbs</p><p>Position: {{ selectedPlayer.position || '—' }} · Bats/Throws: {{ selectedPlayer.bats || '—' }}/{{ selectedPlayer.throws || '—' }}</p></div></div>
                  <p v-if="conflict" class="alert error">Another coach has updated this result. Your draft is preserved. <button @click="reloadSaved">Load saved result</button></p>
                  <form @submit.prevent="saveNext">
                    <fieldset :disabled="saving || event.status === 'completed'">
                      <div v-if="station === 'pitching_velocity'" class="protocol"><label>Pitch protocol<select v-model="draft.protocol" required @change="changed"><option value="">Select protocol</option><option value="fastball">Fastballs only</option><option value="mixed">Mixed / other pitches</option></select></label></div>
                      <label v-if="station === 'pull_strength'">Pull strength protocol<input v-model="draft.protocol" required maxlength="120" placeholder="Device and test position" @input="changed"></label>
                      <div class="attempt-grid" :class="{ single: definition.count === 1 }"><label v-for="(_, i) in draft.values" :key="i">{{ station === 'grip_strength' ? `${i < 3 ? 'Left' : 'Right'} ${i % 3 + 1}` : definition.count === 1 ? `Total ${definition.name} (${definition.unit})` : `Attempt ${i + 1} (${definition.unit})` }}<input v-model="draft.values[i]" type="number" :inputmode="definition.unit === 'reps' ? 'numeric' : 'decimal'" :step="definition.unit === 'reps' ? 1 : 0.001" :min="definition.min" :max="definition.max" required @input="changed"></label></div>
                      <label>Notes (optional)<textarea v-model="draft.notes" maxlength="2000" placeholder="Form, modifications, or test notes…" @input="changed"></textarea></label>
                      <div class="save-actions"><button type="button" @click="reloadSaved">Reload saved</button><button class="primary" :disabled="saving || conflict" type="submit">{{ saving ? 'Saving…' : 'Save & Next Player →' }}</button></div>
                    </fieldset>
                  </form>
                  <p v-if="event.status === 'completed'" class="alert">This assessment is completed. Reopen it to edit results.</p>
                </template>
                <div v-else class="empty"><p>Add or select a player to begin scoring.</p><button class="primary" :disabled="event.status === 'completed'" @click="showAdd = true">＋ Add Player</button></div>
              </section>
              <section class="panel recent"><h2>Recent Entries</h2><div class="table-scroll"><table><thead><tr><th>Player</th><th>Station</th><th>Result</th><th>Time</th><th>Coach</th></tr></thead><tbody><tr v-for="r in results.slice(0,15)" :key="r.id"><td><button @click="openEntry(r)">{{ playerName(r.player_id) }}</button></td><td>{{ stations[r.station].name }}</td><td>{{ summary(r) }}</td><td>{{ new Date(r.updated_at).toLocaleString() }}</td><td>{{ r.coach }}</td></tr><tr v-if="!results.length"><td colspan="5">No entries yet.</td></tr></tbody></table></div></section>
            </div>
            <aside class="progress-column" :class="{ expanded: showProgress }"><section class="panel panel-pad"><h2>Station Progress</h2><p>{{ progress(station) }} of {{ players.length }} complete ({{ percentComplete(progress(station), players.length) }}%)</p><progress :value="progress(station)" :max="players.length || 1"></progress><button v-for="(s, key) in stations" :key="key" class="progress-row" :class="{ selected: station === key }" :disabled="saving" @click="selectStation(key)"><span>{{ s.name }}</span><span>{{ progress(key) }}/{{ players.length }}</span><small>{{ percentComplete(progress(key), players.length) }}%</small></button></section><section class="panel quick-add"><h2>Quick Add Player</h2><p>Register a player without leaving your station.</p><button :disabled="event.status === 'completed'" @click="showAdd = true">＋ Add New Player</button></section></aside>
          </div>
        </template>
        <section v-if="tab === 'Players'" class="panel panel-pad no-print"><h2>Assessment Players</h2><input v-model="search" placeholder="Search players…" aria-label="Search players"><div class="table-scroll"><table><thead><tr><th>Player</th><th>Age / Grad</th><th>Height / Weight</th><th>Completed</th><th>Missing stations</th><th>Claim status</th></tr></thead><tbody><tr v-for="p in players.filter(p => p.name.toLowerCase().includes(search.toLowerCase()))" :key="p.id"><td><RouterLink :to="`/roster/player/${p.id}`">{{ p.name }}</RouterLink></td><td>{{ p.age }} / {{ p.grad_year }}</td><td>{{ p.height }} / {{ p.weight ?? '—' }} lbs</td><td>{{ completionCount(p.id) }}/{{ Object.keys(stations).length }}</td><td><button v-for="(s, key) in stations" v-show="!completed(p.id, key)" :key="key" class="missing-station" @click="openEntry({ player_id: p.id, station: key })">{{ s.name }}</button></td><td>{{ p.claimed ? 'Claimed' : 'Unclaimed' }} <button v-if="!p.claimed" @click="issueClaim(p)">Issue claim code</button></td></tr></tbody></table></div></section>
        <section v-if="['Results','Rankings'].includes(tab)" class="panel panel-pad no-print"><h2>{{ tab }}</h2><div class="filters"><input v-model="resultSearch" placeholder="Player name" aria-label="Filter by player"><select v-model="rankStation" aria-label="Filter by station"><option value="">All stations</option><option v-for="(s,key) in stations" :key="key" :value="key">{{ s.name }}</option></select><input v-model="ageFilter" type="number" placeholder="Age" aria-label="Filter by age"><input v-model="gradFilter" type="number" placeholder="Graduation year" aria-label="Filter by graduation year"></div><p>Ranks compare recorded results within each station; pitching and pull strength also match the recorded protocol. Grip rankings use the left hand. Percentiles appear only when a compatible benchmark is available.</p><div class="table-scroll"><table><thead><tr><th>Player</th><th>Station</th><th>Result</th><th>Average</th><th>Assessment rank</th><th>Age/Peer %</th><th>Yard/FMTRX %</th><th>Confidence</th></tr></thead><tbody><tr v-for="r in filteredRankings" :key="r.id"><td><button @click="openEntry(r)">{{ r.player.name }}</button></td><td>{{ stations[r.station].name }}</td><td>{{ summary(r) }}</td><td>{{ r.station === 'grip_strength' ? `L ${r.summary.left.average} / R ${r.summary.right.average}` : r.summary.average }}</td><td>#{{ r.rank }} of {{ r.participants }}</td><td>{{ r.peer_percentile ?? '—' }} <small>{{ r.peer_percentile !== null ? r.peer_confidence : '' }}</small></td><td>{{ r.population_percentile ?? '—' }}</td><td>{{ r.confidence }}<small v-if="r.benchmark_note">{{ r.benchmark_note }}</small></td></tr><tr v-if="!filteredRankings.length"><td colspan="8">No matching results.</td></tr></tbody></table></div></section>
        <section v-if="tab === 'Export'" class="panel panel-pad no-print"><h2>Export Assessment</h2><p>Export all registered players and their recorded results, averages, ranks, and available percentiles. Missing tests remain blank.</p><label v-if="snapshot.can_export_phone"><input v-model="includePhone" type="checkbox"> Include phone numbers</label><div class="export-actions"><button class="primary" @click="exportCsv">Download CSV</button><button @click="printReport">Print-friendly report</button></div></section>
        <section class="print-report"><h1>{{ event.name }}</h1><p>{{ event.location }} · {{ event.assessment_date.slice(0,10) }}</p><article v-for="p in players" :key="p.id"><h2>{{ p.name }}</h2><p>Age {{ p.age }} · Grad {{ p.grad_year }} · {{ p.height }} · {{ p.weight ?? '—' }} lbs · {{ p.position }} · {{ p.bats }}/{{ p.throws }}</p><table><thead><tr><th>Station</th><th>Result</th><th>Average</th><th>Rank</th><th>Peer %</th><th>Population %</th></tr></thead><tbody><tr v-for="(s,key) in stations" :key="key"><td>{{ s.name }}</td><template v-for="r in [rankings.find(r => r.player_id === p.id && r.station === key)]" :key="key"><td>{{ summary(r) }}</td><td>{{ r?.summary.average ?? (r?.summary.left ? `L ${r.summary.left.average} / R ${r.summary.right.average}` : '—') }}</td><td>{{ r?.rank ?? '—' }}</td><td>{{ r?.peer_percentile ?? '—' }}</td><td>{{ r?.population_percentile ?? '—' }}</td></template></tr></tbody></table></article></section>
      </template>
    </main>
    <Dialog :open="showCreate" class="fa-dialog" @close="showCreate = false"><div class="dialog-backdrop"/><div class="dialog-wrap"><DialogPanel class="dialog-panel"><DialogTitle>Create Assessment</DialogTitle><form @submit.prevent="createEvent"><label>Team<select v-model="newEvent.team_id" required><option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option></select></label><label>Name<input v-model="newEvent.name" required maxlength="150"></label><label>Location<input v-model="newEvent.location" required maxlength="150"></label><label>Date<input v-model="newEvent.assessment_date" type="date" required></label><p v-if="error" role="alert">{{ error }}</p><div class="save-actions"><button type="button" @click="showCreate = false">Cancel</button><button class="primary" :disabled="adding">Create</button></div></form></DialogPanel></div></Dialog>
    <Dialog :open="showAdd" class="fa-dialog" @close="showAdd = false"><div class="dialog-backdrop"/><div class="dialog-wrap"><DialogPanel class="dialog-panel"><DialogTitle>Add Player</DialogTitle><p>Select an existing team player first, or create a new FMTRX profile.</p><div class="filters"><input v-model="candidateSearch" placeholder="Search team roster" aria-label="Search team roster" @keyup.enter="searchCandidates"><button @click="searchCandidates">Search</button></div><div class="candidates"><button v-for="p in candidates" :key="p.id" :disabled="adding || players.some(x => x.id === p.id)" @click="addPlayer(p.id)">{{ p.name }} · Age {{ p.age ?? '—' }} {{ players.some(x => x.id === p.id) ? ' · Added' : ' · Add' }}</button></div><form @submit.prevent="addPlayer()"><h3>New Player</h3><div class="form-grid"><label>First name<input v-model="newPlayer.first_name" required></label><label>Last name<input v-model="newPlayer.last_name" required></label><label>Phone<input v-model="newPlayer.phone" type="tel" required></label><label>Date of birth<input v-model="newPlayer.born_date" type="date" required></label><label>Graduation year<input v-model="newPlayer.grad_year" type="number" required></label><label>Weight (lbs)<input v-model="newPlayer.weight" type="number" required></label><label>Height (feet)<input v-model="newPlayer.height_in_ft" type="number" min="2" max="8" required></label><label>Height (inches)<input v-model="newPlayer.height_in_inch" type="number" min="0" max="11" required></label><label>Position<select v-model="newPlayer.position"><option value="">Not specified</option><option v-for="p in ['P','C','1B','2B','3B','SS','LF','CF','RF','OF','IF','DH']" :key="p">{{ p }}</option></select></label><label>Bats<select v-model="newPlayer.hit_side"><option value="">Not specified</option><option>R</option><option>L</option><option>S</option></select></label><label>Throws<select v-model="newPlayer.throw_side"><option value="">Not specified</option><option>R</option><option>L</option></select></label></div><p v-if="error" class="error" role="alert">{{ error }}</p><div class="save-actions"><button type="button" @click="showAdd = false">Cancel</button><button class="primary" :disabled="adding">{{ adding ? 'Adding…' : 'Create & Add Player' }}</button></div></form></DialogPanel></div></Dialog>
    <Dialog :open="!!claimCode" class="fa-dialog" @close="claimCode = ''"><div class="dialog-backdrop"/><div class="dialog-wrap"><DialogPanel class="dialog-panel"><DialogTitle>Claim code for {{ claimName }}</DialogTitle><p>This replaces any unused account claim code. Share it only with this player or their parent.</p><strong class="claim-code">{{ claimCode }}</strong><button @click="claimCode = ''">Close</button></DialogPanel></div></Dialog>
  </Layout>
</template>

<style scoped>
.fa-shell{background:#f6f8fc;color:#101d30;min-height:100vh;padding:24px;font-family:inherit;border-radius:12px;max-width:1800px;margin:0 auto}.fa-shell h1{font-size:30px;font-weight:800;margin:0}.fa-shell h2{font-size:19px;font-weight:750;margin:0 0 8px}.fa-shell p{color:#526581;margin:6px 0 14px;font-size:14px}.fa-header,.header-actions,.event-meta,.scoring-heading,.save-actions{display:flex;align-items:center;gap:12px;justify-content:space-between}.fa-header{flex-wrap:wrap}.header-actions{flex-wrap:wrap}.fa-shell button,.dialog-panel button{border:1px solid #d4deeb;background:#fff;border-radius:6px;padding:10px 14px;min-height:44px;font-weight:600;cursor:pointer;color:#1d3b61}.fa-shell button:hover,.dialog-panel button:hover{border-color:#0866ff;background:#f0f6ff}.fa-shell button:disabled,.dialog-panel button:disabled{opacity:.5;cursor:not-allowed}.fa-shell .primary,.dialog-panel .primary{background:#0866ff;color:white;border-color:#0866ff}.fa-shell input,.fa-shell select,.fa-shell textarea,.dialog-panel input,.dialog-panel select{border:1px solid #cbd7e7;border-radius:5px;min-height:44px;padding:9px 11px;background:white;color:#17283e;width:100%;box-sizing:border-box}.fa-shell input:focus,.fa-shell textarea:focus,.fa-shell select:focus,.dialog-panel input:focus{outline:2px solid #0866ff;outline-offset:1px}.fa-shell input[type=checkbox]{width:auto;min-height:0}.header-actions select{width:280px}.event-meta{font-size:13px;color:#536a88;margin:12px 0}.top-tabs{display:flex;gap:24px;border-bottom:1px solid #d5deea;margin:14px 0}.top-tabs button{border:0;border-radius:0;background:transparent}.top-tabs button.active{color:#0060ff;border-bottom:3px solid #0866ff}.station-tabs{display:grid;grid-template-columns:repeat(9,minmax(90px,1fr));gap:8px;margin-bottom:16px;overflow-x:auto;padding:2px}.station-tabs button{padding:8px 4px;font-size:12px}.station-symbol{display:block;font-size:26px;line-height:30px}.station-tabs button.active{background:#0866ff;color:white;border-color:#0866ff}.assessment-grid{display:grid;grid-template-columns:minmax(210px, .95fr) minmax(350px,2.2fr) minmax(215px,.95fr);gap:12px;align-items:start}.panel{border:1px solid #dce4ef;background:white;border-radius:7px;overflow:hidden}.panel-pad{padding:16px}.panel-pad input,.panel-pad select{margin:5px 0}.roster-list{max-height:710px;overflow-y:auto}.fa-shell .player-row{display:flex;align-items:center;gap:10px;text-align:left;border:0;border-top:1px solid #e8edf5;border-radius:0;width:100%;padding:10px;font-size:13px}.player-row strong{display:block}.player-row small{font-size:11px;font-weight:400;color:#526581}.fa-shell .selected{background:#e5f1ff;color:#035df5}.avatar{width:38px;height:38px;flex-shrink:0;border-radius:50%;background:#d8e2ee;color:#385879;display:grid;place-items:center;font-weight:650}.avatar.large{width:68px;height:68px;background:#6669bb;color:white;font-size:24px}.completion{margin-left:auto;font-size:23px;color:#8d9cad}.completion.done{color:#27b54d}.center-column{display:grid;gap:12px}.scoring-heading{padding:18px;border-bottom:1px solid #e7edf5}.scoring-heading>div:last-child{display:flex;gap:8px}.scoring-heading button{font-size:12px;padding:8px}.player-header{display:flex;gap:16px;align-items:center;padding:24px 20px}.player-header h2{font-size:23px}.player-header p{font-size:12px;margin:5px 0}.badge{display:inline-block;background:#e9f4ff;color:#0873ee;border:1px solid #bcd9fa;border-radius:14px;padding:3px 8px;font-size:10px;vertical-align:middle}.scoring form{padding:0 20px 18px}.fa-shell label,.dialog-panel label{display:block;font-size:13px;font-weight:600;margin-bottom:12px}.fa-shell textarea{display:block;min-height:76px;margin-top:6px}.attempt-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:14px 0}.attempt-grid input{display:block;font-size:23px;font-weight:600;margin-top:5px;min-height:55px}.attempt-grid.single{grid-template-columns:1fr;max-width:300px}.attempt-grid.single input{font-size:32px;min-height:64px}.save-actions{margin-top:16px}.save-actions .primary{flex:1}.scoring .save-actions{position:sticky;bottom:0;background:white;padding:10px 0;z-index:2}.recent{padding:14px}.table-scroll{overflow:auto}table{border-collapse:collapse;width:100%;font-size:12px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #e4eaf2;vertical-align:top}th{background:#f0f4f9;white-space:nowrap}td small{display:block}.recent td button{padding:0;min-height:30px;border:0;color:#0764de;text-align:left;font-size:12px}.progress-column{display:grid;gap:14px}progress{width:100%;height:10px;accent-color:#2dc457;margin:0 0 14px}.fa-shell .progress-row{width:100%;display:grid;grid-template-columns:1fr auto auto;gap:8px;border:0;border-bottom:1px solid #edf1f7;border-radius:3px;text-align:left;padding:8px;font-size:12px;font-weight:400}.quick-add{padding:16px;background:#edf6ff;border-color:#cce4ff}.quick-add h2{color:#0866ff}.quick-add button{width:100%}.empty{padding:35px;text-align:center}.alert{padding:12px;border-radius:6px;background:#edf4ff;margin:12px 0}.error{background:#fff0f0!important;color:#a82323!important}.success{background:#eaf8ee;color:#22703a}.filters{display:flex;gap:10px;flex-wrap:wrap;margin:15px 0}.filters input,.filters select{flex:1;min-width:120px}.missing-station{font-size:11px;padding:4px 7px!important;min-height:30px!important;margin:3px}.export-actions{display:flex;gap:12px;margin-top:18px}.mobile-tools,.print-report{display:none}.fa-dialog{position:relative;z-index:10000}.dialog-backdrop{position:fixed;inset:0;background:rgba(9,22,42,.65)}.dialog-wrap{position:fixed;inset:0;overflow-y:auto;display:flex;align-items:flex-start;justify-content:center;padding:40px 16px}.dialog-panel{background:white;border-radius:12px;max-width:650px;width:100%;padding:26px;color:#17283e}.dialog-panel h2{font-size:24px;font-weight:750;margin-bottom:14px}.dialog-panel h3{font-weight:700;margin:18px 0}.dialog-panel p{font-size:14px;margin:10px 0}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}.candidates{display:flex;flex-direction:column;max-height:180px;overflow:auto;gap:5px}.claim-code{display:block;font-size:28px;letter-spacing:.15em;margin:22px 0}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
@media(max-width:1200px){.assessment-grid{grid-template-columns:230px minmax(340px,1fr)}.progress-column{display:none}.progress-column.expanded{display:block;grid-column:1/-1}.mobile-tools{display:flex;gap:10px;margin-bottom:10px}.station-tabs{grid-template-columns:repeat(9,105px)}.header-actions{width:100%}.fa-shell{padding:16px}}
@media(max-width:850px){.assessment-grid{display:flex;flex-direction:column}.center-column,.progress-column,.roster{width:100%}.roster{display:none}.roster.expanded{display:block}.roster-list{max-height:280px}.fa-shell{padding:12px;border-radius:0}.fa-shell h1{font-size:24px}.top-tabs{gap:0;overflow-x:auto;justify-content:space-between}.top-tabs button{padding:10px}.scoring-heading{padding:12px}.player-header{padding:18px 12px}.scoring form{padding:0 12px 12px}.header-actions select{width:100%}.event-meta{flex-wrap:wrap}.player-header h2{font-size:20px}}
@media print{.no-print{display:none!important}.print-report{display:block!important;color:#000}.fa-shell{padding:0;background:white}.print-report article{break-inside:avoid;margin:20px 0}.print-report h1{font-size:24px}.print-report table{font-size:10px}}
</style>
<style>
@media print{body *{visibility:hidden} .print-report,.print-report *{visibility:visible} .print-report{position:absolute;left:0;top:0;width:100%;background:white} @page{size:landscape;margin:12mm}}
</style>
