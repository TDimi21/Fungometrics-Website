import {describe, it, expect} from 'vitest'
import {dashboardRedirect} from '../../resources/js/utils/dashboardRouting.js'
import {rememberCommunicationDestination, consumeCommunicationDestination} from '../../resources/js/utils/communicationDestination.js'

describe('authenticated dashboard routing', () => {
  it('sends a coach to coach home after consuming a saved player-home destination', () => {
    const values = new Map()
    const storage = {getItem: key => values.get(key), setItem: (key, value) => values.set(key, value), removeItem: key => values.delete(key)}
    rememberCommunicationDestination('/player-dashboard?tab=workout', storage)
    const destination = new URL(consumeCommunicationDestination('/dashboard', storage), 'https://app.fmtrx.com')
    expect(dashboardRedirect('coach', destination.pathname, {tab: 'workout'})).toEqual({path: '/dashboard'})
    expect(dashboardRedirect('coach', '/dashboard')).toBeNull()
  })
  it('corrects direct or bookmarked player-home links for coaches', () => {
    expect(dashboardRedirect('coach', '/player-dashboard')).toEqual({path: '/dashboard'})
  })
  it('keeps player workout links working without a redirect loop', () => {
    expect(dashboardRedirect('player', '/dashboard', {tab: 'workout'})).toEqual({path: '/player-dashboard', query: {tab: 'workout'}})
    expect(dashboardRedirect('player', '/player-dashboard')).toBeNull()
  })
  it.each(['/player-videos', '/invitations/accept', '/assessment-reports', '/settings', '/login/coach'])('leaves shared and authentication routes alone: %s', path => {
    expect(dashboardRedirect('coach', path)).toBeNull()
    expect(dashboardRedirect('player', path)).toBeNull()
  })
})
