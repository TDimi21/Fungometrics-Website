import { beforeEach, describe, expect, it } from 'vitest'
import {
  SESSION_SCHEMA_VERSION,
  ensureSessionSchema,
  prepareSessionForUser,
} from '../../resources/js/utils/sessionCache.js'

const createStorage = () => {
  const data = new Map()
  return {
    get length() { return data.size },
    key: index => [...data.keys()][index] ?? null,
    getItem: key => data.has(key) ? data.get(key) : null,
    setItem: (key, value) => data.set(key, String(value)),
    removeItem: key => data.delete(key),
    clear: () => data.clear(),
  }
}

describe('web session cache ownership', () => {
  beforeEach(() => {
    globalThis.sessionStorage = createStorage()
  })

  it('invalidates persisted state when the cache schema changes', () => {
    sessionStorage.setItem('auth', '{"isLogged":{"status":true}}')
    sessionStorage.setItem('teams', '{"team":{"id":"old"}}')
    sessionStorage.setItem('dashboard-cache:v3:old', '{"savedAt":1}')

    expect(ensureSessionSchema()).toBe(true)
    expect(sessionStorage.getItem('auth')).toBeNull()
    expect(sessionStorage.getItem('teams')).toBeNull()
    expect(sessionStorage.getItem('dashboard-cache:v3:old')).toBeNull()
    expect(sessionStorage.getItem('fmtrx:session-schema')).toBe(SESSION_SCHEMA_VERSION)
  })

  it('clears the previous account state when another user signs in', () => {
    ensureSessionSchema()
    prepareSessionForUser('coach-a')
    sessionStorage.setItem('teams', '{"team":{"id":"team-a"}}')
    sessionStorage.setItem('dashboard-cache:v4:coach-a:team-a', '{"savedAt":1}')
    sessionStorage.setItem('auth', '{"isLogged":{"status":true}}')

    expect(prepareSessionForUser('coach-b')).toBe(true)
    expect(sessionStorage.getItem('teams')).toBeNull()
    expect(sessionStorage.getItem('dashboard-cache:v4:coach-a:team-a')).toBeNull()
    expect(sessionStorage.getItem('auth')).not.toBeNull()
  })
})
