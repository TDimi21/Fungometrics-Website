// Tiny per-section fetch-state helper: each dashboard section gets its own
// { loading, error } so one failed endpoint only degrades its own panel, and
// its Retry button re-runs just that section's fetch.

import { reactive } from 'vue'

export const SESSION_EXPIRED_MESSAGE = 'Your session has expired. Please log in again.'

export const createSectionState = () => reactive({
  loading: false,
  error: '',
  unauthorized: false,
})

export const isAuthError = (error) => [401, 403].includes(Number(error?.response?.status))

// Runs `fetcher` for a section: manages loading/error flags and returns the
// fetched value, or null on failure (the error is captured on the state).
export const runSection = async (state, fetcher, errorMessage) => {
  state.loading = true
  state.error = ''
  state.unauthorized = false
  try {
    return await fetcher()
  } catch (error) {
    state.unauthorized = isAuthError(error)
    state.error = state.unauthorized ? SESSION_EXPIRED_MESSAGE : errorMessage
    return null
  } finally {
    state.loading = false
  }
}
