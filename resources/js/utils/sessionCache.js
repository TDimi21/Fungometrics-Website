export const SESSION_SCHEMA_VERSION = 'fmtrx-web-session-v2'

const VERSION_KEY = 'fmtrx:session-schema'
const USER_KEY = 'fmtrx:session-user'
const STORE_KEYS = [
  'auth',
  'user',
  'teams',
  'players',
  'training',
  'trainingActive',
  'liveAB',
  'liveABStore',
]
const USER_PREFIXES = ['dashboard-cache:', 'admin_user_']

const storageKeys = () =>
  Array.from({ length: sessionStorage.length }, (_, index) => sessionStorage.key(index))
    .filter(Boolean)

export const clearUserSessionCaches = ({ includeAuth = false } = {}) => {
  for (const key of storageKeys()) {
    if (
      (includeAuth && key === 'auth')
      || (key !== 'auth' && STORE_KEYS.includes(key))
      || USER_PREFIXES.some(prefix => key.startsWith(prefix))
    ) {
      sessionStorage.removeItem(key)
    }
  }
}

export const ensureSessionSchema = () => {
  if (sessionStorage.getItem(VERSION_KEY) === SESSION_SCHEMA_VERSION) return false

  clearUserSessionCaches({ includeAuth: true })
  sessionStorage.removeItem(USER_KEY)
  sessionStorage.setItem(VERSION_KEY, SESSION_SCHEMA_VERSION)
  return true
}

export const prepareSessionForUser = (userId) => {
  const normalized = userId == null ? '' : String(userId)
  const current = sessionStorage.getItem(USER_KEY) || ''
  if (current && current !== normalized) clearUserSessionCaches()
  sessionStorage.setItem(USER_KEY, normalized)
  return current !== normalized
}

export const sessionUserId = () => sessionStorage.getItem(USER_KEY) || ''
