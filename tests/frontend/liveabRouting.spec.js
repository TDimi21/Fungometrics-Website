import { dashboardRedirect } from '../../resources/js/utils/dashboardRouting.js'
import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'

// Exercise the real navigation guard, including the legacy practice rollout gate.
const source = readFileSync('resources/router/index.js', 'utf8')
const guardSource = source.slice(source.indexOf('router.beforeEach('), source.indexOf('\nexport default router;'))
function guard({ authenticated = true, entitlements = ['liveab_sessions'], audience = 'coach' } = {}) {
  let navigate
  const mocks = {
    dashboardRedirect,
    router: { beforeEach: fn => { navigate = fn } },
    isPlayerInvitationRoute: () => false,
    syncAuthFromToken: () => authenticated,
    pendingCommunicationDestination: () => null,
    rememberCommunicationDestination: vi.fn(),
    useUserStore: () => ({ userData: { type: audience } }),
    canManageSubscriptions: () => false,
    WEB_START_PRACTICE_ENABLED: false,
    START_PRACTICE_BLOCKED_PATHS: ['/create', '/track'],
    isLongTossScoringRoute: () => false,
    isVelocityScoringRoute: () => false,
    hasPlannerSessionContext: () => false,
    useAccessStore: () => ({ loaded: true, summary: { audience, entitlements } }),
    routeEntitlement: (to, role) => to.meta.entitlement || to.meta.entitlementByAudience?.[role],
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

describe('player navigation boundaries', () => {
  it('keeps dashboard fallback on the player home', async () => {
    expect(await guard({audience:'player'})({...route('/dashboard'),query:{access_denied:'x'}})).toEqual({path:'/player-dashboard',query:{access_denied:'x'}});
  });
  it('uses personal statistics access for the real New Statistics route',async()=>{
    const block=source.slice(source.indexOf('name: "new-statistic",'),source.indexOf('name: "new-statistic-session-view",'));
    const text=block.match(/meta: (\{ requiresAuth: true, entitlementByAudience: .* \}),/)[1];
    const meta=new Function(`return (${text})`)();
    expect(await guard({audience:'player',entitlements:['personal_stats']})({path:'/new-statistic',meta,matched:[{meta}]})).toBe(true);
  });
});

it('redirects an authenticated coach away from a saved player dashboard URL', async () => {
  expect(await guard({audience: 'coach'})(route('/player-dashboard'))).toEqual({path: '/dashboard'})
})
