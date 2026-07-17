import { describe, expect, it, vi } from 'vitest'
import { createPlanFeaturesApi } from '../../resources/js/services/planFeatures.js'

describe('authoritative Plan Features API', () => {
  it('loads plans and entitlement metadata from Laravel', async () => {
    const get = vi.fn()
      .mockResolvedValueOnce({ data: { data: { plans: [{ key: 'free' }], feature_groups: [] } } })
      .mockResolvedValueOnce({ data: { data: { entitlements: [{ key: 'notifications' }] } } })
    await expect(createPlanFeaturesApi(get, vi.fn()).load()).resolves.toMatchObject({
      plans: [{ key: 'free' }], entitlements: [{ key: 'notifications' }],
    })
  })

  it('saves through Laravel with the complete versioned payload', async () => {
    const put = vi.fn().mockResolvedValue({ data: { data: { plan: { key: 'coach_pro', version: 4 } } } })
    const payload = { version: 3, reason: 'Alignment', entitlements: [], limits: {} }
    await expect(createPlanFeaturesApi(vi.fn(), put).update('coach_pro', payload)).resolves.toEqual({ key: 'coach_pro', version: 4 })
    expect(put).toHaveBeenCalledWith('/api/admin/billing/plans/coach_pro/entitlements', payload)
  })
})
