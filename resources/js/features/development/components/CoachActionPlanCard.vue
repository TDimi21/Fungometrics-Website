<script setup>
defineProps({
  recommendations: { type: Array, default: () => [] },
  coachNotes: { type: String, default: '' },
})
</script>

<template>
  <div class="rounded-xl border border-white/10 bg-slate-900/70 p-4">
    <h3 class="text-lg font-semibold text-white">Coach Action Plan</h3>
    <p class="mt-1 text-xs text-slate-400">Simple execution plan for the next 1–2 weeks based on current data.</p>

    <div v-if="recommendations.length" class="mt-3 space-y-3 text-sm text-slate-300">
      <div v-for="item in recommendations.slice(0, 3)" :key="item.id || item.title" class="rounded-lg border border-white/10 bg-white/5 p-3">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
          <p class="text-[10px] font-black uppercase tracking-widest"
            :class="item.priority === 'high' ? 'text-red-300' : item.priority === 'medium' ? 'text-yellow-300' : 'text-cyan-300'">
            {{ item.priority || 'medium' }} priority
          </p>
          <p class="text-[10px] font-black uppercase tracking-widest text-white/35">
            Confidence: {{ item.confidence || 'low' }}
          </p>
        </div>
        <p class="font-black text-white">{{ item.title }}</p>
        <p class="mt-2 text-xs leading-relaxed text-slate-400"><strong class="text-slate-200">Why:</strong> {{ item.why || item.recommendation || 'More session data will sharpen this recommendation.' }}</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-400"><strong class="text-slate-200">Action:</strong> {{ item.action || item.recommendation || 'Score the next relevant session.' }}</p>
        <p v-if="item.expected_gain" class="mt-2 text-xs font-black text-emerald-300">Expected Gain: {{ item.expected_gain }}</p>
      </div>
      <p class="text-xs text-slate-500"><strong>Coach notes:</strong> {{ coachNotes || 'No coach notes yet.' }}</p>
    </div>

    <div v-else class="mt-3 rounded-lg border border-white/10 p-3 text-sm text-slate-300">
      Score a bullpen, long toss, exit velocity, or assessment session to unlock action plans.
    </div>
  </div>
</template>
