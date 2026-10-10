<script setup>
import { ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'
const props = defineProps({ playerId: [String, Number], date: String })
const { axiosGet } = useAxiosAuth()
const day = ref(props.date?.slice(0,10) || new Date().toLocaleDateString('en-CA'))
const summary = ref(null), loading = ref(false), error = ref('')
let request = 0
async function load() {
  const id = ++request
  summary.value = null; loading.value = true; error.value = ''
  try {
    const path = props.playerId ? `coach/players/${props.playerId}/workout-weekly-summary` : 'player/workout-weekly-summary'
    const res = await axiosGet(`${path}?date=${encodeURIComponent(day.value)}`)
    if (id === request) summary.value = res.data.data
  } catch { if (id === request) error.value = 'Weekly progress could not load. Please retry.' }
  finally { if (id === request) loading.value = false }
}
watch(() => props.date, date => { if(date) day.value = date.slice(0,10) })
watch([day, () => props.playerId], load, { immediate: true })
const show = (value, sample) => value == null ? 'Not recorded' : sample ? `${value}% (${sample[0]}/${sample[1]})` : value
</script>
<template>
  <section class="weekly-results">
    <header><h3>Weekly workout progress</h3><label>Week of <input v-model="day" type="date" /></label><button @click="load" :disabled="loading">Refresh</button></header>
    <p v-if="loading" role="status">Loading weekly results…</p>
    <p v-else-if="error" role="alert">{{ error }}</p>
    <template v-else-if="summary">
      <p>{{ summary.week_start }} – {{ summary.week_end }}</p>
      <p><strong>{{ summary.current.submitted }}/{{ summary.current.assigned }} workouts submitted</strong> · {{ summary.current.fully_completed }} with all required drills completed</p>
      <p>{{ summary.current.completed_drills }}/{{ summary.current.counted_drills }} required drills completed · Previous week: {{ summary.previous.completed_drills }}/{{ summary.previous.counted_drills }}</p>
      <p>Check-ins received: {{ summary.current.readiness_received }} readiness · {{ summary.current.reflection_received }} reflection · {{ summary.current.reviewed }} coach reviews</p>
      <p v-if="!summary.current.assigned">No workouts assigned for this week.</p>
      <details><summary>Hitting, pitching, defense and strength results</summary>
        <table><thead><tr><th>Recorded result</th><th>This week</th><th>Previous week</th></tr></thead><tbody><tr v-for="metric in summary.metrics" :key="metric.key"><th>{{ metric.label }}</th><td>{{ show(metric.current, metric.current_sample) }}</td><td>{{ show(metric.previous, metric.previous_sample) }}</td></tr></tbody></table>
        <p>{{ summary.current.linked_sessions }} linked sessions this week · {{ summary.previous.linked_sessions }} previous week</p>
        <p>{{ summary.note }}</p>
      </details>
    </template>
  </section>
</template>
<style scoped>
.weekly-results{background:#101d30;border:1px solid #344b68;border-radius:14px;padding:16px;margin:16px 0;color:#edf3ff}.weekly-results header{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.weekly-results h3{font-weight:800;font-size:18px;margin-right:auto}.weekly-results p{margin:10px 0;color:#bdcce0}.weekly-results input,.weekly-results button{background:#182940;color:#edf3ff;border:1px solid #49617e;border-radius:8px;padding:8px;min-height:44px}.weekly-results summary{cursor:pointer;min-height:44px;padding:10px 0}.weekly-results table{width:100%;font-size:13px;border-collapse:collapse}.weekly-results td,.weekly-results th{text-align:left;padding:8px 4px;border-bottom:1px solid #344b68}
</style>
