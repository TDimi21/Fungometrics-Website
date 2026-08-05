<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import RapsodoPitchingSessionReport from '@/components/rapsodo/RapsodoPitchingSessionReport.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const loading = ref(true)
const report = ref(null)
const error = ref('')
const errorCode = ref('')

onMounted(async () => {
  try {
    const params = route.query.player_id ? { player_id: route.query.player_id } : {}
    report.value = (await axiosGet(`data-hub/imports/${route.params.batch}/rapsodo-report`, params)).data.data
  } catch (exception) {
    const status = exception?.response?.status
    errorCode.value = exception?.response?.data?.code || (status === 403 ? 'unauthorized' : 'report_error')
    error.value = exception?.response?.data?.message || 'The Rapsodo report could not be loaded.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Layout>
    <main class="report-page">
      <div class="toolbar"><RouterLink :to="$route.path.startsWith('/player/') ? '/player-dashboard' : '/data-hub'">← Back to reports</RouterLink></div>
      <section v-if="loading" class="report-state loading"><i></i><h1>Loading Rapsodo Pitching Session Report…</h1><p>Reading the completed Import Batch.</p></section>
      <section v-else-if="errorCode==='unauthorized'" class="report-state unauthorized"><h1>Report unavailable</h1><p>You are not authorized to view this player’s report.</p></section>
      <section v-else-if="errorCode==='no_valid_pitches'" class="report-state empty"><h1>No valid pitches</h1><p>This completed import has no reportable Rapsodo pitch events for the mapped player.</p></section>
      <section v-else-if="error" class="report-state error"><h1>Unable to load report</h1><p>{{ error }}</p><small>Error: {{ errorCode }}</small></section>
      <RapsodoPitchingSessionReport v-else-if="report" :report="report" />
    </main>
  </Layout>
</template>

<style scoped>
.report-page{min-height:100vh;padding:18px 20px 60px;background:radial-gradient(circle at 50% -20%,rgba(27,85,103,.32),transparent 42%),#06101d}.toolbar{max-width:1280px;margin:0 auto 10px}.toolbar a{color:#85a5b8;font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.report-state{display:grid;place-items:center;max-width:700px;min-height:320px;margin:70px auto;padding:30px;border:1px solid rgba(255,255,255,.1);border-radius:18px;background:#0b1727;color:#fff;text-align:center}.report-state h1{font-size:23px}.report-state p{color:#8d9db0}.report-state small{color:#ff7f91}.loading i{width:36px;height:36px;border:3px solid rgba(255,255,255,.12);border-top-color:#2ed5ce;border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:600px){.report-page{padding:10px 9px 40px}}@media print{.toolbar{display:none}.report-page{padding:0;background:#fff}}
</style>
