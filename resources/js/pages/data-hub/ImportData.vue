<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import ImportStepper from '@/components/data-hub/ImportStepper.vue'
import PlatformSelector from '@/components/data-hub/PlatformSelector.vue'
import FileDropzone from '@/components/data-hub/FileDropzone.vue'
import DestinationSelector from '@/components/data-hub/DestinationSelector.vue'
import FileStructure from '@/components/data-hub/FileStructure.vue'
import PlayerMapping from '@/components/data-hub/PlayerMapping.vue'
import ColumnMapping from '@/components/data-hub/ColumnMapping.vue'
import InspectionReview from '@/components/data-hub/InspectionReview.vue'
import { DATA_HUB_DESTINATION_GROUPS, DATA_HUB_PLATFORMS, DATA_HUB_SESSION_TYPES } from '@/data/dataHubPlatforms.js'
import { DATA_HUB_MAX_FILE_SIZE_BYTES, platformSupportsFile } from '@/data/dataHubConfig.js'
import { validateDataHubFile } from '@/utils/dataHubWorkflow.js'
import { compatibilityForConcept } from '@/utils/dataHubConceptCompatibility.js'
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
const columnEntries = reactive({})
const dictionary = ref({ domains: [], concepts: [], units: [] })
const mappingApproved = ref(false)
const mappingError = ref('')
const approvingMapping = ref(false)
const approvingPlayers = ref(false)
const confirmedDuplicateTargets = ref([])
const confirmedWarningColumns = ref([])
const confirmedDuplicateConcepts = ref([])
const playerMappingApproved = ref(false)
const inspectionComplete = ref(false)
const structurePending = ref(false)
const sessionValues = {
  Cage: 'cage', 'Live AB': 'live_ab', Bullpen: 'bullpen', Strength: 'strength',
  Mobility: 'mobility', Assessment: 'assessment', 'Batting Practice': 'batting_practice',
  'Pitching Practice': 'pitching_practice', 'Long Toss': 'long_toss', 'Weighted Balls': 'weighted_balls',
  'Exit Velocity': 'exit_velocity', 'Speed & Agility': 'speed_agility', Recovery: 'recovery',
}
const selectedPlatform = computed(() => DATA_HUB_PLATFORMS.find(item => item.key === platformKey.value) || null)
const selectedTeam = computed(() => teams.value.find(item => String(item?.id_team ?? item?.id ?? '') === teamId.value) || null)
const allowedSessionTypes = computed(() => DATA_HUB_SESSION_TYPES)
const mappingValues = computed(() => Object.values(mappings).filter(Boolean))
const hasDuplicates = computed(() => {
  const ids = mappingValues.value
  return new Set(ids).size !== ids.length
})
const canContinue = computed(() => {
  if (step.value === 1) return ['trackman', 'hittrax', 'rapsodo', 'blast-motion', 'generic-csv'].includes(platformKey.value)
  if (step.value === 2) return Boolean(selectedFile.value) && !fileError.value
  if (step.value === 3) return Boolean(selectedTeam.value && sessionType.value) && !inspecting.value
  if (step.value === 4) {
    if (structurePending.value) return false
    const duplicateTargets = mappingValues.value.filter((id, index, values) => values.indexOf(id) !== index)
    const everyPlayerDecided = inspection.value?.players?.every(player => Object.prototype.hasOwnProperty.call(mappings, player.source_key))
    return everyPlayerDecided && mappingValues.value.length > 0 && duplicateTargets.every(id => confirmedDuplicateTargets.value.includes(id)) && !approvingPlayers.value
  }
  if (step.value === 5) {
    const entries = Object.values(columnEntries)
    const mapped = entries.filter(entry => entry.action === 'map' && entry.baseball_concept_id)
    const performanceMapped = mapped.filter(entry => {
      const concept = dictionary.value.concepts.find(item => item.id === entry.baseball_concept_id)
      const domain = dictionary.value.domains.find(item => item.id === concept?.domain_id)?.key
      return domain && domain !== 'session_context'
    })
    const incompatible = mapped.some(entry => {
      const concept = dictionary.value.concepts.find(item => item.id === entry.baseball_concept_id)
      return compatibilityForConcept(sessionType.value, concept, dictionary.value.domains).level === 'incompatible'
    })
    const unconfirmedWarnings = mapped.some(entry => {
      const concept = dictionary.value.concepts.find(item => item.id === entry.baseball_concept_id)
      return compatibilityForConcept(sessionType.value, concept, dictionary.value.domains).level === 'warning'
        && !confirmedWarningColumns.value.includes(entry.source_column_name)
    })
    const conceptIds = mapped.map(entry => entry.baseball_concept_id)
    const duplicateConcepts = conceptIds.filter((id, index) => conceptIds.indexOf(id) !== index)
    return performanceMapped.length > 0 && !incompatible && !unconfirmedWarnings
      && duplicateConcepts.every(id => confirmedDuplicateConcepts.value.includes(id))
      && inspection.value?.source_columns?.every(column => {
      const entry = columnEntries[column.source_column_name]
      return entry && (entry.action !== 'map' || entry.baseball_concept_id)
    }) && !approvingMapping.value
  }
  return true
})
const reviewInspection = computed(() => {
  if (!inspection.value) return null
  return {
    ...inspection.value,
    sample_rows: inspection.value.sample_rows.map(row => ({
      ...row,
      player_id: (() => {
        const source = inspection.value.players.find(player => player.source_name === row.player_external_name)
        return mappings[source?.source_key] || null
      })(),
    })),
  }
})

