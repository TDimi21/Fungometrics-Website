<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Layout from '@/layout/Layout.vue'
import BlastSessionDevelopmentReport from '@/components/blast/BlastSessionDevelopmentReport.vue'
import { useAxiosAuth } from '@/composables/axios-auth.js'

const route = useRoute(), router = useRouter(), { axiosGet } = useAxiosAuth()
const levels = { pro:{label:'Pro'},milb:{label:'MiLB'},college:{label:'College'},high_school_varsity:{label:'High School Varsity'},high_school_jv:{label:'High School JV'},middle_school:{label:'Middle School'},youth:{label:'Youth'} }
const selected = ref(String(route.query.benchmark_level || ''))
const report = ref(null), loading = ref(false), error = ref(''), unauthorized = ref(false)
const load = async () => {
  if (!selected.value) { error.value = 'Select a Benchmark Level to view this report.'; return }
  loading.value = true; error.value = ''; unauthorized.value = false
  try { report.value = (await axiosGet(`data-hub/imports/${route.params.batch}/blast-report`, { benchmark_level:selected.value })).data.data }
  catch (e) { unauthorized.value = e?.response?.status === 401 || e?.response?.status === 403; error.value = e?.response?.data?.message || 'The Blast report could not be loaded.' }
  finally { loading.value = false }
}
watch(selected, async value => { await router.replace({ query: value ? { benchmark_level:value } : {} }); await load() })
onMounted(load)
</script>
<template><Layout><main class="page"><div class="toolbar"><RouterLink to="/data-hub">← Data Hub</RouterLink><label v-if="!report">Benchmark Level<select v-model="selected"><option value="" disabled>Select level</option><option v-for="(level,key) in levels" :key="key" :value="key">{{ level.label }}</option></select></label></div><p v-if="loading" class="state">Loading Blast Session Development Report…</p><p v-else-if="unauthorized" class="state error">You are not authorized to view this player’s report.</p><p v-else-if="error && !report" class="state">{{ error }}</p><BlastSessionDevelopmentReport v-else-if="report" :report="report" :levels="levels" :benchmark-level="selected" @update:benchmark-level="selected=$event" /></main></Layout></template>
<style scoped>.page{width:min(1240px,calc(100% - 28px));margin:0 auto;padding:18px 0 50px}.toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}.toolbar a{color:#ff4964;font-weight:800}.toolbar label{color:#94a3b8;font-size:11px}.toolbar select{margin-left:8px;padding:8px;background:#0b171c;color:#fff;border:1px solid #31515a;border-radius:7px}.state{padding:50px;text-align:center;color:#94a3b8}.error{color:#ff6575}</style>
