<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import ImportStepper from '@/components/data-hub/ImportStepper.vue'
import PlatformSelector from '@/components/data-hub/PlatformSelector.vue'
import FileDropzone from '@/components/data-hub/FileDropzone.vue'
import DestinationSelector from '@/components/data-hub/DestinationSelector.vue'
import PlayerMapping from '@/components/data-hub/PlayerMapping.vue'
import InspectionReview from '@/components/data-hub/InspectionReview.vue'
import { DATA_HUB_PLATFORMS, DATA_HUB_SESSION_TYPES } from '@/data/dataHubPlatforms.js'
import { DATA_HUB_MAX_FILE_SIZE_BYTES, platformSupportsFile } from '@/data/dataHubConfig.js'
import { validateDataHubFile } from '@/utils/dataHubWorkflow.js'
import { useTeamStore } from '@/store/team.js'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const router = useRouter()
const teamStore = useTeamStore()
const { teams, team: activeTeam } = storeToRefs(teamStore)
const { axiosPost, axiosGet } = useAxiosAuth()
const step = ref(1)
const platformKey = ref('')
const selectedFile = ref(null)
const fileError = ref('')
const fileWarning = ref('')
const teamId = ref('')
const sessionType = ref('')
const loadingTeams = ref(false)
const inspecting = ref(false)
const inspectionError = ref('')
const inspection = ref(null)
const teamPlayers = ref([])
const mappings = reactive({})
const duplicateOverride = ref(false)
const inspectionComplete = ref(false)
const sessionValues = {
  Cage: 'cage', 'Live AB': 'live_ab', Bullpen: 'bullpen', Strength: 'strength',
  Mobility: 'mobility', Assessment: 'assessment', 'Batting Practice': 'batting_practice',
  'Pitching Practice': 'pitching_practice',
}
const selectedPlatform = computed(() => DATA_HUB_PLATFORMS.find(item => item.key === platformKey.value) || null)
const selectedTeam = computed(() => teams.value.find(item => String(item?.id_team ?? item?.id ?? '') === teamId.value) || null)
const allowedSessionTypes = computed(() => selectedPlatform.value
  ? DATA_HUB_SESSION_TYPES.filter(type => selectedPlatform.value.sessionTypes.includes(type))
  : DATA_HUB_SESSION_TYPES)
const mappingValues = computed(() => Object.values(mappings).filter(Boolean))
const unresolved = computed(() => inspection.value?.players?.some(player => !mappings[player.external_name]) ?? true)
const hasDuplicates = computed(() => {
  const ids = mappingValues.value.filter(value => value !== '__skip__')
  return new Set(ids).size !== ids.length
})
const canContinue = computed(() => {
  if (step.value === 1) return platformKey.value === 'trackman'
  if (step.value === 2) return Boolean(selectedFile.value) && !fileError.value
  if (step.value === 3) return Boolean(selectedTeam.value && sessionType.value) && !inspecting.value
  if (step.value === 4) return !unresolved.value && (!hasDuplicates.value || duplicateOverride.value)
  return true
})
const reviewInspection = computed(() => {
  if (!inspection.value) return null
  return {
    ...inspection.value,
    sample_rows: inspection.value.sample_rows.map(row => ({
      ...row,
      player_id: mappings[row.player_external_name] === '__skip__' ? null : mappings[row.player_external_name] || null,
    })),
  }
})