const setPlatform = nextKey => {
  const nextPlatform = DATA_HUB_PLATFORMS.find(item => item.key === nextKey)
  if (selectedFile.value && !platformSupportsFile(nextPlatform, selectedFile.value)) selectedFile.value = null
  platformKey.value = nextKey
  if (inspection.value) {
    inspection.value = null
    Object.keys(mappings).forEach(key => delete mappings[key])
    Object.keys(columnEntries).forEach(key => delete columnEntries[key])
  }
  sessionType.value = nextKey === 'rapsodo' ? 'Bullpen' : (nextKey === 'blast-motion' ? 'Batting Practice' : '')
  inspectionError.value = ['trackman', 'hittrax', 'rapsodo', 'blast-motion', 'generic-csv'].includes(nextKey) ? '' : 'This platform is not available for inspection yet.'
}
const setFile = file => {
  const result = validateDataHubFile(file, selectedPlatform.value)
  selectedFile.value = file
  fileError.value = result.error
  fileWarning.value = result.warning
  Object.keys(mappings).forEach(key => delete mappings[key])
  Object.keys(columnEntries).forEach(key => delete columnEntries[key])
  playerMappingApproved.value = false
  mappingApproved.value = false
}
const setTeam = async value => {
  if (teamId.value !== value && inspection.value) {
    Object.keys(mappings).forEach(key => delete mappings[key])
    inspection.value.players = inspection.value.players.map(player => ({ ...player, suggested_player: null, suggested_matches: [] }))
    playerMappingApproved.value = false
  }
  teamId.value = value
  if (inspection.value && value) await loadTeamPlayers()
}
const setSessionType = value => {
  if (sessionType.value !== value) {
    sessionType.value = value
    mappingApproved.value = false
    confirmedWarningColumns.value = []
    confirmedDuplicateConcepts.value = []
  }
}
const updateMapping = (name, value) => {
  if (value === null) delete mappings[name]
  else mappings[name] = value
  confirmedDuplicateTargets.value = confirmedDuplicateTargets.value.filter(id => id !== value)
  playerMappingApproved.value = false
}
const confirmDuplicate = (target, confirmed) => {
  confirmedDuplicateTargets.value = confirmed
    ? [...new Set([...confirmedDuplicateTargets.value, target])]
    : confirmedDuplicateTargets.value.filter(id => id !== target)
}
const confirmWarning = (column, confirmed) => {
  confirmedWarningColumns.value = confirmed
    ? [...new Set([...confirmedWarningColumns.value, column])]
    : confirmedWarningColumns.value.filter(item => item !== column)
}
const confirmDuplicateConcept = (conceptId, confirmed) => {
  confirmedDuplicateConcepts.value = confirmed
    ? [...new Set([...confirmedDuplicateConcepts.value, conceptId])]
    : confirmedDuplicateConcepts.value.filter(item => item !== conceptId)
}
const loadTeamPlayers = async () => {
  const response = await axiosGet('data-hub/player-mappings/roster', { team_id: teamId.value })
  teamPlayers.value = response.data.data
}
const inspectFile = async (structure = null) => {
  inspectionError.value = ''
  inspecting.value = true
  const form = new FormData()
  form.append('platform', platformKey.value)
  form.append('team_id', teamId.value)
  form.append('session_type', sessionValues[sessionType.value])
  form.append('file', selectedFile.value)
  if (structure) form.append('structure', JSON.stringify({ ...structure, platform: platformKey.value }))
  try {
    const [response] = await Promise.all([axiosPost('data-hub/inspect', form), loadTeamPlayers()])
    inspection.value = response.data.data
    structurePending.value = Boolean(
      inspection.value.normalized_inspection?.requires_structure_confirmation
    ) && !structure
    Object.keys(mappings).forEach(key => delete mappings[key])
    inspection.value.players.forEach(player => {
      const automatic = player.suggested_matches?.find(match => match.auto_select)
      if (automatic) mappings[player.source_key] = automatic.player_id
    })
    const headers = inspection.value.source_columns?.map(column => column.source_column_name) || []
    const catalogResponse = await axiosGet('data-hub/dictionary')
    const resolutionResponse = platformKey.value === 'generic-csv'
      ? { data: { data: { columns: inspection.value.source_columns.map(column => ({
        source_column_name: column.source_column_name,
        normalized_source_column: column.normalized_source_column,
        concept_id: column.canonical_concept_id,
        source_unit_key: column.source_unit_key,
        transformation_key: null,
        resolution_source: column.canonical_concept_id ? 'fmtrx_canonical_key' : 'unresolved',
        relationship_type: column.canonical_concept_id ? 'exact_equivalent' : null,
        confidence: column.canonical_concept_id ? 100 : 0,
      })) } } }
      : await axiosPost('data-hub/mappings/resolve', { team_id: teamId.value, platform: platformKey.value, headers })
    dictionary.value = catalogResponse.data.data
    Object.keys(columnEntries).forEach(key => delete columnEntries[key])
    resolutionResponse.data.data.columns.forEach(resolution => {
      const source = inspection.value.source_columns.find(column => column.source_column_name === resolution.source_column_name)
      const identityColumns = ['batter', 'battername', 'hitter', 'pitcher', 'pitchername', 'user']
      const dateColumns = ['date', 'gamedate', 'sessiondate', 'eventtimestamp']
      columnEntries[resolution.source_column_name] = {
        source_column_name: resolution.source_column_name,
        normalized_source_column: resolution.normalized_source_column,
        baseball_concept_id: source?.default_not_importing ? null : resolution.concept_id,
        source_unit_key: resolution.source_unit_key || null,
        transformation_key: resolution.transformation_key || null,
        resolution_source: source?.default_not_importing ? 'source_quality_default' : resolution.resolution_source,
        relationship_type: resolution.relationship_type || null,
        confidence: source?.default_not_importing ? 0 : resolution.confidence,
        required_type: identityColumns.includes(resolution.normalized_source_column) ? 'player_identity' : (dateColumns.includes(resolution.normalized_source_column) ? 'session_date' : null),
        action: source?.default_not_importing ? 'ignore' : (resolution.concept_id ? 'map' : 'ignore'),
        metadata: {
          sample_values: source?.sample_values || [],
          source_warnings: source?.warnings || [],
          controlled_value_transformations: source?.controlled_value_transformations || [],
          source_specific: source?.source_specific || false,
        },
      }
    })
    if (platformKey.value !== 'generic-csv') selectedFile.value = null
    step.value = 4
  } catch (error) {
    inspectionError.value = error?.response?.data?.message || 'File inspection failed. Check the file and try again.'
  } finally {
    inspecting.value = false
  }
}
const applyStructure = async structure => {
  await inspectFile(structure)
  structurePending.value = false
}
const next = async () => {
  if (!canContinue.value) return
  if (step.value < 3) {
    step.value += 1
    return
  }
  if (step.value === 3) {
    if (inspection.value) step.value = 4
    else await inspectFile()
    return
  }
  if (step.value === 4) {
    await approvePlayerMapping()
    return
  }
  if (step.value === 5) {
    await approveColumnMapping()
    return
  }
  finishInspection()
}
const approvePlayerMapping = async () => {
  mappingError.value = ''
  approvingPlayers.value = true
  try {
    if (platformKey.value === 'generic-csv') {
      playerMappingApproved.value = true
      step.value = 5
      return
    }
    await axiosPost('data-hub/player-mappings/approve', {
      team_id: teamId.value,
      platform: platformKey.value,
      mappings: inspection.value.players.map(player => ({
        source_key: player.source_key,
        source_name: player.source_name,
        external_player_id: player.external_player_id,
        roles: player.roles,
        fmtrx_player_id: mappings[player.source_key] || null,
        not_importing: !mappings[player.source_key],
        remember_mapping: player.remember_mapping !== false,
      })),
      confirmed_duplicate_targets: confirmedDuplicateTargets.value,
    })
    playerMappingApproved.value = true
    step.value = 5
  } catch (error) {
    mappingError.value = error?.response?.data?.message || 'The player mapping could not be approved.'
  } finally {
    approvingPlayers.value = false
  }
}
const updateColumnEntry = (name, entry) => {
  columnEntries[name] = entry
  confirmedWarningColumns.value = confirmedWarningColumns.value.filter(item => item !== name)
  confirmedDuplicateConcepts.value = []
  mappingApproved.value = false
}
const approveColumnMapping = async () => {
  mappingError.value = ''
  approvingMapping.value = true
  const unitId = key => dictionary.value.units.find(unit => unit.key === key)?.id || null
  const conceptById = id => dictionary.value.concepts.find(concept => concept.id === id)
  const entries = Object.values(columnEntries).map(entry => ({
    source_column_name: entry.source_column_name,
    normalized_source_column: entry.normalized_source_column,
    baseball_concept_id: entry.baseball_concept_id || null,
    source_unit_id: unitId(entry.source_unit_key),
    canonical_unit_id: unitId(conceptById(entry.baseball_concept_id)?.canonical_unit_key),
    transformation_key: entry.transformation_key || null,
    resolution_source: entry.resolution_source || 'manual',
    confidence: Number(entry.confidence || 0),
    required_type: entry.required_type || null,
    action: entry.action,
    compatibility_level: entry.action === 'map'
      ? compatibilityForConcept(sessionType.value, conceptById(entry.baseball_concept_id), dictionary.value.domains).level
      : 'not_importing',
    warning_confirmed: confirmedWarningColumns.value.includes(entry.source_column_name),
    metadata: entry.metadata || null,
  }))
  try {
    if (platformKey.value === 'generic-csv') {
      mappingApproved.value = true
      step.value = 6
      return
    }
    await axiosPost('data-hub/mappings/approve', {
      team_id: teamId.value,
      platform: platformKey.value,
      template_fingerprint: inspection.value.template_fingerprint,
      headers: inspection.value.source_columns.map(column => column.source_column_name),
      entries,
      destination: sessionValues[sessionType.value],
      confirmed_duplicate_concepts: confirmedDuplicateConcepts.value,
      remember: true,
    })
    mappingApproved.value = true
    step.value = 6
  } catch (error) {
    mappingError.value = error?.response?.data?.message || 'The column mapping could not be approved.'
  } finally {
    approvingMapping.value = false
  }
}
const submitConcept = async column => {
  const proposed = window.prompt(`Proposed Baseball Concept name for "${column.source_column_name}"`, column.source_column_name)
  if (!proposed) return
  await axiosPost('data-hub/concept-submissions', { team_id: teamId.value, source_column_name: column.source_column_name, proposed_display_name: proposed, sample_values: column.sample_values || [] })
}
const back = () => {
  if (step.value === 4) {
    step.value = 3
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
  Object.keys(columnEntries).forEach(key => delete columnEntries[key])
  dictionary.value = { domains: [], concepts: [], units: [] }
  mappingApproved.value = false
  mappingError.value = ''
  confirmedDuplicateTargets.value = []
  confirmedWarningColumns.value = []
  confirmedDuplicateConcepts.value = []
  playerMappingApproved.value = false
  inspectionComplete.value = false
  structurePending.value = false
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
        <div><span class="eyebrow">FMTRX Data Hub</span><h1>Inspect Data</h1><p>Detect file structure, sessions, and players, then map them before any future import.</p></div>
        <div class="phase-badge"><strong>Phase 2A</strong><span>Inspection only</span></div>
      </header>
      <ImportStepper :current-step="step" />
      <div class="wizard-card">
        <div class="wizard-heading">
        <div><span>Step {{ step }} of 6</span><h2>{{ ['Choose TrackMan','Select a data file','Choose the destination','Map imported players','Map source columns','Review normalized data'][step - 1] }}</h2></div>
          <p>{{ step === 3 ? 'Continue uploads the file temporarily for inspection.' : 'No FMTRX sessions or statistics are created.' }}</p>
        </div>
        <PlatformSelector v-if="step === 1" :platforms="DATA_HUB_PLATFORMS" :model-value="platformKey" @update:model-value="setPlatform" />
        <FileDropzone v-else-if="step === 2" :model-value="selectedFile" :error="fileError" :warning="fileWarning" :max-size-bytes="DATA_HUB_MAX_FILE_SIZE_BYTES" @update:model-value="setFile" />
        <DestinationSelector v-else-if="step === 3" :teams="teams" :session-types="allowedSessionTypes" :destination-groups="DATA_HUB_DESTINATION_GROUPS" :team-id="teamId" :session-type="sessionType" :loading="loadingTeams" @update:team-id="setTeam" @update:session-type="setSessionType" />
        <FileStructure v-else-if="step === 4 && structurePending" :inspection="inspection" :applying="inspecting" @apply="applyStructure" />
        <PlayerMapping v-else-if="step === 4" :players="inspection.players" :mappings="mappings" :team-players="teamPlayers" :platform-name="inspection.detected_format?.provider || selectedPlatform?.name" :confirmed-duplicates="confirmedDuplicateTargets" @update:mapping="updateMapping" @confirm:duplicate="confirmDuplicate" @refresh-roster="loadTeamPlayers" />
        <ColumnMapping v-else-if="step === 5" :columns="inspection.source_columns" :concepts="dictionary.concepts" :domains="dictionary.domains" :entries="columnEntries" :destination="sessionType" :confirmed-warnings="confirmedWarningColumns" :confirmed-duplicates="confirmedDuplicateConcepts" @update:entry="updateColumnEntry" @submit-concept="submitConcept" @confirm:warning="confirmWarning" @confirm:duplicate="confirmDuplicateConcept" />
        <InspectionReview v-else :inspection="reviewInspection" :team-name="selectedTeam.name" :destination="sessionType" :mappings="mappings" :column-entries="columnEntries" :team-players="teamPlayers" :concepts="dictionary.concepts" :domains="dictionary.domains" :confirmed-warning-columns="confirmedWarningColumns" :confirmed-duplicate-targets="confirmedDuplicateTargets" :confirmed-duplicate-concepts="confirmedDuplicateConcepts" />
        <p v-if="inspectionError" class="error-message">{{ inspectionError }}</p>
        <p v-if="mappingError" class="error-message">{{ mappingError }}</p>
        <div v-if="inspectionComplete" class="complete-message"><strong>Inspection complete.</strong><span>No data was imported and no FMTRX records were changed.</span></div>
        <footer class="wizard-actions">
          <button type="button" class="cancel-button" @click="cancel">Cancel</button>
          <div><button v-if="step > 1" type="button" class="back-button" @click="back">Back</button><button v-if="!structurePending" type="button" class="continue-button" :disabled="!canContinue" @click="next">{{ inspecting ? 'Inspecting…' : approvingPlayers ? 'Approving players…' : approvingMapping ? 'Approving…' : step === 3 ? 'Approve & Inspect' : step === 4 ? 'Approve Player Mapping' : step === 5 ? 'Approve Mapping' : step === 6 ? 'Finish Inspection' : 'Continue' }} <span>→</span></button></div>
        </footer>
      </div>
    </section>
  </Layout>
</template>

<style scoped>
.data-hub-shell{width:min(1180px,calc(100% - 36px));margin:0 auto;padding:18px 0 50px}.data-hub-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:20px;padding:26px 28px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:linear-gradient(135deg,rgba(25,35,69,.86),rgba(7,14,31,.88))}.eyebrow,.wizard-heading span{color:#ff4964;font-size:10px;font-weight:900;letter-spacing:.16em;text-transform:uppercase}.data-hub-hero h1{margin-top:6px;color:#fff;font-size:clamp(30px,4vw,46px);font-weight:900}.data-hub-hero p,.wizard-heading p{margin-top:6px;color:#94a3b8;font-size:13px}.phase-badge{display:flex;flex-direction:column;padding:12px 15px;border:1px solid rgba(255,43,74,.25);border-radius:12px;background:rgba(255,43,74,.08);text-align:right}.phase-badge strong{color:#ff4964}.phase-badge span{color:#94a3b8;font-size:10px}.wizard-card{margin-top:14px;padding:28px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:linear-gradient(145deg,rgba(26,35,67,.94),rgba(7,13,31,.96))}.wizard-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,.09)}.wizard-heading h2{margin-top:5px;color:#fff;font-size:24px;font-weight:900}.wizard-actions{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:28px;padding-top:22px;border-top:1px solid rgba(255,255,255,.09)}.wizard-actions>div{display:flex;gap:10px}.wizard-actions button{min-height:46px;padding:0 20px;border-radius:11px;font-size:11px;font-weight:900;text-transform:uppercase}.cancel-button,.back-button{border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.05);color:#fff}.continue-button{border:1px solid #ff2b4a;background:#ff2b4a;color:#fff}.continue-button:disabled{opacity:.38}.error-message,.override,.complete-message{display:flex;gap:8px;margin-top:14px;padding:13px 15px;border:1px solid rgba(255,73,100,.25);border-radius:11px;background:rgba(255,43,74,.08);color:#ffd1d8;font-size:12px}.override{border-color:rgba(255,190,64,.3);color:#ffe3ac}.complete-message{border-color:rgba(59,211,154,.25);color:#bff5df}@media(max-width:700px){.data-hub-shell{width:calc(100% - 20px)}.data-hub-hero,.wizard-heading{align-items:flex-start;flex-direction:column}.wizard-card{padding:18px}.wizard-actions{align-items:stretch;flex-direction:column-reverse}.wizard-actions>div{display:grid;grid-template-columns:1fr 1fr}}
</style>
