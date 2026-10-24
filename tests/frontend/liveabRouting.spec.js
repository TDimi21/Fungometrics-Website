import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'

// Exercise the real navigation guard, including the legacy practice rollout gate.
const source = readFileSync('resources/router/index.js', 'utf8')
const guardSource = source.slice(source.indexOf('router.beforeEach('), source.indexOf('\nexport default router;'))
function guard({ authenticated = true, entitlements = ['liveab_sessions'] } = {}) {
  let navigate
  const mocks = {
    router: { beforeEach: fn => { navigate = fn } },
    isPlayerInvitationRoute: () => false,
    syncAuthFromToken: () => authenticated,
    pendingCommunicationDestination: () => null,
    rememberCommunicationDestination: vi.fn(),
    useUserStore: () => ({ userData: { type: 'coach' } }),
    canManageSubscriptions: () => false,
    WEB_START_PRACTICE_ENABLED: false,
    START_PRACTICE_BLOCKED_PATHS: ['/create', '/track'],
    isLongTossScoringRoute: () => false,
    isVelocityScoringRoute: () => false,
    hasPlannerSessionContext: () => false,
    useAccessStore: () => ({ loaded: true, summary: { audience: 'coach', entitlements } }),
    routeEntitlement: to => to.meta.entitlement,
  }
  new Function(...Object.keys(mocks), guardSource)(...Object.values(mocks))
  return navigate
}
function route(path) {
  const meta = { requiresAuth: true, entitlement: 'liveab_sessions' }
  return { path, fullPath: path, meta, matched: [{ meta }] }
}
describe('Live AB web navigation', () => {
  it.each(['/create/live', '/track/live', '/track/live/edit'])('allows an entitled coach to open %s with the legacy rollout disabled', async path => {
    expect(await guard()(route(path))).toBe(true)
  })
  it('still requires Live AB access', async () => {
    expect(await guard({ entitlements: [] })(route('/create/live'))).toEqual({ path: '/dashboard', query: { access_denied: 'liveab_sessions' } })
  })
  it('still requires authentication', async () => {
    expect(await guard({ authenticated: false })(route('/create/live'))).toBe('/')
  })
  it('does not enable unrelated unfinished practice routes', async () => {
    expect(await guard()(route('/create/unfinished'))).toBe('/dashboard')
  })
})