const setPlatform = nextKey => {
  const nextPlatform = DATA_HUB_PLATFORMS.find(item => item.key === nextKey)
  if (selectedFile.value && !platformSupportsFile(nextPlatform, selectedFile.value)) selectedFile.value = null
  platformKey.value = nextKey
  sessionType.value = ''
  inspectionError.value = nextKey === 'trackman' ? '' : 'TrackMan is the only inspection platform available in Phase 2A.'
}
const setFile = file => {
  const result = validateDataHubFile(file, selectedPlatform.value)
  selectedFile.value = file
  fileError.value = result.error
  fileWarning.value = result.warning
}
const updateMapping = (name, value) => {
  mappings[name] = value
  duplicateOverride.value = false
}
const loadTeamPlayers = async () => {
  const response = await axiosGet(`coach/teams/${teamId.value}`)
  const payload = response?.data?.data ?? response?.data ?? {}
  teamPlayers.value = Array.isArray(payload) ? payload : (payload.players ?? payload.team_players ?? [])
}
const inspectFile = async () => {
  inspectionError.value = ''
  inspecting.value = true
  const form = new FormData()
  form.append('platform', platformKey.value)
  form.append('team_id', teamId.value)
  form.append('session_type', sessionValues[sessionType.value])
  form.append('file', selectedFile.value)
  try {
    const [response] = await Promise.all([axiosPost('data-hub/inspect', form), loadTeamPlayers()])
    inspection.value = response.data.data
    Object.keys(mappings).forEach(key => delete mappings[key])
    inspection.value.players.forEach(player => {
      const exact = player.suggested_matches?.find(match => match.match_type === 'exact')
      if (exact) mappings[player.external_name] = exact.player_id
    })
    selectedFile.value = null
    step.value = 4
  } catch (error) {
    inspectionError.value = error?.response?.data?.message || 'TrackMan inspection failed. Check the file and try again.'
  } finally {
    inspecting.value = false
  }
}
const next = async () => {
  if (!canContinue.value) return
  if (step.value < 3) {
    step.value += 1
    return
  }
  if (step.value === 3) {
    await inspectFile()
    return
  }
  if (step.value === 4) {
    step.value = 5
    return
  }
  finishInspection()
}
const back = () => {
  if (step.value === 4) {
    inspection.value = null
    Object.keys(mappings).forEach(key => delete mappings[key])
    step.value = 2
    return
  }
  if (step.value > 1) step.value -= 1
}
const clearWorkflow = () => {
  step.value = 1
  platformKey.value = ''
  selectedFile.value = null
  fileError.value = ''
  fileWarning.value = ''
  teamId.value = ''
  sessionType.value = ''
  inspection.value = null
  inspectionError.value = ''
  teamPlayers.value = []
  Object.keys(mappings).forEach(key => delete mappings[key])
  duplicateOverride.value = false
  inspectionComplete.value = false
}
const cancel = () => {
  clearWorkflow()
  router.push('/data-hub')
}
const finishInspection = () => {
  inspectionComplete.value = true
  window.setTimeout(() => {
    clearWorkflow()
    router.push('/data-hub')
  }, 1200)
}
onMounted(async () => {
  window.addEventListener('fmtrx-logout', clearWorkflow)
  const currentId = String(activeTeam.value?.id_team ?? activeTeam.value?.id ?? '')
  if (currentId) teamId.value = currentId
  loadingTeams.value = true
  try {
    const available = await teamStore.getTeamsFromApi()
    if (available.length) {
      teamStore.setTeams(available)
      if (!teamId.value) teamId.value = String(available[0]?.id_team ?? available[0]?.id ?? '')
    }
  } finally {
    loadingTeams.value = false
  }
})
onUnmounted(() => window.removeEventListener('fmtrx-logout', clearWorkflow))
onBeforeRouteLeave(clearWorkflow)
</script>

