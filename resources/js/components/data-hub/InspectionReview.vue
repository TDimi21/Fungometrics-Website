<script setup>
defineProps({
  inspection: { type: Object, required: true },
  teamName: { type: String, required: true },
  destination: { type: String, required: true },
  mappings: { type: Object, required: true },
})
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
    <div><span>Mapped / skipped</span><strong>{{ Object.values(mappings).filter(v => v && v !== '__skip__').length }} / {{ Object.values(mappings).filter(v => v === '__skip__').length }}</strong></div>
  </div>
  <section class="metrics"><span>Metrics detected</span><p>{{ inspection.metrics_detected.join(', ') || 'None' }}</p></section>
  <section v-if="inspection.warnings.length" class="warnings"><strong>Warnings</strong><p v-for="warning in inspection.warnings" :key="warning">{{ warning }}</p></section>
  <section class="samples"><h3>Normalized sample records</h3><pre v-for="(row,index) in inspection.sample_rows" :key="index">{{ JSON.stringify(row, null, 2) }}</pre></section>
  <div class="notice"><strong>Inspection only.</strong> No FMTRX session or statistics will be created.</div>
</template>

<style scoped>
.review-grid{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid rgba(255,255,255,.1);border-radius:14px;overflow:hidden}.review-grid>div{display:flex;flex-direction:column;gap:5px;padding:16px;border:1px solid rgba(255,255,255,.06)}span{color:#94a3b8;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.1em}strong{color:#fff}.review-grid small,p{color:#94a3b8}.metrics,.warnings,.samples,.notice{margin-top:14px;padding:15px;border:1px solid rgba(255,255,255,.1);border-radius:12px;background:rgba(5,12,29,.45)}.warnings{border-color:rgba(255,190,64,.25)}.samples{max-height:420px;overflow:auto}.samples h3{color:#fff;margin-bottom:10px}.samples pre{overflow:auto;margin:8px 0;padding:10px;border-radius:8px;background:#07101f;color:#b8c7db;font-size:10px}.notice{color:#b8c7db}.notice strong{color:#64e6b4}@media(max-width:650px){.review-grid{grid-template-columns:1fr}}
</style>
