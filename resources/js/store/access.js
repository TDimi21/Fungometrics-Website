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

  const entitlements = computed(() => summary.value.entitlements || [])
  const canAccess = (key) => loaded.value && entitlements.value.includes(key)

  const clear = () => {
    summary.value = emptySummary()
    loaded.value = false
    loading.value = false
    error.value = null
    teamId.value = null
  }

  const refresh = async ({ team_id = null } = {}) => {
    const token = getAuthToken()
    if (!token) {
      clear()
      return summary.value
    }

    loading.value = true
    error.value = null

    try {
      const apiBaseUrl = import.meta.env.VITE_API_ENDPOINT || import.meta.env.API_ENDPOINT || ''
      const response = await axios.get(`${apiBaseUrl}me/access`, {
        params: team_id ? { team_id } : {},
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      })

      summary.value = response?.data?.data || emptySummary()
      teamId.value = team_id
      loaded.value = true
      return summary.value
    } catch (requestError) {
      // Fail closed. Stale paid access must not survive a failed refresh.
      summary.value = emptySummary()
      teamId.value = team_id
      loaded.value = true
      error.value = requestError
      throw requestError
    } finally {
      loading.value = false
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
    clear,
  }
})
