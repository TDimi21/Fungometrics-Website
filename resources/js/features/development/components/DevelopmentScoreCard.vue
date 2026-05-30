<script setup>
const props = defineProps({
  title: { type: String, default: 'Score' },
  score: { type: Number, default: 0 },
  subtitle: { type: String, default: '' },
  clickable: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

const colorClass = (s) => {
  if (s >= 85) return 'text-emerald-300 border-emerald-400/30'
  if (s >= 70) return 'text-yellow-300 border-yellow-400/30'
  return 'text-red-300 border-red-400/30'
}
</script>

<template>
  <button
    type="button"
    class="w-full rounded-xl border bg-slate-900/70 p-4 text-left transition"
    :class="[colorClass(score), clickable ? 'cursor-pointer hover:brightness-110' : 'cursor-default']"
    @click="clickable && emit('select')"
  >
    <p class="text-xs uppercase tracking-wider text-white/60">{{ title }}</p>
    <p class="mt-2 text-3xl font-bold">{{ score }}</p>
    <p v-if="subtitle" class="mt-1 text-xs text-white/70">{{ subtitle }}</p>
    <p class="mt-1 text-[11px] text-white/45">
      0–100 scale · higher is better
      <span v-if="clickable"> · click for details</span>
    </p>
  </button>
</template>
