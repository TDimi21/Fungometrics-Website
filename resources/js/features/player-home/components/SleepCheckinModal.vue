<script setup>
import { ref, watch } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
import { todayDateKey } from '../lib/playerHomeAdapter.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  playerId: { type: [String, Number], default: null },
})

const emit = defineEmits(['close', 'saved'])

const { axiosPost } = useAxiosAuth()

const sleepHours = ref('')
const sleepQuality = ref('')
const saving = ref(false)
const errorMessage = ref('')

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    sleepHours.value = ''
    sleepQuality.value = ''
    errorMessage.value = ''
  }
})

const save = async () => {
  const hours = Number(sleepHours.value)
  const quality = Number(sleepQuality.value)
  if (!props.playerId) return
  if (!Number.isFinite(hours) || hours <= 0 || hours > 24) {
    errorMessage.value = 'Enter sleep hours between 0 and 24.'
    return
  }
  if (!Number.isInteger(quality) || quality < 1 || quality > 5) {
    errorMessage.value = 'Sleep quality must be a whole number from 1 to 5.'
    return
  }
  saving.value = true
  errorMessage.value = ''
  try {
    const res = await axiosPost('player/fitness', {
      user_id: props.playerId,
      fitness_date: todayDateKey(),
      sleep_hours: hours,
      sleep_quality_1_to_5: quality,
    })
    emit('saved', res?.data?.data || null)
    emit('close')
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || 'Could not save sleep check-in.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 px-4"
  >
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-surface-raised p-5 text-white shadow-2xl">
      <h2 class="text-2xl font-black">Daily Recovery Check-In</h2>
      <p class="mt-2 text-sm font-semibold leading-6 text-white/65">
        Log today's sleep so your player metrics stay current.
      </p>

      <p v-if="errorMessage" class="mt-3 rounded-lg border border-accent-2/40 bg-accent-2/10 px-3 py-2 text-xs text-red-100">
        {{ errorMessage }}
      </p>

      <label class="mt-5 block text-xs font-black uppercase tracking-widest text-white/60">
        Hours slept
      </label>
      <input
        v-model="sleepHours"
        type="number"
        min="0"
        max="24"
        step="0.1"
        class="mt-2 h-12 w-full rounded-xl border border-white/15 bg-white/10 px-3 text-lg font-black text-white outline-none focus:border-accent-2"
        placeholder="8"
      />

      <label class="mt-4 block text-xs font-black uppercase tracking-widest text-white/60">
        Sleep quality (1-5)
      </label>
      <input
        v-model="sleepQuality"
        type="number"
        min="1"
        max="5"
        step="1"
        class="mt-2 h-12 w-full rounded-xl border border-white/15 bg-white/10 px-3 text-lg font-black text-white outline-none focus:border-accent-2"
        placeholder="4"
      />

      <div class="mt-5 flex justify-end gap-3">
        <button
          class="h-11 rounded-xl border border-white/15 bg-white/10 px-5 text-sm font-black text-white/70"
          :disabled="saving"
          @click="$emit('close')"
        >
          Later
        </button>
        <button
          class="h-11 rounded-xl bg-accent-2 px-6 text-sm font-black text-white disabled:opacity-60"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>
  </div>
</template>
