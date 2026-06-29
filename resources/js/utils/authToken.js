export const getAuthToken = () => {
  try {
    localStorage.removeItem('auth')
  } catch (_) {}

  try {
    const authStore = sessionStorage.getItem('auth')
    if (!authStore) return ''

    const parsed = JSON.parse(authStore)
    return parsed?.token || parsed?.state?.token || ''
  } catch (_) {
    return ''
  }
}
