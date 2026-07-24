<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import ImportStepper from '@/components/data-hub/ImportStepper.vue'
import PlatformSelector from '@/components/data-hub/PlatformSelector.vue'
import FileDropzone from '@/components/data-hub/FileDropzone.vue'
import DestinationSelector from '@/components/data-hub/DestinationSelector.vue'
import ImportSummary from '@/components/data-hub/ImportSummary.vue'
import { DATA_HUB_PLATFORMS, DATA_HUB_SESSION_TYPES } from '@/data/dataHubPlatforms.js'
import {
  DATA_HUB_MAX_FILE_SIZE_BYTES,
  platformSupportsFile,
} from '@/data/dataHubConfig.js'
import { nextDataHubStep, validateDataHubFile } from '@/utils/dataHubWorkflow.js'
import { useTeamStore } from '@/store/team.js'

const router = useRouter()
const teamStore = useTeamStore()
const { teams, team: activeTeam } = storeToRefs(teamStore)

const step = ref(1)
const platformKey = ref('')
const selectedFile = ref(null)
const fileError = ref('')
const fileWarning = ref('')
const teamId = ref('')
const sessionType = ref('')
const loadingTeams = ref(false)
const previewComplete = ref(false)

const selectedPlatform = computed(() =>
  DATA_HUB_PLATFORMS.find((platform) => platform.key === platformKey.value) || null
)

const selectedTeam = computed(() =>
  teams.value.find((team) => String(team?.id_team ?? team?.id ?? '') === teamId.value) || null
)

const allowedSessionTypes = computed(() => {
  if (!selectedPlatform.value) return DATA_HUB_SESSION_TYPES
  return DATA_HUB_SESSION_TYPES.filter((type) => selectedPlatform.value.sessionTypes.includes(type))
})

const canContinue = computed(() => {
  if (step.value === 1) return Boolean(selectedPlatform.value)
  if (step.value === 2) return Boolean(selectedFile.value) && !fileError.value
  if (step.value === 3) return Boolean(selectedTeam.value && sessionType.value)
  return true
})

const validateFile = (file) => {
  const result = validateDataHubFile(file, selectedPlatform.value)
  fileError.value = result.error
  fileWarning.value = result.warning
  return result.valid
}

const setPlatform = (nextKey) => {
  const nextPlatform = DATA_HUB_PLATFORMS.find((platform) => platform.key === nextKey)
  if (selectedFile.value && !platformSupportsFile(nextPlatform, selectedFile.value)) {
    selectedFile.value = null
    fileError.value = ''
    fileWarning.value = ''
  }
  if (platformKey.value !== nextKey) sessionType.value = ''
  platformKey.value = nextKey
}

const setFile = (file) => {
  selectedFile.value = file
  validateFile(file)
}

const next = () => {
  if (!canContinue.value) return
  if (step.value < 4) {
    step.value = nextDataHubStep(step.value, {
      platform: selectedPlatform.value,
      file: selectedFile.value,
      fileValid: !fileError.value,
      team: selectedTeam.value,
      sessionType: sessionType.value,
    })
    return
  }
  finishPreview()
}

const back = () => {
  previewComplete.value = false
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
  previewComplete.value = false
}

const cancel = () => {
  clearWorkflow()
  router.push('/data-hub')
}

const finishPreview = () => {
  previewComplete.value = true
  window.setTimeout(() => {
    clearWorkflow()
    router.push('/data-hub')
  }, 900)
}

onMounted(async () => {
  window.addEventListener('fmtrx-logout', clearWorkflow)
  const currentId = String(activeTeam.value?.id_team ?? activeTeam.value?.id ?? '')
  if (currentId) teamId.value = currentId

  loadingTeams.value = true
  try {
    const availableTeams = await teamStore.getTeamsFromApi()
    if (availableTeams.length) {
      teamStore.setTeams(availableTeams)
      if (!teamId.value) teamId.value = String(availableTeams[0]?.id_team ?? availableTeams[0]?.id ?? '')
    }
  } finally {
    loadingTeams.value = false
  }
})

onUnmounted(() => {
  window.removeEventListener('fmtrx-logout', clearWorkflow)
})

onBeforeRouteLeave(() => {
  clearWorkflow()
})
</script>

