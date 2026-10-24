<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue'
import Layout from '@/layout/Layout.vue'
import { useTeamStore } from '@/store/team'
import { usePlayerStore } from '@/store/players'
import { useTrainingStore } from '@/store/training'
import { useAxiosAuth } from '@/composables/axios-auth'

const route = useRoute(), router = useRouter()
const teamStore = useTeamStore(), playerStore = usePlayerStore(), trainingStore = useTrainingStore()
const { axiosGet, axiosPost } = useAxiosAuth()
const slug = route.params.slug
const isBullpen = slug === 'bullpen'
const title = isBullpen ? 'Bullpen' : 'Batting'
const participants = isBullpen ? 'Pitchers' : 'Hitters'
const creationUncertain = ref(false)
const teamId = ref(teamStore.team?.id || '')
const teams = computed(() => [...new Map([teamStore.team, ...teamStore.teams].filter(t => t?.id).map(t => [t.id, t])).values()])
const roster = ref([]), selectedIds = ref([]), draftIds = ref([]), search = ref('')
const pickerOpen = ref(false), notesOpen = ref(false), note = ref('')
const loading = ref(false), saving = ref(false), error = ref('')
const selected = computed(() => selectedIds.value.map(id => roster.value.find(p => p.id === id)).filter(Boolean))
const playerName = player => player.name?.full || [player.name?.first, player.name?.last].filter(Boolean).join(' ') || 'Player'
const visiblePlayers = computed(() => roster.value.filter(p => `${playerName(p)} ${p.shirt_number || ''}`.toLowerCase().includes(search.value.trim().toLowerCase())))
const canCreate = computed(() => Boolean(['bullpen', 'batting'].includes(slug) && teamId.value && selected.value.length && !loading.value && !saving.value && !creationUncertain.value))
let rosterRequest = 0
async function loadRoster() {
  const request = ++rosterRequest
  selectedIds.value = []; draftIds.value = []; roster.value = []; error.value = ''; pickerOpen.value = false
  if (!teamId.value) { loading.value = false; return }
  loading.value = true
  try {
    const response = await axiosGet(`coach/teams/${teamId.value}`)
    if (request !== rosterRequest) return
    const players = response.data.data
    if (!Array.isArray(players)) throw new Error('Unexpected roster response')
    roster.value = [...new Map(players.filter(p => p?.id).map(p => [p.id, p])).values()]
  } catch {
    if (request === rosterRequest) error.value = 'Unable to load this team’s players. Please retry.'
  } finally { if (request === rosterRequest) loading.value = false }
}
watch(teamId, loadRoster, { immediate: true })
function openPicker() { draftIds.value = [...selectedIds.value]; search.value = ''; pickerOpen.value = true }
function togglePlayer(id) {
  draftIds.value = draftIds.value.includes(id) ? draftIds.value.filter(value => value !== id) : [...draftIds.value, id]
}
function applyPlayers() { selectedIds.value = [...draftIds.value]; pickerOpen.value = false }
function removePlayer(id) { selectedIds.value = selectedIds.value.filter(value => value !== id) }
function movePlayer(index, direction) {
  const next = index + direction
  if (next < 0 || next >= selectedIds.value.length) return
  const ids = [...selectedIds.value]; [ids[index], ids[next]] = [ids[next], ids[index]]; selectedIds.value = ids
}
const cancel = () => router.push(`/training/${slug}`)
async function createPractice() {
  if (!canCreate.value) return
  saving.value = true; error.value = ''
  try {
    const response = await axiosPost('training', {
      team: teamId.value, type: isBullpen ? 'P' : 'B',
      note: note.value.trim() || `${title} session`,
      players: selected.value.map((player, sort) => ({ id: player.id, sort })),
    })
    const session = response.data.data
    trainingStore.setDataTraining(session)
    trainingStore.setCountBallsTraining(0)
    trainingStore.cleanListPlayer()
    teamStore.setTeam(teams.value.find(t => t.id === teamId.value))
    playerStore.setPlayers(roster.value)
    await router.push({ path: `/track/${slug}`, query: { session: session.id } })
  } catch {
    creationUncertain.value = true
    error.value = 'Unable to confirm creation. Use Cancel to check your session list before creating another practice.'
  } finally { saving.value = false }
}
</script>

