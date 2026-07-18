import fs from 'fs'
import path from 'path'
import { describe, expect, it, vi } from 'vitest'
import { createPlanFeaturesApi } from '../../resources/js/services/planFeatures.js'

const adminPlansSource = fs.readFileSync(
  path.resolve(process.cwd(), 'resources/js/pages/admin/AdminPlans.vue'),
  'utf8',
)

describe('authoritative Plan Features API', () => {
  it('loads plans and entitlement metadata from Laravel', async () => {
    const get = vi.fn()
      .mockResolvedValueOnce({ data: { data: { plans: [{ key: 'free' }], feature_groups: [] } } })
      .mockResolvedValueOnce({ data: { data: {
        entitlements: [{ key: 'notifications' }],
        coverage: [{ key: 'notifications', coverage: { implementation_status: 'not_implemented' } }],
        coverage_summary: { total: 1 },
      } } })
    await expect(createPlanFeaturesApi(get, vi.fn()).load()).resolves.toMatchObject({
      plans: [{ key: 'free' }], entitlements: [{ key: 'notifications' }], coverage_summary: { total: 1 },
    })
    expect(get).toHaveBeenNthCalledWith(1, 'admin/billing/plans')
    expect(get).toHaveBeenNthCalledWith(2, 'admin/billing/entitlements')
    expect(get.mock.calls.flat()).not.toContainEqual(expect.stringContaining('/api//api'))
  })

  it('saves through Laravel with the complete versioned payload', async () => {
    const put = vi.fn().mockResolvedValue({ data: { data: { plan: { key: 'coach_pro', version: 4 } } } })
    const payload = { version: 3, reason: 'Alignment', entitlements: [], limits: {} }
    await expect(createPlanFeaturesApi(vi.fn(), put).update('coach_pro', payload)).resolves.toEqual({ key: 'coach_pro', version: 4 })
    expect(put).toHaveBeenCalledWith('admin/billing/plans/coach_pro/entitlements', payload)
    expect(put.mock.calls.flat()).not.toContainEqual(expect.stringContaining('/api//api'))
  })

  it('rejects an HTML fallback instead of accepting it as an authoritative matrix', async () => {
    const get = vi.fn()
      .mockResolvedValueOnce({
        data: '<!doctype html><html><body>FungoMetrics</body></html>',
        headers: { 'content-type': 'text/html; charset=UTF-8' },
      })
      .mockResolvedValueOnce({ data: { data: { entitlements: [] } } })

    await expect(createPlanFeaturesApi(get, vi.fn()).load()).rejects.toThrow(
      'Plans endpoint returned HTML or invalid authoritative JSON.',
    )
  })

  it('shows the actual load error before the empty-plans state', () => {
    expect(adminPlansSource).toContain("e?.response?.data?.message || e?.message")
    expect(adminPlansSource.indexOf('v-else-if="error"')).toBeLessThan(
      adminPlansSource.indexOf('v-else-if="!plans.length"'),
    )
  })
})
