<script setup>
defineProps({ report: { type: Object, required: true }, eager: Boolean, compact: Boolean });
defineEmits(['expand']);
</script>

<template>
  <figure class="capture" :class="{ 'capture-compact': compact }">
    <figcaption class="capture-bar"><span>{{ report.label }} <span class="capture-kind">/ Session report</span></span><span class="capture-tag">{{ report.src ? 'Test data' : 'Preview pending' }}</span></figcaption>
    <button v-if="report.src" type="button" class="capture-open" :aria-label="`View ${report.label.toLowerCase()} screenshot full screen`" @click="$emit('expand', report, $event)">
      <img :src="report.src" :alt="report.alt" :width="report.width" :height="report.height" :loading="eager ? 'eager' : 'lazy'" :fetchpriority="eager ? 'high' : 'auto'" decoding="async" />
      <span class="capture-expand">View full screen ↗</span>
    </button>
    <div v-else class="capture-empty">
      <span class="capture-symbol" aria-hidden="true">↗</span>
      <strong>{{ report.label }}<br />in focus.</strong>
      <p>Real platform screenshot<br />coming to this preview.</p>
    </div>
  </figure>
</template>

<style scoped>
.capture { margin: 0; overflow: hidden; border: 1px solid #344054; border-radius: 12px; background: #101c2d; box-shadow: 0 22px 65px #0004; }
.capture-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; color: #f5f1e8; background: #142236; font-size: 12px; font-weight: 700; }
.capture-kind { color: #9cabbc; font-weight: 400; }
.capture-tag { border: 1px solid #566174; padding: 4px 7px; border-radius: 4px; font-size: 9px; text-transform: uppercase; letter-spacing: .08em; white-space: nowrap; }
.capture-open { position: relative; display: block; width: 100%; background: #0a1220; cursor: zoom-in; }
.capture-open img { width: 100%; height: auto; display: block; }
.capture-expand { position: absolute; bottom: 12px; right: 12px; background: #061020ed; padding: 9px 12px; border: 1px solid #64748b; border-radius: 5px; color: white; font-size: 12px; }
.capture-open:focus-visible { outline: 3px solid #ffc970; outline-offset: -4px; }
.capture-empty { aspect-ratio: 16 / 10; padding: clamp(24px, 5vw, 60px); display: flex; flex-direction: column; justify-content: center; align-items: flex-start; background: radial-gradient(ellipse at 100% 0%, #233b55, transparent 75%), #0c1726; }
.capture-symbol { color: #ff344f; font-size: 34px; line-height: 1; margin-bottom: 20px; }
.capture-empty strong { font-family: Impact, 'Arial Narrow', sans-serif; text-transform: uppercase; font-weight: 400; font-size: clamp(32px, 4vw, 62px); line-height: 1; color: #f5f1e8; }
.capture-empty p { margin: 16px 0 0; color: #a9b7c9; font-size: 13px; line-height: 1.6; }
.capture-compact .capture-empty { padding: 22px; }
.capture-compact .capture-empty strong { font-size: clamp(26px, 2.6vw, 36px); }
.capture-compact .capture-symbol { display: none; }
.capture-compact .capture-kind { display: none; }
.capture-compact .capture-bar { padding: 12px; }
@media (max-width: 600px) { .capture-kind { display: none; } .capture-bar { padding: 12px; } }
</style>
