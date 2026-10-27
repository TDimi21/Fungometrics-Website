<script setup>
import PlayerPolicyCheckbox from './PlayerPolicyCheckbox.vue'
import { ref, watch, computed } from 'vue'
import { useAxiosAuth } from '@/composables/axios-auth'
const props = defineProps({ model: { type: Object, required: true }, teamId: String })
const { axiosGet } = useAxiosAuth()
const enabled = ref(false)
const deliveryEnabled = ref(false)
const eligible = computed(() => {
  if (!props.model.email) return false
  if (!props.model.born) return true
  if (!/^\d{4}-\d{2}-\d{2}$/.test(props.model.born)) return false
  const today = new Date()
  const cutoff = `${today.getFullYear() - 13}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`
  return props.model.born <= cutoff
})
watch([eligible, enabled, deliveryEnabled], () => { props.model.sendInvitation = !!(eligible.value && enabled.value && deliveryEnabled.value) })
watch(() => props.teamId, async id => {
  enabled.value = false; deliveryEnabled.value = false; props.model.sendInvitation = false
  if (!id) return
  try {
    const { data } = await axiosGet(`coach/teams/${id}/invitations`)
    if (props.teamId !== id) return
    enabled.value = !!data.enabled
    deliveryEnabled.value = !!data.delivery_enabled
  } catch { /* Saving the player is always available. */ }
}, { immediate: true })
</script>
<template>
  <div class="onboarding-fields">
    <label>Date of birth (optional)<input v-model="model.born" type="date" /></label>
    <PlayerPolicyCheckbox :model="model" />
    <label>Position<select v-model="model.position"><option value="">Choose position (optional)</option><option v-for="position in ['P','C','1B','2B','3B','SS','LF','CF','RF','DH','OF','IF']" :key="position">{{ position }}</option></select></label>
    <label><input v-model="model.sendInvitation" type="checkbox" :disabled="!enabled || !eligible" /> Send email invitation after saving</label>
    <p>The player is saved even without contact details. Invitations require an email. Players confirm their birth date and accept the policies before creating a login; self-service login is available at age 13 or older. Delivery follows your team's communications settings.</p>
    <p v-if="!enabled">Invitations are awaiting activation. You can invite from Team Communications later.</p>
  </div>
</template>
<style scoped>
.onboarding-fields{display:grid;gap:12px;grid-column:1/-1}.onboarding-fields label{display:block}.onboarding-fields input:not([type=checkbox]),.onboarding-fields select{display:block;width:100%;padding:10px;border:1px solid #64748b;border-radius:8px;background:#fff;color:#172033}.onboarding-fields p{font-size:13px;line-height:1.5}
</style>
