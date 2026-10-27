<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
const props = defineProps({ token: { type: String, required: true } })
const emit = defineEmits(['ready', 'restart'])
const api = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
const born = ref(''), accepted = ref(false)
const policies = ref(null), phase = ref('age'), busy = ref(false), error = ref('')
const now = new Date()
const today = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`
const under13 = computed(() => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(born.value)) return false
  const cutoff = new Date(); cutoff.setFullYear(cutoff.getFullYear() - 13)
  return new Date(born.value + 'T00:00:00') > cutoff
})
onMounted(async () => {
  try { policies.value = (await axios.get(api + 'player/claim-policies')).data }
  catch { error.value = 'Could not load the policies. Please restart setup when connected.' }
})
async function submit() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try {
    const { data } = await axios.post(api + 'player/claim-setup', {
      born_date: born.value, policy_accepted: accepted.value,
      acknowledgment_version: policies.value?.acknowledgment_version,
      terms_version: policies.value?.terms_version, privacy_version: policies.value?.privacy_version,
    }, { headers: { Authorization: `Bearer ${props.token}` } })
    if (data.next_action === 'credentials') emit('ready')
    else phase.value = data.next_action
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'Unable to continue. Check your connection and try again.'
    if ([401, 419].includes(e.response?.status)) error.value = 'This setup session expired. Restart with your team code; your birth date is saved.'
  } finally { busy.value = false }
}
</script>
<template>
  <section class="claim-setup" aria-labelledby="setup-title">
    <h2 id="setup-title">Finish setting up your profile</h2>
    <p>Your coach does not need to enter your birth date. Your existing team and training history stay with this profile.</p>
    <form @submit.prevent="submit">
      <label for="claim-birth">Player’s date of birth</label>
      <input id="claim-birth" v-model="born" type="date" required min="1900-01-02" :max="today" :disabled="busy || phase !== 'age'">
      <template v-if="phase === 'age'">
        <label class="check"><input v-model="accepted" type="checkbox" :required="!under13"> {{ policies?.player_acknowledgment || 'I accept the Terms and acknowledge the Privacy Policy.' }}</label>
        <p><a :href="policies?.terms_url" target="_blank" rel="noopener noreferrer">Terms of Use</a> · <a :href="policies?.privacy_url" target="_blank" rel="noopener noreferrer">Privacy Policy</a></p>
      </template>
      <p v-if="phase === 'coach_managed'" role="status">Your profile is saved with your team. Players under 13 cannot create their own login yet. Your coach can continue managing your profile.</p>
      <p v-if="error" role="alert" class="error">{{ error }}</p>
      <button v-if="phase !== 'coach_managed'" type="submit" :disabled="busy || !policies">{{ busy ? 'Checking…' : 'Continue' }}</button>
      <button type="button" class="secondary" :disabled="busy" @click="emit('restart')">Restart with team code</button>
    </form>
  </section>
</template>
<style scoped>
.claim-setup{width:100%;max-width:36rem;background:#fff;color:#182338;padding:1.5rem;border-radius:16px;margin:1rem auto;line-height:1.6}.claim-setup h2{font-size:1.4rem;font-weight:750}.claim-setup p{margin:12px 0}.claim-setup label{display:block;font-weight:600;margin-top:16px}.claim-setup input:not([type=checkbox]){display:block;width:100%;border:1px solid #68778a;padding:12px;border-radius:8px;color:#182338;background:#fff}.claim-setup .check{font-weight:400}.check input{margin-right:8px}.claim-setup a{text-decoration:underline;color:#174e91}.claim-setup button{display:block;width:100%;padding:12px;margin-top:16px;background:#ad1230;color:white;border-radius:8px;font-weight:700}.claim-setup button.secondary{background:#e8edf4;color:#182338}.claim-setup button:disabled{opacity:.55}.error{color:#9f1239}
</style>