<template>
  <Layout>
    <main class="setup-shell" :aria-busy="saving">
      <header><p class="setup-eyebrow">New practice</p><h1>{{ title }}</h1></header>
      <div class="setup-context">
        <label>Team<select v-model="teamId" :disabled="saving || pickerOpen"><option value="" disabled>Select team</option><option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option></select></label>
      </div>
      <p v-if="error" role="alert" class="setup-error">{{ error }} <button v-if="!roster.length" :disabled="saving || loading" @click="loadRoster">Retry roster</button></p>
      <button class="setup-row" :disabled="!teamId || loading || saving" aria-haspopup="dialog" @click="openPicker">
        <span>{{ participants.toUpperCase() }} <small>{{ loading ? 'Loading roster…' : selected.length ? `${selected.length} selected` : `Choose ${participants.toLowerCase()}` }}</small></span>
        <svg aria-hidden="true" viewBox="0 0 32 32"><circle cx="12" cy="9" r="5" fill="currentColor"/><path d="M2 28v-5c0-6 20-6 20 0v5z" fill="currentColor"/><path d="M25 9v12m-6-6h12" stroke="currentColor" stroke-width="3"/></svg>
      </button>
      <ol v-if="selected.length" class="selected-players" aria-label="Selected players in session order">
        <li v-for="(player, index) in selected" :key="player.id">
          <span class="avatar" aria-hidden="true">{{ playerName(player).slice(0,1) }}</span>
          <span class="player-name"><strong>{{ playerName(player) }}</strong><small>#{{ player.shirt_number || '—' }} · Order {{ index + 1 }}</small></span>
          <button :disabled="saving || index === 0" :aria-label="`Move ${playerName(player)} earlier`" @click="movePlayer(index, -1)">↑</button>
          <button :disabled="saving || index === selected.length - 1" :aria-label="`Move ${playerName(player)} later`" @click="movePlayer(index, 1)">↓</button>
          <button :disabled="saving" :aria-label="`Remove ${playerName(player)}`" @click="removePlayer(player.id)">×</button>
        </li>
      </ol>
      <div class="practice-kind" aria-label="Practice mode">
        <button type="button" class="regular" aria-pressed="true">REGULAR</button>
        <button type="button" disabled aria-describedby="scripted-help">⚡ {{ isBullpen ? 'SCRIPTED' : 'SCRIPTED BP' }}</button>
      </div>
      <p id="scripted-help" class="scripted-help">Scripted practice is available in the mobile app. Web scoring currently supports regular practice.</p>
      <button class="setup-row" :disabled="saving" :aria-expanded="notesOpen" aria-controls="practice-notes" @click="notesOpen = !notesOpen">
        <span>NOTES <small>{{ note.trim() ? 'Notes added' : 'Optional' }}</small></span><span class="plus" aria-hidden="true">{{ notesOpen ? '−' : '+' }}</span>
      </button>
      <div v-if="notesOpen" id="practice-notes" class="notes-panel"><label for="session-notes">Session notes</label><textarea id="session-notes" v-model="note" :disabled="saving" placeholder="Focus, goals, or reminders for this session" rows="4" /></div>
      <footer class="setup-actions"><button :disabled="saving" class="cancel" @click="cancel">CANCEL</button><button class="create" :disabled="!canCreate" @click="createPractice">{{ saving ? 'CREATING…' : 'CREATE' }}</button></footer>
    </main>
    <Dialog :open="pickerOpen" @close="pickerOpen = false" class="player-picker">
      <div class="picker-backdrop" aria-hidden="true" />
      <div class="picker-position"><DialogPanel class="picker-panel">
        <header><DialogTitle as="h2">Select {{ participants.toLowerCase() }}</DialogTitle><p>Tap a player to select or deselect them.</p></header>
        <label for="player-search">Search players</label><input id="player-search" v-model="search" type="search" placeholder="Name or jersey number" />
        <div class="picker-tools"><span>{{ draftIds.length }} selected</span><button @click="draftIds = roster.map(p => p.id)">Select all</button><button @click="draftIds = []">Clear</button></div>
        <div class="picker-list">
          <p v-if="!visiblePlayers.length">{{ roster.length ? 'No matching players.' : 'No players on this team yet.' }}</p>
          <label v-for="player in visiblePlayers" :key="player.id" class="picker-player" :class="{ chosen: draftIds.includes(player.id) }">
            <input type="checkbox" :checked="draftIds.includes(player.id)" @change="togglePlayer(player.id)" />
            <span class="avatar" aria-hidden="true">{{ playerName(player).slice(0,1) }}</span><span class="player-name"><strong>{{ playerName(player) }}</strong><small>Jersey #{{ player.shirt_number || '—' }}</small></span>
          </label>
        </div>
        <footer class="setup-actions"><button class="cancel" @click="pickerOpen = false">Cancel</button><button class="create" @click="applyPlayers">Done · {{ draftIds.length }} {{ participants.toLowerCase() }}</button></footer>
      </DialogPanel></div>
    </Dialog>
  </Layout>
</template>

