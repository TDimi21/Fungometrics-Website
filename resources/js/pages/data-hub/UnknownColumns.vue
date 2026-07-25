<script setup>
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
import { useTeamStore } from '@/store/team.js'

const { axiosGet, axiosPatch, axiosPost } = useAxiosAuth()
const teamStore = useTeamStore()
const { teams, team } = storeToRefs(teamStore)
const rows = ref([])
const concepts = ref([])
const teamId = ref('')
const error = ref('')
const load = async () => {
  if (!teamId.value) return
  const [unknown, dictionary] = await Promise.all([axiosGet('data-hub/unknown-columns', { team_id: teamId.value }), axiosGet('data-hub/dictionary')])
  rows.value = unknown.data.data
  concepts.value = dictionary.data.data.concepts
}
const update = async (row, status) => {
  error.value = ''
  try {
    await axiosPatch(`data-hub/unknown-columns/${row.id}`, { status, resolved_concept_id: row.resolved_concept_id || null })
    await load()
  } catch (requestError) {
    error.value = requestError?.response?.data?.message || 'The unknown column could not be updated.'
  }
}
const submit = async row => {
  const name = window.prompt('Proposed Baseball Concept name', row.source_column_name)
  if (!name) return
  await axiosPost('data-hub/concept-submissions', { team_id: teamId.value, platform_definition_id: row.platform_definition_id, source_column_name: row.source_column_name, proposed_display_name: name, sample_values: row.sample_values || [] })
}
onMounted(async () => {
  const available = teams.value.length ? teams.value : await teamStore.getTeamsFromApi()
  if (!teams.value.length) teamStore.setTeams(available)
  teamId.value = String(team.value?.id_team ?? team.value?.id ?? available[0]?.id_team ?? available[0]?.id ?? '')
  await load()
})
</script>

<template>
  <Layout><section class="unknown-shell">
    <header><div><span>Data Hub governance</span><h1>Unknown Columns</h1><p>Recall unresolved source fields without exposing them to profiles, statistics, ratings, or research.</p></div><RouterLink to="/data-hub">Back to Data Hub</RouterLink></header>
    <div class="toolbar"><select v-model="teamId" @change="load"><option v-for="item in teams" :key="item.id_team || item.id" :value="String(item.id_team || item.id)">{{ item.name }}</option></select></div>
    <p v-if="error" class="error">{{ error }}</p>
    <div class="table"><div class="table-head"><b>Source column</b><b>Occurrences</b><b>Samples</b><b>First / Last seen</b><b>Resolution</b></div>
      <div v-for="row in rows" :key="row.id" class="table-row">
        <div><strong>{{ row.source_column_name }}</strong><small>{{ row.status }}</small></div><span>{{ row.occurrence_count }}</span><span>{{ row.sample_values?.join(' · ') || '—' }}</span><span>{{ row.first_seen_at }}<br>{{ row.last_seen_at }}</span>
        <div class="actions"><select v-model="row.resolved_concept_id"><option :value="null">Choose concept</option><option v-for="concept in concepts" :key="concept.id" :value="concept.id">{{ concept.display_name }}</option></select><button @click="update(row,'resolved')">Resolve</button><button @click="submit(row)">Submit new</button><button @click="update(row,'archived')">Archive</button></div>
      </div><p v-if="!rows.length" class="empty">No unknown columns have been stored for this team.</p>
    </div>
  </section></Layout>
</template>

<style scoped>
.unknown-shell{width:min(1180px,calc(100% - 36px));margin:auto;padding:20px 0 50px;color:#fff}.unknown-shell>header{display:flex;align-items:end;justify-content:space-between;padding:28px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:#0c1530}.unknown-shell header span{color:#ff4964;font-size:10px;font-weight:900;text-transform:uppercase}.unknown-shell h1{font-size:36px}.unknown-shell p{color:#94a3b8}.unknown-shell a,.actions button{padding:11px 14px;border:1px solid #ff2b4a;border-radius:9px;background:#ff2b4a;color:#fff}.toolbar{margin:14px 0}.toolbar select,.actions select{min-height:40px;padding:0 10px;border:1px solid rgba(255,255,255,.15);border-radius:8px;background:#111a32;color:#fff}.table{border:1px solid rgba(255,255,255,.1);border-radius:14px;overflow:hidden}.table-head,.table-row{display:grid;grid-template-columns:1fr 100px 1.2fr 1fr 2fr;gap:12px;align-items:center;padding:14px}.table-head{background:#101a35;color:#94a3b8;font-size:10px;text-transform:uppercase}.table-row{border-top:1px solid rgba(255,255,255,.08);background:rgba(11,18,38,.85);font-size:11px}.table-row>div:first-child{display:flex;flex-direction:column}.table-row small{color:#94a3b8}.actions{display:flex;flex-wrap:wrap;gap:6px}.actions select{width:100%}.actions button{padding:7px;font-size:9px}.error,.empty{padding:15px}@media(max-width:800px){.table-head{display:none}.table-row{grid-template-columns:1fr}.unknown-shell>header{align-items:start;flex-direction:column;gap:18px}}
</style>
