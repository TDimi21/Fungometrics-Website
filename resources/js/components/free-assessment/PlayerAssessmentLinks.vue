<script setup>
import { ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const props = defineProps({ playerId: { type: String, default: '' } })
const { axiosGet } = useAxiosAuth()
const reports = ref([]), error = ref('')
let generation = 0
watch(() => props.playerId, async id => {
  const current = ++generation; reports.value = []; error.value = ''
  if (!id) return
  try { const { data } = await axiosGet('free-assessment-reports', { player_id: id }); if (current === generation) reports.value = data.data }
  catch (_) { if (current === generation) error.value = 'Assessment reports could not be loaded.' }
}, { immediate: true })
</script>
<template>
  <section v-if="reports.length || error" class="assessment-links">
    <h2>Free Assessment Reports</h2><p v-if="error" role="alert">{{ error }}</p>
    <RouterLink v-for="r in reports" :key="r.id" :to="{ path: '/assessment-reports', query: { assessment: r.assessment_id, player: r.player_id, kind: 'free_assessment' } }"><span><b>{{ r.name }}</b><small>{{ r.assessment_date }} · {{ r.location }} · {{ r.completed_stations }}/{{ r.total_stations }} stations · {{ r.status === 'completed' ? 'Ready' : 'In progress' }}</small></span><strong>View Report →</strong></RouterLink>
  </section>
</template>
<style scoped>
.assessment-links{margin:20px 0;padding:20px;border:1px solid #244966;border-radius:12px;background:#071727;color:#edf5ff}.assessment-links h2{font-size:18px;font-weight:800;margin-bottom:12px}.assessment-links a{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:14px 0;border-top:1px solid #244966}.assessment-links small{display:block;color:#a9bed2;margin-top:6px}.assessment-links strong{color:#38bdf8;font-size:12px}.assessment-links p{color:#f9b5b5}@media(max-width:600px){.assessment-links a{align-items:flex-start;flex-direction:column}}
</style>
