import axios from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAuthToken } from '@/utils/authToken.js'

const emptySummary = () => ({
  plan: 'free',
  audience: null,
  status: 'unknown',
  source: null,
  provider: null,
  expires_at: null,
  team: null,
  entitlements: [],
  limits: {},
  usage: {},
  remaining: {},
})

/**
 * Runtime access authority for the web client.
 *
 * Plan names are display metadata only. Feature decisions must use the
 * entitlements returned by Laravel's /api/me/access endpoint.
 */
export const useAccessStore = defineStore('access', () => {
  const summary = ref(emptySummary())
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref(null)
  const teamId = ref(null)
  let requestSequence = 0
  let activeRequest = null

  const entitlements = computed(() => summary.value.entitlements || [])
  const canAccess = (key) => loaded.value && entitlements.value.includes(key)

  const clear = () => {
    requestSequence += 1
    activeRequest = null
    summary.value = emptySummary()
    loaded.value = false
    loading.value = false
    error.value = null
    teamId.value = null
  }

  const setTeamContext = (nextTeamId) => {
    const normalized = nextTeamId ? String(nextTeamId) : null
    if (teamId.value === normalized) return false

    requestSequence += 1
    activeRequest = null
    teamId.value = normalized
    summary.value = emptySummary()
    loaded.value = false
    loading.value = false
    error.value = null
    return true
  }

  const refresh = async ({ team_id = teamId.value } = {}) => {
    const token = getAuthToken()
    const context = team_id ? String(team_id) : null
    setTeamContext(context)

    // Router, application lifecycle, and page-level checks can all ask for the
    // same access snapshot during one navigation. Share that request instead
    // of sending duplicate /me/access calls that can trigger rate limiting.
    if (activeRequest?.context === context) {
      return activeRequest.promise
    }

    const sequence = ++requestSequence
    loading.value = true
    error.value = null

    const request = (async () => {
      const apiBaseUrl = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
      try {
        const response = await axios.get(`${apiBaseUrl}me/access`, {
          params: context ? { team_id: context } : {},
          withCredentials: true,
          headers: {
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            Accept: 'application/json',
          },
        })

        if (sequence !== requestSequence || teamId.value !== context) return summary.value
        summary.value = response?.data?.data || emptySummary()
        loaded.value = true
        return summary.value
      } catch (requestError) {
        // Fail closed. Stale paid access must not survive a failed refresh.
        if (sequence !== requestSequence || teamId.value !== context) return summary.value
        summary.value = emptySummary()
        loaded.value = true
        error.value = requestError
        throw requestError
      } finally {
        if (sequence === requestSequence) loading.value = false
      }
    })()

    activeRequest = { context, promise: request }
    try {
      return await request
    } finally {
      if (activeRequest?.promise === request) activeRequest = null
    }
  }

  return {
    summary,
    loaded,
    loading,
    error,
    teamId,
    entitlements,
    canAccess,
    refresh,
    setTeamContext,
    clear,
  }
})
