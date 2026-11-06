<script setup>
import {computed, ref} from 'vue'
import {useRoute, useRouter} from 'vue-router'
import {useAccessStore} from '@/store/access.js'
import {useAuthStore} from '@/store/auth.js'
import {useUserStore} from '@/store/user.js'
import {useTeamStore} from '@/store/team.js'
import {apiAuthorizationFailed} from '@/utils/apiTransport.js'
import {discardLegacyAuthToken} from '@/utils/authToken.js'
const route = useRoute(), router = useRouter(), access = useAccessStore(), teams = useTeamStore()
const busy = ref(false)
const denied = computed(() => route.query.access_denied)
const failed = computed(() => access.error || denied.value === 'access_verification_failed')
const message = computed(() => apiAuthorizationFailed.value
  ? 'We could not authorize a data request. Your saved records have not been removed. Retry, or sign in again.'
  : teams.preferenceError ? teams.preferenceError
  : failed.value ? 'Could not verify access. Please retry; this does not mean your plan lacks permission.'
  : denied.value ? `Your current account or team does not have access to ${({'planner_create':'planners','view_advanced_stats':'advanced statistics','view_team_stats':'team statistics','personal_stats':'personal statistics'})[denied.value] || 'this feature'}. Contact your team administrator if this is unexpected.` : '')
async function retry() {
  busy.value = true
  try {
    await teams.initializePreferences()
    await access.refresh({team_id: teams.active_team_id})
    if (denied.value === 'access_verification_failed' || access.canAccess(denied.value)) {
      const query = {...route.query}; delete query.access_denied
      await router.replace({path: route.path, query})
    }
  } catch { /* Keep the failure visible. */ }
  finally {busy.value = false}
}
function signIn() {
  const player = useUserStore().userData?.type === 'player'
  discardLegacyAuthToken(); useAuthStore().isLogged.status = false; access.clear()
  router.push(player ? '/login/player' : '/login/coach')
}
</script>
<template>
  <section v-if="message" role="alert" class="rounded-xl border border-amber-400/50 bg-slate-900 p-4 text-white mb-4">
    <p>{{ message }}</p>
    <button class="underline mr-4" :disabled="busy" @click="retry">{{ busy ? 'Checking…' : 'Retry access check' }}</button>
    <button v-if="apiAuthorizationFailed" class="underline" @click="signIn">Sign in again</button>
  </section>
</template>
