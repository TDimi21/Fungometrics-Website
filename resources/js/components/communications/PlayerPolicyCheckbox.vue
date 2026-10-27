<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
const props = defineProps({model: {type:Object, required:true}})
const error = ref('')
onMounted(async () => {
  props.model.policy_accepted = false
  try {
    const api = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
    props.model.policy_info = (await axios.get(api + 'player/claim-policies')).data
  } catch { error.value = 'Could not load the agreement. Reopen this form when connected.' }
})
</script>
<template>
  <div class="policy-field">
    <label><input v-model="model.policy_accepted" type="checkbox" :disabled="!model.policy_info" required>{{ model.policy_info?.coach_acknowledgment || 'Loading player profile agreement…' }}</label>
    <p v-if="model.policy_info"><a :href="model.policy_info.terms_url" target="_blank" rel="noopener noreferrer">Terms of Use</a> · <a :href="model.policy_info.privacy_url" target="_blank" rel="noopener noreferrer">Privacy Policy</a></p>
    <p v-if="error" role="alert">{{ error }}</p>
    <p>Birth date is optional for the coach. The player supplies it when setting up their login. This acknowledgment does not activate an under-13 account.</p>
  </div>
</template>
<style scoped>
.policy-field{grid-column:1/-1;font-size:14px;line-height:1.6}.policy-field label{display:block}.policy-field input{margin-right:10px}.policy-field a{text-decoration:underline}.policy-field p{margin:8px 0}
</style>
