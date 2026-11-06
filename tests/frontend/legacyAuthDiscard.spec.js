import {it, expect, vi} from 'vitest'
it('does not resurrect a stale stored bearer after establishing a cookie session', async () => {
  vi.resetModules()
  const stale = JSON.stringify({token: '123|oldtokenoldtokenoldtoken'})
  vi.stubGlobal('localStorage', {getItem: () => stale, removeItem: vi.fn()})
  vi.stubGlobal('sessionStorage', {getItem: () => stale, setItem: vi.fn()})
  try {
    const {getAuthToken, discardLegacyAuthToken} = await import('../../resources/js/utils/authToken.js')
    expect(getAuthToken()).toContain('123|')
    discardLegacyAuthToken()
    expect(getAuthToken()).toBe('')
  } finally {vi.unstubAllGlobals()}
})
