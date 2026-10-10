<script setup>
const props = defineProps({step: Number, buckets: Array, included: Array});
const emit = defineEmits(['update:step', 'use', 'preview']);
</script>
<template>
  <section class="workout-guide" aria-label="Workout builder steps">
    <div class="guide-row">
      <label>Jump to step
        <select :value="step" @change="emit('update:step', Number($event.target.value))">
          <option :value="0">1. Workout details</option>
          <option v-for="(b, i) in buckets" :key="b.type" :value="i + 1">{{ i + 2 }}. {{ b.title }}{{ included.includes(b.type) ? ' ✓' : '' }}</option>
          <option :value="buckets.length + 1">{{ buckets.length + 2 }}. Review & finish</option>
        </select>
      </label>
      <button type="button" @click="emit('preview')">Preview workout</button>
    </div>
    <progress :value="step + 1" :max="buckets.length + 2" aria-label="Builder progress"></progress>
    <h2>{{ step === 0 ? 'Workout details' : step > buckets.length ? 'Review & finish' : buckets[step - 1].title }}</h2>
    <template v-if="step > 0 && step <= buckets.length">
      <p v-if="buckets[step - 1].type === 'daily_readiness'">Daily Readiness is included automatically in every workout. Players complete their check-in before training.</p><p v-else>{{ buckets[step - 1].hint }}. Include this section if it fits your workout, or skip ahead.</p>
      <button v-if="!included.includes(buckets[step - 1].type)" type="button" class="primary" @click="emit('use', buckets[step - 1])">Use this section</button>
    </template>
    <div class="guide-row">
      <button type="button" :disabled="step === 0" @click="emit('update:step', step - 1)">← Back</button>
      <button v-if="step < buckets.length + 1" type="button" @click="emit('update:step', step + 1)">{{ step > 0 && !included.includes(buckets[step - 1].type) ? 'Skip section →' : 'Next →' }}</button>
    </div>
  </section>
</template>
<style scoped>
.workout-guide{border:1px solid #344b68;border-radius:14px;background:#101d30;padding:20px;margin-bottom:20px;color:#edf3ff}.guide-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.workout-guide label{font-size:13px;color:#b9c9df}.workout-guide select{display:block;margin-top:6px;max-width:100%;background:#17263d;color:#fff;padding:10px;border:1px solid #49617e;border-radius:8px}.workout-guide button{min-height:44px;padding:10px 16px;background:#21334e;border:1px solid #49617e;border-radius:8px;color:#fff;font-weight:700}.workout-guide button:disabled{opacity:.4}.workout-guide h2{font-size:23px;font-weight:800;margin:12px 0}.workout-guide p{color:#b9c9df;margin-bottom:14px}.workout-guide progress{width:100%;height:6px;margin-top:18px;accent-color:#ef334b}.workout-guide .primary{background:#cc2434;margin-bottom:16px}
</style>