<template>
  <Layout>
    <section class="data-hub-shell">
      <header class="data-hub-hero">
        <div>
          <span class="eyebrow">FMTRX Data Hub</span>
          <h1>Import Data</h1>
          <p>Bring baseball technology data into one connected FMTRX workflow.</p>
        </div>
        <div class="phase-badge"><strong>Phase 1</strong><span>Preview experience</span></div>
      </header>

      <ImportStepper :current-step="step" />

      <div class="wizard-card">
        <div class="wizard-heading">
          <div>
            <span>Step {{ step }} of 4</span>
            <h2 v-if="step === 1">Choose a platform</h2>
            <h2 v-else-if="step === 2">Select a data file</h2>
            <h2 v-else-if="step === 3">Choose the destination</h2>
            <h2 v-else>Review your import setup</h2>
          </div>
          <p v-if="step === 1">Select where this data originated.</p>
          <p v-else-if="step === 2">Your file remains local and is not uploaded during Phase 1.</p>
          <p v-else-if="step === 3">Choose the FMTRX team and future session destination.</p>
          <p v-else>Confirm the setup before player mapping is introduced in Phase 2.</p>
        </div>

        <PlatformSelector
          v-if="step === 1"
          :platforms="DATA_HUB_PLATFORMS"
          :model-value="platformKey"
          @update:model-value="setPlatform"
        />

        <FileDropzone
          v-else-if="step === 2"
          :model-value="selectedFile"
          :error="fileError"
          :warning="fileWarning"
          :max-size-bytes="DATA_HUB_MAX_FILE_SIZE_BYTES"
          @update:model-value="setFile"
        />

        <DestinationSelector
          v-else-if="step === 3"
          :teams="teams"
          :session-types="allowedSessionTypes"
          :team-id="teamId"
          :session-type="sessionType"
          :loading="loadingTeams"
          @update:team-id="teamId = $event"
          @update:session-type="sessionType = $event"
        />

        <template v-else>
          <ImportSummary
            :platform="selectedPlatform"
            :file="selectedFile"
            :team-name="selectedTeam.name"
            :session-type="sessionType"
          />
          <div v-if="previewComplete" class="complete-message">
            <strong>Preview complete.</strong>
            <span>No file was uploaded and no FMTRX records were changed.</span>
          </div>
        </template>

        <footer class="wizard-actions">
          <button type="button" class="cancel-button" @click="cancel">Cancel</button>
          <div>
            <button v-if="step > 1" type="button" class="back-button" @click="back">Back</button>
            <button type="button" class="continue-button" :disabled="!canContinue" @click="next">
              {{ step === 4 ? 'Finish Preview' : 'Continue' }}
              <span>→</span>
            </button>
          </div>
        </footer>
      </div>
    </section>
  </Layout>
</template>

<style scoped>
.data-hub-shell { width:min(1180px,calc(100% - 36px)); margin:0 auto; padding:18px 0 50px; }
.data-hub-hero { display:flex; align-items:flex-end; justify-content:space-between; gap:24px; margin-bottom:20px; padding:26px 28px; border:1px solid rgba(255,255,255,.12); border-radius:20px; background:linear-gradient(135deg,rgba(25,35,69,.86),rgba(7,14,31,.88)); box-shadow:0 22px 55px rgba(0,0,0,.28); }
.eyebrow,.wizard-heading span { color:#ff4964; font-size:10px; font-weight:900; letter-spacing:.16em; text-transform:uppercase; }
.data-hub-hero h1 { margin-top:6px; color:#fff; font-size:clamp(30px,4vw,46px); font-weight:900; letter-spacing:-.03em; }
.data-hub-hero p,.wizard-heading p { margin-top:6px; color:rgba(226,232,240,.66); font-size:13px; }
.phase-badge { display:flex; flex-direction:column; min-width:145px; padding:12px 15px; border:1px solid rgba(255,43,74,.25); border-radius:12px; background:rgba(255,43,74,.08); text-align:right; }
.phase-badge strong { color:#ff4964; font-size:11px; letter-spacing:.1em; text-transform:uppercase; }
.phase-badge span { color:rgba(255,255,255,.6); font-size:10px; }
.wizard-card { margin-top:14px; padding:28px; border:1px solid rgba(255,255,255,.12); border-radius:20px; background:linear-gradient(145deg,rgba(26,35,67,.94),rgba(7,13,31,.96)); box-shadow:0 24px 65px rgba(0,0,0,.35); }
.wizard-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:24px; padding-bottom:20px; border-bottom:1px solid rgba(255,255,255,.09); }
.wizard-heading h2 { margin-top:5px; color:#fff; font-size:24px; font-weight:900; }
.wizard-heading p { max-width:430px; text-align:right; }
.wizard-actions { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-top:28px; padding-top:22px; border-top:1px solid rgba(255,255,255,.09); }
.wizard-actions > div { display:flex; gap:10px; }
.wizard-actions button { min-height:46px; padding:0 20px; border-radius:11px; font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; transition:.16s ease; }
.cancel-button,.back-button { border:1px solid rgba(255,255,255,.14); background:rgba(255,255,255,.05); color:rgba(255,255,255,.78); }
.cancel-button:hover,.back-button:hover { background:rgba(255,255,255,.11); color:#fff; }
.continue-button { display:inline-flex; align-items:center; justify-content:center; gap:18px; min-width:150px; border:1px solid #ff2b4a; background:#ff2b4a; color:#fff; box-shadow:0 10px 24px rgba(255,43,74,.2); }
.continue-button:hover:not(:disabled) { background:#ff4964; transform:translateY(-1px); }
.continue-button:disabled { cursor:not-allowed; opacity:.38; box-shadow:none; }
.continue-button span { color:#fff; font-size:18px; }
.complete-message { display:flex; gap:8px; margin-top:14px; padding:13px 15px; border:1px solid rgba(59,211,154,.25); border-radius:11px; background:rgba(59,211,154,.08); color:rgba(206,255,236,.72); font-size:12px; }
.complete-message strong { color:#64e6b4; }
@media (max-width:700px) {
  .data-hub-shell { width:min(100% - 20px,1180px); }
  .data-hub-hero,.wizard-heading { align-items:flex-start; flex-direction:column; }
  .phase-badge { text-align:left; }
  .wizard-card { padding:18px; }
  .wizard-heading p { text-align:left; }
  .wizard-actions { align-items:stretch; flex-direction:column-reverse; }
  .wizard-actions > div { display:grid; grid-template-columns:1fr 1fr; }
  .wizard-actions button { width:100%; }
}
</style>
