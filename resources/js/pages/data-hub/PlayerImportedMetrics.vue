<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const route = useRoute()
const { axiosGet } = useAxiosAuth()
const metrics = ref([])
const loading = ref(true)
const error = ref('')
const events = computed(() => {
  const grouped = new Map()
  metrics.value.forEach(metric => {
    if (!grouped.has(metric.event_id)) grouped.set(metric.event_id, { id: metric.event_id, occurred_at: metric.occurred_at, event_order: metric.event_order, metrics: [] })
    grouped.get(metric.event_id).metrics.push(metric)
  })
  return [...grouped.values()]
})
const formatDate = value => value ? new Date(value).toLocaleString() : 'Date unavailable'

onMounted(async () => {
  try {
    const response = await axiosGet(`data-hub/players/${route.params.id}/metrics`)
    metrics.value = response.data.data || []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Imported metrics could not be loaded.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Layout>
    <section class="metrics-shell">
      <header><RouterLink to="/data-hub">← Data Hub</RouterLink><span>Imported player history</span><h1>Blast Motion Metrics</h1><p>Canonical swing data saved from approved Blast CSV imports.</p></header>
      <p v-if="loading" class="state">Loading imported metrics…</p>
      <p v-else-if="error" class="state error">{{ error }}</p>
      <p v-else-if="!events.length" class="state">No imported Blast metrics are available for this player.</p>
      <div v-else class="event-list">
        <article v-for="event in events" :key="event.id">
          <div class="event-heading"><div><span>Swing {{ event.event_order }}</span><strong>{{ formatDate(event.occurred_at) }}</strong></div><small>{{ event.metrics.length }} metrics</small></div>
          <div class="metric-grid"><div v-for="metric in event.metrics" :key="metric.canonical_key"><span>{{ metric.display_name }}</span><strong>{{ metric.value }} <small>{{ metric.canonical_unit_key || '' }}</small></strong><code>{{ metric.canonical_key }}</code></div></div>
        </article>
      </div>
    </section>
  </Layout>
</template>

<style scoped>
.metrics-shell{width:min(1180px,calc(100% - 36px));margin:0 auto;padding:20px 0 50px;color:#fff}.metrics-shell>header{padding:28px;border:1px solid rgba(255,255,255,.12);border-radius:18px;background:linear-gradient(135deg,rgba(27,37,72,.94),rgba(6,12,29,.96))}header a,header span{color:#ff4964;font-size:10px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}header span{display:block;margin-top:20px}h1{margin-top:5px;font-size:34px;font-weight:900}header p,.state{margin-top:8px;color:#94a3b8}.state{padding:28px;text-align:center}.error{color:#ff8294}.event-list{display:grid;gap:12px;margin-top:16px}.event-list article{overflow:hidden;border:1px solid rgba(255,255,255,.1);border-radius:15px;background:rgba(11,18,38,.82)}.event-heading{display:flex;align-items:center;justify-content:space-between;padding:16px 18px}.event-heading div{display:flex;flex-direction:column}.event-heading span,.metric-grid span{color:#94a3b8;font-size:9px;font-weight:900;text-transform:uppercase}.event-heading small{color:#ff4964}.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-top:1px solid rgba(255,255,255,.08)}.metric-grid>div{display:flex;flex-direction:column;gap:5px;padding:15px;border:1px solid rgba(255,255,255,.04)}.metric-grid strong{font-size:18px}.metric-grid strong small{color:#94a3b8;font-size:10px}.metric-grid code{overflow:hidden;color:#77bfff;font-size:9px;text-overflow:ellipsis}@media(max-width:800px){.metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:500px){.metric-grid{grid-template-columns:1fr}.metrics-shell{width:calc(100% - 20px)}}
</style>
