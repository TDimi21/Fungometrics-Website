<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <section class="relative overflow-hidden rounded-2xl border border-white/10 bg-surface-raised/75 p-4 text-white">
    <div v-if="title || subtitle || $slots.badge" class="mb-3 flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 v-if="title" class="text-lg font-black tracking-wide">{{ title }}</h2>
        <p v-if="subtitle" class="mt-1 text-xs text-text-muted">{{ subtitle }}</p>
      </div>
      <slot name="badge" />
    </div>
    <slot />
  </section>
</template>
