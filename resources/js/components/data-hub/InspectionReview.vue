<script setup>
import { computed } from 'vue'
const props = defineProps({
  inspection: { type: Object, required: true },
  teamName: { type: String, required: true },
  destination: { type: String, required: true },
  mappings: { type: Object, required: true },
  columnEntries: { type: Object, required: true },
})
const connectedPlayers = computed(() => props.inspection.players.filter(player => props.mappings[player.source_key]))
const notImportingPlayers = computed(() => props.inspection.players.filter(player => !props.mappings[player.source_key]))
const importingEvents = computed(() => connectedPlayers.value.reduce((total, player) => total + player.row_count, 0))
const ignoredEvents = computed(() => notImportingPlayers.value.reduce((total, player) => total + player.row_count, 0))
const connectedColumns = computed(() => Object.values(props.columnEntries).filter(entry => entry.action === 'map' && entry.baseball_concept_id))
const ignoredColumns = computed(() => Object.values(props.columnEntries).filter(entry => entry.action !== 'map' || !entry.baseball_concept_id))
</script>

<template>
  <div class="review-grid">
    <div><span>Platform</span><strong>TrackMan</strong></div>
    <div><span>File</span><strong>{{ inspection.file.name }}</strong></div>
    <div><span>Team / destination</span><strong>{{ teamName }} · {{ destination }}</strong></div>
    <div><span>Format detected</span><strong>{{ inspection.detected_format.data_type }}</strong></div>
    <div><span>Session</span><strong>{{ inspection.session.primary_date || 'Not detected' }}</strong><small>{{ inspection.session.facility || 'Facility not detected' }}</small></div>
    <div><span>Total / usable / invalid</span><strong>{{ inspection.counts.total_rows }} / {{ inspection.counts.usable_rows }} / {{ inspection.counts.invalid_rows }}</strong></div>
    <div><span>Players found</span><strong>{{ inspection.counts.players_found }}</strong></div>
    <div><span>Connected / Not Importing</span><strong>{{ connectedPlayers.length }} / {{ notImportingPlayers.length }}</strong></div>
    <div><span>Connected / Not Importing columns</span><strong>{{ connectedColumns.length }} / {{ ignoredColumns.length }}</strong></div>
  </div>
  <section class="import-summary">
    <div><span>Total events</span><strong>{{ importingEvents + ignoredEvents }}</strong></div><div><span>Importing</span><strong>{{ importingEvents }}</strong></div><div><span>Ignored</span><strong>{{ ignoredEvents }}</strong></div>
    <article><h3>Connected players</h3><p v-for="player in connectedPlayers" :key="player.source_key">✓ {{ player.source_name }} — {{ player.row_count }}</p><p v-if="!connectedPlayers.length">No players connected.</p></article>
    <article><h3>Not Importing</h3><p v-for="player in notImportingPlayers" :key="player.source_key">{{ player.source_name }} — {{ player.row_count }}</p><p v-if="!notImportingPlayers.length">All source players are connected.</p></article>
  </section>
  <section class="metrics"><span>Metrics detected</span><p>{{ inspection.metrics_detected.join(', ') || 'None' }}</p></section>
  <section v-if="inspection.warnings.length" class="warnings"><strong>Warnings</strong><p v-for="warning in inspection.warnings" :key="warning">{{ warning }}</p></section>
  <section class="samples"><h3>Normalized sample records</h3><pre v-for="(row,index) in inspection.sample_rows" :key="index">{{ JSON.stringify(row, null, 2) }}</pre></section>
  <div class="notice"><strong>Inspection only.</strong> No FMTRX session or statistics will be created.</div>
</template>

<style scoped>
.review-grid{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid rgba(255,255,255,.1);border-radius:14px;overflow:hidden}.review-grid>div{display:flex;flex-direction:column;gap:5px;padding:16px;border:1px solid rgba(255,255,255,.06)}span{color:#94a3b8;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.1em}strong{color:#fff}.review-grid small,p{color:#94a3b8}.metrics,.warnings,.samples,.notice{margin-top:14px;padding:15px;border:1px solid rgba(255,255,255,.1);border-radius:12px;background:rgba(5,12,29,.45)}.warnings{border-color:rgba(255,190,64,.25)}.samples{max-height:420px;overflow:auto}.samples h3{color:#fff;margin-bottom:10px}.samples pre{overflow:auto;margin:8px 0;padding:10px;border-radius:8px;background:#07101f;color:#b8c7db;font-size:10px}.notice{color:#b8c7db}.notice strong{color:#64e6b4}@media(max-width:650px){.review-grid{grid-template-columns:1fr}}
</style>
