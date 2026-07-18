import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from 'axios'

vi.mock('axios', () => ({
  default: { get: vi.fn() },
}))

vi.mock('../../resources/js/utils/authToken.js', () => ({
  getAuthToken: () => 'test-token',
}))

import { useAccessStore } from '../../resources/js/store/access.js'

describe('web access store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('uses Laravel entitlements rather than the plan name', async () => {
    axios.get.mockResolvedValue({
      data: {
        data: {
          plan: 'free',
          entitlements: ['view_assessment_reports'],
          limits: {},
        },
      },
    })

    const access = useAccessStore()
    await access.refresh()

    expect(access.canAccess('view_assessment_reports')).toBe(true)
    expect(access.canAccess('view_advanced_stats')).toBe(false)
  })

  it('fails closed and removes stale paid access when refresh fails', async () => {
    const access = useAccessStore()
    access.summary = { plan: 'coach_pro', entitlements: ['ai_analytics'], limits: {} }
    access.loaded = true
    axios.get.mockRejectedValue(new Error('network unavailable'))

    await expect(access.refresh()).rejects.toThrow('network unavailable')
    expect(access.canAccess('ai_analytics')).toBe(false)
    expect(access.summary.plan).toBe('free')
  })

  it('invalidates team access immediately and ignores a stale team response', async () => {
    let resolveFirst
    axios.get
      .mockImplementationOnce(() => new Promise(resolve => { resolveFirst = resolve }))
      .mockResolvedValueOnce({ data: { data: { plan: 'free', entitlements: [], team: { id: 'team-b' } } } })
    const access = useAccessStore()
    const first = access.refresh({ team_id: 'team-a' })
    access.setTeamContext('team-b')
    expect(access.canAccess('performance_overview')).toBe(false)
    await access.refresh({ team_id: 'team-b' })
    resolveFirst({ data: { data: { plan: 'coach_pro', entitlements: ['performance_overview'], team: { id: 'team-a' } } } })
    await first
    expect(access.teamId).toBe('team-b')
    expect(access.canAccess('performance_overview')).toBe(false)
  })
})
