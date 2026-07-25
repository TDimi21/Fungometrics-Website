<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { getAuthToken } from '@/utils/authToken.js'
import { useTeamStore } from '@/store/team.js'

const { axiosGet } = useAxiosAuth()
const teamStore = useTeamStore()
const { teams, team } = storeToRefs(teamStore)
const templates = ref([])
const teamId = ref('')
const templateKey = ref('assessment')
const loading = ref(false)
const error = ref('')
const prioritized = computed(() => templates.value.filter(item => item.priority <= 4))
const additional = computed(() => templates.value.filter(item => item.priority > 4))
const teamIdOf = item => String(item?.id_team ?? item?.id ?? '')

const download = async () => {
  if (!teamId.value || !templateKey.value) return
  loading.value = true
  error.value = ''
  try {
    const base = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
    const query = new URLSearchParams({ team_id: teamId.value, template: templateKey.value })
    const response = await fetch(`${base}data-hub/templates/download?${query}`, {
      credentials: 'include',
      headers: getAuthToken() ? { Authorization: `Bearer ${getAuthToken()}` } : {},
    })
    if (!response.ok) throw new Error('Template download failed.')
    const blob = await response.blob()
    const disposition = response.headers.get('content-disposition') || ''
    const name = disposition.match(/filename="([^"]+)"/)?.[1] || `fmtrx-${templateKey.value}.csv`
    const href = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = href
    anchor.download = name
    anchor.click()
    URL.revokeObjectURL(href)
  } catch (exception) {
    error.value = exception.message || 'Template download failed.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const currentId = teamIdOf(team.value)
  const available = teams.value.length ? teams.value : await teamStore.getTeamsFromApi()
  if (!teams.value.length && available.length) teamStore.setTeams(available)
  teamId.value = currentId || teamIdOf(available[0])
  const response = await axiosGet('data-hub/templates')
  templates.value = response.data.data
})
</script>

<template>
  <section class="template-card">
    <div class="template-copy">
      <span>Offline bulk entry</span>
      <h2>FMTRX CSV Templates</h2>
      <p>Download the same fields coaches use in FMTRX, prefilled with active roster players and their stable FMTRX Player ID.</p>
    </div>
    <div class="template-controls">
      <label><span>Team</span><select v-model="teamId"><option value="">Select a team</option><option v-for="item in teams" :key="teamIdOf(item)" :value="teamIdOf(item)">{{ item.name }}</option></select></label>
      <label><span>Template</span><select v-model="templateKey">
        <optgroup label="Priority web-form templates"><option v-for="item in prioritized" :key="item.key" :value="item.key">{{ item.label }}</option></optgroup>
        <optgroup label="Ball-by-ball templates"><option v-for="item in additional" :key="item.key" :value="item.key">{{ item.label }}</option></optgroup>
      </select></label>
      <button type="button" :disabled="!teamId || loading" @click="download">{{ loading ? 'Preparing…' : 'Download Template' }}</button>
      <small v-if="error">{{ error }}</small>
    </div>
  </section>
</template>

<style scoped>
.template-card{display:grid;grid-template-columns:1fr 1.2fr;gap:28px;margin-top:16px;padding:26px;border:1px solid rgba(255,255,255,.1);border-radius:18px;background:linear-gradient(135deg,rgba(18,27,53,.92),rgba(8,15,32,.9));color:#fff}.template-copy span,.template-controls label>span{color:#ff4964;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.template-copy h2{margin-top:5px;font-size:24px;font-weight:900}.template-copy p{margin-top:8px;color:rgba(226,232,240,.62);font-size:12px;line-height:1.6}.template-controls{display:grid;grid-template-columns:1fr 1fr;gap:10px}.template-controls label{display:flex;flex-direction:column;gap:7px}.template-controls select{min-height:44px;padding:0 11px;border:1px solid rgba(255,255,255,.14);border-radius:9px;background:#0b142c;color:#fff}.template-controls button{grid-column:1/-1;min-height:44px;border:0;border-radius:9px;background:#ff2b4a;color:#fff;font-size:11px;font-weight:900;text-transform:uppercase}.template-controls button:disabled{cursor:not-allowed;opacity:.45}.template-controls small{grid-column:1/-1;color:#ff8798}@media(max-width:760px){.template-card,.template-controls{grid-template-columns:1fr}}
</style>
