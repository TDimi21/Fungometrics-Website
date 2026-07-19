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

let volatileLegacyToken = ''

export const migrateLegacyAuthToken = () => {
  try {
    const localAuth = localStorage.getItem('auth')
    const sessionAuth = sessionStorage.getItem('auth')
    volatileLegacyToken = volatileLegacyToken
      || findToken(localAuth)
      || findToken(sessionAuth)
      || findToken(sessionStorage.getItem('user'))

    if (volatileLegacyToken) {
      sessionStorage.setItem('auth', JSON.stringify({
        isLogged: { status: true },
      }))
    }

    localStorage.removeItem('auth')
    return volatileLegacyToken
  } catch (_) {}

  return ''
}

export const getAuthToken = () => {
  try {
    return migrateLegacyAuthToken() || ''
  } catch (_) {
    return ''
  }
}

export const discardLegacyAuthToken = () => {
  volatileLegacyToken = ''
}