<template>
  <Layout>
    <section class="data-hub-shell">
      <header class="data-hub-hero">
        <div><span class="eyebrow">FMTRX Data Hub</span><h1>Inspect TrackMan Data</h1><p>Detect sessions and players, then map them before any future import.</p></div>
        <div class="phase-badge"><strong>Phase 2A</strong><span>Inspection only</span></div>
      </header>
      <ImportStepper :current-step="step" />
      <div class="wizard-card">
        <div class="wizard-heading">
          <div><span>Step {{ step }} of 5</span><h2>{{ ['Choose TrackMan','Select a data file','Choose the destination','Map imported players','Review normalized data'][step - 1] }}</h2></div>
          <p>{{ step === 3 ? 'Continue uploads the file temporarily for inspection.' : 'No FMTRX sessions or statistics are created.' }}</p>
        </div>
        <PlatformSelector v-if="step === 1" :platforms="DATA_HUB_PLATFORMS" :model-value="platformKey" @update:model-value="setPlatform" />
        <FileDropzone v-else-if="step === 2" :model-value="selectedFile" :error="fileError" :warning="fileWarning" :max-size-bytes="DATA_HUB_MAX_FILE_SIZE_BYTES" @update:model-value="setFile" />
        <DestinationSelector v-else-if="step === 3" :teams="teams" :session-types="allowedSessionTypes" :team-id="teamId" :session-type="sessionType" :loading="loadingTeams" @update:team-id="teamId = $event" @update:session-type="sessionType = $event" />
        <PlayerMapping v-else-if="step === 4" :players="inspection.players" :mappings="mappings" :team-players="teamPlayers" @update:mapping="updateMapping" />
        <InspectionReview v-else :inspection="reviewInspection" :team-name="selectedTeam.name" :destination="sessionType" :mappings="mappings" />
        <p v-if="inspectionError" class="error-message">{{ inspectionError }}</p>
        <label v-if="step === 4 && hasDuplicates" class="override"><input v-model="duplicateOverride" type="checkbox"> I understand that multiple TrackMan names will map to the same FMTRX player.</label>
        <div v-if="inspectionComplete" class="complete-message"><strong>Inspection complete.</strong><span>No data was imported and no FMTRX records were changed.</span></div>
        <footer class="wizard-actions">
          <button type="button" class="cancel-button" @click="cancel">Cancel</button>
          <div><button v-if="step > 1" type="button" class="back-button" @click="back">Back</button><button type="button" class="continue-button" :disabled="!canContinue" @click="next">{{ inspecting ? 'Inspecting…' : step === 3 ? 'Approve & Inspect' : step === 5 ? 'Finish Inspection' : 'Continue' }} <span>→</span></button></div>
        </footer>
      </div>
    </section>
  </Layout>
</template>

<style scoped>
.data-hub-shell{width:min(1180px,calc(100% - 36px));margin:0 auto;padding:18px 0 50px}.data-hub-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:20px;padding:26px 28px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:linear-gradient(135deg,rgba(25,35,69,.86),rgba(7,14,31,.88))}.eyebrow,.wizard-heading span{color:#ff4964;font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.data-hub-hero h1{margin-top:6px;color:#fff;font-size:clamp(30px,4vw,46px);font-weight:900}.data-hub-hero p,.wizard-heading p{margin-top:6px;color:#94a3b8;font-size:13px}.phase-badge{display:flex;flex-direction:column;padding:12px 15px;border:1px solid rgba(255,43,74,.25);border-radius:12px;background:rgba(255,43,74,.08);text-align:right}.phase-badge strong{color:#ff4964}.phase-badge span{color:#94a3b8;font-size:10px}.wizard-card{margin-top:14px;padding:28px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:linear-gradient(145deg,rgba(26,35,67,.94),rgba(7,13,31,.96))}.wizard-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,.09)}.wizard-heading h2{margin-top:5px;color:#fff;font-size:24px;font-weight:900}.wizard-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:28px;padding-top:22px;border-top:1px solid rgba(255,255,255,.09)}.wizard-actions>div{display:flex;gap:10px}.wizard-actions button{min-height:46px;padding:0 20px;border-radius:11px;font-size:11px;font-weight:900;text-transform:uppercase}.cancel-button,.back-button{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#fff}.continue-button{border:1px solid #ff2b4a;background:#ff2b4a;color:#fff}.continue-button:disabled{opacity:.38}.error-message,.override,.complete-message{display:flex;gap:8px;margin-top:14px;padding:13px 15px;border:1px solid rgba(255,73,100,.25);border-radius:11px;background:rgba(255,43,74,.08);color:#ffd1d8;font-size:12px}.override{border-color:rgba(255,190,64,.3);color:#ffe3ac}.complete-message{border-color:rgba(59,211,154,.25);color:#bff5df}@media(max-width:700px){.data-hub-shell{width:calc(100% - 20px)}.data-hub-hero,.wizard-heading{align-items:flex-start;flex-direction:column}.wizard-card{padding:18px}.wizard-actions{align-items:stretch;flex-direction:column-reverse}.wizard-actions>div{display:grid;grid-template-columns:1fr 1fr}}
</style>
