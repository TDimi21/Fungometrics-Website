<script setup>
defineProps({
  platform: { type: Object, required: true },
  file: { type: Object, required: true },
  teamName: { type: String, required: true },
  sessionType: { type: String, required: true },
})
</script>

<template>
  <div class="summary">
    <div><span>Platform</span><strong>{{ platform.name }}</strong></div>
    <div><span>File</span><strong>{{ file.name }}</strong><small>{{ (file.size / 1024 / 1024).toFixed(2) }} MB</small></div>
    <div><span>Rows found</span><strong>Not analyzed yet</strong></div>
    <div><span>Players found</span><strong>Not analyzed yet</strong></div>
    <div><span>Import status</span><strong>Ready for future analysis</strong></div>
    <div><span>Destination team</span><strong>{{ teamName }}</strong></div>
    <div><span>Session type</span><strong>{{ sessionType }}</strong></div>
  </div>
  <div class="phase-notice">
    <strong>Preview only</strong>
    <span>Phase 1 does not upload this file or write data into FMTRX.</span>
  </div>
</template>

<style scoped>
.summary { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); overflow:hidden; border:1px solid rgba(255,255,255,.1); border-radius:16px; background:rgba(5,12,29,.5); }
.summary > div { display:flex; min-height:95px; flex-direction:column; justify-content:center; gap:5px; padding:18px 22px; border-right:1px solid rgba(255,255,255,.08); border-bottom:1px solid rgba(255,255,255,.08); }
.summary span { color:rgba(148,163,184,.78); font-size:10px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; }
.summary strong { color:#fff; font-size:16px; overflow-wrap:anywhere; }
.summary small { color:rgba(226,232,240,.55); font-size:11px; }
.phase-notice { display:flex; gap:10px; margin-top:16px; padding:14px 16px; border:1px solid rgba(255,190,64,.24); border-radius:12px; background:rgba(255,190,64,.07); color:rgba(255,235,190,.76); font-size:12px; }
.phase-notice strong { color:#ffc45c; }
@media (max-width:650px) { .summary { grid-template-columns:1fr; } .phase-notice { flex-direction:column; } }
</style>
