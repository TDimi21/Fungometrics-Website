<script setup>
import { computed } from 'vue'

const props = defineProps({ score: { type: Object, required: true } })
const width = computed(() => props.score.available ? Math.max(0, Math.min(100, Number(props.score.score))) : 0)
const tone = computed(() => !props.score.available ? 'neutral' : width.value >= 90 ? 'elite' : width.value >= 75 ? 'above' : width.value >= 50 ? 'average' : 'developing')
</script>
<template>
  <article class="score-card" :class="tone" :aria-label="`${score.label}: ${score.available ? `${score.score}, ${score.status}` : 'Needs Data'}`">
    <div class="score-head"><div><small>{{ score.label }}</small><p><strong>{{ score.available ? Math.round(score.score) : '—' }}</strong><span>{{ score.status }}</span></p></div></div>
    <div class="bar" :class="{ dashed: !score.available }"><span :style="{ width: `${width}%` }" /></div>
    <p>{{ score.summary }}</p>
    <ul><li><b>Improve:</b> {{ score.improve }}</li><li><b>Focus:</b> {{ score.focus }}</li></ul>
  </article>
</template>
<style scoped>
.score-card{--tone:#ef3340;width:100%;text-align:left;padding:13px 14px;background:#081827;border:1px solid #254154;border-radius:9px;color:#edf4f8}.score-head{display:flex;justify-content:space-between;align-items:center}.score-head small{font-size:9px;text-transform:uppercase;letter-spacing:.09em;color:#8aa0b0}.score-head p{display:flex;align-items:baseline;gap:9px}.score-head strong{font-size:29px;line-height:1;font-weight:900}.score-head span{text-transform:uppercase;color:var(--tone);font-size:9px;font-weight:900}.bar{height:4px;border-radius:9px;background:#283d4a;overflow:hidden;margin:7px 0}.bar span{display:block;height:100%;background:var(--tone)}.bar.dashed{background:repeating-linear-gradient(90deg,#3c4d58 0 7px,transparent 7px 11px)}.score-card>p{font-size:10px;color:#9badb9}.score-card ul{margin-top:5px;font-size:9px;color:#718898;line-height:1.45}.score-card li::before{content:'•';color:var(--tone);margin-right:5px}.score-card.elite{--tone:#ef3340}.score-card.above{--tone:#e66a2f}.score-card.average{--tone:#f2b84b}.score-card.developing{--tone:#47a5da}.score-card.neutral{--tone:#718391}
</style>