<style scoped>
.setup-shell{max-width:780px;margin:32px auto;padding:28px;color:var(--app-text,#f8fafc)}header{margin-bottom:24px}h1{font-size:30px;font-weight:800}header p{color:var(--app-text-soft,#b8c2d4);margin-top:8px}.setup-eyebrow{text-transform:uppercase;font-size:12px;letter-spacing:2px}.setup-context{display:flex;gap:16px;margin-bottom:24px}.setup-context label{flex:1}label{font-size:14px;font-weight:600}select,textarea,input[type=search]{display:block;width:100%;padding:12px;margin-top:8px;background:var(--app-surface,#111827);color:var(--app-text,#fff);border:1px solid var(--app-border,#68738a);border-radius:10px}button{min-height:44px;border-radius:12px;padding:10px 16px;font-weight:650}button:disabled{opacity:.45;cursor:not-allowed}button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid #35aaff;outline-offset:3px}.setup-row{width:100%;min-height:88px;background:var(--app-surface-soft,#252938);color:var(--app-text,#fff);display:flex;align-items:center;justify-content:space-between;text-align:left;border:1px solid var(--app-border,#49516a);margin:12px 0;padding:20px 24px;font-size:23px;font-weight:500;border-radius:20px}.setup-row small{display:block;font-size:13px;color:var(--app-text-soft,#bac6d8);margin-top:4px}.setup-row svg{width:34px;height:34px}.plus{font-size:32px;font-weight:800}.setup-actions{display:flex;gap:20px;margin-top:20px}.setup-actions button{flex:1;font-size:18px;padding:16px}.cancel{background:var(--app-surface-soft,#343447);color:var(--app-text,#fff);border:1px solid var(--app-border,#68738a)}.create{background:#d61132;color:#fff}.create:disabled{background:#dce1e5;color:#56616e;opacity:1}.selected-players{list-style:none;padding:0;margin:18px 0}.selected-players li{display:flex;align-items:center;gap:8px;padding:10px 4px;border-bottom:1px solid var(--app-border,#49516a)}.selected-players button{padding:8px 12px;background:var(--app-surface-soft,#252938)}.avatar{display:grid;place-items:center;width:38px;height:38px;flex-shrink:0;border-radius:50%;background:#203f66;color:#fff;font-weight:700}.player-name{flex:1;min-width:0;overflow-wrap:anywhere}.player-name small{display:block;font-size:12px;color:var(--app-text-soft,#bac6d8);margin-top:4px}.notes-panel{padding:16px 4px}.setup-error{background:#fff1f2;color:#92152e;padding:12px;border-radius:10px}.player-picker{position:relative;z-index:100}.picker-backdrop{position:fixed;inset:0;background:#000a}.picker-position{position:fixed;inset:0;overflow-y:auto;display:flex;align-items:center;justify-content:center;padding:20px}.picker-panel{width:100%;max-width:620px;max-height:90dvh;display:flex;flex-direction:column;border-radius:20px;background:var(--app-surface,#111827);color:var(--app-text,#fff);padding:24px;border:1px solid var(--app-border,#68738a)}h2{font-size:24px;font-weight:750}.picker-tools{display:flex;align-items:center;gap:8px;margin:12px 0;font-size:14px}.picker-tools span{margin-right:auto}.picker-list{overflow-y:auto;min-height:80px}.picker-player{display:flex;align-items:center;gap:12px;padding:14px;border:1px solid var(--app-border,#68738a);border-radius:12px;margin-bottom:8px;cursor:pointer}.picker-player.chosen{border-color:#ef4765;box-shadow:inset 3px 0 #ef4765}.picker-player input{width:20px;height:20px;accent-color:#d61132;flex-shrink:0}@media(max-width:600px){.setup-shell{padding:16px;margin:12px auto}.setup-row{padding:18px;font-size:21px}.setup-actions{gap:12px}.picker-position{padding:10px}.picker-panel{padding:16px}.setup-context{flex-direction:column}.player-name{font-size:14px}.selected-players button{padding:8px}}
.setup-shell{position:relative;isolation:isolate;max-width:1100px;min-height:660px;background:#0b0f1f;color:#fff;border-radius:24px;padding:32px}.setup-shell::before{content:'';position:absolute;z-index:-1;left:0;right:0;top:180px;height:290px;background:#6d113640;clip-path:polygon(0 0,100% 90%,100% 100%,0 55%);pointer-events:none}.setup-shell .setup-row{background:#272a3a;border-color:transparent;color:#fff;border-radius:24px;font-size:27px}.setup-shell .setup-row small{color:#c3c7d4}.setup-shell .setup-row svg{width:42px;height:42px}.practice-kind{display:grid;grid-template-columns:1fr 1fr;margin:18px 0 0;border:2px solid #ff2451;border-radius:22px;overflow:hidden}.practice-kind button{border-radius:0;color:white;font-size:22px;font-weight:800;min-height:68px}.practice-kind .regular{background:#ff2451}.practice-kind button:disabled{opacity:.7}.scripted-help{font-size:12px;color:#bfc5d4;margin:8px 4px 14px}.setup-shell .setup-actions{flex-direction:column;align-items:center;gap:18px}.setup-shell .setup-actions button{width:min(100%,520px);font-size:25px;min-height:64px;border-radius:22px}.setup-shell .cancel{background:#373848;border-color:transparent;color:white}.setup-shell .create{background:#ff2451}.setup-shell .create:disabled{background:#e3e6e8;color:#969fa7}.setup-shell .setup-context{max-width:360px;margin-bottom:20px}.setup-shell select,.setup-shell textarea{background:#232839;color:white}.setup-shell .selected-players button{color:white;background:#34384b}.setup-shell .player-name small{color:#c3c7d4}@media(max-width:600px){.setup-shell{padding:18px;min-height:600px}.practice-kind button{font-size:17px}.setup-shell .setup-row{font-size:24px}.setup-shell .setup-actions button{font-size:23px}}
</style>
