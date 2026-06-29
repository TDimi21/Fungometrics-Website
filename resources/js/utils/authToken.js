const findToken = (value, depth = 0) => {
  if (!value || depth > 4) return ''

  if (typeof value === 'string') {
    const trimmed = value.trim()
    if (!trimmed) return ''
    if (/^\d+\|[A-Za-z0-9_\-]+/.test(trimmed)) return trimmed
    if (trimmed.length > 20 && !trimmed.startsWith('{') && !trimmed.startsWith('[')) return trimmed
    try {
      return findToken(JSON.parse(trimmed), depth + 1)
    } catch (_) {
      return ''
    }
  }

  if (typeof value !== 'object') return ''

  for (const key of ['token', 'access_token', 'plainTextToken']) {
    const token = findToken(value?.[key], depth + 1)
    if (token) return token
  }

  for (const key of ['state', 'auth', 'data']) {
    const token = findToken(value?.[key], depth + 1)
    if (token) return token
  }

  return ''
}

export const migrateLegacyAuthToken = () => {
  try {
    const currentAuth = sessionStorage.getItem('auth')
    if (findToken(currentAuth)) return

    const legacyAuth = localStorage.getItem('auth')
    const token = findToken(legacyAuth)
    if (!token) return

    sessionStorage.setItem('auth', JSON.stringify({
      isLogged: { status: true },
      token,
    }))
  } catch (_) {}
}

export const getAuthToken = () => {
  try {
    migrateLegacyAuthToken()
    const authStore = sessionStorage.getItem('auth')
    const token = findToken(authStore)
    return token || ''
  } catch (_) {
    return ''
  } finally {
    try {
      localStorage.removeItem('auth')
    } catch (_) {}
  }
}
