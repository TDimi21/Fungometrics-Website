<script setup>
import { computed, onMounted } from 'vue'
import { useAccessStore } from '@/store/access.js'

const props = defineProps({
  entitlement: { type: String, required: true },
  label: { type: String, default: 'This feature' },
})

const access = useAccessStore()
const allowed = computed(() => access.canAccess(props.entitlement))

onMounted(async () => {
  if (!access.loaded && !access.loading) {
    try {
      await access.refresh()
    } catch (_) {
      // The locked state below is the intentional fail-closed result.
    }
  }
})
</script>

<template>
  <div v-if="access.loading && !access.loaded" class="p-6 text-center text-white/70">
    Checking access…
  </div>
  <slot v-else-if="allowed" />
  <slot v-else name="locked">
    <section class="mx-auto max-w-xl rounded-xl border border-white/10 bg-white/5 p-6 text-center text-white">
      <h2 class="text-xl font-bold">Upgrade required</h2>
      <p class="mt-2 text-white/70">{{ label }} is not included in your current access.</p>
      <RouterLink class="mt-4 inline-block font-bold text-fungo-red" to="/purchase">
        View plans
      </RouterLink>
    </section>
  </slot>
</template>
