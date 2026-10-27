<script setup>
import { onMounted, ref } from 'vue'
import DashCard from '@/features/shared/components/DashCard.vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const props = defineProps({ activeTeamId: { type: String, default: null } })
const emit = defineEmits(['select-team'])
const { axiosGet, axiosDelete } = useAxiosAuth()
const teams = ref([]), loading = ref(true), busy = ref(false), selected = ref(null), error = ref(''), message = ref('')
async function load() {
  loading.value = true; error.value = ''
  try { teams.value = (await axiosGet('player/me/teams')).data.data }
  catch { error.value = 'Unable to load your teams. Please try again.' }
  finally { loading.value = false }
}
async function leave() {
  if (!selected.value || busy.value) return
  busy.value = true; error.value = ''; message.value = ''
  const team = selected.value
  try {
    await axiosDelete('player/me/teams/', team.id)
    teams.value = teams.value.filter(item => item.id !== team.id)
    selected.value = null
    if (props.activeTeamId === team.id) emit('select-team', null)
    message.value = `You left ${team.name}. Your profile and training history are preserved.`
  } catch { error.value = 'Unable to leave this team. Refresh the list and try again.' }
  finally { busy.value = false }
}
onMounted(load)
</script>
<template>
  <DashCard>
    <h2 class="text-lg font-black">My Teams</h2>
    <p class="my-2 text-sm">Choose a team to see your data collected by that team.</p>
    <button type="button" class="mb-3 w-full rounded-lg border px-4 py-3 text-left font-bold"
      :class="!activeTeamId ? 'border-accent-2 bg-accent-2/20' : 'border-white/20'"
      :aria-pressed="!activeTeamId" @click="emit('select-team', null)">All My Data
      <span class="mt-1 block text-xs font-normal">Individual sessions and data from all your teams combined.</span>
    </button>
    <p v-if="loading" role="status">Loading teams…</p>
    <p v-if="error" role="alert">{{ error }} <button type="button" class="underline" :disabled="busy" @click="load">Refresh teams</button></p>
    <p v-if="message" role="status" class="my-2 text-sm">{{ message }}</p>
    <p v-if="!loading && !error && !teams.length" class="text-sm">You are not connected to any teams.</p>
    <ul class="space-y-3">
      <li v-for="team in teams" :key="team.id" class="rounded-lg border border-current/20 p-3">
        <div class="flex items-center justify-between gap-3">
          <button type="button" class="min-h-[44px] flex-1 rounded-lg px-2 py-3 text-left font-bold break-words"
            :class="activeTeamId === team.id ? 'bg-accent-2/20 text-white ring-1 ring-accent-2' : 'hover:bg-white/10'"
            :aria-pressed="activeTeamId === team.id" @click="emit('select-team', team)">{{ team.name }}
            <span class="block text-xs font-normal">View my team data →</span>
          </button>
          <button type="button" class="shrink-0 underline min-h-[44px] px-2" :disabled="busy" @click="selected = team; message = ''">Leave team</button>
        </div>
        <div v-if="selected?.id === team.id" class="mt-2 text-sm">
          <p>Leave {{ team.name }}? You will no longer be on this team's active roster. Your player profile and recorded training history will remain.</p>
          <div class="mt-3 flex gap-3">
            <button type="button" class="rounded-lg bg-red-700 px-3 py-3 text-white" :disabled="busy" @click="leave">{{ busy ? 'Leaving…' : 'Confirm leave' }}</button>
            <button type="button" class="px-3 py-3 underline" :disabled="busy" @click="selected = null">Cancel</button>
          </div>
        </div>
      </li>
    </ul>
  </DashCard>
</template>
