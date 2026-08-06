<script setup>
defineProps({
  state: { type: String, required: true }, // 'loading' | 'error' | 'empty'
  message: { type: String, default: '' },
  retryable: { type: Boolean, default: true },
})

defineEmits(['retry'])
</script>

<template>
  <div
    v-if="state === 'loading'"
    class="animate-pulse rounded-xl border border-white/10 bg-white/5 px-4 py-6 text-center text-sm text-text-muted"
  >
    {{ message || 'Loading…' }}
  </div>

  <div
    v-else-if="state === 'error'"
    class="rounded-xl border border-accent-2/40 bg-accent-2/10 px-4 py-4 text-center text-sm text-white"
    data-testid="state-panel-error"
  >
    <p>{{ message || 'Something went wrong.' }}</p>
    <button
      v-if="retryable"
      type="button"
      class="mt-3 rounded-lg border border-accent-2/60 bg-accent-2/20 px-4 py-2 text-xs font-black uppercase tracking-wider text-white"
      @click="$emit('retry')"
    >
      Retry
    </button>
  </div>

  <div
    v-else-if="state === 'empty'"
    class="rounded-xl border border-white/10 bg-white/5 px-4 py-6 text-center text-sm text-text-muted"
  >
    <slot>{{ message || 'No data yet.' }}</slot>
  </div>
</template>
