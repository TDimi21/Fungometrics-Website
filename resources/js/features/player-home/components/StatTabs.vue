<script setup>
defineProps({
  modelValue: { type: String, required: true },
  tabs: { type: Array, required: true },
})

defineEmits(['update:modelValue', 'toggle-workout'])
</script>

<template>
  <div class="sticky top-0 z-30 -mx-4 mb-4 border-b border-white/10 bg-surface-raised/95 px-4 pb-3 pt-1 backdrop-blur">
    <div class="mb-4 flex justify-center">
      <div class="relative flex w-full max-w-sm rounded-2xl border border-white/15 bg-surface-raised p-1.5 shadow-lg">
        <span
          class="absolute inset-y-1.5 left-1.5 w-[calc(50%-0.375rem)] rounded-xl bg-accent-2 shadow-md transition-transform duration-300 ease-out"
          :class="modelValue === 'workout' ? 'translate-x-full' : 'translate-x-0'"
        ></span>
        <button
          type="button"
          class="relative z-10 flex-1 rounded-xl py-3.5 text-base font-black uppercase tracking-wide transition-colors duration-200"
          :class="modelValue !== 'workout' ? 'text-white' : 'text-white/55'"
          @click="modelValue === 'workout' && $emit('toggle-workout')"
        >
          Stats
        </button>
        <button
          type="button"
          class="relative z-10 flex-1 rounded-xl py-3.5 text-base font-black uppercase tracking-wide transition-colors duration-200"
          :class="modelValue === 'workout' ? 'text-white' : 'text-white/55'"
          @click="modelValue !== 'workout' && $emit('toggle-workout')"
        >
          Workout
        </button>
      </div>
    </div>
    <div v-if="modelValue !== 'workout'" class="flex flex-wrap justify-center gap-2">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        class="rounded-full border px-4 py-2 text-sm font-black"
        :class="modelValue === tab.key ? 'border-accent-2 bg-accent-2 text-white' : 'border-white/20 bg-white/5 text-white/65'"
        @click="$emit('update:modelValue', tab.key)"
      >
        {{ tab.label }}
      </button>
    </div>
  </div>
</template>
